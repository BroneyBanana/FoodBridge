<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../config.php';
require __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$genericMessage = 'If that email exists, a verification code has been sent.';

$stmt = mysqli_prepare($dbConn, 'SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo json_encode(['success' => true, 'message' => $genericMessage]);
    exit;
}

// Invalidate any previous pending password_reset OTPs for this email
$invalidateStmt = mysqli_prepare(
    $dbConn,
    "UPDATE otp_verifications SET status = 'expired' WHERE email = ? AND purpose = 'password_reset' AND status = 'pending'"
);
mysqli_stmt_bind_param($invalidateStmt, 's', $email);
mysqli_stmt_execute($invalidateStmt);
mysqli_stmt_close($invalidateStmt);

// Generate a 6-digit OTP
$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otpHash = password_hash($otp, PASSWORD_DEFAULT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$insertStmt = mysqli_prepare(
    $dbConn,
    "INSERT INTO otp_verifications (email, otp_hash, purpose, expires_at, status) VALUES (?, ?, 'password_reset', ?, 'pending')"
);
mysqli_stmt_bind_param($insertStmt, 'sss', $email, $otpHash, $expiresAt);
mysqli_stmt_execute($insertStmt);
mysqli_stmt_close($insertStmt);

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = getenv('EMAIL_SERVER');
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('EMAIL_USERNAME');
    $mail->Password   = getenv('EMAIL_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) getenv('EMAIL_PORT');

    $mail->setFrom(getenv('EMAIL_USERNAME'), 'FoodBridge');
    $mail->addAddress($email, $user['full_name']);

    $mail->isHTML(true);
    $mail->Subject = 'Your FoodBridge password reset code';
    $mail->Body    = "Hi {$user['full_name']},<br><br>
                      Your password reset code is: <strong style=\"font-size:20px;\">{$otp}</strong><br><br>
                      This code expires in 15 minutes. If you didn't request this, ignore this email.";

    $mail->send();
} catch (Exception $e) {
    error_log('Mailer Error: ' . $mail->ErrorInfo);
}

echo json_encode(['success' => true, 'message' => $genericMessage]);