<?php
require_once '../includes/config.php';
require_once '../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);
    
    // Get current locations for all active deliveries
    $locations = $volunteerManager->getActiveDeliveryLocations($_SESSION['user_id']);
    
    header('Content-Type: application/json');
    echo json_encode($locations);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}