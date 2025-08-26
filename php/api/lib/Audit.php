<?php

require_once __DIR__ . '/Database.php';

class Audit {
    public static function log(?int $userId, string $action, array $details = [], ?string $ip = null): void {
        $db = Database::getConnection();
        // fetch prev hash
        $prev = null;
        $stmt = $db->query('SELECT curr_hash FROM audit_logs ORDER BY log_id DESC LIMIT 1');
        $row = $stmt->fetch();
        if ($row && isset($row['curr_hash'])) $prev = $row['curr_hash'];
        // compute new hash using DB function to keep parity
        $payload = json_encode($details);
        $hashStmt = $db->prepare("SELECT compute_audit_hash(:prev, :payload::jsonb) AS h");
        $hashStmt->execute([':prev' => $prev, ':payload' => $payload]);
        $curr = $hashStmt->fetchColumn();
        $ins = $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address, prev_hash, curr_hash) VALUES (:u, :a, :d::jsonb, :ip, :p, :c)');
        $ins->execute([
            ':u' => $userId,
            ':a' => $action,
            ':d' => $payload,
            ':ip' => $ip,
            ':p' => $prev,
            ':c' => $curr,
        ]);
    }
}


