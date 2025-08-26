<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';

class DashboardController {
    private PDO $db;
    private array $config;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->config = require __DIR__ . '/../config.php';
    }

    private function auth(): array {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (stripos($auth, 'Bearer ') !== 0) { Response::json(['error' => 'Unauthorized'], 401); exit; }
        $token = trim(substr($auth, 7));
        try {
            $payload = JWT::verify($token, $this->config['jwt']['secret'], $this->config['jwt']['audience'], $this->config['jwt']['issuer']);
            return $payload;
        } catch (Exception $e) {
            Response::json(['error' => 'Unauthorized'], 401); exit;
        }
    }

    private function kpisForUser(int $userId): array {
        $total = $this->db->prepare('SELECT COUNT(*) FROM access_requests WHERE requester_id = :u');
        $total->execute([':u' => $userId]);
        $pending = $this->db->prepare("SELECT COUNT(*) FROM access_requests WHERE requester_id = :u AND status IN ('submitted','pending_manager','pending_hr','in_fulfillment')");
        $pending->execute([':u' => $userId]);
        $approved = $this->db->prepare("SELECT COUNT(*) FROM access_requests WHERE requester_id = :u AND status = 'completed'");
        $approved->execute([':u' => $userId]);
        return [
            'total_requests' => (int)$total->fetchColumn(),
            'pending_requests' => (int)$pending->fetchColumn(),
            'completed_requests' => (int)$approved->fetchColumn(),
        ];
    }

    public function requester(): void {
        $payload = $this->auth();
        $uid = (int)$payload['sub'];
        $kpis = $this->kpisForUser($uid);
        $recent = $this->db->prepare('SELECT request_id, type, status, created_at FROM access_requests WHERE requester_id = :u ORDER BY request_id DESC LIMIT 10');
        $recent->execute([':u' => $uid]);
        Response::json(['kpis' => $kpis, 'recent' => $recent->fetchAll()]);
    }

    public function manager(): void {
        $this->auth();
        // Placeholder: pending approvals list (in a real app link to team relationships)
        $stmt = $this->db->query("SELECT request_id, requester_id, type, status, created_at FROM access_requests WHERE status IN ('submitted','pending_manager') ORDER BY created_at DESC LIMIT 20");
        Response::json(['pending_approvals' => $stmt->fetchAll()]);
    }

    public function cos(): void {
        $this->auth();
        $stmt = $this->db->query("SELECT request_id, type, status, created_at FROM access_requests WHERE (payload->>'cos') IS NOT NULL ORDER BY created_at DESC LIMIT 20");
        Response::json(['cos_requests' => $stmt->fetchAll()]);
    }

    public function hr(): void {
        $this->auth();
        $stmt = $this->db->query("SELECT request_id, requester_id, type, status, created_at FROM access_requests WHERE status IN ('pending_hr','termination') ORDER BY created_at DESC LIMIT 20");
        Response::json(['hr_queue' => $stmt->fetchAll()]);
    }

    public function ict(): void {
        $this->auth();
        $stmt = $this->db->query("SELECT request_id, requester_id, type, status, created_at FROM access_requests WHERE status IN ('approved','in_fulfillment') ORDER BY created_at DESC LIMIT 20");
        Response::json(['fulfillment_queue' => $stmt->fetchAll()]);
    }

    public function admin(): void {
        $this->auth();
        $counts = $this->db->query('SELECT status, COUNT(*) as c FROM access_requests GROUP BY status');
        $byType = $this->db->query('SELECT type, COUNT(*) as c FROM access_requests GROUP BY type');
        Response::json([
            'by_status' => $counts->fetchAll(),
            'by_type' => $byType->fetchAll(),
        ]);
    }
}


