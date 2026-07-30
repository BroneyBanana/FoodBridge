<?php
header('Content-Type: application/json');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth_path = __DIR__ . '/../../../auth.php';
if (file_exists($auth_path)) {
    include_once($auth_path);
}

// Database Connection Fallback
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

$action = $_GET['action'] ?? '';

// 1. READ ALL VOUCHERS
if ($action === 'fetch_all') {
    $sql = "SELECT voucher_id, brand_name, reward_title, voucher_code, required_donations, expiration_date FROM vouchers ORDER BY voucher_id DESC";
    $result = $db_connect->query($sql);
    
    $vouchers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $vouchers[] = $row;
        }
    }
    echo json_encode(["status" => "success", "data" => $vouchers]);
    exit();
}

// Read JSON Input Body
$input = json_decode(file_get_contents("php://input"), true);

// 2. CREATE NEW VOUCHER
if ($action === 'create') {
    $brand_name = trim($input['brand_name'] ?? '');
    $reward_title = trim($input['reward_title'] ?? '');
    $voucher_code = trim($input['voucher_code'] ?? '');
    $required_donations = (int)($input['required_donations'] ?? 0);
    $expiration_date = trim($input['expiration_date'] ?? '');

    if (empty($brand_name) || empty($reward_title) || empty($voucher_code) || empty($expiration_date)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit();
    }

    // Append standard end-of-day time string for DATETIME column format
    $datetime_exp = $expiration_date . " 23:59:59";

    $stmt = $db_connect->prepare("INSERT INTO vouchers (brand_name, reward_title, voucher_code, required_donations, expiration_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $brand_name, $reward_title, $voucher_code, $required_donations, $datetime_exp);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Voucher created successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create voucher: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// 3. UPDATE EXISTING VOUCHER
if ($action === 'update') {
    $voucher_id = (int)($input['voucher_id'] ?? 0);
    $brand_name = trim($input['brand_name'] ?? '');
    $reward_title = trim($input['reward_title'] ?? '');
    $voucher_code = trim($input['voucher_code'] ?? '');
    $required_donations = (int)($input['required_donations'] ?? 0);
    $expiration_date = trim($input['expiration_date'] ?? '');

    if ($voucher_id <= 0 || empty($brand_name) || empty($reward_title) || empty($voucher_code) || empty($expiration_date)) {
        echo json_encode(["status" => "error", "message" => "Invalid voucher data."]);
        exit();
    }

    $datetime_exp = $expiration_date . " 23:59:59";

    $stmt = $db_connect->prepare("UPDATE vouchers SET brand_name = ?, reward_title = ?, voucher_code = ?, required_donations = ?, expiration_date = ? WHERE voucher_id = ?");
    $stmt->bind_param("sssisi", $brand_name, $reward_title, $voucher_code, $required_donations, $datetime_exp, $voucher_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Voucher updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update voucher: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// 4. DELETE VOUCHER
if ($action === 'delete') {
    $voucher_id = (int)($input['voucher_id'] ?? 0);

    if ($voucher_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid Voucher ID."]);
        exit();
    }

    // Optional: Delete related records in voucher_redemptions first
    $stmt_del_red = $db_connect->prepare("DELETE FROM voucher_redemptions WHERE voucher_id = ?");
    $stmt_del_red->bind_param("i", $voucher_id);
    $stmt_del_red->execute();
    $stmt_del_red->close();

    $stmt = $db_connect->prepare("DELETE FROM vouchers WHERE voucher_id = ?");
    $stmt->bind_param("i", $voucher_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Voucher deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete voucher."]);
    }
    $stmt->close();
    exit();
}

echo json_encode(["status" => "error", "message" => "Invalid API Action"]);