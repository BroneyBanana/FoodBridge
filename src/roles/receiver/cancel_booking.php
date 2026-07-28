<?php
session_start();
require_once '../../../database/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$receiver_id = $_SESSION['user']['id'];
$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid booking ID']);
    exit();
}

// ---------------- transaction start ---------------- //
mysqli_begin_transaction($dbConn);

try {

    // ---------------- QUERY 1: ensure booking is available ---------------- //
    $sql_check = "SELECT bookings.donation_id, bookings.quantity, bookings.status, bookings.receiver_id
                  FROM bookings
                  WHERE bookings.booking_id = ?
                  FOR UPDATE";
    $stmt_check = mysqli_prepare($dbConn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'i', $booking_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $booking = mysqli_fetch_assoc($result_check);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    if ((int) $booking['receiver_id'] !== $receiver_id) {
        throw new Exception('This booking does not belong to you');
    }

    if ($booking['status'] !== 'reserved') {
        throw new Exception('Only upcoming bookings can be cancelled');
    }

    $donation_id = $booking['donation_id'];
    $quantity_to_restore = $booking['quantity'];



    // ---------------- QUERY 2: chhange d booking 2 cancelled ---------------- //
    $sql_cancel = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
    $stmt_cancel = mysqli_prepare($dbConn, $sql_cancel);
    mysqli_stmt_bind_param($stmt_cancel, 'i', $booking_id);
    mysqli_stmt_execute($stmt_cancel);


    // ---------------- QUERY 3: restore donation quantity ---------------- //
    $sql_restore = "UPDATE donations 
                     SET quantity = quantity + ?,
                         status = CASE WHEN status = 'completed' THEN 'active' ELSE status END
                     WHERE donation_id = ?";
    $stmt_restore = mysqli_prepare($dbConn, $sql_restore);
    mysqli_stmt_bind_param($stmt_restore, 'di', $quantity_to_restore, $donation_id);
    mysqli_stmt_execute($stmt_restore);


    mysqli_commit($dbConn);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    mysqli_rollback($dbConn);
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}




?>