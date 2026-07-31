<?php
// src/auth/verify-otp.php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    require_once __DIR__ . '/../../database/db.php';

    $email = trim($_POST['email'] ?? '');
    $otp   = trim($_POST['otp'] ?? '');

    // Build a debug array to see what's happening
    $debug = [];

    if (empty($email) || empty($otp)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and OTP are required.',
            'debug' => $debug
        ]);
        exit;
    }
    if (!preg_match('/^\d{6}$/', $otp)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP format.',
            'debug' => $debug
        ]);
        exit;
    }

    $debug['email'] = $email;
    $debug['otp'] = $otp;

    // Query for pending OTP
    $stmt = mysqli_prepare($dbConn, 
        "SELECT otp_id, otp_hash, expires_at FROM otp_verifications 
         WHERE email = ? AND purpose = 'password_reset' AND status = 'pending'
         ORDER BY otp_id DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $record = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$record) {
        $debug['record_found'] = false;
        echo json_encode([
            'success' => false,
            'message' => 'No pending OTP found. Please request a new one.',
            'debug' => $debug
        ]);
        exit;
    }

    $debug['record_found'] = true;
    $debug['expires_at'] = $record['expires_at'];

    // Verify hash
    $hashMatch = password_verify($otp, $record['otp_hash']);
    $debug['hash_match'] = $hashMatch;

    if (!$hashMatch) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP.',
            'debug' => $debug
        ]);
        exit;
    }

    // Check expiry
    $now = new DateTime();
    $expiry = new DateTime($record['expires_at']);
    $debug['now'] = $now->format('Y-m-d H:i:s');
    $debug['is_expired'] = ($now > $expiry);

    if ($now > $expiry) {
        // Mark as expired
        $updateStmt = mysqli_prepare($dbConn, "UPDATE otp_verifications SET status = 'expired' WHERE otp_id = ?");
        mysqli_stmt_bind_param($updateStmt, "i", $record['otp_id']);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
        echo json_encode([
            'success' => false,
            'message' => 'OTP has expired. Please request a new one.',
            'debug' => $debug
        ]);
        exit;
    }

    // Mark as used
    $updateStmt = mysqli_prepare($dbConn, "UPDATE otp_verifications SET status = 'used' WHERE otp_id = ?");
    mysqli_stmt_bind_param($updateStmt, "i", $record['otp_id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified.',
        'debug' => $debug
    ]);
} catch (Throwable $e) {
    error_log("verify-otp.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => ['error' => $e->getMessage()]
    ]);
}
?>