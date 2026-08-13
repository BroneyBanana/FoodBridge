<?php
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';
require_once '../../../database/db.php';

// Tell the browser to expect a JSON response
header('Content-Type: application/json');

// 1. Validate that the user is logged in (as a receiver)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit();
}

$receiver_id = $_SESSION['user']['id'];

// 2. Validate that QR data was actually sent
if (!isset($_POST['qr_data'])) {
    echo json_encode(['success' => false, 'error' => 'No QR data received.']);
    exit();
}

// 3. Parse the QR Data 
// Your QR JS currently sends: "donation_id=X"
parse_str($_POST['qr_data'], $scanned_data);
$donation_id = $scanned_data['donation_id'] ?? null;

if (!$donation_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid QR code format.']);
    exit();
}

// 4. Update the Bookings Table
// We look for a booking matching THIS donation and THIS receiver that is currently 'reserved'
$sql = "UPDATE bookings SET status = 'collected' WHERE donation_id = ? AND receiver_id = ? AND status = 'reserved'";
$stmt = mysqli_prepare($dbConn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $donation_id, $receiver_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Check if a row was actually updated
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode(['success' => true, 'message' => 'Pickup verified successfully!']);
        } else {
            // If 0 rows were affected, they either already collected it, or they don't have a reservation
            echo json_encode(['success' => false, 'error' => 'No active reservation found for this donation.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error during update.']);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare database query.']);
}
?>