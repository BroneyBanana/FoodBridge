<?php
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

$apiKey = $_ENV['TOMTOM_API_KEY'];

$startLat = $_GET['startLat'];
$startLng = $_GET['startLng'];
$destLat = $_GET['destLat'];
$destLng = $_GET['destLng'];

$url = "https://api.tomtom.com/routing/1/calculateRoute/{$startLat},{$startLng}:{$destLat},{$destLng}/json?key={$apiKey}&traffic=true&routeType=fastest";

$response = file_get_contents($url);
echo $response;
?>
