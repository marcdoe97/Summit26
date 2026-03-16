<?php
declare(strict_types=1);
session_start();

// ─── CONFIGURATION ────────────────────────────────────────────────────────
// Change this password before going live!
define('ADMIN_PASSWORD', 'summit26admin');
define('DB_PATH', __DIR__ . '/data/registrations.db');

// ─── AUTH ─────────────────────────────────────────────────────────────────
$error = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_auth'] = true;
    } else {
        $error = 'Incorrect password.';
    }
}

$authenticated = !empty($_SESSION['admin_auth']);

// ─── DATABASE ─────────────────────────────────────────────────────────────
function getDb(): PDO {
    if (!file_exists(DB_PATH)) {
        die('<p style="padding:40px;font-family:sans-serif;color:#e53e3e;">No database found yet. Submit the registration form first.</p>');
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// ─── CSV EXPORT ───────────────────────────────────────────────────────────
if ($authenticated && isset($_GET['export'])) {
    $pdo  = getDb();
    $rows = $pdo->query("SELECT id, fname, lname, email, company, role, message, created FROM registrations ORDER BY created DESC")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="summit26_registrations_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Company', 'Role', 'Message', 'Registered At']);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

// ─── DELETE ───────────────────────────────────────────────────────────────
if ($authenticated && isset($_POST['delete_id'])) {
    $pdo  = getDb();
    $stmt = $pdo->prepare('DELETE FROM registrations WHERE id = ?');
    $stmt->execute([(int) $_POST['delete_id']]);
    header('Location: admin.php');
    exit;
}

// ─── FETCH DATA ───────────────────────────────────────────────────────────
$registrations = [];
$stats         = ['total' => 0, 'today' => 0, 'companies' => 0];
$search        = '';

if ($authenticated) {
    $pdo    = getDb();
    $search = trim($_GET['q'] ?? '');

    if ($search !== '') {
        $like  = '%' . $search . '%';
        $query = "SELECT * FROM registrations WHERE fname LIKE ? OR lname LIKE ? OR email LIKE ? OR company LIKE ? ORDER BY created DESC";
        $stmt  = $pdo->prepare($query);
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM registrations ORDER BY created DESC");
    }
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats['total']     = (int) $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
    $stats['today']     = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE date(created) = date('now')")->fetchColumn();
    $stats['companies'] = (int) $pdo->query("SELECT COUNT(DISTINCT company) FROM registrations WHERE company != ''")->fetchColumn();
}

// ─── ROLES summary ────────────────────────────────────────────────────────
$roleStats = [];
if ($authenticated) {
    $pdo = getDb();
    $rs  = $pdo->query("SELECT role, COUNT(*) as cnt FROM registrations WHERE role != '' GROUP BY role ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rs as $r) $roleStats[$r['role']] = (int) $r['cnt'];
}
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
    :root {
      --blue:   #0066CC;
      --cyan:   #00B4D8;
      --orange: #FF6B35;
      --purple: #6B5B95;
      --dark:   #0B132B;
      --green:  #22c55e;
      --red:    #ef4444;
      --bg:     #F1F5F9;
      --card:   #fff;
      --border: #E2E8F0;
      --muted:  #64748B;
      --text:   #1E293B;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
    a { color: var(--blue); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* ─ Login ─ */
    .login-wrap {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, var(--dark), #0d2353);
    }
    .login-box {
      background: #fff;
      border-radius: 16px;
      padding: 48px 40px;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .login-logo {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      letter-spacing: -0.03em;
      margin-bottom: 6px;
    }
    .login-logo span { color: var(--cyan); }
    .login-sub { font-size: 0.83rem; color: var(--muted); margin-bottom: 32px; }
    .login-label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; letter-spacing: 0.04em; }
    .login-input {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.92rem;
      outline: none;
      transition: border-color 0.2s;
      margin-bottom: 20px;
    }
    .login-input:focus { border-color: var(--blue); }
    .login-btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, var(--blue), var(--cyan));
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    .login-btn:hover { opacity: 0.9; }
    .login-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: var(--red);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 0.85rem;
      margin-bottom: 16px;
    }

    /* ─ Layout ─ */
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 220px; height: 100%;
      background: var(--dark);
      padding: 28px 0;
      z-index: 100;
    }
    .sidebar-logo {
      padding: 0 24px 28px;
      font-weight: 700;
      font-size: 1.1rem;
      color: #fff;
      letter-spacing: -0.02em;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .sidebar-logo span { color: var(--cyan); }
    .sidebar-label {
      padding: 20px 24px 8px;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.25);
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 24px;
      color: rgba(255,255,255,0.6);
      font-size: 0.88rem;
      font-weight: 500;
      transition: all 0.2s;
      text-decoration: none;
    }
    .sidebar-link:hover, .sidebar-link.active { color: #fff; background: rgba(255,255,255,0.06); text-decoration: none; }
    .sidebar-link .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--cyan); flex-shrink: 0; }

    .main { margin-left: 220px; padding: 32px; min-height: 100vh; }

    /* ─ Header ─ */
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 32px;
      flex-wrap: wrap;
      gap: 16px;
    }
    .top-bar h1 { font-size: 1.4rem; font-weight: 700; color: var(--text); }
    .top-bar-right { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

    /* ─ Buttons ─ */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 18px;
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      text-decoration: none;
    }
    .btn:hover { text-decoration: none; opacity: 0.9; transform: translateY(-1px); }
    .btn-primary { background: linear-gradient(135deg, var(--blue), var(--cyan)); color: #fff; }
    .btn-outline { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
    .btn-outline:hover { color: var(--text); border-color: #cbd5e1; }
    .btn-danger { background: #fef2f2; color: var(--red); border: 1px solid #fecaca; padding: 5px 10px; font-size: 0.78rem; }
    .btn-danger:hover { background: var(--red); color: #fff; border-color: var(--red); }
    .btn-green { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
    .btn-logout { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.1); margin: 8px 16px 0; width: calc(100% - 32px); justify-content: center; }
    .btn-logout:hover { background: rgba(255,255,255,0.12); color: #fff; transform: none; }

    /* ─ Stats cards ─ */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
    .stat-card {
      background: var(--card);
      border-radius: 12px;
      padding: 24px;
      border: 1px solid var(--border);
      box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .stat-label { font-size: 0.78rem; font-weight: 600; color: var(--muted); letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 8px; }
    .stat-num { font-size: 2.2rem; font-weight: 700; color: var(--text); line-height: 1; }
    .stat-sub { font-size: 0.78rem; color: var(--muted); margin-top: 6px; }
    .stat-card.accent-blue .stat-num { color: var(--blue); }
    .stat-card.accent-green .stat-num { color: var(--green); }
    .stat-card.accent-purple .stat-num { color: var(--purple); }

    /* ─ Search ─ */
    .search-bar {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      align-items: center;
      flex-wrap: wrap;
    }
    .search-input {
      flex: 1;
      min-width: 200px;
      padding: 9px 14px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.88rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .search-input:focus { border-color: var(--blue); }
    .result-count { font-size: 0.83rem; color: var(--muted); }

    /* ─ Table ─ */
    .table-wrap { background: var(--card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    table { width: 100%; border-collapse: collapse; }
    thead { background: #F8FAFC; }
    th {
      text-align: left;
      padding: 12px 16px;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--muted);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    td { padding: 13px 16px; border-bottom: 1px solid #F1F5F9; vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #F8FAFC; }
    .cell-name { font-weight: 600; color: var(--text); }
    .cell-email { color: var(--blue); font-size: 0.85rem; }
    .cell-company { font-size: 0.85rem; color: var(--text); }
    .cell-role span {
      background: rgba(0,102,204,0.08);
      color: var(--blue);
      padding: 3px 10px;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .cell-date { font-size: 0.8rem; color: var(--muted); white-space: nowrap; }
    .cell-msg { font-size: 0.82rem; color: var(--muted); max-width: 200px; }
    .cell-msg .msg-preview { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; display: block; }
    .empty-state { text-align: center; padding: 60px; color: var(--muted); }
    .empty-state h3 { font-size: 1rem; margin-bottom: 8px; }

    /* ─ Role chart ─ */
    .roles-card {
      background: var(--card);
      border-radius: 12px;
      padding: 24px;
      border: 1px solid var(--border);
      margin-bottom: 28px;
    }
    .roles-card h3 { font-size: 0.88rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 16px; }
    .role-bar-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .role-bar-label { min-width: 170px; font-size: 0.82rem; color: var(--text); font-weight: 500; }
    .role-bar-track { flex: 1; height: 8px; background: #F1F5F9; border-radius: 4px; overflow: hidden; }
    .role-bar-fill { height: 100%; background: linear-gradient(90deg, var(--blue), var(--cyan)); border-radius: 4px; transition: width 0.6s ease; }
    .role-bar-count { min-width: 28px; text-align: right; font-size: 0.82rem; font-weight: 600; color: var(--blue); }

    @media (max-width: 900px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 20px; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
      .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<?php if (!$authenticated): ?>
<!-- ══════════════════════ LOGIN ══════════════════════ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">SUMMIT<span>26</span></div>
    <div class="login-sub">Admin Dashboard · Secure Access</div>
    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <label class="login-label" for="pw">Password</label>
      <input class="login-input" type="password" id="pw" name="password" autofocus placeholder="Enter admin password" />
      <button class="login-btn" type="submit">Sign In &rarr;</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════ ADMIN ══════════════════════ -->
<div class="sidebar">
  <div class="sidebar-logo">SUMMIT<span>26</span></div>
  <div class="sidebar-label">Registrations</div>
  <a class="sidebar-link active" href="admin.php">
    <span class="dot"></span> All Registrations
  </a>
  <a class="sidebar-link" href="admin.php?export=1">
    <span class="dot" style="background:var(--orange);"></span> Export CSV
  </a>
  <div class="sidebar-label">Event</div>
  <a class="sidebar-link" href="index.php" target="_blank">
    <span class="dot" style="background:var(--purple);"></span> View Website
  </a>
  <form method="POST" style="margin-top:auto;padding-top:32px;">
    <button name="logout" class="btn btn-logout">Sign Out</button>
  </form>
</div>

<div class="main">
  <div class="top-bar">
    <h1>Registrations</h1>
    <div class="top-bar-right">
      <a href="admin.php?export=1" class="btn btn-green">&#8659; Export CSV</a>
      <a href="index.php" target="_blank" class="btn btn-outline">View Site &nearr;</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card accent-blue">
      <div class="stat-label">Total Registrations</div>
      <div class="stat-num"><?= $stats['total'] ?></div>
      <div class="stat-sub">All time</div>
    </div>
    <div class="stat-card accent-green">
      <div class="stat-label">New Today</div>
      <div class="stat-num"><?= $stats['today'] ?></div>
      <div class="stat-sub"><?= date('d. M Y') ?></div>
    </div>
    <div class="stat-card accent-purple">
      <div class="stat-label">Companies</div>
      <div class="stat-num"><?= $stats['companies'] ?></div>
      <div class="stat-sub">Unique organisations</div>
    </div>
  </div>

  <!-- Role breakdown -->
  <?php if (!empty($roleStats)): ?>
  <div class="roles-card">
    <h3>Registrations by Role</h3>
    <?php $maxRole = max($roleStats); foreach ($roleStats as $r => $c): ?>
    <div class="role-bar-row">
      <div class="role-bar-label"><?= htmlspecialchars($r) ?></div>
      <div class="role-bar-track">
        <div class="role-bar-fill" style="width:<?= round($c / $maxRole * 100) ?>%"></div>
      </div>
      <div class="role-bar-count"><?= $c ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Search -->
  <form class="search-bar" method="GET">
    <input class="search-input" type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email or company…" />
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="admin.php" class="btn btn-outline">Clear</a>
    <?php endif; ?>
    <span class="result-count"><?= count($registrations) ?> result<?= count($registrations) !== 1 ? 's' : '' ?></span>
  </form>

  <!-- Table -->
  <div class="table-wrap">
    <?php if (empty($registrations)): ?>
    <div class="empty-state">
      <h3><?= $search ? 'No results found.' : 'No registrations yet.' ?></h3>
      <p><?= $search ? 'Try a different search term.' : 'Registrations will appear here once the first form is submitted.' ?></p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Company</th>
          <th>Role</th>
          <th>Message</th>
          <th>Registered</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registrations as $reg): ?>
        <tr>
          <td style="color:var(--muted);font-size:0.8rem;"><?= $reg['id'] ?></td>
          <td class="cell-name"><?= htmlspecialchars($reg['fname'] . ' ' . $reg['lname']) ?></td>
          <td class="cell-email"><a href="mailto:<?= htmlspecialchars($reg['email']) ?>"><?= htmlspecialchars($reg['email']) ?></a></td>
          <td class="cell-company"><?= htmlspecialchars($reg['company'] ?: '—') ?></td>
          <td class="cell-role">
            <?php if ($reg['role']): ?>
              <span><?= htmlspecialchars($reg['role']) ?></span>
            <?php else: echo '—'; endif; ?>
          </td>
          <td class="cell-msg">
            <?php if ($reg['message']): ?>
              <span class="msg-preview" title="<?= htmlspecialchars($reg['message']) ?>"><?= htmlspecialchars($reg['message']) ?></span>
            <?php else: echo '—'; endif; ?>
          </td>
          <td class="cell-date">
            <?= date('d.m.Y', strtotime($reg['created'])) ?><br>
            <span style="color:var(--muted);font-size:0.75rem;"><?= date('H:i', strtotime($reg['created'])) ?></span>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Delete this registration?')">
              <input type="hidden" name="delete_id" value="<?= $reg['id'] ?>" />
              <button type="submit" class="btn btn-danger">&#128465; Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
</body>
</html>
