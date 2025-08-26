<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';

class ReportsController {
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

    public function requestsSummary(): void {
        $this->requireAuth();
        [$where, $params] = $this->filters();
        $byType = $this->db->prepare('SELECT type, COUNT(*) AS count FROM access_requests ' . $where . ' GROUP BY type ORDER BY type');
        $byType->execute($params);
        $byStatus = $this->db->prepare('SELECT status, COUNT(*) AS count FROM access_requests ' . $where . ' GROUP BY status ORDER BY status');
        $byStatus->execute($params);
        Response::json(['by_type' => $byType->fetchAll(), 'by_status' => $byStatus->fetchAll()]);
    }

    public function requestsCsv(): void {
        $this->requireAuth();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="requests.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['request_id','requester_id','system_id','type','status','created_at']);
        [$where, $params] = $this->filters();
        $sql = "SELECT request_id, requester_id, system_id, type::text, status::text, created_at FROM access_requests $where ORDER BY request_id DESC LIMIT 2000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
        fclose($out);
    }

    private function filters(): array {
        $q = $_GET ?? [];
        $clauses = [];
        $params = [];
        if (!empty($q['type'])) { $clauses[] = 'type = :type::request_type'; $params[':type'] = $q['type']; }
        if (!empty($q['status'])) { $clauses[] = 'status = :status::request_status'; $params[':status'] = $q['status']; }
        if (!empty($q['from'])) { $clauses[] = 'created_at >= :from'; $params[':from'] = $q['from']; }
        if (!empty($q['to'])) { $clauses[] = 'created_at <= :to'; $params[':to'] = $q['to']; }
        $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';
        return [$where, $params];
    }
}


