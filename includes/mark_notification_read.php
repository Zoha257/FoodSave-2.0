<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/classes/Notification.php');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$notificationId = $_POST['notification_id'] ?? null;

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    $notification = new Notification($pdo);
    $success = $notification->markAsRead($notificationId, $_SESSION['user_id']);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}