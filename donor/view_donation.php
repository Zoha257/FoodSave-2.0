<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$donation_id = $_GET['id'] ?? null;
if (!$donation_id || !is_numeric($donation_id)) {
    redirectTo('my_donations.php');
}

try {
    $pdo = getDBConnection();
    
    // Get donation details (only if it belongs to this donor)
    $stmt = $pdo->prepare("SELECT * FROM food_donations WHERE id = ? AND donor_id = ?");
    $stmt->execute([$donation_id, $_SESSION['user_id']]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donation) {
        redirectTo('my_donations.php');
    }
    
    // Get pickup requests for this donation
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as ngo_name, u.phone as ngo_phone, 
               u.email as ngo_email, u.address as ngo_address, 
               u.city as ngo_city, u.state as ngo_state
        FROM pickup_requests pr 
        JOIN users u ON pr.ngo_id = u.id 
        WHERE pr.donation_id = ? 
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$donation_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Donation - FoodSave</title>
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
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_donation.php">
                                <i class="fas fa-plus"></i> Add Donation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my_donations.php">
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
                    <h1 class="h2">Donation Details</h1>
                    <div>
                        <?php if ($donation['status'] === 'available'): ?>
                            <a href="edit_donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Donation
                            </a>
                        <?php endif; ?>
                        <a href="my_donations.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to My Donations
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Donation Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-utensils"></i> Donation Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Title:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['title']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Description:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['description'] ?: 'No description provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Category:</strong></div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Quantity:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['quantity']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Expiration Date:</strong></div>
                                    <div class="col-sm-9">
                                        <?php 
                                        $exp_date = strtotime($donation['expiration_date']);
                                        $today = strtotime('today');
                                        $class = '';
                                        if ($exp_date <= $today) {
                                            $class = 'text-danger';
                                        } elseif ($exp_date <= strtotime('+2 days')) {
                                            $class = 'text-warning';
                                        }
                                        ?>
                                        <span class="<?php echo $class; ?>">
                                            <?php echo date('F j, Y', $exp_date); ?>
                                            <?php if ($exp_date <= $today): ?>
                                                <small>(Expired)</small>
                                            <?php elseif ($exp_date <= strtotime('+2 days')): ?>
                                                <small>(Expires Soon)</small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Status:</strong></div>
                                    <div class="col-sm-9">
                                        <span class="badge status-<?php echo $donation['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $donation['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Created:</strong></div>
                                    <div class="col-sm-9"><?php echo date('F j, Y g:i A', strtotime($donation['created_at'])); ?></div>
                                </div>
                                <?php if ($donation['updated_at'] && $donation['updated_at'] !== $donation['created_at']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Last Updated:</strong></div>
                                    <div class="col-sm-9"><?php echo date('F j, Y g:i A', strtotime($donation['updated_at'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($donation['status'] === 'available'): ?>
                                        <a href="edit_donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit Donation
                                        </a>
                                        <a href="my_donations.php?delete=<?php echo $donation['id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this donation?')">
                                            <i class="fas fa-trash"></i> Delete Donation
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (count($requests) > 0): ?>
                                        <a href="pickup_requests.php" class="btn btn-info">
                                            <i class="fas fa-truck"></i> Manage Pickup Requests
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="add_donation.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add New Donation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status & Statistics -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-bar"></i> Statistics
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-info"><?php echo count($requests); ?></h4>
                                        <small>Total Requests</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-warning">
                                            <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'pending'; })); ?>
                                        </h4>
                                        <small>Pending</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-success">
                                            <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'approved'; })); ?>
                                        </h4>
                                        <small>Approved</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-primary">
                                            <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'completed'; })); ?>
                                        </h4>
                                        <small>Completed</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Time Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-clock"></i> Time Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Days until expiration:</strong>
                                    <?php 
                                    $days_until_exp = ceil((strtotime($donation['expiration_date']) - time()) / (60 * 60 * 24));
                                    if ($days_until_exp < 0) {
                                        echo '<span class="text-danger">Expired ' . abs($days_until_exp) . ' days ago</span>';
                                    } elseif ($days_until_exp <= 2) {
                                        echo '<span class="text-warning">' . $days_until_exp . ' days</span>';
                                    } else {
                                        echo '<span class="text-success">' . $days_until_exp . ' days</span>';
                                    }
                                    ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Days since posted:</strong>
                                    <?php echo ceil((time() - strtotime($donation['created_at'])) / (60 * 60 * 24)); ?> days
                                </div>
                                <?php if ($donation['updated_at'] && $donation['updated_at'] !== $donation['created_at']): ?>
                                <div class="mb-2">
                                    <strong>Last updated:</strong>
                                    <?php echo ceil((time() - strtotime($donation['updated_at'])) / (60 * 60 * 24)); ?> days ago
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pickup Requests -->
                <?php if (!empty($requests)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-truck"></i> Pickup Requests (<?php echo count($requests); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($requests as $request): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($request['ngo_name']); ?></h6>
                                        <span class="badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <small>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['ngo_email']); ?><br>
                                                <?php if ($request['ngo_phone']): ?>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['ngo_phone']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($request['ngo_address']): ?>
                                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($request['ngo_city'] . ', ' . $request['ngo_state']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($request['pickup_date']): ?>
                                            <div class="mb-2">
                                                <strong>Requested Pickup:</strong><br>
                                                <small>
                                                    <?php echo date('M j, Y', strtotime($request['pickup_date'])); ?>
                                                    <?php if ($request['pickup_time']): ?>
                                                        at <?php echo date('g:i A', strtotime($request['pickup_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['request_message']): ?>
                                            <div class="mb-2">
                                                <strong>Message:</strong><br>
                                                <small><?php echo htmlspecialchars($request['request_message']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <small class="text-muted">
                                            Requested on <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                        </small>
                                        
                                        <?php if ($request['status'] === 'pending'): ?>
                                        <div class="mt-2">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <a href="pickup_requests.php?action=reject&id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to reject this request?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                                <a href="pickup_requests.php?action=approve&id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Are you sure you want to approve this request?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle"></i> No pickup requests have been made for this donation yet.
                    <?php if ($donation['status'] === 'available'): ?>
                        NGOs can browse and request pickup for available donations.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>

