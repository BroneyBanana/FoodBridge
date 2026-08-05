<?php
// src/auth/reset-password.php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Dotenv\Dotenv;
try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();

    require_once __DIR__ . '/../../database/db.php';

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($email) || empty($password) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($dbConn, "UPDATE users SET password_hash = ? WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $hashed, $email);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($dbConn)]);
    }
    mysqli_stmt_close($stmt);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}