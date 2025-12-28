<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/tokens.php';
require_once __DIR__ . '/../utils/email.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

function requireAuth() {
    global $pdo;
    $token = null;

    // Debug logging
    error_log("Checking Auth...");
    error_log("Cookies: " . print_r($_COOKIE, true));
    error_log("Server Headers: " . print_r($_SERVER, true));

    if (isset($_COOKIE[JWT_COOKIE_NAME])) {
        $token = $_COOKIE[JWT_COOKIE_NAME];
        error_log("Token found in cookie");
    } else {
        $authHeader = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server keys can be mixed case
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $authHeader = $requestHeaders['Authorization'];
            }
        }

        if ($authHeader) {
            $matches = [];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
                // error_log("Token found in header");
            }
        }
    }

    if (!$token) {
        error_log("No token found");
        jsonResponse(["message" => "Authentication required"], 401);
    }

    $payload = verifyJWT($token);
    if (!$payload) {
        error_log("Invalid JWT token: " . $token);
        jsonResponse(["message" => "Invalid or expired token"], 401);
    }

    // Check for 2FA scope restriction
    if (isset($payload['scope']) && $payload['scope'] === 'pre-2fa') {
        error_log("Token has pre-2fa scope, access denied");
        jsonResponse(["message" => "2FA verification required"], 401);
    }



    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$payload['sub']]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(["message" => "Invalid user"], 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $permission = $stmt->fetch();

    if (!$permission) {
         // Auto-create permission
         $stmt = $pdo->prepare("INSERT INTO permissions (user_id, role, can_edit_profile, can_view_attendance, status) VALUES (?, 'user', 1, 1, 'active')");
         $stmt->execute([$user['id']]);
         
         $stmt = $pdo->prepare("SELECT * FROM permissions WHERE user_id = ?");
         $stmt->execute([$user['id']]);
         $permission = $stmt->fetch();
    }

    if ($permission['status'] !== 'active') {
         jsonResponse(["message" => "Access blocked"], 403);
    }

    return ['user' => $user, 'permission' => $permission];
}

function logActivity($userId, $type, $description, $meta = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, type, description, meta) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $description, json_encode($meta)]);
    } catch (Exception $e) {
        // Ignore logging errors
    }
}

require_once __DIR__ . '/../utils/PHPMailer/Exception.php';
require_once __DIR__ . '/../utils/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../utils/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailOTP($email, $code) {
    // Try to send via PHPMailer (SMTP)
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        
        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Default
        }
        
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, 'Growix Global');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Growix Global OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #4f46e5;'>Verify Your Login</h2>
                <p>Use the following OTP code to complete your login or signup:</p>
                <h1 style='font-size: 32px; letter-spacing: 5px; color: #1f2937;'>$code</h1>
                <p>This code will expire in 10 minutes.</p>
                <p style='color: #666; font-size: 12px; margin-top: 20px;'>If you did not request this code, please ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your OTP code is: $code";

        $mail->send();
        
        // Log success as well
        $logEntry = "[" . date('Y-m-d H:i:s') . "] SMTP Success: Email to $email" . PHP_EOL;
        file_put_contents(__DIR__ . '/../../email_log.txt', $logEntry, FILE_APPEND);
        
        return true;
    } catch (Exception $e) {
        // Fallback to File Log if SMTP fails
        $errorMessage = $mail->ErrorInfo;
        $message = "Your 2FA Login OTP code is: " . $code;
        $logEntry = "[" . date('Y-m-d H:i:s') . "] SMTP Failed ($errorMessage). FALLBACK Log: Email to $email: $message" . PHP_EOL;
        
        // Log to a file in the project root
        $logFile = __DIR__ . '/../../email_log.txt';
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        return true; // Return true so the UI still shows the OTP input
    }
}

function sendOTP($phoneNumber, $code) {
    // Legacy function - redirected to file log for phone as well if still used
    // But we are moving to Email OTP.
    
    $message = "Your OTP code is: " . $code;
    $logEntry = "[" . date('Y-m-d H:i:s') . "] SMS to $phoneNumber: $message" . PHP_EOL;
    $logFile = __DIR__ . '/../../otp_log.txt';
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    return true;
}
?>
