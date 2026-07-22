<?php
session_start();
require_once '../../../database/db.php';

// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
//     http_response_code(403);
//     echo json_encode(['error' => 'Not authorized']);
//     exit();
// }

// $receiver_id = $_SESSION['user_id'];

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
    header('Location: ../../auth/login.php'); // or the http_response_code/json version for the two AJAX files
    exit();
}

$receiver_id = $_SESSION['user']['id'];


// pull the three values JS will send when the receiver confirms a booking
$donation_id    = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
$pickup_slot_id = filter_input(INPUT_POST, 'pickup_slot_id', FILTER_VALIDATE_INT);
$quantity       = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);

if (!$donation_id || !$pickup_slot_id || !$quantity || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid booking data']);
    exit();
}

// QUERY 3 TRANSACTION START
mysqli_begin_transaction($dbConn);

try {
    // QUERY 3A: LOCK DONATION 
    $sql_check = "SELECT quantity FROM donations WHERE donation_id = ? FOR UPDATE"; 
    $stmt_check = mysqli_prepare($dbConn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'i', $donation_id);
    mysqli_stmt_execute($stmt_check); 
    $result_check = mysqli_stmt_get_result($stmt_check);
    $donation = mysqli_fetch_assoc($result_check);

    // quantity requested > quantity available
    if (!$donation || $donation['quantity'] < $quantity) {
        throw new Exception('Not enough quantity left');
    }

    // QUERY 3A-2: NO HOGGING — same rule as get_slots.php's Query 2,
    // checked again here because this is the real security boundary,
    // not just the dropdown. FOR UPDATE keeps it race-safe against
    // two simultaneous requests from the same receiver.
    $sql_dup = "SELECT booking_id FROM bookings
                WHERE donation_id = ?
                AND receiver_id = ?
                AND status != 'cancelled'
                FOR UPDATE";
    $stmt_dup = mysqli_prepare($dbConn, $sql_dup);
    mysqli_stmt_bind_param($stmt_dup, 'ii', $donation_id, $receiver_id);
    mysqli_stmt_execute($stmt_dup);
    $result_dup = mysqli_stmt_get_result($stmt_dup);

    if (mysqli_fetch_assoc($result_dup)) {
        throw new Exception('You already have a booking on this donation');
    }


    // QUERY 3B: INSERT BOOKING
    $sql_insert = "INSERT INTO bookings (donation_id, pickup_slot_id, receiver_id, booking_time, quantity, status)
                   VALUES (?, ?, ?, NOW(), ?, 'reserved')";

    $stmt_insert = mysqli_prepare($dbConn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, 'iiid', $donation_id, $pickup_slot_id, $receiver_id, $quantity);

    // if sametime donation is being filled in by another ppl
    if (!mysqli_stmt_execute($stmt_insert)) {
        throw new Exception('This slot was just taken by someone else');
    }


    // QUERY 3C: DEDUCT IF BOOKED 
    $new_quantity = $donation['quantity'] - $quantity;
    $sql_update = "UPDATE donations SET quantity = ? WHERE donation_id = ?";
    $stmt_update = mysqli_prepare($dbConn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, 'di', $new_quantity, $donation_id);
    mysqli_stmt_execute($stmt_update);


    // QUERY 3D
    if ($new_quantity <= 0) {
        $sql_complete = "UPDATE donations SET status = 'completed' WHERE donation_id = ?";
        $stmt_complete = mysqli_prepare($dbConn, $sql_complete);
        mysqli_stmt_bind_param($stmt_complete, 'i', $donation_id);
        mysqli_stmt_execute($stmt_complete);
    }

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