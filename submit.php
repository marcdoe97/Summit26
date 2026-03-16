<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ─── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Rate limiting (simple IP-based, 5 submissions per hour) ──────────────
$rateFile = __DIR__ . '/data/rate_' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
$now      = time();
$limit    = 5;
$window   = 3600; // 1 hour

if (file_exists($rateFile)) {
    $rate = json_decode(file_get_contents($rateFile), true);
    $rate['hits'] = array_filter($rate['hits'] ?? [], fn($t) => $now - $t < $window);
    if (count($rate['hits']) >= $limit) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many requests. Please try again later.']);
        exit;
    }
} else {
    $rate = ['hits' => []];
}
$rate['hits'][] = $now;
file_put_contents($rateFile, json_encode($rate), LOCK_EX);

// ─── Sanitize & validate input ─────────────────────────────────────────────
function clean(string $val): string {
    return trim(strip_tags($val));
}

$fname   = clean($_POST['fname']   ?? '');
$lname   = clean($_POST['lname']   ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$company = clean($_POST['company'] ?? '');
$role    = clean($_POST['role']    ?? '');
$message = clean($_POST['message'] ?? '');

$errors = [];
if (empty($fname))  $errors[] = 'First name is required.';
if (empty($lname))  $errors[] = 'Last name is required.';
if (!$email)        $errors[] = 'A valid email address is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ─── Ensure data directory exists ─────────────────────────────────────────
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

// ─── SQLite connection ─────────────────────────────────────────────────────
$dbPath = $dataDir . '/registrations.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL;'); // Better concurrent write handling

    // Create table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS registrations (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            fname     TEXT    NOT NULL,
            lname     TEXT    NOT NULL,
            email     TEXT    NOT NULL,
            company   TEXT,
            role      TEXT,
            message   TEXT,
            ip        TEXT,
            created   TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");

    // Check for duplicate email
    $check = $pdo->prepare('SELECT id FROM registrations WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'This email address is already registered.']);
        exit;
    }

    // Insert registration
    $stmt = $pdo->prepare("
        INSERT INTO registrations (fname, lname, email, company, role, message, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $fname,
        $lname,
        (string) $email,
        $company,
        $role,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

} catch (PDOException $e) {
    error_log('Summit26 DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'A server error occurred. Please try again.']);
    exit;
}

// ─── Success ───────────────────────────────────────────────────────────────
echo json_encode([
    'ok'   => true,
    'name' => $fname
]);
