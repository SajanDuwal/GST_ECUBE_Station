<?php
$host = "sql311.infinityfree.com";
$user = "if0_40453969";
$pass = "godisgay9055";
$db   = "if0_40453969_apn_gst_db";

$host = "localhost";
$user = "root";
$pass = "";
$db   = "apn_gst_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>

