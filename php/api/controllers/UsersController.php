<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';

class UsersController {
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

    private function requireAuth(): ?int {
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
        $this->requireAuth();
        $stmt = $this->db->query('SELECT user_id, full_name, email, phone, department, role_id, is_active, last_login_at, created_at FROM users ORDER BY user_id DESC');
        $rows = $stmt->fetchAll();
        Response::json($rows);
    }

    public function get(int $id): void {
        $this->requireAuth();
        $stmt = $this->db->prepare('SELECT user_id, full_name, email, phone, department, role_id, is_active, last_login_at, created_at FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { Response::json(['error' => 'Not found'], 404); return; }
        Response::json($row);
    }

    public function logins(int $id): void {
        $this->requireAuth();
        // Derive login events from audit logs (login_success)
        $stmt = $this->db->prepare("SELECT created_at, ip_address FROM audit_logs WHERE user_id = :id AND action = 'login_success' ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([':id' => $id]);
        Response::json($stmt->fetchAll());
    }

    public function create(): void {
        $this->requireAuth();
        $data = $this->readJson();
        $full_name = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        if ($full_name === '' || $email === '' || $password === '') {
            Response::json(['error' => 'full_name, email, password required'], 400);
            return;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $stmt = $this->db->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (:n, :e, :p) RETURNING user_id');
            $stmt->execute([':n' => $full_name, ':e' => $email, ':p' => $hash]);
            $uid = (int)$stmt->fetchColumn();
            Response::json(['user_id' => $uid], 201);
        } catch (PDOException $e) {
            Response::json(['error' => 'Email exists'], 409);
        }
    }

    public function update(int $id): void {
        $this->requireAuth();
        $data = $this->readJson();
        $fields = ['full_name','phone','department','role_id','is_active'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (empty($sets)) { Response::json(['message' => 'No changes']); return; }
        $sql = 'UPDATE users SET ' . implode(',', $sets) . ', updated_at = NOW() WHERE user_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::json(['message' => 'Updated']);
    }

    public function delete(int $id): void {
        $this->requireAuth();
        $stmt = $this->db->prepare('DELETE FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $id]);
        Response::json(['message' => 'Deleted']);
    }
}


