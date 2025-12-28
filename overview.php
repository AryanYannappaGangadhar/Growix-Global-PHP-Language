<?php
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

$year = date('Y');
$month = date('m');

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND MONTH(check_in_at) = ? AND YEAR(check_in_at) = ?");
$stmt->execute([$user['id'], $month, $year]);
$records = $stmt->fetchAll();

$totalDays = count($records);
$presentDays = 0;
foreach ($records as $record) {
    if ($record['status'] === 'PRESENT') {
        $presentDays++;
    }
}

$attendanceRate = $totalDays === 0 ? 0 : round(($presentDays / $totalDays) * 100);

// Format records
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

jsonResponse([
    'month' => (int)$month,
    'year' => (int)$year,
    'totalDays' => $totalDays,
    'presentDays' => $presentDays,
    'attendanceRate' => $attendanceRate,
    'records' => $formattedRecords
]);
?>
