<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$farmType = $_GET['farm_type'] ?? 'both';
$date = $_GET['date'] ?? date('Y-m-d');

$pendingTasks = 0;

// Check for missing daily records
if ($farmType === 'poultry' || $farmType === 'both') {
    $layerCheck = $pdo->prepare("SELECT COUNT(*) FROM layer_daily_records WHERE record_date = ?");
    $layerCheck->execute([$date]);
    if ($layerCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
    
    $broilerCheck = $pdo->prepare("SELECT COUNT(*) FROM broiler_daily_records WHERE record_date = ?");
    $broilerCheck->execute([$date]);
    if ($broilerCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
}

if ($farmType === 'ruminant' || $farmType === 'both') {
    $ruminantCheck = $pdo->prepare("SELECT COUNT(*) FROM ruminant_daily_records WHERE record_date = ?");
    $ruminantCheck->execute([$date]);
    if ($ruminantCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
}

// Check for low stock items
$lowStockCheck = $pdo->prepare("SELECT COUNT(*) FROM stock_items 
                               WHERE farm_type IN (?, 'both') 
                               AND current_stock <= min_stock_level");
$lowStockCheck->execute([$farmType]);
$lowStockCount = $lowStockCheck->fetchColumn();

echo json_encode([
    'pending_tasks' => $pendingTasks,
    'low_stock_items' => $lowStockCount,
    'date' => $date,
    'farm_type' => $farmType
]);
?>