<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM sales_records WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Sale record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>