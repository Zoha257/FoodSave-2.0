<?php
require_once '../includes/config.php';
require_once '../includes/classes/Feedback.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

try {
    $pdo = getDBConnection();
    $feedback = new Feedback($pdo);
    
    // Get overall statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT fd.id) as total_donations,
            COUNT(DISTINCT pr.id) as total_pickups,
            COUNT(DISTINCT v.id) as total_volunteers,
            AVG(f.rating) as avg_rating
        FROM food_donations fd
        LEFT JOIN pickup_requests pr ON fd.id = pr.donation_id
        LEFT JOIN delivery_assignments da ON pr.id = da.pickup_request_id
        LEFT JOIN volunteers v ON da.volunteer_id = v.id
        LEFT JOIN feedback f ON pr.id = f.pickup_request_id
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top-rated volunteers
    $stmt = $pdo->query("
        SELECT 
            v.id,
            u.full_name,
            vr.average_rating,
            vr.total_ratings,
            COUNT(da.id) as total_deliveries
        FROM volunteers v
        JOIN users u ON v.user_id = u.id
        JOIN volunteer_ratings vr ON v.id = vr.volunteer_id
        LEFT JOIN delivery_assignments da ON v.id = da.volunteer_id
        GROUP BY v.id
        HAVING vr.total_ratings >= 5
        ORDER BY vr.average_rating DESC
        LIMIT 5
    ");
    $topVolunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent feedback
    $stmt = $pdo->query("
        SELECT 
            f.*,
            u.full_name,
            fd.title as donation_title
        FROM feedback f
        JOIN pickup_requests pr ON f.pickup_request_id = pr.id
        JOIN food_donations fd ON pr.donation_id = fd.id
        JOIN users u ON (
            CASE 
                WHEN f.feedback_type = 'donor' THEN fd.donor_id = u.id
                ELSE pr.ngo_id = u.id
            END
        )
        ORDER BY f.created_at DESC
        LIMIT 10
    ");
    $recentFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Server error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports - FoodSave Admin</title>
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
                        <?php include '../includes/notification_header.php'; ?>
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
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="feedback_reports.php">
                                <i class="fas fa-comments"></i> Feedback Reports
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
                    <h1 class="h2">Feedback Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportReport()">Export</button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Donations</h5>
                                <h2 class="card-text"><?php echo number_format($stats['total_donations']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Pickups</h5>
                                <h2 class="card-text"><?php echo number_format($stats['total_pickups']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Active Volunteers</h5>
                                <h2 class="card-text"><?php echo number_format($stats['total_volunteers']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Average Rating</h5>
                                <h2 class="card-text"><?php echo number_format($stats['avg_rating'], 1); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Rating Distribution</h5>
                                <canvas id="ratingChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Monthly Activity</h5>
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Volunteers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Top-Rated Volunteers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Rating</th>
                                        <th>Total Ratings</th>
                                        <th>Deliveries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topVolunteers as $volunteer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($volunteer['full_name']); ?></td>
                                            <td>
                                                <?php
                                                $rating = round($volunteer['average_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $rating ? '★' : '☆';
                                                }
                                                ?>
                                                (<?php echo number_format($volunteer['average_rating'], 1); ?>)
                                            </td>
                                            <td><?php echo number_format($volunteer['total_ratings']); ?></td>
                                            <td><?php echo number_format($volunteer['total_deliveries']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Feedback -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Feedback</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentFeedback as $feedback): ?>
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">
                                        <?php echo htmlspecialchars($feedback['donation_title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($feedback['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <?php
                                    $rating = $feedback['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '★' : '☆';
                                    }
                                    ?>
                                </p>
                                <p class="mb-1"><?php echo htmlspecialchars($feedback['comments']); ?></p>
                                <small class="text-muted">
                                    By <?php echo htmlspecialchars($feedback['full_name']); ?> 
                                    (<?php echo ucfirst($feedback['feedback_type']); ?>)
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
    // Rating Distribution Chart
    const ratingCtx = document.getElementById('ratingChart').getContext('2d');
    new Chart(ratingCtx, {
        type: 'bar',
        data: {
            labels: ['1★', '2★', '3★', '4★', '5★'],
            datasets: [{
                label: 'Number of Ratings',
                data: [
                    <?php
                    for ($i = 1; $i <= 5; $i++) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE rating = ?");
                        $stmt->execute([$i]);
                        echo $stmt->fetchColumn() . ',';
                    }
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Monthly Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: <?php
                $stmt = $pdo->query("
                    SELECT DATE_FORMAT(created_at, '%b %Y') as month
                    FROM pickup_requests
                    GROUP BY YEAR(created_at), MONTH(created_at)
                    ORDER BY created_at DESC
                    LIMIT 6
                ");
                echo json_encode(array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN)));
            ?>,
            datasets: [{
                label: 'Completed Deliveries',
                data: <?php
                    $stmt = $pdo->query("
                        SELECT COUNT(*) as count
                        FROM pickup_requests
                        WHERE status = 'completed'
                        GROUP BY YEAR(created_at), MONTH(created_at)
                        ORDER BY created_at DESC
                        LIMIT 6
                    ");
                    echo json_encode(array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN)));
                ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    function exportReport() {
        // Implement report export functionality
        alert('Report export functionality will be implemented here.');
    }
    </script>
</body>
</html>