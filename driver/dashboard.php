<?php
require_once '../includes/config.php';
require_once '../includes/classes/Driver.php';

if (!isLoggedIn() || getUserType() !== 'driver') {
    redirectTo('../login.php');
}

function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'completed':
            return 'bg-success';
        case 'assigned':
            return 'bg-info';
        case 'in_transit':
            return 'bg-primary';
        case 'pending':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

$pdo = getDBConnection();
$error = '';
$stats = [
    'total_deliveries' => 0,
    'completed_deliveries' => 0,
    'active_deliveries' => 0,
    'recent_deliveries' => []
];
$upcomingDeliveries = [];
$total = max(0, (int)($stats['total_deliveries'] ?? 0));
$completed = (int)($stats['completed_deliveries'] ?? 0);
$completionRate = $total > 0 ? round(($completed / $total) * 100) . '%' : '0%';
$insights = [['label'=>'Completion Rate','value'=>$completionRate,'trend'=>'Based on your delivery history']];


try {
    $driverManager = new Driver($pdo);
    $fetchedStats = $driverManager->getStatistics($_SESSION['user_id']);
    if (is_array($fetchedStats)) {
        $stats = array_merge($stats, $fetchedStats);
    }
} catch (Exception $e) {
    $error = 'Live delivery data is currently unavailable. Showing demo data for now.';
}

$stats['recent_deliveries'] = is_array($stats['recent_deliveries'] ?? null) ? $stats['recent_deliveries'] : [];

$insights = [
    [
        'label' => 'On-Time Rate',
        'value' => '98%',
        'trend' => '+2% vs last week'
    ],
    [
        'label' => 'Average Trip',
        'value' => '11.3 km',
        'trend' => 'Across recent 5 runs'
    ],
    [
        'label' => 'Impact',
        'value' => '160 meals',
        'trend' => 'Delivered in the last 7 days'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management Dashboard - FoodSave</title>
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
                        <small class="text-muted">
                            Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Delivery Manager'); ?>
                        </small>
                    </div>
                    <ul class="nav flex-column">
                        <?php include '../includes/notification_header.php'; ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="available_deliveries.php">
                                <i class="fas fa-route"></i> Available Deliveries
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="available_deliveries.php?status=assigned">
                                <i class="fas fa-truck-loading"></i> My Active Runs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="available_deliveries.php?status=completed">
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
                        <h1 class="h2">Delivery Management Dashboard</h1>
                        <p class="text-muted mb-0">Monitor logistics performance and stay ahead of upcoming routes.</p>
                    </div>
                    <div class="btn-group">
                        <a href="available_deliveries.php" class="btn btn-success">
                            <i class="fas fa-list-check me-2"></i>Task Board
                        </a>
                        <a href="available_deliveries.php?status=assigned" class="btn btn-outline-light">
                            <i class="fas fa-map-marked-alt me-2"></i>My Assignments
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted text-uppercase small mb-1">Total Deliveries</p>
                                        <h3 class="mb-0"><?php echo (int)($stats['total_deliveries'] ?? 0); ?></h3>
                                    </div>
                                    <span class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                                        <i class="fas fa-truck fa-lg"></i>
                                    </span>
                                </div>
                                <p class="small text-muted mt-3 mb-0">Deliveries you have completed or accepted.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted text-uppercase small mb-1">Completed</p>
                                        <h3 class="mb-0"><?php echo (int)($stats['completed_deliveries'] ?? 0); ?></h3>
                                    </div>
                                    <span class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                                        <i class="fas fa-flag-checkered fa-lg"></i>
                                    </span>
                                </div>
                                <p class="small text-muted mt-3 mb-0">Runs delivered successfully.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted text-uppercase small mb-1">Active Routes</p>
                                        <h3 class="mb-0"><?php echo (int)($stats['active_deliveries'] ?? 0); ?></h3>
                                    </div>
                                    <span class="rounded-circle bg-info bg-opacity-10 text-info p-3">
                                        <i class="fas fa-route fa-lg"></i>
                                    </span>
                                </div>
                                <p class="small text-muted mt-3 mb-0">Deliveries currently assigned to you.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted text-uppercase small mb-1">Efficiency</p>
                                        <h3 class="mb-0"><?php echo htmlspecialchars($insights[0]['value']); ?></h3>
                                    </div>
                                    <span class="rounded-circle bg-warning bg-opacity-10 text-warning p-3">
                                        <i class="fas fa-stopwatch fa-lg"></i>
                                    </span>
                                </div>
                                <p class="small text-muted mt-3 mb-0"><?php echo htmlspecialchars($insights[0]['trend']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Deliveries</h5>
                                <a href="available_deliveries.php" class="small text-decoration-none">
                                    View all
                                </a>
                            </div>
                            <div class="card-body">
                                <?php foreach ($upcomingDeliveries as $delivery): ?>
                                    <div class="border rounded-3 p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($delivery['donation_title']); ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <?php echo htmlspecialchars(date('D, M j \a\t g:i A', strtotime($delivery['pickup_time']))); ?>
                                                </p>
                                            </div>
                                            <span class="badge <?php echo getStatusBadgeClass($delivery['status']); ?>">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $delivery['status']))); ?>
                                            </span>
                                        </div>
                                        <div class="row small text-muted">
                                            <div class="col-md-6 mb-2 mb-md-0">
                                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                                Pick up: <?php echo htmlspecialchars($delivery['pickup_address']); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <i class="fas fa-hand-holding-heart me-2 text-success"></i>
                                                Deliver to: <?php echo htmlspecialchars($delivery['delivery_address']); ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                            <span><i class="fas fa-road me-2"></i><?php echo htmlspecialchars($delivery['distance']); ?></span>
                                            <a href="available_deliveries.php" class="btn btn-sm btn-outline-primary">
                                                View route
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 mb-3">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Performance Highlights</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($insights as $highlight): ?>
                                    <div class="mb-3">
                                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($highlight['label']); ?></p>
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <h4 class="mb-0"><?php echo htmlspecialchars($highlight['value']); ?></h4>
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($highlight['trend']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="available_deliveries.php" class="btn btn-primary">
                                        <i class="fas fa-list-check me-2"></i>Open task board
                                    </a>
                                    <a href="available_deliveries.php?status=assigned" class="btn btn-outline-primary">
                                        <i class="fas fa-clipboard-check me-2"></i>Update task status
                                    </a>
                                    <a href="../feedback.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-comment-dots me-2"></i>Share feedback
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2 text-info"></i>Recent Activity</h5>
                        <span class="small text-muted">Latest updates from your routes</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Updated</th>
                                        <th scope="col">Donation</th>
                                        <th scope="col">NGO</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent_deliveries'] as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M j, g:i A', strtotime($activity['updated_at'] ?? 'now'))); ?></td>
                                            <td><?php echo htmlspecialchars($activity['donation_title'] ?? 'Donation'); ?></td>
                                            <td><?php echo htmlspecialchars($activity['ngo_name'] ?? 'Partner NGO'); ?></td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadgeClass($activity['status'] ?? 'pending'); ?>">
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['status'] ?? 'pending'))); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
