<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['type']) && isset($_GET['date'])) {
    $type = $_GET['type'];
    $date = $_GET['date'];
    
    if ($type === 'layer') {
        $stmt = $pdo->prepare("SELECT * FROM layer_daily_records WHERE record_date = ?");
        $stmt->execute([$date]);
        $record = $stmt->fetch();
    } elseif ($type === 'broiler') {
        $stmt = $pdo->prepare("SELECT * FROM broiler_daily_records WHERE record_date = ?");
        $stmt->execute([$date]);
        $record = $stmt->fetch();
    } elseif ($type === 'ruminant' && isset($_GET['animal_type'])) {
        $stmt = $pdo->prepare("SELECT * FROM ruminant_daily_records 
                               WHERE record_date = ? AND animal_type = ?");
        $stmt->execute([$date, $_GET['animal_type']]);
        $record = $stmt->fetch();
    }
    
    echo json_encode($record ?: null);
}
?>