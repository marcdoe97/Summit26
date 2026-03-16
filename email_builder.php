<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ── Public API ─────────────────────────────────────────────────────────── */

function buildEmailHtml(string $recipientName, PDO $pdo): string {
    $s        = getSettings($pdo);
    $agenda   = $pdo->query("SELECT * FROM agenda   ORDER BY day, sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
    $speakers = $pdo->query("SELECT * FROM speakers ORDER BY rowid")->fetchAll(PDO::FETCH_ASSOC);

    $name     = htmlspecialchars($recipientName);
    $tagline  = htmlspecialchars($s['event_tagline']       ?? '');
    $date     = htmlspecialchars($s['event_date_label']    ?? '');
    $locName  = htmlspecialchars($s['event_location_name'] ?? '');
    $locAddr  = htmlspecialchars($s['event_address']       ?? '');
    $website  = htmlspecialchars($s['event_website']       ?? '#');
    $intro    = nl2br(htmlspecialchars($s['email_intro']   ?? ''));
    $closing  = nl2br(htmlspecialchars($s['email_closing'] ?? ''));

    $agendaHtml   = _buildAgendaSection($agenda, $s);
    $speakersHtml = _buildSpeakersSection($speakers);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>SUMMIT 26 – Registration Confirmed</title>
</head>
<body style="margin:0;padding:0;background-color:#F1F5F9;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F1F5F9;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

  <!-- HEADER -->
  <tr>
    <td style="background-color:#0066CC;background:linear-gradient(135deg,#0066CC 0%,#00B4D8 100%);border-radius:16px 16px 0 0;padding:36px 40px;text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-0.03em;">SUMMIT<span style="color:#A5F3FC;">26</span></div>
      <div style="font-size:11px;color:rgba(255,255,255,0.65);margin-top:6px;letter-spacing:0.14em;text-transform:uppercase;">{$tagline}</div>
    </td>
  </tr>

  <!-- CONFIRMATION + GREETING -->
  <tr>
    <td style="background-color:#ffffff;padding:40px 40px 24px;">
      <div style="text-align:center;margin-bottom:24px;">
        <span style="display:inline-block;background-color:#f0fdf4;border:1.5px solid #86efac;border-radius:50px;padding:7px 20px;">
          <span style="color:#15803d;font-size:13px;font-weight:700;">&#10003;&nbsp;&nbsp;Registration Confirmed</span>
        </span>
      </div>
      <h1 style="font-size:24px;font-weight:800;color:#0B132B;margin:0 0 14px;line-height:1.25;text-align:center;">Hi {$name}, your spot is reserved!</h1>
      <p style="font-size:15px;color:#475569;margin:0;line-height:1.75;">{$intro}</p>
    </td>
  </tr>

  <!-- EVENT DETAILS BOX -->
  <tr>
    <td style="background-color:#ffffff;padding:0 40px 28px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;">
        <tr><td style="padding:18px 22px 6px;">
          <span style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:0.12em;">Event Details</span>
        </td></tr>
        <tr><td style="padding:4px 22px 18px;">
          <table cellpadding="0" cellspacing="0" border="0">
            <tr><td style="padding:6px 0;font-size:14px;color:#1E293B;"><span style="margin-right:10px;">&#128197;</span><strong>{$date}</strong></td></tr>
            <tr><td style="padding:6px 0;font-size:14px;color:#1E293B;"><span style="margin-right:10px;">&#128205;</span>{$locName} &middot; {$locAddr}</td></tr>
            <tr><td style="padding:6px 0;font-size:14px;color:#1E293B;"><span style="margin-right:10px;">&#127760;</span><a href="{$website}" style="color:#0066CC;text-decoration:none;">{$website}</a></td></tr>
          </table>
        </td></tr>
      </table>
    </td>
  </tr>

  <!-- AGENDA -->
  <tr>
    <td style="background-color:#ffffff;padding:0 40px 28px;">
      <h2 style="font-size:17px;font-weight:800;color:#0B132B;margin:0 0 16px;padding-bottom:10px;border-bottom:2px solid #F1F5F9;">&#128197;&nbsp; Conference Agenda</h2>
      {$agendaHtml}
    </td>
  </tr>

  <!-- SPEAKERS -->
  <tr>
    <td style="background-color:#ffffff;padding:0 40px 28px;">
      <h2 style="font-size:17px;font-weight:800;color:#0B132B;margin:0 0 16px;padding-bottom:10px;border-bottom:2px solid #F1F5F9;">&#127908;&nbsp; Speakers</h2>
      {$speakersHtml}
    </td>
  </tr>

  <!-- CTA BUTTON -->
  <tr>
    <td style="background-color:#ffffff;padding:0 40px 36px;text-align:center;">
      <a href="{$website}" style="display:inline-block;background-color:#FF6B35;background:linear-gradient(135deg,#FF6B35 0%,#6B5B95 100%);color:#ffffff;text-decoration:none;padding:14px 38px;border-radius:50px;font-size:15px;font-weight:700;letter-spacing:-0.01em;">
        View Full Agenda &amp; Details &#8594;
      </a>
    </td>
  </tr>

  <!-- CLOSING -->
  <tr>
    <td style="background-color:#ffffff;padding:0 40px 36px;border-bottom:2px solid #F8FAFC;">
      <p style="font-size:14px;color:#64748B;margin:0;line-height:1.75;">{$closing}</p>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style="background-color:#0B132B;border-radius:0 0 16px 16px;padding:28px 40px;text-align:center;">
      <div style="font-size:17px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;margin-bottom:6px;">SUMMIT<span style="color:#00B4D8;">26</span></div>
      <div style="font-size:11px;color:rgba(255,255,255,0.35);letter-spacing:0.1em;text-transform:uppercase;">{$tagline}</div>
      <div style="font-size:11px;color:rgba(255,255,255,0.25);margin-top:14px;">{$locName} &middot; {$date}</div>
      <div style="font-size:10px;color:rgba(255,255,255,0.18);margin-top:10px;">You received this because you registered for SUMMIT 26.</div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function sendConfirmationEmail(string $toEmail, string $recipientName, PDO $pdo): bool {
    $s       = getSettings($pdo);
    $from    = $s['email_from_address'] ?? 'noreply@summit26.example.com';
    $fromN   = $s['email_from_name']    ?? 'SUMMIT 26 Team';
    $subject = $s['email_subject']      ?? 'SUMMIT 26 – Registration Confirmed';

    $html    = buildEmailHtml($recipientName, $pdo);
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromN} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $ok = @mail($toEmail, $subject, $html, $headers);
    error_log("Summit26 email " . ($ok ? "sent" : "FAILED") . " to {$toEmail}");
    return $ok;
}

/* ── Private helpers ────────────────────────────────────────────────────── */

function _buildAgendaSection(array $agenda, array $s): string {
    if (empty($agenda)) {
        return '<p style="font-size:14px;color:#94A3B8;">No agenda published yet.</p>';
    }

    $typeStyles = [
        'keynote'  => ['bg' => '#0066CC', 'color' => '#fff',    'label' => 'Keynote'],
        'session'  => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'label' => 'Session'],
        'workshop' => ['bg' => '#FEE2D5', 'color' => '#C2410C', 'label' => 'Workshop'],
        'panel'    => ['bg' => '#EDE9FE', 'color' => '#6D28D9', 'label' => 'Panel'],
        'break'    => ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => 'Break'],
        'closing'  => ['bg' => '#0B132B', 'color' => '#fff',    'label' => 'Closing'],
    ];

    // Group by day
    $byDay = [];
    foreach ($agenda as $item) {
        $byDay[(int) $item['day']][] = $item;
    }

    $html = '';
    foreach ($byDay as $day => $items) {
        $dayLabel = htmlspecialchars($s["event_day{$day}_label"] ?? "Day {$day}");
        $html .= <<<HTML
<div style="background-color:#0066CC;background:linear-gradient(90deg,#0066CC,#00B4D8);color:#fff;padding:9px 16px;border-radius:8px 8px 0 0;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">{$dayLabel}</div>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-top:none;border-radius:0 0 8px 8px;margin-bottom:20px;">
HTML;
        foreach ($items as $i => $item) {
            $borderBottom = ($i < count($items) - 1) ? 'border-bottom:1px solid #F1F5F9;' : '';
            $ts           = $typeStyles[$item['type']] ?? $typeStyles['session'];
            $typeBg       = $ts['bg'];
            $typeColor    = $ts['color'];
            $typeLabel    = htmlspecialchars($ts['label']);
            $time         = htmlspecialchars($item['time_label']);
            $title        = htmlspecialchars($item['title']);
            $speaker      = htmlspecialchars($item['speaker']);

            $speakerHtml = $speaker
                ? "<div style=\"font-size:11px;color:#64748B;margin-top:3px;\">{$speaker}</div>"
                : '';

            $html .= <<<HTML
<tr>
  <td style="padding:10px 14px;width:52px;font-size:13px;font-weight:700;color:#0066CC;white-space:nowrap;vertical-align:top;{$borderBottom}">{$time}</td>
  <td style="padding:10px 6px;width:72px;vertical-align:top;{$borderBottom}">
    <span style="display:inline-block;background-color:{$typeBg};color:{$typeColor};font-size:9px;font-weight:700;padding:2px 7px;border-radius:4px;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap;">{$typeLabel}</span>
  </td>
  <td style="padding:10px 14px;vertical-align:top;{$borderBottom}">
    <div style="font-size:13px;font-weight:700;color:#1E293B;">{$title}</div>
    {$speakerHtml}
  </td>
</tr>
HTML;
        }
        $html .= '</table>';
    }

    return $html;
}

