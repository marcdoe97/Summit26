<?php
declare(strict_types=1);

if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/data/registrations.db');
}

function getDb(): PDO {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    _initSchema($pdo);
    return $pdo;
}

function _initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS registrations (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            fname   TEXT NOT NULL,
            lname   TEXT NOT NULL,
            email   TEXT NOT NULL UNIQUE,
            company TEXT,
            role    TEXT,
            message TEXT,
            ip      TEXT,
            created TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agenda (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            day         INTEGER NOT NULL DEFAULT 1,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            time_label  TEXT    NOT NULL DEFAULT '',
            title       TEXT    NOT NULL DEFAULT '',
            speaker     TEXT    NOT NULL DEFAULT '',
            description TEXT    NOT NULL DEFAULT '',
            type        TEXT    NOT NULL DEFAULT 'session'
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS speakers (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            name    TEXT NOT NULL DEFAULT '',
            role    TEXT NOT NULL DEFAULT '',
            company TEXT NOT NULL DEFAULT '',
            bio     TEXT NOT NULL DEFAULT ''
        )
    ");

    _seedDefaults($pdo);
}

function _seedDefaults(PDO $pdo): void {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($count > 0) return;

    $settings = [
        'event_name'          => 'SUMMIT 26',
        'event_tagline'       => 'Automation to Autonomy',
        'event_date_label'    => 'September 18–19, 2026',
        'event_day1_label'    => 'Day 1 — September 18, 2026',
        'event_day2_label'    => 'Day 2 — September 19, 2026',
        'event_location_name' => 'Munich Convention Center',
        'event_address'       => 'Am Messesee 2, 81829 München, Germany',
        'event_website'       => 'https://summit26.example.com',
        'email_from_name'     => 'SUMMIT 26 Team',
        'email_from_address'  => 'hello@summit26.example.com',
        'email_subject'       => 'Your SUMMIT 26 Registration is Confirmed!',
        'email_intro'         => "We're thrilled to confirm your registration for SUMMIT 26 – Automation to Autonomy.\n\nBelow you'll find all the key details, the full conference agenda, and our speaker lineup. We look forward to seeing you in Munich!",
        'email_closing'       => "We'll keep you updated with any news ahead of the event. If you have any questions, don't hesitate to reach out — we're happy to help.",
    ];

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Default Agenda
    $agenda = [
        [1, 10,  '09:00', 'Registration & Welcome Coffee',                '',                               'Doors open. Pick up your badge, explore the exhibition and connect with early arrivals.',                         'break'],
        [1, 20,  '10:00', 'The Future of Autonomous Systems',             'Dr. Sarah Chen — Nexus Labs',    'A panoramic view of where automation ends and true machine autonomy begins — and what it means for every industry.','keynote'],
        [1, 30,  '11:30', 'AI-Driven Automation at Scale',                'Marcus Weber — AutomateAI GmbH', 'Real-world case studies on deploying AI automation across thousands of nodes without losing control.',               'session'],
        [1, 40,  '13:00', 'Lunch & Networking',                           '',                               'Curated lunch with themed networking tables. Meet speakers and attendees in a structured open format.',             'break'],
        [1, 50,  '14:00', 'From Automation to Autonomy — Hands-On Lab',  'Elena Rossi — CloudMind',        'Build an end-to-end autonomous decision pipeline using modern LLM agents and orchestration frameworks.',             'workshop'],
        [1, 60,  '16:00', 'Ethics & Governance in Autonomous AI',         'Moderated Panel',                'When machines make decisions, who is responsible? A frank panel discussion on accountability and regulation.',       'panel'],
        [2, 10,  '09:30', 'Intelligent Infrastructure: Self-Healing Systems','James Park — DataFlow Corp', 'How the world\'s largest platforms are adopting zero-touch infrastructure and what we can learn from them.',        'keynote'],
        [2, 20,  '11:00', 'The Autonomous Enterprise: A Blueprint',       'Dr. Priya Sharma — FutureTech', 'Strategies for enterprises aiming to fully automate core operations by 2030.',                                      'session'],
        [2, 30,  '13:00', 'Networking Lunch & Demo Floor',                '',                               'Live product demonstrations from leading vendors and startups in the autonomous technology space.',                 'break'],
        [2, 40,  '14:30', 'Multi-Agent Systems — Building Collaborative AI','Elena Rossi & James Park',    'Design patterns for coordinating multiple autonomous agents to solve complex, multi-step problems reliably.',       'workshop'],
        [2, 50,  '16:30', 'Closing Keynote & Award Ceremony',             'All Speakers',                   'Highlights, community awards, and a preview of what to expect at SUMMIT 27.',                                      'closing'],
    ];

    $stmt = $pdo->prepare("INSERT INTO agenda (day, sort_order, time_label, title, speaker, description, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($agenda as $item) {
        $stmt->execute($item);
    }

    // Default Speakers
    $speakers = [
        ['Dr. Sarah Chen',   'Head of AI Research',       'Nexus Labs',             'Pioneer in autonomous decision systems with 15+ years shaping enterprise AI at scale.'],
        ['Marcus Weber',     'Chief Technology Officer',  'AutomateAI GmbH',        'Architect of automation platforms handling 10M+ daily decisions across logistics and finance.'],
        ['Elena Rossi',      'Principal Engineer',        'CloudMind',              'Leading engineer behind open-source LLM orchestration frameworks used by 40,000+ developers.'],
        ['James Park',       'VP Engineering',            'DataFlow Corp',          'Designed self-healing infrastructure for one of the world\'s top 10 most-visited platforms.'],
        ['Dr. Priya Sharma', 'Chief Strategy Officer',    'FutureTech',             'Strategy architect helping Fortune 500 companies navigate the transition to autonomous operations.'],
        ['Thomas Müller',    'Director of Robotics',      'IndustrialAI AG',        'Brings autonomous robotics from research labs to factory floors with measurable ROI.'],
        ['Aiko Kimura',      'Research Scientist',        'Autonomous Systems Lab', 'Published researcher in reinforcement learning and multi-agent coordination with 80+ citations.'],
        ['Raphaël Laurent',  'Head of AI Ethics',         'EthicsFirst Foundation', 'Advises the EU and G20 on frameworks for responsible deployment of autonomous technologies.'],
    ];

    $stmt = $pdo->prepare("INSERT INTO speakers (name, role, company, bio) VALUES (?, ?, ?, ?)");
    foreach ($speakers as $s) {
        $stmt->execute($s);
    }
}

/* ── Helpers ────────────────────────────────────────────────────────────── */
function getSettings(PDO $pdo): array {
    return $pdo->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
}

function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v !== false ? (string) $v : $default;
}

function saveSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")
        ->execute([$key, $value]);
}
