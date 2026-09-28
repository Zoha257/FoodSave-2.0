<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'volunteer') {
    redirectTo('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('active_deliveries.php');
}

$assignmentId = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$status = trim($_POST['status'] ?? '');
$latitude = trim($_POST['latitude'] ?? '');
$longitude = trim($_POST['longitude'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$allowedStatuses = ['started', 'at_pickup', 'picked_up', 'in_transit', 'at_delivery', 'delivered'];

if ($assignmentId <= 0 || !in_array($status, $allowedStatuses, true)) {
    $_SESSION['error_message'] = 'Invalid assignment update request.';
    redirectTo('active_deliveries.php');
}

$latValue = $latitude !== '' ? (float)$latitude : null;
$lngValue = $longitude !== '' ? (float)$longitude : null;

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);

    $stmt = $pdo->prepare('SELECT da.id FROM delivery_assignments da JOIN volunteers v ON da.volunteer_id = v.id WHERE da.id = ? AND v.user_id = ?');
    $stmt->execute([$assignmentId, $_SESSION['user_id']]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['error_message'] = 'You are not assigned to this delivery.';
        redirectTo('active_deliveries.php');
    }

    if ($volunteerManager->updateDeliveryStatus($assignmentId, $status, $latValue, $lngValue, $notes)) {
        $_SESSION['success_message'] = 'Delivery status updated successfully.';
    } else {
        $_SESSION['error_message'] = 'Unable to update delivery status. Please try again.';
    }
} catch (Exception $e) {
    error_log('Volunteer status update error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Unexpected error while updating the status.';
}

redirectTo('active_deliveries.php');
