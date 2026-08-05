<?php
header('Content-Type: application/json');
error_reporting(0); // Prevent HTML errors from breaking JSON output

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Auth and Database Include
$auth_path = __DIR__ . '/../../../auth.php';
if (file_exists($auth_path)) {
    include_once($auth_path);
}

// Direct DB connection fallback
if (!isset($db_connect) || $db_connect === null) {
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "foodbridge";

    $db_connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($db_connect->connect_error) {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        exit();
    }
}

// 2. Validate Donor Session (role stored lowercase in DB, e.g. 'donor')
if (!isset($_SESSION['user']['role']) || strtolower($_SESSION['user']['role']) !== "donor" || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$donor_id = (int) $_SESSION['user']['id'];

// 3. Read JSON Body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["voucher_id"]) || !filter_var($data['voucher_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(["status" => "error", "message" => "Invalid voucher ID."]);
    exit();
}

$voucher_id = (int) $data["voucher_id"];

try {
    $db_connect->begin_transaction();

    // 4. Fetch Total Donor Packs Donated (stored directly on users table)
    $donations_sql = "SELECT COALESCE(total_food_donated, 0) AS total_donated FROM users WHERE user_id = ? AND role = 'donor'";
    $stmt1 = $db_connect->prepare($donations_sql);
    $stmt1->bind_param("i", $donor_id);
    $stmt1->execute();
    $don_res = $stmt1->get_result()->fetch_assoc();
    $total_donated = (int) ($don_res['total_donated'] ?? 0);
    $stmt1->close();

    // 5. Fetch Required Donations & Voucher Code from Database
    $voucher_sql = "SELECT required_donations, voucher_code FROM vouchers WHERE voucher_id = ?";
    $stmt2 = $db_connect->prepare($voucher_sql);
    $stmt2->bind_param("i", $voucher_id);
    $stmt2->execute();
    $v_result = $stmt2->get_result();

    if (!$v_result || $v_result->num_rows === 0) {
        throw new Exception("Voucher not found.");
    }

    $v_row = $v_result->fetch_assoc();
    $required_donations = (int) $v_row["required_donations"];
    $voucher_code = $v_row["voucher_code"];
    $stmt2->close();

    if ($total_donated < $required_donations) {
        throw new Exception("Insufficient donations to unlock voucher.");
    }

    // 6. Check if Voucher Has Already Been Redeemed
    $check_sql = "SELECT redemption_id FROM voucher_redemptions WHERE donor_id = ? AND voucher_id = ?";
    $stmt3 = $db_connect->prepare($check_sql);
    $stmt3->bind_param("ii", $donor_id, $voucher_id);
    $stmt3->execute();
    $check_res = $stmt3->get_result();

    if ($check_res && $check_res->num_rows > 0) {
        throw new Exception("Voucher already redeemed.");
    }
    $stmt3->close();

    // 7. Insert Redemption with explicit 'redeemed' status
    $status_value = 'redeemed';
    $redeem_sql = "INSERT INTO voucher_redemptions (donor_id, voucher_id, status) VALUES (?, ?, ?)";
    $stmt4 = $db_connect->prepare($redeem_sql);
    $stmt4->bind_param("iis", $donor_id, $voucher_id, $status_value);

    if (!$stmt4->execute()) {
        throw new Exception("Redemption insert failed.");
    }
    $stmt4->close();

    $db_connect->commit();

    // Return success along with the voucher code
    echo json_encode([
        "status" => "success",
        "voucher_code" => $voucher_code
    ]);

} catch (Exception $e) {
    if ($db_connect->connect_errno === 0) {
        $db_connect->rollback();
    }
    error_log("Redemption Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}
?>