<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/classes/Notification.php');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $notification = new Notification($pdo);
    $notifications = $notification->getUnreadNotifications($_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'count' => count($notifications)
    ]);
} catch (Exception $e) {
    error_log("Error checking notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}