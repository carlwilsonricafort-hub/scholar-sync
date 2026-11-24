<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Load config (defines DB_* constants) and DB connection class ──
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/resources/Database.php';

// ── URL Parsing ───────────────────────────────────────────────────
// Examples:
//   /student-portal/api/users           → resource=users
//   /student-portal/api/users/5         → resource=users, id=5
//   /student-portal/api/users/5/password→ resource=users, id=5, sub=password
//   /student-portal/api/users/login     → resource=users, action=login
//   /student-portal/api/payments?user_id=3
//   /student-portal/api/payment-logs?user_id=3

$requestURI = $_SERVER['REQUEST_URI'];
$basePath   = '/student-portal/api/';

if (strpos($requestURI, $basePath) === 0) {
    $path = substr($requestURI, strlen($basePath));
} else {
    $path = preg_replace('#^.*/api/#', '', $requestURI);
}

// Strip query string
if (($pos = strpos($path, '?')) !== false) {
    $path = substr($path, 0, $pos);
}

$segments = explode('/', trim($path, '/'));
$resource = $segments[0] ?? '';
$rawSeg1  = $segments[1] ?? '';
$id       = is_numeric($rawSeg1) ? (int)$rawSeg1 : null;
$sub      = $segments[2] ?? null;

$method = $_SERVER['REQUEST_METHOD'];
$data   = json_decode(file_get_contents("php://input"), true) ?? [];

// ── Resource: users ───────────────────────────────────────────────
if ($resource === 'users') {
    require_once __DIR__ . '/resources/User.php';
    $res = new User();

    // POST /api/users/login
    if ($rawSeg1 === 'login') {
        if ($method === 'POST') { $res->login($data); }
        else                   { respond405(); }
        exit();
    }

    // PUT /api/users/{id}/password
    if ($sub === 'password') {
        if ($method === 'PUT' && $id) { $res->changePassword($id, $data); }
        else                          { respond405(); }
        exit();
    }

    // GET with ?email=
    if ($method === 'GET' && !empty($_GET['email'])) {
        $res->getByEmail(trim($_GET['email']));
        exit();
    }

    switch ($method) {
        case 'GET':    $id ? $res->get($id)           : $res->getAll();            break;
        case 'POST':   $res->create($data);                                        break;
        case 'PUT':    $id ? $res->update($id, $data) : respond400('ID required'); break;
        case 'DELETE': $id ? $res->delete($id)        : respond400('ID required'); break;
        default:       respond405();
    }
    exit();
}

// ── Resource: payments ────────────────────────────────────────────
if ($resource === 'payments') {
    require_once __DIR__ . '/resources/Payment.php';
    $res = new Payment();

    switch ($method) {
        case 'GET':
            if ($id)                          { $res->get($id); }
            elseif (!empty($_GET['user_id'])) { $res->getByUser((int)$_GET['user_id']); }
            else                              { respond400('user_id or payment id required'); }
            break;
        case 'POST':  $res->create($data);                                        break;
        case 'PUT':   $id ? $res->update($id, $data) : respond400('ID required'); break;
        default:      respond405();
    }
    exit();
}

// ── Resource: payment-logs ────────────────────────────────────────
if ($resource === 'payment-logs') {
    require_once __DIR__ . '/resources/PaymentLog.php';
    $res = new PaymentLog();

    switch ($method) {
        case 'GET':
            if (!empty($_GET['user_id'])) { $res->getByUser((int)$_GET['user_id']); }
            else                          { respond400('user_id required'); }
            break;
        case 'POST': $res->create($data); break;
        default:     respond405();
    }
    exit();
}

// ── 404 ───────────────────────────────────────────────────────────
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Resource not found']);

// ── Helper functions ──────────────────────────────────────────────
function respond400(string $msg): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
}

function respond405(): void
{
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}