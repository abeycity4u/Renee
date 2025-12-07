<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['type']) && isset($_GET['date'])) {
    $type = $_GET['type'];
    $date = $_GET['date'];
    
    // Calculate yesterday's date
    $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
    
    if ($type === 'layer') {
        $stmt = $pdo->prepare("SELECT * FROM layer_daily_records WHERE record_date = ?");
        $stmt->execute([$yesterday]);
        $record = $stmt->fetch();
        
        if ($record) {
            $closingStock = $record['opening_stock'] - $record['mortality'];
            echo json_encode([
                'closing_stock' => $closingStock,
                'previous_record' => $record
            ]);
        } else {
            echo json_encode(['closing_stock' => null, 'message' => 'No record found for yesterday']);
        }
    } 
    elseif ($type === 'broiler') {
        $stmt = $pdo->prepare("SELECT * FROM broiler_daily_records WHERE record_date = ?");
        $stmt->execute([$yesterday]);
        $record = $stmt->fetch();
        
        if ($record) {
            $closingStock = $record['opening_stock'] - $record['mortality'];
            echo json_encode([
                'closing_stock' => $closingStock,
                'previous_record' => $record
            ]);
        } else {
            echo json_encode(['closing_stock' => null, 'message' => 'No record found for yesterday']);
        }
    }
    elseif ($type === 'ruminant' && isset($_GET['animal_type'])) {
        $stmt = $pdo->prepare("SELECT * FROM ruminant_daily_records 
                               WHERE record_date = ? AND animal_type = ?");
        $stmt->execute([$yesterday, $_GET['animal_type']]);
        $record = $stmt->fetch();
        
        if ($record) {
            $closingStock = $record['opening_stock'] - $record['mortality'];
            echo json_encode([
                'closing_stock' => $closingStock,
                'previous_record' => $record
            ]);
        } else {
            echo json_encode(['closing_stock' => null, 'message' => 'No record found for yesterday']);
        }
    }
    else {
        echo json_encode(['error' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['error' => 'Missing parameters']);
}
?>