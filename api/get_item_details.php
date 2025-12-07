<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $itemId = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if ($item) {
        echo json_encode($item);
    } else {
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    echo json_encode(['error' => 'Item ID required']);
}
?>