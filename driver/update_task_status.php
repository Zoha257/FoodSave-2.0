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

// Get task ID and new status
$taskId = $_POST['task_id'] ?? null;
$status = $_POST['status'] ?? null;
$latitude = trim($_POST['latitude'] ?? '');
$longitude = trim($_POST['longitude'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$taskId || !$status) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: available_deliveries.php');
    exit();
}

// Validate status
$validStatuses = ['picked_up', 'in_transit', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    $_SESSION['error'] = 'Invalid status.';
    header('Location: available_deliveries.php');
    exit();
}

// Initialize Driver class
$pdo = getDBConnection();
$driver = new Driver($pdo);

try {
    // Update the task status
    $latValue = $latitude === '' ? null : (float)$latitude;
    $lngValue = $longitude === '' ? null : (float)$longitude;

    $result = $driver->updateTaskStatus($taskId, $status, $_SESSION['user_id'], $latValue, $lngValue, $notes ?: null);
    
    if ($result) {
        $_SESSION['success'] = 'Delivery status updated successfully!';
    } else {
        $_SESSION['error'] = 'Error updating delivery status.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Redirect based on the new status
if ($status === 'completed') {
    header('Location: dashboard.php');
} else {
    header('Location: available_deliveries.php?status=' . urlencode($status));
}
exit();