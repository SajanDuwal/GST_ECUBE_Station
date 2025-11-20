<?php
header("Content-Type: application/json");

// Load DB connection
require "db_connect.php";

// ---------------------
// Read JSON Input
// ---------------------
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data["GST_data"])) {
    echo json_encode(["response" => "Invalid JSON"]);
    exit;
}

$GST = $data["GST_data"];

// ---------------------
// 1. Validate Credentials
// ---------------------
$EXPECTED_USER = "gst_user";
$EXPECTED_PASS = "apn_sajan_123";

$username = $GST["credentials"]["username"] ?? null;
$password = $GST["credentials"]["password"] ?? null;

if ($username !== $EXPECTED_USER || $password !== $EXPECTED_PASS) {
    echo json_encode(["response" => "Invalid credentials"]);
    exit;
}

// ---------------------
// 2. Verify station_data exists
// ---------------------
if (!isset($GST["station_data"][0])) {
    echo json_encode(["response" => "Missing station_data"]);
    exit;
}

$stationData = $GST["station_data"][0];

// ---------------------
// Extract all values
// ---------------------

// Station ID
$station_id = $GST["station_id"]["id"];

// Lat/Lon
$lat = $GST["station_id"]["location"]["lat"];
$lon = $GST["station_id"]["location"]["lon"];

// Date/Time
$date = $GST["station_id"]["timestamp"]["date"];
$time = $GST["station_id"]["timestamp"]["time"];
$day  = $GST["station_id"]["timestamp"]["day"];

// WiFi & Status
$status = $GST["status"];
$wifi   = $GST["wifi_strength"];

// IP & Sensor Data
$ip       = $stationData["ip_address"];
$temp     = $stationData["sensors_data"]["temp"];
$pressure = $stationData["sensors_data"]["pressure"];
$humidity = $stationData["sensors_data"]["humidity"];
$depth    = $stationData["sensors_data"]["depth"];

// ---------------------
// 3. AUTO-INCREMENT SN
// ---------------------
$result = mysqli_query($conn, "SELECT MAX(SN) AS max_sn FROM gst_tbl");
$row = mysqli_fetch_assoc($result);
$SN = ($row["max_sn"] ?? 0) + 1;

// ---------------------
// 4. INSERT into database
// ---------------------
$sql = "INSERT INTO gst_tbl (
            Username, Password, ID, Latitude, Longitude, 
            Date, Time, Day, Status, WiFi_Strength, IP_Address, 
            Temperature, Pressure, Humidity, Depth
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssiddssssisdddd",
    $username, $password, $station_id, $lat, $lon,  
    $date, $time, $day, $status, $wifi, $ip,
    $temp, $pressure, $humidity, $depth
);

if ($stmt->execute()) {
    echo json_encode(["response" => "successfully uploaded"]);
} else {
    echo json_encode(["response" => "failed to upload", "error" => $stmt->error]);
}
?>
