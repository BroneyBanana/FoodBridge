<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/db.php';

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');

if ($email === '' || $otp === '' || $newPassword === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$stmt = mysqli_prepare(
    $dbConn,
    "SELECT otp_id, otp_hash, expires_at FROM otp_verifications
     WHERE email = ? AND purpose = 'password_reset' AND status = 'pending'
     ORDER BY otp_id DESC LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$record = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$record) {
    echo json_encode(['success' => false, 'message' => 'No pending reset request found. Please request a new code.']);
    exit;
}

if (strtotime($record['expires_at']) < time()) {
    $expireStmt = mysqli_prepare($dbConn, "UPDATE otp_verifications SET status = 'expired' WHERE otp_id = ?");
    mysqli_stmt_bind_param($expireStmt, 'i', $record['otp_id']);
    mysqli_stmt_execute($expireStmt);
    mysqli_stmt_close($expireStmt);

    echo json_encode(['success' => false, 'message' => 'This code has expired. Please request a new one.']);
    exit;
}

if (!password_verify($otp, $record['otp_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
    exit;
}

// OTP valid — update password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateStmt = mysqli_prepare($dbConn, 'UPDATE users SET password_hash = ? WHERE email = ?');
mysqli_stmt_bind_param($updateStmt, 'ss', $newHash, $email);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

// Mark OTP as used so it can't be reused
$usedStmt = mysqli_prepare($dbConn, "UPDATE otp_verifications SET status = 'used' WHERE otp_id = ?");
mysqli_stmt_bind_param($usedStmt, 'i', $record['otp_id']);
mysqli_stmt_execute($usedStmt);
mysqli_stmt_close($usedStmt);

echo json_encode(['success' => true, 'message' => 'Your password has been reset. You can now sign in.']);