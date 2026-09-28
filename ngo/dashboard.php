<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'dashboard';

try {
    $pdo = getDBConnection();
    $ngo_id = $_SESSION['user_id'];
    
    // Get NGO statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_requests FROM pickup_requests WHERE ngo_id = ?");
    $stmt->execute([$ngo_id]);
    $total_requests = $stmt->fetch(PDO::FETCH_ASSOC)['total_requests'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests FROM pickup_requests WHERE ngo_id = ? AND status = 'pending'");
    $stmt->execute([$ngo_id]);
    $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_requests FROM pickup_requests WHERE ngo_id = ? AND status = 'completed'");
    $stmt->execute([$ngo_id]);
    $completed_requests = $stmt->fetch(PDO::FETCH_ASSOC)['completed_requests'];
    
    // Get available donations count
    $stmt = $pdo->query("SELECT COUNT(*) as available_donations FROM food_donations WHERE status = 'available'");
    $available_donations = $stmt->fetch(PDO::FETCH_ASSOC)['available_donations'];
    
    // Get recent available donations
    $stmt = $pdo->query("
        SELECT fd.*, u.full_name as donor_name 
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        WHERE fd.status = 'available' 
        ORDER BY fd.created_at DESC 
        LIMIT 10
    ");
    $recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get my recent requests
    $stmt = $pdo->prepare("
        SELECT pr.*, fd.title, u.full_name as donor_name 
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON fd.donor_id = u.id 
        WHERE pr.ngo_id = ? 
        ORDER BY pr.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$ngo_id]);
    $my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Dashboard - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <a href="browse_donations.php" class="btn btn-success">
                        <i class="fas fa-search"></i> Browse Donations
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-utensils fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $available_donations; ?></h4>
                                <p class="mb-0">Available Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-paper-plane fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $total_requests; ?></h4>
                                <p class="mb-0">Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $pending_requests; ?></h4>
                                <p class="mb-0">Pending Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4 style="color:white;"><?php echo $completed_requests; ?></h4>
                                <p class="mb-0">Completed Requests</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Available Donations -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-black">Recent Available Donations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Donor</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Expires</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donations as $donation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($donation['title']); ?></td>
                                                <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                                <td><?php echo ucfirst($donation['category']); ?></td>
                                                <td><?php echo htmlspecialchars($donation['quantity']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($donation['expiration_date'])); ?></td>
                                                <td>
                                                    <a href="request_pickup.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-success">Request</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Recent Requests -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-black">My Recent Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_requests)): ?>
                                    <p class="text-muted">No requests yet</p>
                                <?php else: ?>
                                    <?php foreach ($my_requests as $request): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['title']); ?></h6>
                                        <small class="text-muted">From: <?php echo htmlspecialchars($request['donor_name']); ?></small>
                                        <div class="mt-1">
                                            <span class="badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
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

