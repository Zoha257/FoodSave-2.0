<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

try {
    $pdo = getDBConnection();
    $donor_id = $_SESSION['user_id'];
    
    // Get donor statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_donations FROM food_donations WHERE donor_id = ?");
    $stmt->execute([$donor_id]);
    $total_donations = $stmt->fetch(PDO::FETCH_ASSOC)['total_donations'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_donations FROM food_donations WHERE donor_id = ? AND status = 'available'");
    $stmt->execute([$donor_id]);
    $active_donations = $stmt->fetch(PDO::FETCH_ASSOC)['active_donations'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_donations FROM food_donations WHERE donor_id = ? AND status = 'picked_up'");
    $stmt->execute([$donor_id]);
    $completed_donations = $stmt->fetch(PDO::FETCH_ASSOC)['completed_donations'];
    
    // Get pending requests
    $stmt = $pdo->prepare("
        SELECT pr.*, fd.title, u.full_name as ngo_name 
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON pr.ngo_id = u.id 
        WHERE fd.donor_id = ? AND pr.status = 'pending'
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$donor_id]);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent donations
    $stmt = $pdo->prepare("
        SELECT * FROM food_donations 
        WHERE donor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$donor_id]);
    $recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - FoodSave</title>
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
                        <h5 class="text-white">FoodSave Donor</h5>
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
                            <a class="nav-link" href="add_donation.php">
                                <i class="fas fa-plus"></i> Add Donation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_donations.php">
                                <i class="fas fa-utensils"></i> My Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pickup_requests.php">
                                <i class="fas fa-truck"></i> Pickup Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="food_safety.php">
                                <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> Profile
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
                    <a href="add_donation.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New Donation
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-utensils fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $total_donations; ?></h4>
                                <p class="mb-0">Total Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $active_donations; ?></h4>
                                <p class="mb-0">Active Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $completed_donations; ?></h4>
                                <p class="mb-0">Completed Donations</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Pending Pickup Requests -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Pending Pickup Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_requests)): ?>
                                    <p class="text-muted">No pending pickup requests</p>
                                <?php else: ?>
                                    <?php foreach ($pending_requests as $request): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['title']); ?></h6>
                                        <small class="text-muted">Requested by: <?php echo htmlspecialchars($request['ngo_name']); ?></small>
                                        <p class="small mb-1"><?php echo htmlspecialchars($request['request_message']); ?></p>
                                        <div class="mt-1">
                                            <a href="pickup_requests.php?action=approve&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success">
                                                Approve & View
                                            </a>
                                            <a href="pickup_requests.php?action=reject&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger">
                                                Reject & View
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Donations -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark">Recent Donations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donations as $donation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($donation['title']); ?></td>
                                                <td>
                                                    <span class="badge status-<?php echo $donation['status']; ?>">
                                                        <?php echo ucfirst($donation['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j', strtotime($donation['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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

