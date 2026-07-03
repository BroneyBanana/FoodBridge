<?php
$localhost = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'foodbridge';

$dbConn = mysqli_connect($localhost, $user, $pass, $dbName);

if (mysqli_connect_errno()) {
    die('<script>alert("Connection failed: Please check your SQL connection!");</script>');
}
?>

