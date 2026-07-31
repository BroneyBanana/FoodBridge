<?php
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load PHPMailer manually (same as register.php)
require_once __DIR__ . '/../../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer-master/src/SMTP.php';

// Load Dotenv (if using Composer autoload for it)
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    // Check required env vars
    if (empty($_ENV['EMAIL_SERVER']) || empty($_ENV['EMAIL_PASSWORD'])) {
        echo json_encode(['success' => false, 'message' => 'SMTP credentials missing in .env']);
        exit;
    }

    require_once __DIR__ . '/../../database/db.php';

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Check if user exists
    $stmt = mysqli_prepare($dbConn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with this email address.']);
        exit;
    }

    // Delete old pending OTPs
    $delStmt = mysqli_prepare($dbConn, "DELETE FROM otp_verifications WHERE email = ? AND purpose = 'password_reset' AND status = 'pending'");
    mysqli_stmt_bind_param($delStmt, "s", $email);
    mysqli_stmt_execute($delStmt);
    mysqli_stmt_close($delStmt);

    // Generate OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 seconds'));

    $insertStmt = mysqli_prepare($dbConn,
        "INSERT INTO otp_verifications (email, otp_hash, purpose, expires_at, status) VALUES (?, ?, 'password_reset', ?, 'pending')"
    );
    mysqli_stmt_bind_param($insertStmt, "sss", $email, $otpHash, $expiresAt);
    if (!mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($dbConn)]);
        exit;
    }
    mysqli_stmt_close($insertStmt);

    // ---------- Send email using same pattern as registration ----------
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['EMAIL_SERVER'];
        $mail->Password   = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int) ($_ENV['EMAIL_PORT'] ?? 465);

        $mail->setFrom($_ENV['EMAIL_SERVER'], 'FoodBridge');
        $mail->addAddress($email);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body    = "Your OTP for password reset is: $otp\n\nThis code expires in 90 seconds.";

        if (!$mail->send()) {
            echo json_encode(['success' => false, 'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Email error: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Verification code sent successfully.']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}