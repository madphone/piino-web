<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbDir = __DIR__ . '/data';
if (!file_exists($dbDir)) {
    @mkdir($dbDir, 0755, true);
}

$dbPath = $dbDir . '/feedback_reports.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at_ms INTEGER NOT NULL,
        created_at_iso TEXT NOT NULL,
        type TEXT NOT NULL,
        user_email TEXT,
        message TEXT,
        usb_telemetry TEXT,
        device_model TEXT,
        android_sdk TEXT,
        app_version TEXT,
        client_ip TEXT
    )");
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON payload'
    ]);
    exit;
}

$type = isset($data['type']) ? trim($data['type']) : 'general';
$userEmail = isset($data['user_email']) ? filter_var(trim($data['user_email']), FILTER_SANITIZE_EMAIL) : '';
$message = isset($data['message']) ? trim($data['message']) : '';
$usbTelemetry = isset($data['usb_telemetry']) ? (is_array($data['usb_telemetry']) ? json_encode($data['usb_telemetry'], JSON_PRETTY_PRINT) : trim($data['usb_telemetry'])) : '';
$deviceModel = isset($data['device_model']) ? trim($data['device_model']) : '';
$androidSdk = isset($data['android_sdk']) ? trim($data['android_sdk']) : '';
$appVersion = isset($data['app_version']) ? trim($data['app_version']) : '';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$nowMs = round(microtime(true) * 1000);
$nowIso = date('c');

try {
    $stmt = $pdo->prepare("INSERT INTO feedback_reports 
        (created_at_ms, created_at_iso, type, user_email, message, usb_telemetry, device_model, android_sdk, app_version, client_ip) 
        VALUES (:created_at_ms, :created_at_iso, :type, :user_email, :message, :usb_telemetry, :device_model, :android_sdk, :app_version, :client_ip)");
    
    $stmt->execute([
        ':created_at_ms' => $nowMs,
        ':created_at_iso' => $nowIso,
        ':type' => $type,
        ':user_email' => $userEmail,
        ':message' => $message,
        ':usb_telemetry' => $usbTelemetry,
        ':device_model' => $deviceModel,
        ':android_sdk' => $androidSdk,
        ':app_version' => $appVersion,
        ':client_ip' => $clientIp
    ]);
} catch (Exception $e) {
    // Continue even if DB write fails
    error_log("SQLite write failed: " . $e->getMessage());
}

// Send E-Mail Notification
$to = 'mad+nodusfeedback@piino.de';
$subjectType = strtoupper(str_replace('_', ' ', $type));
$subject = "[Nodus Feedback] " . $subjectType . " (" . ($deviceModel ?: 'Android Device') . ")";

$emailBody = "=== NODUS USER FEEDBACK & DIAGNOSTICS REPORT ===\n\n";
$emailBody .= "Timestamp:    " . $nowIso . "\n";
$emailBody .= "Type:         " . $type . "\n";
$emailBody .= "User E-Mail:  " . ($userEmail ?: 'Anonymous / Not provided') . "\n";
$emailBody .= "Device Model: " . ($deviceModel ?: 'Unknown') . "\n";
$emailBody .= "Android SDK:  " . ($androidSdk ?: 'Unknown') . "\n";
$emailBody .= "App Version:  " . ($appVersion ?: 'Unknown') . "\n";
$emailBody .= "Client IP:    " . $clientIp . "\n\n";

if (!empty($message)) {
    $emailBody .= "--- USER MESSAGE ---\n" . $message . "\n\n";
}

if (!empty($usbTelemetry)) {
    $emailBody .= "--- USB HARDWARE TELEMETRY ---\n" . $usbTelemetry . "\n\n";
}

$emailBody .= "================================================\n";

$headers = [];
$headers[] = "From: Nodus Feedback Bot <noreply@piino.de>";
if (!empty($userEmail)) {
    $headers[] = "Reply-To: " . $userEmail;
}
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Mailer: PHP/" . phpversion();

@mail($to, $subject, $emailBody, implode("\r\n", $headers));

echo json_encode([
    'status' => 'ok',
    'message' => 'Feedback report successfully recorded and dispatched'
]);
