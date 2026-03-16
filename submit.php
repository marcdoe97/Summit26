<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_builder.php';

// ── Rate limiting: 5 submissions per hour per IP ───────────────────────────
$rateFile = __DIR__ . '/data/rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';
$now      = time();
$window   = 3600;
$limit    = 5;

if (file_exists($rateFile)) {
    $rate         = json_decode(file_get_contents($rateFile), true);
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

// ── Validate ───────────────────────────────────────────────────────────────
function cleanField(string $val): string {
    return trim(strip_tags($val));
}

$fname   = cleanField($_POST['fname']   ?? '');
$lname   = cleanField($_POST['lname']   ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$company = cleanField($_POST['company'] ?? '');
$role    = cleanField($_POST['role']    ?? '');
$message = cleanField($_POST['message'] ?? '');

$errors = [];
if (empty($fname)) $errors[] = 'First name is required.';
if (empty($lname)) $errors[] = 'Last name is required.';
if (!$email)       $errors[] = 'A valid email address is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Save to DB ─────────────────────────────────────────────────────────────
try {
    $pdo = getDb();

    // Duplicate email check
    $check = $pdo->prepare("SELECT id FROM registrations WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'This email address is already registered.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO registrations (fname, lname, email, company, role, message, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $fname,
        $lname,
        (string) $email,
        $company,
        $role,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    // ── Send confirmation email (non-blocking: failure does not break registration) ──
    try {
        sendConfirmationEmail((string) $email, $fname . ' ' . $lname, $pdo);
    } catch (Throwable $e) {
        error_log('Summit26 email exception: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log('Summit26 DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'A server error occurred. Please try again.']);
    exit;
}

echo json_encode(['ok' => true, 'name' => $fname]);
