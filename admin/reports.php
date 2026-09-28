<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

try {
    $pdo = getDBConnection();
    
    // Overall Statistics
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT user_type, COUNT(*) as count FROM users WHERE user_type != 'admin' GROUP BY user_type");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['users'][$row['user_type']] = $row['count'];
    }
    
    // Total donations by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM food_donations GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['donations'][$row['status']] = $row['count'];
    }
    
    // Total requests by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM pickup_requests GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['requests'][$row['status']] = $row['count'];
    }
    
    // Monthly donation trends (last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM food_donations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $monthly_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Category distribution
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as count 
        FROM food_donations 
        GROUP BY category 
        ORDER BY count DESC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top donors
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(fd.id) as donation_count,
               COUNT(CASE WHEN fd.status = 'picked_up' THEN 1 END) as completed_count
        FROM users u 
        LEFT JOIN food_donations fd ON u.id = fd.donor_id 
        WHERE u.user_type = 'donor' 
        GROUP BY u.id 
        HAVING donation_count > 0
        ORDER BY donation_count DESC 
        LIMIT 10
    ");
    $top_donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top NGOs
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(pr.id) as request_count,
               COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed_count
        FROM users u 
        LEFT JOIN pickup_requests pr ON u.id = pr.ngo_id 
        WHERE u.user_type = 'ngo' 
        GROUP BY u.id 
        HAVING request_count > 0
        ORDER BY request_count DESC 
        LIMIT 10
    ");
    $top_ngos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity
    $stmt = $pdo->query("
        SELECT 'donation' as type, fd.title as title, u.full_name as user_name, fd.created_at as date
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        UNION ALL
        SELECT 'request' as type, CONCAT('Request for: ', fd.title) as title, u.full_name as user_name, pr.created_at as date
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON pr.ngo_id = u.id 
        ORDER BY date DESC 
        LIMIT 20
    ");
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FoodSave Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">FoodSave Admin</h5>
                        <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_donations.php">
                                <i class="fas fa-utensils"></i> Manage Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="guidelines.php">
                                <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Reports</h1>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light" data-bs-toggle="collapse" data-bs-target="#exportPanel" aria-expanded="false" aria-controls="exportPanel">
                            <i class="fas fa-file-export"></i> Export Data
                        </button>
                        <button class="btn btn-success" onclick="window.print()" type="button">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>

                <div class="collapse" id="exportPanel">
                    <div class="card mb-4">
                        <div class="card-body">
                            <form class="row g-3 align-items-end" method="GET" action="export_report.php">
                                <div class="col-md-4">
                                    <label class="form-label" for="exportType">Dataset</label>
                                    <select class="form-select" id="exportType" name="type" required>
                                        <option value="donations">Donations</option>
                                        <option value="requests">Pickup Requests</option>
                                        <option value="users">Users</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="exportFrom">From</label>
                                    <input type="date" class="form-control" id="exportFrom" name="from">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="exportTo">To</label>
                                    <input type="date" class="form-control" id="exportTo" name="to">
                                </div>
                                <div class="col-md-2 text-md-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-download"></i> Download CSV
                                    </button>
                                </div>
                            </form>
                            <p class="text-muted small mt-3 mb-0">Leave the date range empty to export all records for the selected dataset.</p>
                        </div>
                    </div>
                </div>

                <!-- Overview Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4><?php echo ($stats['users']['donor'] ?? 0) + ($stats['users']['ngo'] ?? 0); ?></h4>
                                <p class="mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4><?php echo array_sum($stats['donations'] ?? []); ?></h4>
                                <p class="mb-0">Total Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4><?php echo array_sum($stats['requests'] ?? []); ?></h4>
                                <p class="mb-0">Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['donations']['picked_up'] ?? 0; ?></h4>
                                <p class="mb-0">Successful Deliveries</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Charts -->
                    <div class="col-md-8">
                        <!-- Monthly Donations Chart -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Monthly Donation Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" height="100"></canvas>
                            </div>
                        </div>

                        <!-- Category Distribution Chart -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Donation Categories</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Tables -->
                    <div class="col-md-4">
                        <!-- Top Donors -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Top Donors</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_donors)): ?>
                                    <p class="text-muted">No donors yet</p>
                                <?php else: ?>
                                    <?php foreach ($top_donors as $donor): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($donor['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $donor['completed_count']; ?> completed</small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo $donor['donation_count']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top NGOs -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Most Active NGOs</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_ngos)): ?>
                                    <p class="text-muted">No NGOs yet</p>
                                <?php else: ?>
                                    <?php foreach ($top_ngos as $ngo): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($ngo['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $ngo['completed_count']; ?> completed</small>
                                        </div>
                                        <span class="badge bg-info"><?php echo $ngo['request_count']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-dark">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Activity</th>
                                        <th>User</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_activity)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No recent activity</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $activity['type'] === 'donation' ? 'primary' : 'info'; ?>">
                                                    <?php echo ucfirst($activity['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Donation Status Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td>Available</td>
                                            <td><span class="badge status-available"><?php echo $stats['donations']['available'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Requested</td>
                                            <td><span class="badge status-requested"><?php echo $stats['donations']['requested'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Picked Up</td>
                                            <td><span class="badge status-picked-up"><?php echo $stats['donations']['picked_up'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Expired</td>
                                            <td><span class="badge status-expired"><?php echo $stats['donations']['expired'] ?? 0; ?></span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Request Status Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td>Pending</td>
                                            <td><span class="badge status-pending"><?php echo $stats['requests']['pending'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Approved</td>
                                            <td><span class="badge status-approved"><?php echo $stats['requests']['approved'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Rejected</td>
                                            <td><span class="badge status-rejected"><?php echo $stats['requests']['rejected'] ?? 0; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td>Completed</td>
                                            <td><span class="badge status-completed"><?php echo $stats['requests']['completed'] ?? 0; ?></span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
        // Monthly Donations Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_donations, 'month')); ?>,
                datasets: [{
                    label: 'Donations',
                    data: <?php echo json_encode(array_column($monthly_donations, 'count')); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($cat) { return ucfirst(str_replace('_', ' ', $cat['category'])); }, $category_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_stats, 'count')); ?>,
                    backgroundColor: [
                        '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
                        '#6f42c1', '#fd7e14', '#20c997', '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

