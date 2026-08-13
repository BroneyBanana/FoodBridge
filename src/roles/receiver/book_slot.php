<?php
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';
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
    // QUERY 3A: CHECK AVAILABILITY
    // donations.quantity is never written to (leader's rule — donations table is
    // the donor's permanent record, not a live stock counter), so "how much is
    // left" is computed fresh from bookings every time instead of locking and
    // reading a stored number. No FOR UPDATE here — leader's call, concurrent
    // simultaneous bookings on the same donation aren't a concern for testing.
    $sql_check = "SELECT donations.quantity - COALESCE(booked.booked_qty, 0) AS available_quantity
                  FROM donations
                  LEFT JOIN (
                      SELECT donation_id, SUM(quantity) AS booked_qty
                      FROM bookings
                      WHERE status IN ('reserved', 'collected')
                      GROUP BY donation_id
                  ) AS booked ON booked.donation_id = donations.donation_id
                  WHERE donations.donation_id = ?";
    $stmt_check = mysqli_prepare($dbConn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'i', $donation_id);
    mysqli_stmt_execute($stmt_check); 
    $result_check = mysqli_stmt_get_result($stmt_check);
    $donation = mysqli_fetch_assoc($result_check);

    // quantity requested > quantity available
    if (!$donation || $donation['available_quantity'] < $quantity) {
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


    // QUERY 3C and 3D are gone — there's nothing left to write to donations.
    // The old version deducted quantity and flipped status to 'completed' here.
    // Now that availability is computed fresh from bookings on every read
    // (Query 3A above, and browse-donations.php's Query 1), inserting this
    // booking IS the entire effect — no second write needed to "make it count".

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