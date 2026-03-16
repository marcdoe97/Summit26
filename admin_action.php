<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_auth'])) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_builder.php';

$pdo         = getDb();
$action      = $_POST['action']       ?? '';
$redirectTab = $_POST['redirect_tab'] ?? 'registrations';

function clean(string $v): string { return trim(strip_tags($v)); }
function cleanInt(mixed $v): int  { return max(0, (int) $v); }

switch ($action) {

    // ── Registrations ────────────────────────────────────────────────────
    case 'delete_registration':
        $id = cleanInt($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([$id]);
        }
        break;

    // ── Event Info ───────────────────────────────────────────────────────
    case 'save_event_info':
        $fields = [
            'event_name', 'event_tagline', 'event_date_label',
            'event_day1_label', 'event_day2_label',
            'event_location_name', 'event_address', 'event_website',
        ];
        foreach ($fields as $f) {
            saveSetting($pdo, $f, clean($_POST[$f] ?? ''));
        }
        break;

    // ── Agenda ───────────────────────────────────────────────────────────
    case 'add_agenda':
        $pdo->prepare("
            INSERT INTO agenda (day, sort_order, time_label, title, speaker, description, type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            cleanInt($_POST['day']         ?? 1),
            cleanInt($_POST['sort_order']  ?? 0),
            clean($_POST['time_label']     ?? ''),
            clean($_POST['title']          ?? ''),
            clean($_POST['speaker']        ?? ''),
            clean($_POST['description']    ?? ''),
            clean($_POST['type']           ?? 'session'),
        ]);
        break;

    case 'edit_agenda':
        $id = cleanInt($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("
                UPDATE agenda SET day=?, sort_order=?, time_label=?, title=?, speaker=?, description=?, type=?
                WHERE id=?
            ")->execute([
                cleanInt($_POST['day']         ?? 1),
                cleanInt($_POST['sort_order']  ?? 0),
                clean($_POST['time_label']     ?? ''),
                clean($_POST['title']          ?? ''),
                clean($_POST['speaker']        ?? ''),
                clean($_POST['description']    ?? ''),
                clean($_POST['type']           ?? 'session'),
                $id,
            ]);
        }
        break;

    case 'delete_agenda':
        $id = cleanInt($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM agenda WHERE id = ?")->execute([$id]);
        }
        break;

    // ── Speakers ─────────────────────────────────────────────────────────
    case 'add_speaker':
        $pdo->prepare("
            INSERT INTO speakers (name, role, company, bio)
            VALUES (?, ?, ?, ?)
        ")->execute([
            clean($_POST['name']    ?? ''),
            clean($_POST['role']    ?? ''),
            clean($_POST['company'] ?? ''),
            clean($_POST['bio']     ?? ''),
        ]);
        break;

    case 'edit_speaker':
        $id = cleanInt($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("
                UPDATE speakers SET name=?, role=?, company=?, bio=? WHERE id=?
            ")->execute([
                clean($_POST['name']    ?? ''),
                clean($_POST['role']    ?? ''),
                clean($_POST['company'] ?? ''),
                clean($_POST['bio']     ?? ''),
                $id,
            ]);
        }
        break;

    case 'delete_speaker':
        $id = cleanInt($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM speakers WHERE id = ?")->execute([$id]);
        }
        break;

    // ── Email Settings ───────────────────────────────────────────────────
    case 'save_email_settings':
        $fields = ['email_from_name', 'email_from_address', 'email_subject', 'email_intro', 'email_closing'];
        foreach ($fields as $f) {
            // Allow newlines in text areas
            $val = ($f === 'email_intro' || $f === 'email_closing')
                ? trim(strip_tags($_POST[$f] ?? ''))
                : clean($_POST[$f] ?? '');
            saveSetting($pdo, $f, $val);
        }
        break;

    // ── Send Test Email ──────────────────────────────────────────────────
    case 'send_test_email':
        $testTo = filter_var(trim($_POST['test_address'] ?? ''), FILTER_VALIDATE_EMAIL);
        if ($testTo) {
            $ok = sendConfirmationEmail((string) $testTo, 'Test User', $pdo);
            header('Location: admin.php?tab=email&msg=' . ($ok ? 'test_sent' : 'test_failed'));
            exit;
        }
        break;
}

header('Location: admin.php?tab=' . urlencode($redirectTab) . '&msg=saved');
exit;
