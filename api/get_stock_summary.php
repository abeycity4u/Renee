<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$farmType = $_GET['farm_type'] ?? 'both';

// Get low stock count
$lowStockQuery = "SELECT COUNT(*) as low_stock_count 
                  FROM stock_items 
                  WHERE farm_type IN (?, 'both') 
                  AND current_stock <= min_stock_level";
$lowStockStmt = $pdo->prepare($lowStockQuery);
$lowStockStmt->execute([$farmType]);
$lowStockCount = $lowStockStmt->fetchColumn();

// Get total stock value
$valueQuery = "SELECT SUM(current_stock * 100) as total_value FROM stock_items 
               WHERE farm_type IN (?, 'both')";
$valueStmt = $pdo->prepare($valueQuery);
$valueStmt->execute([$farmType]);
$totalValue = $valueStmt->fetchColumn();

// Get recent stock changes
$changesQuery = "SELECT COUNT(*) as recent_changes FROM stock_transactions 
                 WHERE farm_type = ? AND transaction_date = CURDATE()";
$changesStmt = $pdo->prepare($changesQuery);
$changesStmt->execute([$farmType]);
$recentChanges = $changesStmt->fetchColumn();

echo json_encode([
    'updated' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'low_stock_count' => $lowStockCount,
    'total_stock_value' => $totalValue,
    'recent_changes' => $recentChanges
]);
?>