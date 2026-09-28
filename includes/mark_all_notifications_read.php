<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/classes/Notification.php');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $success = $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}