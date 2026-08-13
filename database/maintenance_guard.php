<?php
require_once __DIR__ . '/db.php';

$role = $_SESSION['user']['role'] ?? null;

if (in_array($role, ['donor', 'receiver'], true) && isMaintenanceModeEnabled($dbConn)) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }

    session_destroy();
    header('Location: /FoodBridge/src/auth/login.php?maintenance=1');
    exit;
}
