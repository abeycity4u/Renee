<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['date']) && isset($_GET['animal_type'])) {
    $date = $_GET['date'];
    $animalType = $_GET['animal_type'];
    
    $stmt = $pdo->prepare("SELECT id FROM ruminant_daily_records 
                           WHERE record_date = ? AND animal_type = ?");
    $stmt->execute([$date, $animalType]);
    
    echo json_encode(['exists' => $stmt->fetch() ? true : false]);
} else {
    echo json_encode(['error' => 'Missing parameters']);
}
?>