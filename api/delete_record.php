<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = $_GET['id'];
    
    try {
        if ($type === 'layer') {
            $stmt = $pdo->prepare("DELETE FROM layer_daily_records WHERE id = ?");
        } elseif ($type === 'broiler') {
            $stmt = $pdo->prepare("DELETE FROM broiler_daily_records WHERE id = ?");
        }
        
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>