<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$user_id = $_GET['id'] ?? null;
if (!$user_id || !is_numeric($user_id)) {
    redirectTo('manage_users.php');
}

try {
    $pdo = getDBConnection();
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT fd.id) as donation_count,
               COUNT(DISTINCT pr.id) as request_count,
               SUM(CASE WHEN fd.status = 'picked_up' THEN 1 ELSE 0 END) as completed_donations,
               SUM(CASE WHEN pr.status = 'completed' THEN 1 ELSE 0 END) as completed_requests
        FROM users u 
        LEFT JOIN food_donations fd ON u.id = fd.donor_id 
        LEFT JOIN pickup_requests pr ON u.id = pr.ngo_id 
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        redirectTo('manage_users.php');
    }
    
    // Get recent donations (if donor)
    $recent_donations = [];
    if ($user['user_type'] === 'donor') {
        $stmt = $pdo->prepare("
            SELECT * FROM food_donations 
            WHERE donor_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get recent requests (if NGO)
    $recent_requests = [];
    if ($user['user_type'] === 'ngo') {
        $stmt = $pdo->prepare("
            SELECT pr.*, fd.title as donation_title 
            FROM pickup_requests pr 
            JOIN food_donations fd ON pr.donation_id = fd.id 
            WHERE pr.ngo_id = ? 
            ORDER BY pr.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - FoodSave Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
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
                            <a class="nav-link active" href="manage_users.php">
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
                    <h1 class="h2">User Details</h1>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <div class="row">
                    <!-- User Information -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user"></i> User Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Full Name:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Username:</strong></div>
                                    <div class="col-sm-8">@<?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Email:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Phone:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>User Type:</strong></div>
                                    <div class="col-sm-8">
                                        <span class="badge bg-<?php echo $user['user_type'] === 'donor' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Status:</strong></div>
                                    <div class="col-sm-8">
                                        <span class="badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Joined:</strong></div>
                                    <div class="col-sm-8"><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location & Contact -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> Location & Contact
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Address:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>City:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['city'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>State:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['state'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>ZIP Code:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($user['zip_code'] ?: 'Not provided'); ?></div>
                                </div>
                                
                                <?php if ($user['address'] && $user['city'] && $user['state']): ?>
                                <div class="mt-3">
                                    <div id="map" style="height: 200px; border-radius: 5px;"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-primary">
                                    <?php echo $user['user_type'] === 'donor' ? $user['donation_count'] : $user['request_count']; ?>
                                </h4>
                                <p class="mb-0">
                                    <?php echo $user['user_type'] === 'donor' ? 'Total Donations' : 'Total Requests'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-success">
                                    <?php echo $user['user_type'] === 'donor' ? $user['completed_donations'] : $user['completed_requests']; ?>
                                </h4>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-info">
                                    <?php 
                                    $success_rate = 0;
                                    $total = $user['user_type'] === 'donor' ? $user['donation_count'] : $user['request_count'];
                                    $completed = $user['user_type'] === 'donor' ? $user['completed_donations'] : $user['completed_requests'];
                                    if ($total > 0) {
                                        $success_rate = round(($completed / $total) * 100);
                                    }
                                    echo $success_rate . '%';
                                    ?>
                                </h4>
                                <p class="mb-0">Success Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-warning">
                                    <?php echo round((time() - strtotime($user['created_at'])) / (60 * 60 * 24)); ?>
                                </h4>
                                <p class="mb-0">Days Active</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if ($user['user_type'] === 'donor' && !empty($recent_donations)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-utensils"></i> Recent Donations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_donations as $donation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donation['title']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?></td>
                                        <td><?php echo htmlspecialchars($donation['quantity']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $donation['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $donation['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($donation['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['user_type'] === 'ngo' && !empty($recent_requests)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-truck"></i> Recent Pickup Requests
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Donation</th>
                                        <th>Status</th>
                                        <th>Pickup Date</th>
                                        <th>Requested</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['donation_title']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $request['pickup_date'] ? date('M j, Y', strtotime($request['pickup_date'])) : 'Not specified'; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    
    <?php if ($user['address'] && $user['city'] && $user['state']): ?>
    <script>
        function initMap() {
            const address = "<?php echo htmlspecialchars($user['address'] . ', ' . $user['city'] . ', ' . $user['state']); ?>";
            const geocoder = new google.maps.Geocoder();
            
            geocoder.geocode({ address: address }, function(results, status) {
                if (status === 'OK') {
                    const map = new google.maps.Map(document.getElementById('map'), {
                        zoom: 15,
                        center: results[0].geometry.location
                    });
                    
                    new google.maps.Marker({
                        position: results[0].geometry.location,
                        map: map,
                        title: "<?php echo htmlspecialchars($user['full_name']); ?>"
                    });
                }
            });
        }
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo getGoogleMapsApiKey(); ?>&callback=initMap"></script>
    <?php endif; ?>
</body>
</html>

