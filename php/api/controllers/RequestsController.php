<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Audit.php';
require_once __DIR__ . '/../lib/Notifications.php';

class RequestsController {
    private PDO $db;
    private array $config;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->config = require __DIR__ . '/../config.php';
    }

    private function authUserId(): ?int {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (stripos($auth, 'Bearer ') !== 0) return null;
        $token = trim(substr($auth, 7));
        try {
            $payload = JWT::verify($token, $this->config['jwt']['secret'], $this->config['jwt']['audience'], $this->config['jwt']['issuer']);
            return (int)$payload['sub'];
        } catch (Exception $e) {
            return null;
        }
    }

    private function requireAuth(): int {
        $uid = $this->authUserId();
        if (!$uid) { Response::json(['error' => 'Unauthorized'], 401); exit; }
        return $uid;
    }

    private function readJson(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    public function list(): void {
        $uid = $this->requireAuth();
        $stmt = $this->db->prepare('SELECT * FROM access_requests WHERE requester_id = :uid ORDER BY request_id DESC');
        $stmt->execute([':uid' => $uid]);
        Response::json($stmt->fetchAll());
    }

    public function get(int $id): void {
        $uid = $this->requireAuth();
        $stmt = $this->db->prepare('SELECT * FROM access_requests WHERE request_id = :id AND requester_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $uid]);
        $row = $stmt->fetch();
        if (!$row) { Response::json(['error' => 'Not found'], 404); return; }
        Response::json($row);
    }

    public function downloadAttachment(int $id, int $index): void {
        $uid = $this->requireAuth();
        $stmt = $this->db->prepare('SELECT attachments FROM access_requests WHERE request_id = :id AND requester_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $uid]);
        $row = $stmt->fetch();
        if (!$row) { Response::json(['error' => 'Not found'], 404); return; }
        $attachments = json_decode($row['attachments'] ?? '[]', true) ?: [];
        if (!isset($attachments[$index])) { Response::json(['error' => 'Attachment not found'], 404); return; }
        $att = $attachments[$index];
        $name = $att['name'] ?? ('attachment_' . $index);
        $mime = $att['type'] ?? 'application/octet-stream';
        $data = $att['data'] ?? '';
        $bin = base64_decode($data, true);
        if ($bin === false) { Response::json(['error' => 'Invalid attachment'], 400); return; }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo $bin;
    }

    public function create(): void {
        $uid = $this->requireAuth();
        $data = $this->readJson();
        $type = $data['type'] ?? null; // new/additional/reactivation/termination
        $system_id = $data['system_id'] ?? null;
        $payload = $data['payload'] ?? [];
        if (!$type) { Response::json(['error' => 'type required'], 400); return; }
        // COS validation (physician requests)
        $isCos = isset($payload['cos']) ? (bool)$payload['cos'] : false;
        if ($isCos) {
            $requiredCos = ['provider_group','provider_type','specialty','service','admitting','ordering','sign','cosign'];
            $missing = [];
            foreach ($requiredCos as $f) { if (!array_key_exists($f, $payload) || $payload[$f] === '' || $payload[$f] === null) { $missing[] = $f; } }
            if ($missing) { Response::json(['error' => 'missing_cos_fields', 'fields' => $missing], 400); return; }
        }
        $stmt = $this->db->prepare('INSERT INTO access_requests (requester_id, system_id, type, status, payload, attachments) VALUES (:uid, :sid, :t::request_type, :s::request_status, :p::jsonb, :a::jsonb) RETURNING request_id');
        $stmt->execute([
            ':uid' => $uid,
            ':sid' => $system_id,
            ':t' => $type,
            ':s' => 'submitted',
            ':p' => json_encode($payload),
            ':a' => json_encode($data['attachments'] ?? []),
        ]);
        $rid = (int)$stmt->fetchColumn();
        $this->db->prepare('INSERT INTO request_events (request_id, actor_user_id, action, comment) VALUES (:rid, :uid, :a, :c)')
            ->execute([':rid' => $rid, ':uid' => $uid, ':a' => 'submitted', ':c' => null]);
        Audit::log($uid, 'request_created', ['request_id' => $rid, 'type' => $type]);
        Response::json(['request_id' => $rid], 201);
    }

    public function comment(int $id): void {
        $uid = $this->requireAuth();
        $data = $this->readJson();
        $comment = trim($data['comment'] ?? '');
        if ($comment === '') { Response::json(['error' => 'comment required'], 400); return; }
        $this->db->prepare('INSERT INTO request_events (request_id, actor_user_id, action, comment) VALUES (:rid, :uid, :a, :c)')
            ->execute([':rid' => $id, ':uid' => $uid, ':a' => 'comment', ':c' => $comment]);
        Audit::log($uid, 'request_comment', ['request_id' => $id]);
        Response::json(['message' => 'Comment added']);
    }

    private function updateStatus(int $id, int $actorUserId, string $newStatus, string $action, ?string $comment = null): void {
        $stmt = $this->db->prepare('UPDATE access_requests SET status = :s, updated_at = NOW() WHERE request_id = :id');
        $stmt->execute([':s' => $newStatus, ':id' => $id]);
        $this->db->prepare('INSERT INTO request_events (request_id, actor_user_id, action, comment) VALUES (:rid, :uid, :a, :c)')
            ->execute([':rid' => $id, ':uid' => $actorUserId, ':a' => $action, ':c' => $comment]);
        Audit::log($actorUserId, 'request_status_change', ['request_id' => $id, 'to' => $newStatus, 'action' => $action]);
        // Notify requester
        $q = $this->db->prepare('SELECT u.email FROM access_requests r JOIN users u ON r.requester_id = u.user_id WHERE r.request_id = :id');
        $q->execute([':id' => $id]);
        if ($email = $q->fetchColumn()) {
            Notifications::sendEmail($email, 'Request update', "Your request #$id changed to $newStatus");
        }
    }

    public function approveManager(int $id): void {
        $uid = $this->requireAuth();
        // For MVP, skip RBAC check and assume caller is a manager
        // Transition: submitted -> pending_hr OR approved (if not requiring HR)
        $data = $this->readJson();
        $comment = trim($data['comment'] ?? '');
        $needsHr = $data['needs_hr'] ?? true; // default to HR step for safety
        $new = $needsHr ? 'pending_hr' : 'approved';
        $this->updateStatus($id, $uid, $new, 'approved_manager', $comment ?: null);
        Response::json(['message' => 'Manager approval recorded', 'status' => $new]);
    }

    public function approveHr(int $id): void {
        $uid = $this->requireAuth();
        // For MVP, assume caller is HR
        $data = $this->readJson();
        $comment = trim($data['comment'] ?? '');
        // Transition: pending_hr -> approved
        $this->updateStatus($id, $uid, 'approved', 'approved_hr', $comment ?: null);
        Response::json(['message' => 'HR approval recorded', 'status' => 'approved']);
    }

    public function reject(int $id): void {
        $uid = $this->requireAuth();
        $data = $this->readJson();
        $comment = trim($data['comment'] ?? '');
        if ($comment === '') { Response::json(['error' => 'Rejection comment required'], 400); return; }
        $this->updateStatus($id, $uid, 'rejected', 'rejected', $comment);
        Response::json(['message' => 'Request rejected']);
    }

    public function markFulfillment(int $id): void {
        $uid = $this->requireAuth();
        // Transition: approved -> in_fulfillment -> completed
        $data = $this->readJson();
        $status = $data['status'] ?? '';
        if (!in_array($status, ['in_fulfillment','completed'], true)) {
            Response::json(['error' => 'status must be in_fulfillment or completed'], 400); return;
        }
        $comment = trim($data['comment'] ?? '');
        $this->updateStatus($id, $uid, $status, $status, $comment ?: null);
        Response::json(['message' => 'Fulfillment status updated', 'status' => $status]);
    }
}


