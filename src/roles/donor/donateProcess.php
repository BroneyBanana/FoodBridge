<?php
    session_start();

    require_once "../../../database/db.php";

    // mysqli_report() → Tells MySQLi how to react when something goes wrong.
    // MYSQLI_REPORT_ERROR → Detect database errors.
    // MYSQLI_REPORT_STRICT → Instead of just returning false, immediately jump to the catch block by throwing an exception.
    // | → Means "use both options together."
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if(!isset($_SESSION['user'])){
        header("Location: ../../auth/login.php");
        exit();
    }

    if($_SERVER['REQUEST_METHOD'] !== "POST") {
        die("Invalid Request!");
    }

    $image = $_FILES['foodImage'] ?? null;
    $foodName = trim($_POST['foodName'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $expiryDate = $_POST['expiryDate'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    $pickupAddress = trim($_POST['pickupAddress'] ?? '');
    $pickupSlots = $_POST['pickup_slots'] ?? '';

    // Validation part
    $errors = [];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSizeBytes = 5 * 1024 * 1024; // 5MB

    if(!isset($image) || $image['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please upload a photo of the food.";
    } else if ($image['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "The image is not uploaded successfully. Please try again.";
    } else if (!in_array(mime_content_type($image['tmp_name']), $allowedTypes)) {
        $errors[] = "Only jpg, png, webp images are allowed. Please upload again.";
    } else if ($image['size'] > $maxSizeBytes) {
        $errors[] = "Please upload image that is smaller than 5MB.";
    }

    if(empty($foodName)) {
        $errors[] = "The food name field is empty. Please fill in the field!";
    }else if (strlen($foodName) > 150) {
        $errors[] = "The food name is too long. Please fill in the field again!";
    }

    $allowedCategory = ['cookedMeal', 'rawProduce', 'bakery', 'beverages', 'cannedGoods', 'others'];
    if(!in_array($category, $allowedCategory)) {
        $errors[] = "Invalid food category.";
    }

    if (!is_numeric($quantity)) {
        $errors[] = "Food quantity must be a number. Please fill in the field again!";
    } else if($quantity <= 0) {
        $errors[] = "Food quantity cannot be 0 or negative. Please fill in the field again!";
    }

    $allowedUnit = ['portions', 'kg', 'pieces'];
    if(!in_array($unit, $allowedUnit)) {
        $errors[] = "Invalid food unit.";
    }

    $expiryTimestamp = strtotime($expiryDate);

    if($expiryTimestamp === false) {
        $errors[] = "Invalid expiry date.";
    } else if ($expiryTimestamp <= time()) {
        $errors[] = "Expiry date must be in the future. If not you cannot donate this food.";
    }

    $allowedAllergies = ['nuts', 'dairy', 'gluten', 'shellfish', 'eggs', 'soy', 'vegan-safe', 'none'];
    if(empty($allergies) || !is_array($allergies)) {
        $errors[] = "You need to select at least one Allergies tag from the given tags.";
    } else {
        foreach ($allergies as $tag) {
            if (!in_array($tag, $allowedAllergies)) {
                $errors[] = "Invalid allergies tags selected.";
                break;
            }
        }
        if(in_array('none', $allergies) && count($allergies) > 1) {
            $errors[] = 'The "None" tag cannot be selected together with other allergy tags.';
        }
    }

    if(empty($pickupAddress)) {
        $errors[] = "The pickup address field is empty. Please fill in the field!";
    }

    if(empty($pickupSlots) || !is_array($pickupSlots)) {
        $errors[] = "The pickup slots field is empty. Please click the + and add at least 1 slot.";
    } else if (count($pickupSlots) > 3) {
        $errors[] = "Only maximum 3 pickup slots can be added.";
    } else {
        foreach ($pickupSlots as $slot) {
            $slotTimestamp = strtotime($slot);
            if($slotTimestamp === false || $slotTimestamp <= time()) {
                $errors[] = "All the pickup slots must be in the future.";
                break;
            }
            ////////
            if ($expiryTimestamp === false) {
                $errors[] = "Invalid expiry date.";
            } else if ($slotTimestamp > $expiryTimestamp) {
                $errors[] = "Pickup slot cannot be after the expiry date.";
                break;
            }
        }
    }

    if(!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: donate.php");
        exit();
    }

    try {
        // Transactions will make the inserts changes temporarily. 
        // Do not permanently save them until I say commit.
        mysqli_begin_transaction($dbConn);

        $fileName = uniqid() . "_" . basename($image['name']);
        $destinationPath = "../../uploads/donations/" . $fileName;

        $imageUrlToStore = "uploads/donations/" . $fileName;  

        if (!move_uploaded_file($image['tmp_name'], $destinationPath)) {
            $_SESSION['errors'] = ["Failed to upload the food image."];
            header("Location: donate.php");
            exit();
        }

        // question, my databse says the image is image url, is it means i need to save as url only, no need to handle the image or what? or still need to handle the image although it is save as url? i dont understand.
        $sqlDonation = "INSERT INTO donations (donor_id, food_name, category, quantity, unit, image_url, pickup_address, expiry_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        // Prepare statement tell the database, this is the format, use the bind param format as i write after this prepare code 
        // The ? is a placeholder for the actual values that will be inserted into the database.
        $stmtDonation = mysqli_prepare($dbConn, $sqlDonation);
        // Tells the database which actual values to insert into the insert value ?, ?, 
        // prepare statement will use this bind param format
        mysqli_stmt_bind_param($stmtDonation, "issdssss", $_SESSION['user']['id'], $foodName, $category, $quantity, $unit, $imageUrlToStore, $pickupAddress, $expiryDate);
        // Execute finally runs, it make the changes on database.
        mysqli_stmt_execute($stmtDonation);
        $donationId = mysqli_insert_id($dbConn);
        mysqli_stmt_close($stmtDonation);

        $sqlAllergy = "INSERT INTO donation_allergy_tags (donation_id, allergy_name) VALUES (?, ?)";
        $stmtAllergy = mysqli_prepare($dbConn, $sqlAllergy);
        foreach ($allergies as $allergy) {
            mysqli_stmt_bind_param(
                $stmtAllergy,
                "is",
                $donationId,
                $allergy
            );
            mysqli_stmt_execute($stmtAllergy);
        }
        mysqli_stmt_close($stmtAllergy);

        $sqlPickupSlot = "INSERT INTO pickup_slots (donation_id, timeslot) VALUES (?, ?)";
        $stmtPickup = mysqli_prepare($dbConn, $sqlPickupSlot);
        foreach ($pickupSlots as $slots) {
            mysqli_stmt_bind_param(
                $stmtPickup,
                "is",
                $donationId,
                $slots
            );
            mysqli_stmt_execute($stmtPickup);
        }
        mysqli_stmt_close($stmtPickup);

        //if all inserts success, then commit, make the database changes permanently now
        mysqli_commit($dbConn);

        $_SESSION['success'] = "Food donation added successfully!";
        header("Location: my-donations.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($dbConn);
        // Undo all changes made during this transaction.
        if(file_exists($destinationPath)){
            unlink($destinationPath);
        }

        $_SESSION['errors'] = ["Failed to create donation."];
        header("Location: donate.php");
        exit();
    }
?>