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