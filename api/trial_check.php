<?php
header('Content-Type: application/json');

$dbDir = __DIR__ . '/data';
if (!file_exists($dbDir)) {
    @mkdir($dbDir, 0755, true);
}

$dbPath = $dbDir . '/trial_devices.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS trial_devices (
        device_id TEXT PRIMARY KEY,
        first_seen_ms INTEGER NOT NULL,
        last_seen_ms INTEGER NOT NULL,
        ip_address TEXT
    )");
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);

$deviceId = isset($data['device_id']) ? trim($data['device_id']) : '';

if (empty($deviceId)) {
    if (isset($_GET['device_id'])) {
        $deviceId = trim($_GET['device_id']);
    }
}

if (empty($deviceId)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing device_id'
    ]);
    exit;
}

// Sanitize device ID (hash or alphanumeric string)
$deviceId = preg_replace('/[^a-zA-Z0-9_-]/', '', $deviceId);
$nowMs = round(microtime(true) * 1000);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $stmt = $pdo->prepare("SELECT first_seen_ms FROM trial_devices WHERE device_id = :device_id");
    $stmt->execute([':device_id' => $deviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $firstSeenMs = (int)$row['first_seen_ms'];
        
        $updateStmt = $pdo->prepare("UPDATE trial_devices SET last_seen_ms = :last_seen_ms, ip_address = :ip WHERE device_id = :device_id");
        $updateStmt->execute([
            ':last_seen_ms' => $nowMs,
            ':ip' => $clientIp,
            ':device_id' => $deviceId
        ]);

        $trialDurationMs = 3 * 24 * 60 * 60 * 1000; // 3 Days
        $isExpired = ($nowMs - $firstSeenMs) > $trialDurationMs;

        echo json_encode([
            'status' => 'ok',
            'device_id' => $deviceId,
            'first_seen_ms' => $firstSeenMs,
            'is_expired' => $isExpired,
            'server_time_ms' => $nowMs
        ]);
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO trial_devices (device_id, first_seen_ms, last_seen_ms, ip_address) VALUES (:device_id, :first_seen_ms, :last_seen_ms, :ip)");
        $insertStmt->execute([
            ':device_id' => $deviceId,
            ':first_seen_ms' => $nowMs,
            ':last_seen_ms' => $nowMs,
            ':ip' => $clientIp
        ]);

        echo json_encode([
            'status' => 'ok',
            'device_id' => $deviceId,
            'first_seen_ms' => $nowMs,
            'is_expired' => false,
            'server_time_ms' => $nowMs
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Query failed: ' . $e->getMessage()
    ]);
}
