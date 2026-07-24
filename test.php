<?php
require_once 'database/db.php';
$query = "SELECT user_id, full_name, status, trust_score FROM users";
$result = mysqli_query($dbConn, $query);
$out = [];
while ($row = mysqli_fetch_assoc($result)) {
    $out[] = $row;
}
file_put_contents('db_dump.txt', print_r($out, true));
echo "Done";
