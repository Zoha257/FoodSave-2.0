<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

try {
    $pdo = getDBConnection();
    
    // Get statistics
    $stats = [];
    
    // Total users by type
    $stmt = $pdo->query("SELECT user_type, COUNT(*) as count FROM users WHERE user_type != 'admin' GROUP BY user_type");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['user_type']] = $row['count'];
    }
    
    // Total donations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM food_donations");
    $stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending requests
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pickup_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent donations
    $stmt = $pdo->query("
        SELECT fd.*, u.full_name as donor_name 
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        ORDER BY fd.created_at DESC 
        LIMIT 10
    ");
    $recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending NGO registrations
    $stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'ngo' AND status = 'pending' ORDER BY created_at DESC");
    $pending_ngos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FoodSave</title>
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
                        <!-- Include notification header -->
                        <?php include '../includes/notification_header.php'; ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="ai_dashboard.php">
                                <i class="fas fa-robot"></i> AI Research
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="ai_matching.php">
                                <i class="fas fa-route"></i> Smart Matching
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
                    <h1 class="h2">Dashboard</h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-store fa-2x mb-2"></i>
                                    <h4><?php echo $stats['donor'] ?? 0; ?></h4>
                                <p class="mb-0">Total Donors</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-hands-helping fa-2x mb-2"></i>
                                    <h4><?php echo $stats['ngo'] ?? 0; ?></h4>
                                <p class="mb-0">Total NGOs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-utensils fa-2x mb-2"></i>
                                    <h4><?php echo $stats['total_donations'] ?? 0; ?></h4>
                                <p class="mb-0">Total Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4><?php echo $stats['pending_requests'] ?? 0; ?></h4>
                                <p class="mb-0">Pending Requests</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Donations -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Recent Food Donations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Donor</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donations as $donation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($donation['title']); ?></td>
                                                <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                                <td><?php echo ucfirst($donation['category']); ?></td>
                                                <td>
                                                    <span class="badge status-<?php echo $donation['status']; ?>">
                                                        <?php echo ucfirst($donation['status']); ?>
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
                    </div>

                    <!-- Pending NGO Approvals -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Pending NGO Approvals</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_ngos)): ?>
                                    <p class="text-muted">No pending approvals</p>
                                <?php else: ?>
                                    <?php foreach ($pending_ngos as $ngo): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ngo['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($ngo['email']); ?></small>
                                        <div class="mt-1">
                                            <a href="approve_ngo.php?id=<?php echo $ngo['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                                            <a href="approve_ngo.php?id=<?php echo $ngo['id']; ?>&action=reject" class="btn btn-sm btn-danger">Reject</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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

