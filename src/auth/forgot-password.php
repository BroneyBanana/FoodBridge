<?php
// src/auth/forgot-password.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../database/db.php';

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
        exit;
    }

    // Check if user exists
    $stmt = mysqli_prepare($dbConn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with this email address.']);
        exit;
    }

    // Generate 6-digit OTP and set expiration to +90 seconds from now
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 seconds'));

    // Save OTP & expiry timestamp into database
    $updateStmt = mysqli_prepare($dbConn, "UPDATE users SET reset_otp = ?, reset_otp_expires_at = ? WHERE email = ?");
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($dbConn)]);
        exit;
    }

    mysqli_stmt_bind_param($updateStmt, "sss", $otp, $expiresAt, $email);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    echo json_encode(['success' => true, 'message' => 'Verification code sent successfully.']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>