<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_auth'])) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/email_builder.php';

$pdo = getDb();
echo buildEmailHtml('Preview User', $pdo);
