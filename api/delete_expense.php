<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM farm_expenses WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Expense record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>