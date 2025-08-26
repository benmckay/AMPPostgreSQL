<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';

class AuditController {
    private PDO $db;
    private array $config;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->config = require __DIR__ . '/../config.php';
    }

    private function requireAuth(): void {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (stripos($auth, 'Bearer ') !== 0) { Response::json(['error' => 'Unauthorized'], 401); exit; }
        $token = trim(substr($auth, 7));
        try {
            JWT::verify($token, $this->config['jwt']['secret'], $this->config['jwt']['audience'], $this->config['jwt']['issuer']);
        } catch (Exception $e) {
            Response::json(['error' => 'Unauthorized'], 401); exit;
        }
    }

    public function verifyChain(): void {
        $this->requireAuth();
        $stmt = $this->db->query('SELECT log_id, details, prev_hash, curr_hash FROM audit_logs ORDER BY log_id');
        $prev = null; $ok = true; $badAt = null;
        while ($row = $stmt->fetch()) {
            $payload = json_encode($row['details']);
            $h = $this->db->prepare("SELECT compute_audit_hash(:prev, :payload::jsonb) AS h");
            $h->execute([':prev' => $prev, ':payload' => $payload]);
            $calc = $h->fetchColumn();
            if ($calc !== $row['curr_hash']) { $ok = false; $badAt = (int)$row['log_id']; break; }
            $prev = $row['curr_hash'];
        }
        Response::json(['valid' => $ok, 'break_at' => $badAt]);
    }

    public function exportCsv(): void {
        $this->requireAuth();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['log_id','user_id','action','ip_address','created_at','prev_hash','curr_hash','details']);
        $stmt = $this->db->query("SELECT log_id, user_id, action, ip_address::text, created_at, prev_hash, curr_hash, details::text FROM audit_logs ORDER BY log_id DESC LIMIT 2000");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
        fclose($out);
    }
}


