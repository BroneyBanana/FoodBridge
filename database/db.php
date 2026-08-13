<?php
$localhost = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'foodbridge';

$dbConn = mysqli_connect($localhost, $user, $pass, $dbName);

if (mysqli_connect_errno()) {
    die('<script>alert("Connection failed: Please check your SQL connection!");</script>');
}

function isMaintenanceModeEnabled(mysqli $connection): bool
{
    $result = mysqli_query($connection, 'SELECT maintenance_mode FROM platform_settings LIMIT 1');

    if (!$result) {
        return false;
    }

    $settings = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return ($settings['maintenance_mode'] ?? 'off') === 'on';
}
?>

