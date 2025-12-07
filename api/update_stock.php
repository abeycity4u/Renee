<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $pdo->beginTransaction();
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
        $stmt->execute([$data['item_id']]);
        $item = $stmt->fetch();
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Calculate new stock
        $previous_stock = $item['current_stock'];
        
        if ($data['type'] == 'received') {
            $new_stock = $previous_stock + $data['quantity'];
        } else if ($data['type'] == 'used') {
            if ($data['quantity'] > $previous_stock) {
                throw new Exception('Insufficient stock. Available: ' . $previous_stock);
            }
            $new_stock = $previous_stock - $data['quantity'];
        } else {
            throw new Exception('Invalid transaction type');
        }
        
        // Update stock item
        $updateStmt = $pdo->prepare("UPDATE stock_items SET current_stock = ? WHERE id = ?");
        $updateStmt->execute([$new_stock, $data['item_id']]);
        
        // Record transaction
        $transStmt = $pdo->prepare("INSERT INTO stock_transactions 
            (stock_item_id, transaction_type, quantity, previous_stock, new_stock, 
             transaction_date, remarks, user_id, farm_type) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)");
        $transStmt->execute([
            $data['item_id'],
            $data['type'],
            $data['quantity'],
            $previous_stock,
            $new_stock,
            $data['remarks'] ?? null,
            $_SESSION['user_id'],
            $data['farm_type']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully',
            'new_stock' => $new_stock
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>