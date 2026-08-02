<?php
// Nodus Pro - Live Analytics & Admin Dashboard
header('Content-Type: text/html; charset=UTF-8');

$accessKey = $_GET['key'] ?? '';
$validKey = 'piinostats2026'; // Secret key for access

if ($accessKey !== $validKey) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Zugriff verweigert</title>';
    echo '<style>body{background:#0D1317;color:#FFF;font-family:sans-serif;display:flex;height:100vh;align-items:center;justify-content:center;margin:0;} .card{background:#161E24;padding:30px;border-radius:16px;border:1px solid #242E36;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,0.5);} h2{color:#FF5252;margin-top:0;} input{padding:10px;border-radius:8px;border:1px solid #242E36;background:#0F172A;color:#FFF;margin-right:8px;} button{padding:10px 16px;border-radius:8px;border:none;background:#00B0FF;color:#000;font-weight:bold;cursor:pointer;}</style></head><body>';
    echo '<div class="card"><h2>🔒 Admin-Zugriff geschützt</h2><p style="color:#888;">Bitte gib den Sicherheitsschlüssel ein:</p><form method="GET"><input type="password" name="key" placeholder="Sicherheitsschlüssel"><button type="submit">Öffnen</button></form></div></body></html>';
    exit;
}

$dbDir = __DIR__ . '/data';
$trialDbPath = $dbDir . '/trial_devices.sqlite';
$feedbackDbPath = $dbDir . '/feedback_reports.sqlite';

$devices = [];
$feedbackList = [];

// 1. Fetch Trial Devices
if (file_exists($trialDbPath)) {
    try {
        $pdoTrial = new PDO('sqlite:' . $trialDbPath);
        $pdoTrial->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdoTrial->query("SELECT * FROM trial_devices ORDER BY last_seen_ms DESC");
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// 2. Fetch Feedback & Telemetry
if (file_exists($feedbackDbPath)) {
    try {
        $pdoFb = new PDO('sqlite:' . $feedbackDbPath);
        $pdoFb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdoFb->query("SELECT * FROM feedback_reports ORDER BY id DESC");
        $feedbackList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$totalDevices = count($devices);
$nowMs = round(microtime(true) * 1000);
$active24h = 0;
foreach ($devices as $d) {
    if (($nowMs - (int)$d['last_seen_ms']) <= 86400000) {
        $active24h++;
    }
}
$totalFeedback = count($feedbackList);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nodus Pro • Live Tester & Dashboard Stats</title>
    <style>
        :root {
            --bg-color: #0D1317;
            --card-bg: #161E24;
            --border-color: #242E36;
            --accent-cyan: #00B0FF;
            --accent-green: #34D399;
            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #FFF;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge-pro {
            background: rgba(0, 176, 255, 0.15);
            color: var(--accent-cyan);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            border: 1px solid rgba(0, 176, 255, 0.3);
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .metric-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .metric-card .title {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card .value {
            font-size: 32px;
            font-weight: bold;
            margin-top: 8px;
            color: #FFF;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #FFF;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
        }
        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }
        th {
            background: #11181E;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover {
            background: rgba(255,255,255,0.02);
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .dot-green { background: #34D399; box-shadow: 0 0 8px #34D399; }
        .dot-yellow { background: #FBBF24; }
        .dot-gray { background: #64748B; }
        .code-box {
            font-family: monospace;
            background: #0F172A;
            padding: 4px 8px;
            border-radius: 6px;
            color: #38BDF8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📡 Nodus Pro <span class="badge-pro">LIVE MONITORING</span></h1>
            <div style="color: var(--text-muted); font-size: 13px;">Serverzeit: <?= date('d.m.Y H:i:s') ?></div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="title">Registrierte Geräte (Gesamt)</div>
                <div class="value" style="color: var(--accent-cyan);"><?= $totalDevices ?></div>
            </div>
            <div class="metric-card">
                <div class="title">Aktiv in den letzten 24h</div>
                <div class="value" style="color: var(--accent-green);"><?= $active24h ?></div>
            </div>
            <div class="metric-card">
                <div class="title">Feedback & USB Berichte</div>
                <div class="value" style="color: #A855F7;"><?= $totalFeedback ?></div>
            </div>
        </div>

        <div class="section-title">📱 Registrierte Geräte & Tester-Aktivität</div>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Geräte-ID (Hash)</th>
                    <th>Erstmals gestartet</th>
                    <th>Zuletzt aktiv</th>
                    <th>IP-Adresse</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devices)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Noch keine Geräte registriert.</td></tr>
                <?php else: ?>
                    <?php foreach ($devices as $d): 
                        $diffSec = time() - floor((int)$d['last_seen_ms'] / 1000);
                        if ($diffSec < 3600) {
                            $statusClass = 'dot-green';
                            $statusText = 'Jetzt / Heute aktiv';
                        } elseif ($diffSec < 86400 * 3) {
                            $statusClass = 'dot-yellow';
                            $statusText = 'Vor 1–3 Tagen';
                        } else {
                            $statusClass = 'dot-gray';
                            $statusText = 'Inaktiv';
                        }
                    ?>
                    <tr>
                        <td><span class="status-dot <?= $statusClass ?>"></span> <?= $statusText ?></td>
                        <td><span class="code-box"><?= htmlspecialchars($d['device_id']) ?></span></td>
                        <td><?= date('d.m.Y H:i', floor((int)$d['first_seen_ms'] / 1000)) ?></td>
                        <td><?= date('d.m.Y H:i', floor((int)$d['last_seen_ms'] / 1000)) ?></td>
                        <td><?= htmlspecialchars($d['ip_address'] ?? 'n/a') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">💬 Feedback & Telemetrie-Berichte</div>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kategorie</th>
                    <th>Nachricht / USB Telemetrie</th>
                    <th>Geräte-Details</th>
                    <th>E-Mail (Kontakt)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($feedbackList)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Noch kein Feedback eingegangen.</td></tr>
                <?php else: ?>
                    <?php foreach ($feedbackList as $fb): ?>
                    <tr>
                        <td><?= htmlspecialchars($fb['created_at'] ?? 'n/a') ?></td>
                        <td><strong style="color: var(--accent-cyan);"><?= htmlspecialchars($fb['category'] ?? 'Feedback') ?></strong></td>
                        <td><?= nl2br(htmlspecialchars($fb['message'] ?? '')) ?></td>
                        <td><span class="code-box"><?= htmlspecialchars($fb['device_info'] ?? 'n/a') ?></span></td>
                        <td><?= htmlspecialchars($fb['email'] ?? 'Anonym') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
