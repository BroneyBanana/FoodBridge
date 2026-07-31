<?php
// reset-password.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../database/db.php';

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

try {
    // Verify OTP one more time for security
    $stmt = mysqli_prepare($dbConn, 'SELECT reset_otp FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user || $user['reset_otp'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid session or verification code.']);
        exit;
    }

    // Hash the new password and clear reset_otp
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = mysqli_prepare($dbConn, 'UPDATE users SET password_hash = ?, reset_otp = NULL WHERE email = ?');
    
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'ss', $newHash, $email);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        echo json_encode(['success' => true, 'message' => 'Password reset successful! Redirecting to login...']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>