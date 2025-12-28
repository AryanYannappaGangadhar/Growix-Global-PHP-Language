<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

$now = new DateTime();
$date = $now->format('Y-m-d');
$time = $now->format('Y-m-d H:i:s');

// Check if already checked in today
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user['id'], $date]);
$attendance = $stmt->fetch();

if (!$attendance) {
    // Check In
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in_at, date, status) VALUES (?, ?, ?, 'PRESENT')");
    $stmt->execute([$user['id'], $time, $date]);
    
    // Fetch created record
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user['id'], $date]);
    $attendance = $stmt->fetch();

    logActivity($user['id'], 'attendance_check_in', 'User checked in', ['checkInAt' => $time]);

    // Format for frontend
    $attendance['checkInAt'] = $attendance['check_in_at'];
    $attendance['checkOutAt'] = $attendance['check_out_at'];
    
    jsonResponse(["message" => "Checked in", "attendance" => $attendance]);
}

if (!$attendance['check_out_at']) {
    // Check Out
    $stmt = $pdo->prepare("UPDATE attendance SET check_out_at = ? WHERE id = ?");
    $stmt->execute([$time, $attendance['id']]);

    $attendance['check_out_at'] = $time;
    $attendance['checkInAt'] = $attendance['check_in_at'];
    $attendance['checkOutAt'] = $attendance['check_out_at'];

    logActivity($user['id'], 'attendance_check_out', 'User checked out', ['checkOutAt' => $time]);

    jsonResponse(["message" => "Checked out", "attendance" => $attendance]);
}

jsonResponse(["message" => "Already checked in and out today"], 400);
?>
