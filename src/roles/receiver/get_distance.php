<?php
require_once '../../../config.php';

$startLat = $_GET['startLat'];
$startLng = $_GET['startLng'];
$destLat = $_GET['destLat'];
$destLng = $_GET['destLng'];

$url = "https://api.tomtom.com/routing/1/calculateRoute/{$startLat},{$startLng}:{$destLat},{$destLng}/json?key=" . TOMTOM_API_KEY . "&traffic=true&routeType=fastest";

// 4. Fetch and return
$response = file_get_contents($url);
echo $response;
?>