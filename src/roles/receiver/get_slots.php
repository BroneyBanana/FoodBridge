<<<<<<< HEAD
    <?php
    session_start();
    require_once '../../../database/db.php';

    // check receiver is logged in — this is an AJAX endpoint, still needs the same guard
    // if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    //     http_response_code(403);
    //     echo json_encode(['error' => 'Not authorized']);
    //     exit();
    // }

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
    header('Location: ../../auth/login.php'); // or the http_response_code/json version for the two AJAX files
    exit();
}

    // validate the incoming donation_id before it goes anywhere near SQL
    $donation_id = filter_input(INPUT_GET, 'donation_id', FILTER_VALIDATE_INT);

    if (!$donation_id || $donation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid donation ID']);
        exit();
    }

    // ------ QUERY 2 : OPEN PICKUP SLOTS FOR THE DONATION ------ //
    $sql_slots = "SELECT pickup_slots.pickup_slot_id, pickup_slots.timeslot
                FROM pickup_slots
                LEFT JOIN bookings ON bookings.pickup_slot_id = pickup_slots.pickup_slot_id
                AND bookings.status != 'cancelled'
                WHERE pickup_slots.donation_id = ?
                AND pickup_slots.timeslot > NOW()
                AND bookings.booking_id IS NULL
                ORDER BY pickup_slots.timeslot ASC";

    // prep
    $stmt_slots = mysqli_prepare($dbConn, $sql_slots);

    // bind
    mysqli_stmt_bind_param($stmt_slots, 'i', $donation_id);

    // execute
    mysqli_stmt_execute($stmt_slots);

    // fetch
    $result_slots = mysqli_stmt_get_result($stmt_slots);
    $slots = [];
    while ($row = mysqli_fetch_assoc($result_slots)) {
        $slots[] = $row;
    }

    // send back as JSON so JS's fetch() can read it
    header('Content-Type: application/json');
    echo json_encode($slots);
?>
=======

<?php
session_start();
require_once '../../../database/db.php';

// check receiver is logged in — this is an AJAX endpoint, still needs the same guard
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
//     http_response_code(403);
//     echo json_encode(['error' => 'Not authorized']);
//     exit();
// }

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$receiver_id = $_SESSION['user']['id'];

// validate the incoming donation_id before it goes anywhere near SQL
$donation_id = filter_input(INPUT_GET, 'donation_id', FILTER_VALIDATE_INT);

if (!$donation_id || $donation_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid donation ID']);
    exit();
}

// ------ QUERY 1 : IS THERE ANY QUANTITY LEFT ON THE DONATION? ------ //
$sql_donation = "SELECT quantity, status FROM donations WHERE donation_id = ?";
$stmt_donation = mysqli_prepare($dbConn, $sql_donation);
mysqli_stmt_bind_param($stmt_donation, 'i', $donation_id);
mysqli_stmt_execute($stmt_donation);
$result_donation = mysqli_stmt_get_result($stmt_donation);
$donation = mysqli_fetch_assoc($result_donation);

if (!$donation || $donation['quantity'] <= 0 || $donation['status'] !== 'active') {
    header('Content-Type: application/json');
    echo json_encode([]); // no slots — donation is exhausted or inactive
    exit();
}

// ------ QUERY 2 : HAS THIS RECEIVER ALREADY BOOKED THIS DONATION? ------ //
// this is the "no hogging" rule — one receiver, one active booking per donation
$sql_existing = "SELECT booking_id FROM bookings
                  WHERE donation_id = ?
                  AND receiver_id = ?
                  AND status != 'cancelled'";
$stmt_existing = mysqli_prepare($dbConn, $sql_existing);
mysqli_stmt_bind_param($stmt_existing, 'ii', $donation_id, $receiver_id);
mysqli_stmt_execute($stmt_existing);
$result_existing = mysqli_stmt_get_result($stmt_existing);

if (mysqli_fetch_assoc($result_existing)) {
    // this receiver already has a booking on this donation — don't show slots at all
    header('Content-Type: application/json');
    echo json_encode(['error' => 'You already have a booking on this donation']);
    exit();
}

// ------ QUERY 3 : OPEN PICKUP SLOTS FOR THE DONATION ------ //
// NOTE: no longer excludes slots that already have a booking —
// multiple receivers CAN share the same timeslot, gated only by
// quantity (Query 1) and the per-user check (Query 2) above
$sql_slots = "SELECT pickup_slots.pickup_slot_id, pickup_slots.timeslot
              FROM pickup_slots
              WHERE pickup_slots.donation_id = ?
              AND pickup_slots.timeslot > NOW()
              ORDER BY pickup_slots.timeslot ASC";

// prep
$stmt_slots = mysqli_prepare($dbConn, $sql_slots);

// bind
mysqli_stmt_bind_param($stmt_slots, 'i', $donation_id);

// execute
mysqli_stmt_execute($stmt_slots);

// fetch
$result_slots = mysqli_stmt_get_result($stmt_slots);
$slots = [];
while ($row = mysqli_fetch_assoc($result_slots)) {
    $slots[] = $row;
}

// send back as JSON so JS's fetch() can read it
header('Content-Type: application/json');
echo json_encode($slots);
?>
>>>>>>> Yeoh
