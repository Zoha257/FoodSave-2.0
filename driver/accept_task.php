<?php
require_once '../includes/config.php';
require_once '../includes/classes/Driver.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: available_deliveries.php');
    exit();
}

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    $_SESSION['error'] = 'Unauthorized access.';
    header('Location: ../login.php');
    exit();
}

// Get task ID
$taskId = $_POST['task_id'] ?? null;
if (!$taskId) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: available_deliveries.php');
    exit();
}

// Initialize Driver class
$pdo = getDBConnection();
$driver = new Driver($pdo);

try {
    // Accept the delivery task
    $result = $driver->acceptTask($taskId, $_SESSION['user_id']);
    
    if ($result) {
        $_SESSION['success'] = 'Delivery task accepted successfully!';
        header('Location: available_deliveries.php?status=assigned');
    } else {
        $_SESSION['error'] = 'Error accepting delivery task.';
        header('Location: available_deliveries.php');
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: available_deliveries.php');
}
exit();