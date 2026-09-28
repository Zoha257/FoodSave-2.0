<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Volunteer.php';
require_once __DIR__ . '/../includes/classes/Notification.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('my_requests.php');
}

$pickupRequestId = isset($_POST['pickup_request_id']) ? (int)$_POST['pickup_request_id'] : 0;
$volunteerId = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : 0;
$pickupTime = trim($_POST['pickup_time'] ?? '');

if ($pickupRequestId <= 0) {
    $_SESSION['error_message'] = 'Choose a donation request before assigning logistics support.';
    redirectTo('my_requests.php');
}

if ($volunteerId <= 0) {
    $_SESSION['error_message'] = 'Please choose a volunteer before saving the assignment.';
    redirectTo('my_requests.php');
}

try {
    $pdo = getDBConnection();
    $ngoId = $_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT id FROM pickup_requests WHERE id = ? AND ngo_id = ?');
    $stmt->execute([$pickupRequestId, $ngoId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['error_message'] = 'Invalid pickup request selected.';
        redirectTo('my_requests.php');
    }

    $volunteerManager = new Volunteer($pdo);
    $notification = new Notification($pdo);

    $stmt = $pdo->prepare('SELECT id FROM volunteers WHERE id = ? AND ngo_id = ?');
    $stmt->execute([$volunteerId, $ngoId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['error_message'] = 'The selected volunteer is not part of your organization.';
        redirectTo('my_requests.php');
    }

    if ($pickupTime !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $pickupTime);
        if (!$dt) {
            $_SESSION['error_message'] = 'Please provide the pickup time in a valid format.';
            redirectTo('my_requests.php');
        }
        $pickupTime = $dt->format('Y-m-d H:i:s');
    } else {
        $pickupTime = null;
    }

    if ($volunteerManager->assignDelivery($volunteerId, $pickupRequestId, $pickupTime)) {
        // Notify volunteer about assignment
        $volunteerRecord = $volunteerManager->getAssignmentForRequest($pickupRequestId);
        if (!empty($volunteerRecord['user_id'])) {
            $notification->create(
                $volunteerRecord['user_id'],
                'New Pickup Assignment',
                'A new food pickup has been assigned to you. Please confirm availability.',
                'info',
                'pickup',
                $pickupRequestId
            );
        }

        $_SESSION['success_message'] = 'Delivery assignment saved successfully.';
    } else {
        $_SESSION['error_message'] = 'Could not save the delivery assignment. Please try again.';
    }
} catch (Exception $e) {
    error_log('Assign delivery error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Unexpected error while assigning the delivery.';
}

redirectTo('my_requests.php');
