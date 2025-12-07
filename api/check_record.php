<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['type']) && isset($_GET['date'])) {
    $type = $_GET['type'];
    $date = $_GET['date'];
    
    if ($type === 'layer') {
        $stmt = $pdo->prepare("SELECT id FROM layer_daily_records WHERE record_date = ?");
        $stmt->execute([$date]);
        $exists = $stmt->fetch() ? true : false;
    } elseif ($type === 'broiler') {
        $stmt = $pdo->prepare("SELECT id FROM broiler_daily_records WHERE record_date = ?");
        $stmt->execute([$date]);
        $exists = $stmt->fetch() ? true : false;
    }
    
    echo json_encode(['exists' => $exists]);
}
?>