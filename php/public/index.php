<?php

require_once __DIR__ . '/../api/lib/Database.php';
require_once __DIR__ . '/../api/lib/Response.php';
require_once __DIR__ . '/../api/lib/JWT.php';

// Simple router (no frameworks)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$reqId = bin2hex(random_bytes(6));
$start = microtime(true);
header('X-Request-Id: ' . $reqId);
// Structured request start log
error_log(json_encode(['ts'=>date('c'),'event'=>'request_start','requestId'=>$reqId,'method'=>$method,'path'=>$path]));
register_shutdown_function(function() use ($reqId, $method, $path, $start) {
    $durMs = (int) round((microtime(true) - $start) * 1000);
    error_log(json_encode(['ts'=>date('c'),'event'=>'request_end','requestId'=>$reqId,'method'=>$method,'path'=>$path,'duration_ms'=>$durMs]));
});

// Allow CORS for dev; harden later
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Security headers (baseline)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' http: https:");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Route dispatch
function route(string $m, string $pattern): bool {
    global $path, $method;
    if ($method !== $m) return false;
    return $path === $pattern;
}

function readJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

// Auth endpoints
if (route('POST', '/api/auth/login')) {
    require_once __DIR__ . '/../api/controllers/AuthController.php';
    (new AuthController())->login();
    exit;
}

if (route('POST', '/api/auth/verify-otp')) {
    require_once __DIR__ . '/../api/controllers/AuthController.php';
    (new AuthController())->verifyOtp();
    exit;
}

if (route('POST', '/api/auth/reset-password')) {
    require_once __DIR__ . '/../api/controllers/AuthController.php';
    (new AuthController())->resetPassword();
    exit;
}

if (route('PUT', '/api/auth/update-password')) {
    require_once __DIR__ . '/../api/controllers/AuthController.php';
    (new AuthController())->updatePassword();
    exit;
}

// Users endpoints
if (route('GET', '/api/users')) {
    require_once __DIR__ . '/../api/controllers/UsersController.php';
    (new UsersController())->list();
    exit;
}

if (preg_match('#^/api/users/(\d+)$#', $path, $m)) {
    require_once __DIR__ . '/../api/controllers/UsersController.php';
    $ctrl = new UsersController();
    if ($method === 'GET') { $ctrl->get((int)$m[1]); exit; }
    if ($method === 'PUT') { $ctrl->update((int)$m[1]); exit; }
    if ($method === 'DELETE') { $ctrl->delete((int)$m[1]); exit; }
}

if (route('POST', '/api/users')) {
    require_once __DIR__ . '/../api/controllers/UsersController.php';
    (new UsersController())->create();
    exit;
}

if (preg_match('#^/api/users/(\\d+)/logins$#', $path, $m) && $method === 'GET') {
    require_once __DIR__ . '/../api/controllers/UsersController.php';
    (new UsersController())->logins((int)$m[1]);
    exit;
}

// Requests endpoints
if (route('GET', '/api/requests')) {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->list();
    exit;
}

if (route('POST', '/api/requests')) {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->create();
    exit;
}

if (preg_match('#^/api/requests/(\\d+)$#', $path, $m)) {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    $ctrl = new RequestsController();
    if ($method === 'GET') { $ctrl->get((int)$m[1]); exit; }
}

if (preg_match('#^/api/requests/(\\d+)/comment$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->comment((int)$m[1]);
    exit;
}

if (preg_match('#^/api/requests/(\\d+)/attachments/(\\d+)$#', $path, $m) && $method === 'GET') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->downloadAttachment((int)$m[1], (int)$m[2]);
    exit;
}

// Approvals & fulfillment
if (preg_match('#^/api/requests/(\\d+)/approve/manager$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->approveManager((int)$m[1]);
    exit;
}
if (preg_match('#^/api/requests/(\\d+)/approve/hr$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->approveHr((int)$m[1]);
    exit;
}
if (preg_match('#^/api/requests/(\\d+)/reject$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->reject((int)$m[1]);
    exit;
}
if (preg_match('#^/api/requests/(\\d+)/fulfillment$#', $path, $m) && $method === 'POST') {
    require_once __DIR__ . '/../api/controllers/RequestsController.php';
    (new RequestsController())->markFulfillment((int)$m[1]);
    exit;
}

// Dashboards endpoints
if (route('GET', '/api/dashboard/requester')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->requester();
    exit;
}
if (route('GET', '/api/dashboard/manager')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->manager();
    exit;
}
if (route('GET', '/api/dashboard/cos')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->cos();
    exit;
}
if (route('GET', '/api/dashboard/hr')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->hr();
    exit;
}
if (route('GET', '/api/dashboard/ict')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->ict();
    exit;
}
if (route('GET', '/api/dashboard/admin')) {
    require_once __DIR__ . '/../api/controllers/DashboardController.php';
    (new DashboardController())->admin();
    exit;
}

// Reports
if (route('GET', '/api/reports/requests/summary')) {
    require_once __DIR__ . '/../api/controllers/ReportsController.php';
    (new ReportsController())->requestsSummary();
    exit;
}
if (route('GET', '/api/reports/requests.csv')) {
    require_once __DIR__ . '/../api/controllers/ReportsController.php';
    (new ReportsController())->requestsCsv();
    exit;
}

// Audit verification
if (route('GET', '/api/audit/verify')) {
    require_once __DIR__ . '/../api/controllers/AuditController.php';
    (new AuditController())->verifyChain();
    exit;
}

if (route('GET', '/api/audit/export.csv')) {
    require_once __DIR__ . '/../api/controllers/AuditController.php';
    (new AuditController())->exportCsv();
    exit;
}

Response::json(['error' => 'Not Found', 'path' => $path], 404);


