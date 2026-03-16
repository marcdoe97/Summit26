<?php
declare(strict_types=1);
session_start();

define('ADMIN_PASSWORD', 'summit26admin'); // ← Change before going live!

require_once __DIR__ . '/db.php';

// ── Auth ───────────────────────────────────────────────────────────────────
$authError = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_auth'] = true;
    } else {
        $authError = 'Incorrect password.';
    }
}
$authenticated = !empty($_SESSION['admin_auth']);

// ── CSV export ─────────────────────────────────────────────────────────────
if ($authenticated && isset($_GET['export'])) {
    $pdo  = getDb();
    $rows = $pdo->query("SELECT id,fname,lname,email,company,role,message,created FROM registrations ORDER BY created DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="summit26_' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['ID','First Name','Last Name','Email','Company','Role','Message','Registered At']);
    foreach ($rows as $r) fputcsv($f, $r);
    fclose($f);
    exit;
}

// ── Load data ──────────────────────────────────────────────────────────────
$tab  = $_GET['tab'] ?? 'registrations';
$msg  = $_GET['msg'] ?? '';

$pdo           = null;
$settings      = [];
$registrations = [];
$stats         = ['total' => 0, 'today' => 0, 'companies' => 0];
$roleStats     = [];
$agenda        = [];
$speakers      = [];
$search        = '';
$editItem      = null;

if ($authenticated) {
    $pdo      = getDb();
    $settings = getSettings($pdo);

    if ($tab === 'registrations') {
        $search = trim($_GET['q'] ?? '');
        if ($search !== '') {
            $like  = '%' . $search . '%';
            $stmt  = $pdo->prepare("SELECT * FROM registrations WHERE fname LIKE ? OR lname LIKE ? OR email LIKE ? OR company LIKE ? ORDER BY created DESC");
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $pdo->query("SELECT * FROM registrations ORDER BY created DESC");
        }
        $registrations             = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['total']            = (int) $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
        $stats['today']            = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE date(created)=date('now')")->fetchColumn();
        $stats['companies']        = (int) $pdo->query("SELECT COUNT(DISTINCT company) FROM registrations WHERE company!=''")->fetchColumn();
        $rs                        = $pdo->query("SELECT role, COUNT(*) c FROM registrations WHERE role!='' GROUP BY role ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rs as $r) $roleStats[$r['role']] = (int) $r['c'];
    }

    if ($tab === 'agenda') {
        $agenda = $pdo->query("SELECT * FROM agenda ORDER BY day, sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $s2 = $pdo->prepare("SELECT * FROM agenda WHERE id=?");
            $s2->execute([$editId]);
            $editItem = $s2->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if ($tab === 'speakers') {
        $speakers = $pdo->query("SELECT * FROM speakers ORDER BY rowid")->fetchAll(PDO::FETCH_ASSOC);
        $editId   = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $s2 = $pdo->prepare("SELECT * FROM speakers WHERE id=?");
            $s2->execute([$editId]);
            $editItem = $s2->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function v(array $d, string $k): string { return e($d[$k] ?? ''); }
function sel(string $current, string $value): string { return $current === $value ? ' selected' : ''; }
$typeOptions = ['keynote' => 'Keynote', 'session' => 'Session', 'workshop' => 'Workshop', 'panel' => 'Panel', 'break' => 'Break', 'closing' => 'Closing'];
$typeBadge   = [
    'keynote'  => ['#0066CC','#fff'],
    'session'  => ['#DBEAFE','#1D4ED8'],
    'workshop' => ['#FEE2D5','#C2410C'],
    'panel'    => ['#EDE9FE','#6D28D9'],
    'break'    => ['#F1F5F9','#64748B'],
    'closing'  => ['#0B132B','#fff'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SUMMIT 26 · Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root{--blue:#0066CC;--cyan:#00B4D8;--orange:#FF6B35;--purple:#6B5B95;--dark:#0B132B;--green:#22c55e;--red:#ef4444;--bg:#F1F5F9;--card:#fff;--border:#E2E8F0;--muted:#64748B;--text:#1E293B;}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}
    a{color:var(--blue);text-decoration:none;}a:hover{text-decoration:underline;}
    textarea,input,select{font-family:inherit;}

    /* ─ Login ─ */
    .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--dark),#0d2353);}
    .login-box{background:#fff;border-radius:16px;padding:48px 40px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
    .login-logo{font-size:1.4rem;font-weight:700;color:var(--dark);letter-spacing:-.03em;margin-bottom:4px;}
    .login-logo span{color:var(--cyan);}
    .login-sub{font-size:.82rem;color:var(--muted);margin-bottom:28px;}
    .login-err{background:#fef2f2;border:1px solid #fecaca;color:var(--red);border-radius:8px;padding:10px 14px;font-size:.84rem;margin-bottom:16px;}
    .lbl{display:block;font-size:.78rem;font-weight:600;color:var(--muted);margin-bottom:6px;letter-spacing:.04em;}
    .inp{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.92rem;outline:none;transition:border-color .2s;margin-bottom:18px;}
    .inp:focus{border-color:var(--blue);}
    .login-btn{width:100%;padding:12px;background:linear-gradient(135deg,var(--blue),var(--cyan));color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:600;cursor:pointer;}

    /* ─ Layout ─ */
    .sidebar{position:fixed;top:0;left:0;width:230px;height:100%;background:var(--dark);padding-bottom:24px;z-index:100;display:flex;flex-direction:column;overflow-y:auto;}
    .sidebar-logo{padding:24px 24px 22px;font-weight:800;font-size:1.1rem;color:#fff;letter-spacing:-.02em;border-bottom:1px solid rgba(255,255,255,.08);}
    .sidebar-logo span{color:var(--cyan);}
    .sidebar-section{padding:18px 24px 6px;font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);}
    .sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 24px;color:rgba(255,255,255,.55);font-size:.88rem;font-weight:500;transition:all .2s;border-left:3px solid transparent;}
    .sidebar-link:hover{color:#fff;background:rgba(255,255,255,.05);text-decoration:none;}
    .sidebar-link.active{color:#fff;background:rgba(0,180,216,.12);border-left-color:var(--cyan);}
    .sidebar-link .icon{width:18px;text-align:center;font-size:1rem;}
    .sidebar-spacer{flex:1;}
    .sidebar-bottom{padding:16px;}
    .main{margin-left:230px;padding:32px;min-height:100vh;}
    .top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:16px;}
    .top-bar h1{font-size:1.35rem;font-weight:700;}
    .top-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}

    /* ─ Alerts ─ */
    .alert{padding:12px 18px;border-radius:8px;font-size:.88rem;font-weight:500;margin-bottom:24px;display:flex;align-items:center;gap:10px;}
    .alert-success{background:#f0fdf4;border:1px solid #86efac;color:#15803d;}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:var(--red);}
    .alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;}

    /* ─ Buttons ─ */
    .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:.84rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
    .btn:hover{opacity:.9;transform:translateY(-1px);text-decoration:none;}
    .btn-primary{background:linear-gradient(135deg,var(--blue),var(--cyan));color:#fff;}
    .btn-orange{background:linear-gradient(135deg,var(--orange),var(--purple));color:#fff;}
    .btn-green{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
    .btn-outline{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
    .btn-outline:hover{color:var(--text);border-color:#cbd5e1;}
    .btn-danger{background:#fef2f2;color:var(--red);border:1px solid #fecaca;padding:5px 10px;font-size:.78rem;}
    .btn-danger:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .btn-edit{background:#eff6ff;color:var(--blue);border:1px solid #bfdbfe;padding:5px 12px;font-size:.78rem;}
    .btn-edit:hover{background:var(--blue);color:#fff;border-color:var(--blue);}
    .btn-sidebar-logout{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1);border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:500;transition:all .2s;}
    .btn-sidebar-logout:hover{background:rgba(255,255,255,.12);color:#fff;}

    /* ─ Cards / Stats ─ */
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:24px;}
    .stat-card{background:var(--card);border-radius:12px;padding:22px;border:1px solid var(--border);}
    .stat-label{font-size:.72rem;font-weight:700;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;}
    .stat-num{font-size:2rem;font-weight:700;line-height:1;}
    .stat-sub{font-size:.75rem;color:var(--muted);margin-top:4px;}
    .blue  .stat-num{color:var(--blue);}
    .green .stat-num{color:var(--green);}
    .purple.stat-num{color:var(--purple);}

    /* ─ Table ─ */
    .table-wrap{background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;}
    table{width:100%;border-collapse:collapse;}
    thead{background:#F8FAFC;}
    th{text-align:left;padding:11px 16px;font-size:.72rem;font-weight:700;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap;}
    td{padding:12px 16px;border-bottom:1px solid #F1F5F9;vertical-align:top;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:#F8FAFC;}
    .cell-name{font-weight:600;}
    .cell-email{color:var(--blue);font-size:.84rem;}
    .cell-date{font-size:.78rem;color:var(--muted);white-space:nowrap;}
    .cell-msg{font-size:.8rem;color:var(--muted);max-width:180px;}
    .truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;display:block;}
    .role-badge{background:rgba(0,102,204,.08);color:var(--blue);padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:600;white-space:nowrap;}
    .empty-state{text-align:center;padding:56px;color:var(--muted);}
    .empty-state h3{font-size:1rem;margin-bottom:6px;}

    /* ─ Role bars ─ */
    .roles-card{background:var(--card);border-radius:12px;padding:22px;border:1px solid var(--border);margin-bottom:24px;}
    .roles-card h3{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px;}
    .bar-row{display:flex;align-items:center;gap:10px;margin-bottom:9px;}
    .bar-label{min-width:175px;font-size:.82rem;font-weight:500;}
    .bar-track{flex:1;height:7px;background:#F1F5F9;border-radius:4px;overflow:hidden;}
    .bar-fill{height:100%;background:linear-gradient(90deg,var(--blue),var(--cyan));border-radius:4px;}
    .bar-count{min-width:24px;text-align:right;font-size:.82rem;font-weight:600;color:var(--blue);}

    /* ─ Search ─ */
    .search-row{display:flex;gap:10px;margin-bottom:18px;align-items:center;flex-wrap:wrap;}
    .search-inp{flex:1;min-width:180px;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;outline:none;transition:border-color .2s;}
    .search-inp:focus{border-color:var(--blue);}
    .result-count{font-size:.82rem;color:var(--muted);}

    /* ─ Forms (settings tabs) ─ */
    .card{background:var(--card);border-radius:12px;border:1px solid var(--border);padding:28px;margin-bottom:24px;}
    .card-title{font-size:1rem;font-weight:700;color:var(--text);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border);}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:16px;}
    .form-label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);margin-bottom:6px;letter-spacing:.03em;}
    .form-input,.form-select,.form-textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.9rem;outline:none;transition:border-color .2s;background:#fff;color:var(--text);}
    .form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--blue);}
    .form-textarea{resize:vertical;min-height:100px;}
    .form-full{grid-column:1/-1;}
    .form-hint{font-size:.75rem;color:var(--muted);margin-top:4px;}

    /* ─ Agenda / Speaker list ─ */
    .agenda-day{font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:linear-gradient(90deg,var(--blue),var(--cyan));padding:8px 16px;border-radius:8px 8px 0 0;margin-top:20px;}
    .agenda-day:first-child{margin-top:0;}
    .type-pill{display:inline-block;padding:2px 9px;border-radius:4px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;}

    /* ─ Divider in form area ─ */
    .or-divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--muted);font-size:.8rem;}
    .or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}

    /* ─ Email preview panel ─ */
    .preview-panel{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
    .preview-toolbar{padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;}
    .preview-toolbar span{font-size:.85rem;font-weight:600;color:var(--text);}
    .preview-iframe{width:100%;height:560px;border:none;display:block;}

    @media(max-width:900px){.sidebar{display:none;}.main{margin-left:0;}.stats-grid{grid-template-columns:1fr 1fr;}.form-grid{grid-template-columns:1fr;}}
    @media(max-width:600px){.stats-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<?php if (!$authenticated): ?>
<!-- ══════════════ LOGIN ══════════════ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">SUMMIT<span>26</span></div>
    <div class="login-sub">Admin Dashboard · Secure Access</div>
    <?php if ($authError): ?><div class="login-err"><?= e($authError) ?></div><?php endif; ?>
    <form method="POST">
      <label class="lbl" for="pw">Password</label>
      <input class="inp" type="password" id="pw" name="password" autofocus placeholder="Enter admin password" />
      <button class="login-btn" type="submit">Sign In &rarr;</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════ ADMIN ══════════════ -->
<div class="sidebar">
  <div class="sidebar-logo">SUMMIT<span>26</span></div>

  <div class="sidebar-section">Registrations</div>
  <a class="sidebar-link <?= $tab==='registrations'?'active':'' ?>" href="admin.php?tab=registrations">
    <span class="icon">👥</span> Registrations
  </a>

  <div class="sidebar-section">Content</div>
  <a class="sidebar-link <?= $tab==='event'?'active':'' ?>" href="admin.php?tab=event">
    <span class="icon">📋</span> Event Info
  </a>
  <a class="sidebar-link <?= $tab==='agenda'?'active':'' ?>" href="admin.php?tab=agenda">
    <span class="icon">📅</span> Agenda
  </a>
  <a class="sidebar-link <?= $tab==='speakers'?'active':'' ?>" href="admin.php?tab=speakers">
    <span class="icon">🎤</span> Speakers
  </a>

  <div class="sidebar-section">Email</div>
  <a class="sidebar-link <?= $tab==='email'?'active':'' ?>" href="admin.php?tab=email">
    <span class="icon">✉️</span> Email Settings
  </a>
  <a class="sidebar-link" href="email_preview.php" target="_blank">
    <span class="icon">👁️</span> Preview Email
  </a>

  <div class="sidebar-section">Tools</div>
  <a class="sidebar-link" href="admin.php?export=1">
    <span class="icon">⬇️</span> Export CSV
  </a>
  <a class="sidebar-link" href="index.php" target="_blank">
    <span class="icon">🌐</span> View Website
  </a>

  <div class="sidebar-spacer"></div>
  <div class="sidebar-bottom">
    <form method="POST">
      <button name="logout" class="btn-sidebar-logout" type="submit">⎋ &nbsp;Sign Out</button>
    </form>
  </div>
</div>

<div class="main">

<?php
/* ── Flash messages ─────────────────────────────────────────────────────── */
$msgMap = [
    'saved'       => ['success', '✓ Changes saved successfully.'],
    'test_sent'   => ['success', '✓ Test email sent! Check your inbox (and spam folder).'],
    'test_failed' => ['error',   '⚠ Email could not be sent. Check your PHP mail configuration.'],
];
if ($msg && isset($msgMap[$msg])):
    [$msgType, $msgText] = $msgMap[$msg];
?>
<div class="alert alert-<?= $msgType ?>"><?= $msgText ?></div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   TAB: REGISTRATIONS
═══════════════════════════════════════════════════════════════════ */ ?>
<?php if ($tab === 'registrations'): ?>
<div class="top-bar">
  <h1>Registrations</h1>
  <div class="top-actions">
    <a href="admin.php?export=1" class="btn btn-green">⬇ Export CSV</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-label">Total</div>
    <div class="stat-num"><?= $stats['total'] ?></div>
    <div class="stat-sub">All time</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Today</div>
    <div class="stat-num"><?= $stats['today'] ?></div>
    <div class="stat-sub"><?= date('d. M Y') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Companies</div>
    <div class="stat-num" style="color:var(--purple);"><?= $stats['companies'] ?></div>
    <div class="stat-sub">Unique orgs</div>
  </div>
</div>

<?php if (!empty($roleStats)): ?>
<div class="roles-card">
  <h3>By Role</h3>
  <?php $maxRole = max($roleStats); foreach ($roleStats as $r => $c): ?>
  <div class="bar-row">
    <div class="bar-label"><?= e($r) ?></div>
    <div class="bar-track"><div class="bar-fill" style="width:<?= round($c/$maxRole*100) ?>%"></div></div>
    <div class="bar-count"><?= $c ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<form class="search-row" method="GET">
  <input type="hidden" name="tab" value="registrations" />
  <input class="search-inp" type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, email or company…" />
  <button type="submit" class="btn btn-primary">Search</button>
  <?php if ($search): ?><a href="admin.php?tab=registrations" class="btn btn-outline">Clear</a><?php endif; ?>
  <span class="result-count"><?= count($registrations) ?> result<?= count($registrations)!==1?'s':'' ?></span>
</form>

<div class="table-wrap">
  <?php if (empty($registrations)): ?>
  <div class="empty-state">
    <h3><?= $search?'No results.':'No registrations yet.' ?></h3>
    <p><?= $search?'Try a different search term.':'The first registration will appear here.' ?></p>
  </div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>#</th><th>Name</th><th>Email</th><th>Company</th><th>Role</th><th>Message</th><th>Registered</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($registrations as $reg): ?>
    <tr>
      <td style="color:var(--muted);font-size:.78rem;"><?= $reg['id'] ?></td>
      <td class="cell-name"><?= e($reg['fname'].' '.$reg['lname']) ?></td>
      <td class="cell-email"><a href="mailto:<?= e($reg['email']) ?>"><?= e($reg['email']) ?></a></td>
      <td><?= e($reg['company']??'—') ?></td>
      <td><?php if($reg['role']): ?><span class="role-badge"><?= e($reg['role']) ?></span><?php else: echo '—'; endif; ?></td>
      <td class="cell-msg"><?php if($reg['message']): ?><span class="truncate" title="<?= e($reg['message']) ?>"><?= e($reg['message']) ?></span><?php else: echo '—'; endif; ?></td>
      <td class="cell-date"><?= date('d.m.Y', strtotime($reg['created'])) ?><br><span style="font-size:.73rem;"><?= date('H:i', strtotime($reg['created'])) ?></span></td>
      <td>
        <form method="POST" action="admin_action.php" onsubmit="return confirm('Delete this registration?')">
          <input type="hidden" name="action" value="delete_registration" />
          <input type="hidden" name="id" value="<?= $reg['id'] ?>" />
          <input type="hidden" name="redirect_tab" value="registrations" />
          <button type="submit" class="btn btn-danger">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php /* ═══════════════════════════════════════════════════════════════
   TAB: EVENT INFO
═══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'event'): ?>
<div class="top-bar"><h1>Event Info</h1></div>
<form method="POST" action="admin_action.php">
  <input type="hidden" name="action" value="save_event_info" />
  <input type="hidden" name="redirect_tab" value="event" />

  <div class="card">
    <div class="card-title">General</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Event Name</label>
        <input class="form-input" type="text" name="event_name" value="<?= v($settings,'event_name') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Tagline / Motto</label>
        <input class="form-input" type="text" name="event_tagline" value="<?= v($settings,'event_tagline') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Date Label (shown in email)</label>
        <input class="form-input" type="text" name="event_date_label" value="<?= v($settings,'event_date_label') ?>" placeholder="e.g. September 18–19, 2026" />
      </div>
      <div class="form-group">
        <label class="form-label">Website URL</label>
        <input class="form-input" type="text" name="event_website" value="<?= v($settings,'event_website') ?>" placeholder="https://..." />
      </div>
      <div class="form-group">
        <label class="form-label">Day 1 Label</label>
        <input class="form-input" type="text" name="event_day1_label" value="<?= v($settings,'event_day1_label') ?>" placeholder="Day 1 — September 18, 2026" />
      </div>
      <div class="form-group">
        <label class="form-label">Day 2 Label</label>
        <input class="form-input" type="text" name="event_day2_label" value="<?= v($settings,'event_day2_label') ?>" placeholder="Day 2 — September 19, 2026" />
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Location</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Venue Name</label>
        <input class="form-input" type="text" name="event_location_name" value="<?= v($settings,'event_location_name') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Full Address</label>
        <input class="form-input" type="text" name="event_address" value="<?= v($settings,'event_address') ?>" />
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>

<?php /* ═══════════════════════════════════════════════════════════════
   TAB: AGENDA
═══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'agenda'): ?>
<div class="top-bar">
  <h1>Agenda</h1>
  <div class="top-actions">
    <a href="admin.php?tab=agenda" class="btn btn-outline">+ Add New</a>
  </div>
</div>

<!-- Edit / Add form -->
<div class="card">
  <?php if ($editItem): ?>
  <div class="card-title">Edit Agenda Item</div>
  <form method="POST" action="admin_action.php">
    <input type="hidden" name="action" value="edit_agenda" />
    <input type="hidden" name="redirect_tab" value="agenda" />
    <input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>" />
  <?php else: ?>
  <div class="card-title">Add New Agenda Item</div>
  <form method="POST" action="admin_action.php">
    <input type="hidden" name="action" value="add_agenda" />
    <input type="hidden" name="redirect_tab" value="agenda" />
  <?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Day</label>
        <select class="form-select" name="day">
          <option value="1"<?= sel((string)($editItem['day']??''),'1') ?>>Day 1</option>
          <option value="2"<?= sel((string)($editItem['day']??''),'2') ?>>Day 2</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Display Order <span class="form-hint">(lower = first)</span></label>
        <input class="form-input" type="number" name="sort_order" value="<?= (int)($editItem['sort_order']??0) ?>" min="0" step="10" />
      </div>
      <div class="form-group">
        <label class="form-label">Time</label>
        <input class="form-input" type="text" name="time_label" value="<?= v($editItem??[],'time_label') ?>" placeholder="09:00" required />
      </div>
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-select" name="type">
          <?php foreach ($typeOptions as $val => $label): ?>
          <option value="<?= $val ?>"<?= sel($editItem['type']??'session',$val) ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-full">
        <label class="form-label">Title</label>
        <input class="form-input" type="text" name="title" value="<?= v($editItem??[],'title') ?>" required />
      </div>
      <div class="form-group form-full">
        <label class="form-label">Speaker(s)</label>
        <input class="form-input" type="text" name="speaker" value="<?= v($editItem??[],'speaker') ?>" placeholder="Name — Company (leave empty for breaks)" />
      </div>
      <div class="form-group form-full">
        <label class="form-label">Short Description</label>
        <textarea class="form-textarea" name="description" rows="2"><?= v($editItem??[],'description') ?></textarea>
      </div>
    </div>
    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn btn-primary"><?= $editItem ? 'Save Changes' : '+ Add Item' ?></button>
      <?php if ($editItem): ?><a href="admin.php?tab=agenda" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Agenda list grouped by day -->
<?php
$byDay = [];
foreach ($agenda as $item) $byDay[(int)$item['day']][] = $item;
foreach ($byDay as $day => $items): ?>
<div class="agenda-day"><?= e($settings["event_day{$day}_label"] ?? "Day {$day}") ?></div>
<div class="table-wrap" style="border-radius:0 0 12px 12px;margin-bottom:24px;">
  <table>
    <thead>
      <tr><th>Order</th><th>Time</th><th>Type</th><th>Title</th><th>Speaker</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item):
      $tb = $typeBadge[$item['type']] ?? $typeBadge['session'];
    ?>
    <tr>
      <td style="color:var(--muted);font-size:.8rem;"><?= (int)$item['sort_order'] ?></td>
      <td style="font-weight:700;color:var(--blue);white-space:nowrap;"><?= e($item['time_label']) ?></td>
      <td><span class="type-pill" style="background:<?= $tb[0] ?>;color:<?= $tb[1] ?>;"><?= e($typeOptions[$item['type']]??$item['type']) ?></span></td>
      <td style="font-weight:600;"><?= e($item['title']) ?></td>
      <td style="font-size:.84rem;color:var(--muted);"><?= e($item['speaker']??'—') ?></td>
      <td>
        <div style="display:flex;gap:6px;">
          <a href="admin.php?tab=agenda&edit=<?= (int)$item['id'] ?>" class="btn btn-edit">✏ Edit</a>
          <form method="POST" action="admin_action.php" onsubmit="return confirm('Delete this item?')">
            <input type="hidden" name="action" value="delete_agenda" />
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>" />
            <input type="hidden" name="redirect_tab" value="agenda" />
            <button type="submit" class="btn btn-danger">🗑</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>
<?php if (empty($agenda)): ?>
<div class="card"><p style="color:var(--muted);text-align:center;padding:24px 0;">No agenda items yet. Add one above.</p></div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   TAB: SPEAKERS
═══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'speakers'): ?>
<div class="top-bar"><h1>Speakers</h1></div>

<!-- Edit / Add form -->
<div class="card">
  <?php if ($editItem): ?>
  <div class="card-title">Edit Speaker</div>
  <form method="POST" action="admin_action.php">
    <input type="hidden" name="action" value="edit_speaker" />
    <input type="hidden" name="redirect_tab" value="speakers" />
    <input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>" />
  <?php else: ?>
  <div class="card-title">Add New Speaker</div>
  <form method="POST" action="admin_action.php">
    <input type="hidden" name="action" value="add_speaker" />
    <input type="hidden" name="redirect_tab" value="speakers" />
  <?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-input" type="text" name="name" value="<?= v($editItem??[],'name') ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Role / Title</label>
        <input class="form-input" type="text" name="role" value="<?= v($editItem??[],'role') ?>" placeholder="e.g. Head of AI Research" />
      </div>
      <div class="form-group">
        <label class="form-label">Company / Organisation</label>
        <input class="form-input" type="text" name="company" value="<?= v($editItem??[],'company') ?>" />
      </div>
      <div class="form-group form-full">
        <label class="form-label">Short Bio</label>
        <textarea class="form-textarea" name="bio" rows="2"><?= v($editItem??[],'bio') ?></textarea>
      </div>
    </div>
    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn btn-primary"><?= $editItem ? 'Save Changes' : '+ Add Speaker' ?></button>
      <?php if ($editItem): ?><a href="admin.php?tab=speakers" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="table-wrap">
  <?php if (empty($speakers)): ?>
  <div class="empty-state"><h3>No speakers yet.</h3><p>Add one above.</p></div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>#</th><th>Name</th><th>Role</th><th>Company</th><th>Bio</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($speakers as $sp): ?>
    <tr>
      <td style="color:var(--muted);font-size:.78rem;"><?= (int)$sp['id'] ?></td>
      <td class="cell-name"><?= e($sp['name']) ?></td>
      <td style="color:var(--cyan);font-size:.84rem;font-weight:500;"><?= e($sp['role']??'—') ?></td>
      <td style="font-size:.84rem;"><?= e($sp['company']??'—') ?></td>
      <td class="cell-msg"><span class="truncate" title="<?= e($sp['bio']??'') ?>"><?= e($sp['bio']??'—') ?></span></td>
      <td>
        <div style="display:flex;gap:6px;">
          <a href="admin.php?tab=speakers&edit=<?= (int)$sp['id'] ?>" class="btn btn-edit">✏ Edit</a>
          <form method="POST" action="admin_action.php" onsubmit="return confirm('Delete this speaker?')">
            <input type="hidden" name="action" value="delete_speaker" />
            <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>" />
            <input type="hidden" name="redirect_tab" value="speakers" />
            <button type="submit" class="btn btn-danger">🗑</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php /* ═══════════════════════════════════════════════════════════════
   TAB: EMAIL SETTINGS
═══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'email'): ?>
<div class="top-bar">
  <h1>Email Settings</h1>
  <div class="top-actions">
    <a href="email_preview.php" target="_blank" class="btn btn-outline">👁 Preview Email</a>
  </div>
</div>

<!-- Email Settings Form -->
<form method="POST" action="admin_action.php">
  <input type="hidden" name="action" value="save_email_settings" />
  <input type="hidden" name="redirect_tab" value="email" />
  <div class="card">
    <div class="card-title">Sender & Subject</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">From Name</label>
        <input class="form-input" type="text" name="email_from_name" value="<?= v($settings,'email_from_name') ?>" />
        <div class="form-hint">Displayed as the sender name in email clients.</div>
      </div>
      <div class="form-group">
        <label class="form-label">From Email Address</label>
        <input class="form-input" type="email" name="email_from_address" value="<?= v($settings,'email_from_address') ?>" />
        <div class="form-hint">Must be a valid address on your domain for best deliverability.</div>
      </div>
      <div class="form-group form-full">
        <label class="form-label">Email Subject</label>
        <input class="form-input" type="text" name="email_subject" value="<?= v($settings,'email_subject') ?>" />
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-title">Email Body Text</div>
    <div class="form-group">
      <label class="form-label">Intro Text <span class="form-hint">(shown after the greeting, before the agenda)</span></label>
      <textarea class="form-textarea" name="email_intro" rows="4"><?= v($settings,'email_intro') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Closing Text <span class="form-hint">(shown after speakers, before the footer)</span></label>
      <textarea class="form-textarea" name="email_closing" rows="3"><?= v($settings,'email_closing') ?></textarea>
    </div>
  </div>
  <button type="submit" class="btn btn-primary">Save Email Settings</button>
</form>

<div class="or-divider">Send Test Email</div>

<div class="card">
  <div class="card-title">Send Test Confirmation Email</div>
  <p style="font-size:.88rem;color:var(--muted);margin-bottom:18px;">
    Sends a fully rendered confirmation email (with current agenda &amp; speakers) to the address below. Use this to check formatting and deliverability.
  </p>
  <form method="POST" action="admin_action.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="action" value="send_test_email" />
    <input type="hidden" name="redirect_tab" value="email" />
    <div class="form-group" style="flex:1;min-width:220px;margin:0;">
      <label class="form-label">Test Recipient Address</label>
      <input class="form-input" type="email" name="test_address" required placeholder="you@example.com" />
    </div>
    <button type="submit" class="btn btn-orange" style="margin-bottom:0;">Send Test ✉</button>
  </form>
</div>

<div class="card" style="background:#fffbeb;border-color:#fde68a;">
  <div class="card-title" style="border-color:#fde68a;">⚠ SMTP / Deliverability Note</div>
  <p style="font-size:.86rem;color:#92400e;line-height:1.7;">
    This uses PHP's built-in <code>mail()</code> function. On most shared hosting this works out of the box. If emails land in spam or fail to send, ask your host to enable <strong>SPF &amp; DKIM records</strong> for your domain, or switch to an SMTP service (e.g. Mailgun, SendGrid, Brevo).
    <br><br>Check your server's PHP error log for <strong>"Summit26 email FAILED"</strong> entries if test emails don't arrive.
  </p>
</div>

<?php endif; ?>
</div><!-- /.main -->
<?php endif; ?>
</body>
</html>
