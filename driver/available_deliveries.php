<?php
require_once '../includes/config.php';
require_once '../includes/classes/Driver.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

// Initialize the Driver class
$pdo = getDBConnection();
$driver = new Driver($pdo);

// Get delivery tasks based on status filter
$validStatuses = ['pending', 'assigned', 'picked_up', 'in_transit', 'completed'];
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, $validStatuses, true)) {
    $status = 'pending';
}

$tasks = $driver->listDeliveryTasks($status, $_SESSION['user_id']);
$statusLabels = [
    'pending' => ['label' => 'Available', 'badge' => 'bg-secondary'],
    'assigned' => ['label' => 'Assigned', 'badge' => 'bg-info'],
    'picked_up' => ['label' => 'Picked Up', 'badge' => 'bg-primary'],
    'in_transit' => ['label' => 'In Transit', 'badge' => 'bg-warning text-dark'],
    'completed' => ['label' => 'Completed', 'badge' => 'bg-success']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Task Board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">FoodSave Delivery Management</h5>
                        <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Delivery Manager'); ?></small>
                    </div>

                    <ul class="nav flex-column">
                        <?php include '../includes/notification_header.php'; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $status === 'pending' ? ' active' : ''; ?>" href="available_deliveries.php">
                                <i class="fas fa-route"></i> Task Board
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $status === 'assigned' ? ' active' : ''; ?>" href="available_deliveries.php?status=assigned">
                                <i class="fas fa-truck-loading"></i> My Active Runs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $status === 'completed' ? ' active' : ''; ?>" href="available_deliveries.php?status=completed">
                                <i class="fas fa-history"></i> Delivery History
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Delivery Task Board</h1>
                        <p class="text-muted mb-0">Assign, update, and review logistics activity in one place.</p>
                    </div>
                    <div class="btn-group">
                        <a href="available_deliveries.php" class="btn btn-success">
                            <i class="fas fa-truck-front me-2"></i>Available Tasks
                        </a>
                        <a href="available_deliveries.php?status=assigned" class="btn btn-outline-light">
                            <i class="fas fa-clipboard-check me-2"></i>My Assignments
                        </a>
                    </div>
                </div>

                <ul class="nav nav-pills mb-4">
                    <?php foreach ($statusLabels as $key => $meta): ?>
                        <li class="nav-item me-2">
                            <a class="nav-link<?php echo $status === $key ? ' active' : ''; ?>" href="?status=<?php echo $key; ?>">
                                <?php echo htmlspecialchars($meta['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (empty($tasks)): ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-circle-info me-2"></i>
                        <div>No delivery tasks found for this view. Check back soon.</div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($tasks as $task): ?>
                            <div class="col-xl-6">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-start">
                                        <?php
                                            $badge = $statusLabels[$task['status']] ?? ['badge' => 'bg-secondary'];
                                            $pickupLabel = $task['pickup_time'] ? date('M j, g:i A', strtotime($task['pickup_time'])) : 'Awaiting schedule';
                                        ?>
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($task['donation_title']); ?></h5>
                                            <span class="text-muted small"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($pickupLabel); ?></span>
                                        </div>
                                        <span class="badge <?php echo $badge['badge']; ?>">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $task['status']))); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <h6 class="text-uppercase text-muted small mb-2">Pickup</h6>
                                            <p class="mb-1 fw-semibold"><i class="fas fa-hand-holding-heart me-2 text-success"></i><?php echo htmlspecialchars($task['donor_name']); ?></p>
                                            <p class="mb-0 small text-muted"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($task['pickup_address']); ?></p>
                                            <p class="mb-0 small text-muted ms-4"><?php echo htmlspecialchars($task['pickup_city'] . ', ' . $task['pickup_state'] . ' ' . $task['pickup_zip']); ?></p>
                                            <p class="mb-0 small text-muted ms-4"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($task['donor_phone']); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="text-uppercase text-muted small mb-2">Drop-off</h6>
                                            <p class="mb-1 fw-semibold"><i class="fas fa-people-carry-box me-2 text-primary"></i><?php echo htmlspecialchars($task['ngo_name']); ?></p>
                                            <p class="mb-0 small text-muted"><i class="fas fa-location-dot me-2"></i><?php echo htmlspecialchars($task['delivery_address']); ?></p>
                                            <p class="mb-0 small text-muted ms-4"><?php echo htmlspecialchars($task['delivery_city'] . ', ' . $task['delivery_state'] . ' ' . $task['delivery_zip']); ?></p>
                                            <p class="mb-0 small text-muted ms-4"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($task['ngo_phone']); ?></p>
                                        </div>

                                        <?php if ($task['status'] === 'pending'): ?>
                                            <form action="accept_task.php" method="POST" class="d-grid">
                                                <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-truck-fast me-2"></i>Accept Delivery
                                                </button>
                                            </form>
                                        <?php elseif (in_array($task['status'], ['assigned','picked_up','in_transit'], true) && (int) $task['driver_id'] === (int) $_SESSION['user_id']): ?>
                                            <div class="d-grid gap-2">
                                                <form action="update_task_status.php" method="POST">
                                                    <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                                    <input type="hidden" name="status" value="picked_up">
                                                    <button type="submit" class="btn btn-outline-primary">
                                                        <i class="fas fa-box-open me-2"></i>Mark Picked Up
                                                    </button>
                                                </form>
                                                <form action="update_task_status.php" method="POST">
                                                    <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                                    <input type="hidden" name="status" value="in_transit">
                                                    <button type="submit" class="btn btn-outline-warning">
                                                        <i class="fas fa-route me-2"></i>Start Transit
                                                    </button>
                                                </form>
                                                <form action="update_task_status.php" method="POST">
                                                    <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-flag-checkered me-2"></i>Mark Delivered
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-secondary mb-0" role="alert">
                                                <i class="fas fa-circle-check me-2"></i>Status updated. Awaiting further instructions.
                                            </div>
                                        <?php endif; ?>
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
        document.querySelectorAll('form[action="update_task_status.php"]').forEach(form => {
            form.addEventListener('submit', function () {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function (position) {
                        ['latitude','longitude'].forEach(function(name, i) {
                            let input = form.querySelector('input[name="'+name+'"]');
                            if (!input) { input=document.createElement('input'); input.type='hidden'; input.name=name; form.appendChild(input); }
                            input.value = i===0 ? position.coords.latitude : position.coords.longitude;
                        });
                    });
                }
            }, {once:true});
            form.addEventListener('submit', event => {
                const message = form.querySelector('input[name="status"]').value === 'completed'
                    ? 'Confirm delivery completion?'
                    : 'Confirm pickup status update?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>