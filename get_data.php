<?php
require 'db_connect.php';
header('Content-Type: application/json');

try {
    $date = isset($_GET['Date']) ? $_GET['Date'] : null;
    
    if ($date) {
        // FIXED: use SN instead of id
        $stmt = $pdo->prepare("SELECT * FROM gst_tbl WHERE date = ? ORDER BY SN DESC LIMIT 100");
        $stmt->execute([$date]);
    } else {
        $stmt = $pdo->query("SELECT * FROM gst_tbl ORDER BY SN DESC LIMIT 100");
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add timestamp field for each row
    foreach ($data as &$row) {
        $row['timestamp'] = $row['date'] . ' ' . $row['time'];
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
