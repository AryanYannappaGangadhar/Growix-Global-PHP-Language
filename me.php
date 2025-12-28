<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND check_in_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY check_in_at ASC");
$stmt->execute([$user['id'], $days]);
$records = $stmt->fetchAll();

// Format for frontend (snake_case to camelCase)
$formattedRecords = array_map(function($record) {
    return [
        'id' => $record['id'],
        'user' => $record['user_id'],
        'checkInAt' => $record['check_in_at'],
        'checkOutAt' => $record['check_out_at'],
        'status' => $record['status'],
        'date' => $record['date']
    ];
}, $records);

jsonResponse(["records" => $formattedRecords]);
?>
