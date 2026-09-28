<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Notification.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($requestId <= 0) {
    $_SESSION['error_message'] = 'Invalid pickup request selected.';
    redirectTo('my_requests.php');
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT pr.id, pr.status, pr.donation_id, pr.ngo_id, pr.updated_at, fd.donor_id, fd.title
         FROM pickup_requests pr
         JOIN food_donations fd ON pr.donation_id = fd.id
         WHERE pr.id = ? AND pr.ngo_id = ?
         FOR UPDATE"
    );
    $stmt->execute(array($requestId, $_SESSION['user_id']));
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Pickup request not found or access denied.';
        redirectTo('my_requests.php');
    }

    if ($request['status'] === 'completed') {
        $pdo->rollBack();
        $_SESSION['success_message'] = 'This pickup was already marked as completed.';
        redirectTo('my_requests.php');
    }

    if (!in_array($request['status'], array('approved', 'in_transit', 'assigned', 'picked_up'), true)) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Only approved pickups can be completed.';
        redirectTo('my_requests.php');
    }

    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE pickup_requests SET status = 'completed', updated_at = ? WHERE id = ?");
    $stmt->execute(array($now, $requestId));

    $stmt = $pdo->prepare("UPDATE food_donations SET status = 'completed', updated_at = ? WHERE id = ?");
    $stmt->execute(array($now, $request['donation_id']));

    $stmt = $pdo->prepare('SELECT id, volunteer_id FROM delivery_assignments WHERE pickup_request_id = ? FOR UPDATE');
    $stmt->execute(array($requestId));
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assignment) {
        $stmt = $pdo->prepare("UPDATE delivery_assignments SET status = 'delivered', delivery_time = ?, updated_at = ? WHERE id = ?");
        $stmt->execute(array($now, $now, $assignment['id']));

        if (!empty($assignment['volunteer_id'])) {
            $stmt = $pdo->prepare("UPDATE volunteers SET availability_status = 'available', updated_at = ? WHERE id = ?");
            $stmt->execute(array($now, $assignment['volunteer_id']));

            $stmt = $pdo->prepare('INSERT INTO delivery_tracking (delivery_assignment_id, status_update, created_at) VALUES (?, ?, ?)');
            $stmt->execute(array($assignment['id'], 'delivered', $now));
        }
    }

    $pdo->commit();

    try {
        $notification = new Notification($pdo);
        $notification->create(
            (int) $request['donor_id'],
            'Pickup Completed',
            'The NGO has confirmed that the donation "' . $request['title'] . '" was successfully delivered.',
            'success',
            'pickup',
            $requestId
        );
    } catch (Exception $notificationError) {
        error_log('Notification error (pickup completed): ' . $notificationError->getMessage());
    }

    $_SESSION['success_message'] = 'Pickup marked as completed successfully.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error marking pickup completed: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Unable to mark this pickup as completed right now.';
}

redirectTo('my_requests.php');
