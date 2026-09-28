<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'volunteer') {
    redirectTo('login.php');
}

$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);

    $assignments = $volunteerManager->getAssignmentsForVolunteer($_SESSION['user_id']);
} catch (Exception $e) {
    $assignments = [];
    $errorMessage = 'Unable to load active deliveries right now.';
    error_log('Volunteer active deliveries error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Deliveries - FoodSave Volunteer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php $activePage = 'deliveries'; include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Active Deliveries</h1>
                        <p class="text-muted mb-0">Update statuses and share your live location to help NGOs track the pickup.</p>
                    </div>
                </div>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($assignments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You have no active delivery assignments right now.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1 text-dark"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                            <small class="text-muted text-dark">
                                                Pickup #<?php echo $assignment['pickup_request_id']; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $assignment['status']))); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong class="text-dark">Pickup:</strong><br>
                                            <?php echo htmlspecialchars($assignment['pickup_address']); ?><br>
                                            <?php echo htmlspecialchars($assignment['pickup_city']); ?>, <?php echo htmlspecialchars($assignment['pickup_state']); ?> <?php echo htmlspecialchars($assignment['pickup_zip']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong class="text-dark">Deliver To:</strong><br>
                                            <?php echo htmlspecialchars($assignment['delivery_address']); ?><br>
                                            <?php echo htmlspecialchars($assignment['delivery_city']); ?>, <?php echo htmlspecialchars($assignment['delivery_state']); ?> <?php echo htmlspecialchars($assignment['delivery_zip']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong class="text-dark">Donor Contact:</strong><br>
                                            <?php echo htmlspecialchars($assignment['donor_name']); ?><br>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($assignment['donor_phone'] ?? 'N/A'); ?>
                                                &nbsp;|
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($assignment['donor_email']); ?>
                                            </small>
                                        </div>

                                        <form action="update_delivery_status.php" method="POST" class="mt-3">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <div class="mb-3">
                                                <label for="status-<?php echo $assignment['id']; ?>" class="form-label">Update Status</label>
                                                <select class="form-select" id="status-<?php echo $assignment['id']; ?>" name="status" required>
                                                    <?php
                                                    $statusOptions = ['started', 'at_pickup', 'picked_up', 'in_transit', 'at_delivery', 'delivered'];
                                                    foreach ($statusOptions as $statusOption):
                                                        ?>
                                                        <option value="<?php echo $statusOption; ?>"
                                                            <?php echo $assignment['status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst(str_replace('_', ' ', $statusOption)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="lat-<?php echo $assignment['id']; ?>">Latitude</label>
                                                    <input type="text" class="form-control" name="latitude" id="lat-<?php echo $assignment['id']; ?>" placeholder="e.g. 37.7749">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="lng-<?php echo $assignment['id']; ?>">Longitude</label>
                                                    <input type="text" class="form-control" name="longitude" id="lng-<?php echo $assignment['id']; ?>" placeholder="e.g. -122.4194">
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center mt-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm me-2 use-location-btn" data-target="<?php echo $assignment['id']; ?>">
                                                    <i class="fas fa-location-arrow me-1"></i>Use My Location
                                                </button>
                                                <small class="text-muted">Location is optional but helps NGOs track live progress.</small>
                                            </div>

                                            <div class="mt-3">
                                                <label class="form-label" for="notes-<?php echo $assignment['id']; ?>">Notes</label>
                                                <textarea class="form-control" id="notes-<?php echo $assignment['id']; ?>" name="notes" rows="2" placeholder="Add any status details (optional)"></textarea>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <small class="text-muted">Remember to mark as delivered once you complete the drop-off.</small>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i>Update Status
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
        document.querySelectorAll('.use-location-btn').forEach(button => {
            button.addEventListener('click', () => {
                const assignmentId = button.dataset.target;
                const latField = document.getElementById(`lat-${assignmentId}`);
                const lngField = document.getElementById(`lng-${assignmentId}`);

                if (!navigator.geolocation) {
                    alert('Geolocation is not supported on this device.');
                    return;
                }

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Locating...';

                navigator.geolocation.getCurrentPosition((position) => {
                    latField.value = position.coords.latitude.toFixed(6);
                    lngField.value = position.coords.longitude.toFixed(6);
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-location-arrow me-1"></i>Use My Location';
                }, () => {
                    alert('Unable to fetch your current location.');
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-location-arrow me-1"></i>Use My Location';
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            });
        });
    </script>
</body>
</html>