function _buildSpeakersSection(array $speakers): string {
    if (empty($speakers)) {
        return '<p style="font-size:14px;color:#94A3B8;">No speakers published yet.</p>';
    }

    // Two-column layout
    $html  = '<table width="100%" cellpadding="0" cellspacing="0" border="0">';
    $count = count($speakers);
    for ($i = 0; $i < $count; $i += 2) {
        $html .= '<tr>';
        for ($j = $i; $j < min($i + 2, $count); $j++) {
            $sp      = $speakers[$j];
            $name    = htmlspecialchars($sp['name']);
            $role    = htmlspecialchars($sp['role']);
            $company = htmlspecialchars($sp['company']);
            $html .= <<<HTML
<td style="padding:10px 12px 10px 0;width:50%;vertical-align:top;border-bottom:1px solid #F1F5F9;">
  <div style="font-size:13px;font-weight:700;color:#1E293B;">{$name}</div>
  <div style="font-size:11px;color:#00B4D8;font-weight:500;margin-top:2px;">{$role}</div>
  <div style="font-size:11px;color:#94A3B8;">{$company}</div>
</td>
HTML;
        }
        // Fill empty column if odd number
        if ($count % 2 !== 0 && $i + 1 >= $count) {
            $html .= '<td style="width:50%;"></td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}
