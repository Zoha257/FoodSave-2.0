<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$message = '';

// Handle donation deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM food_donations WHERE id = ? AND donor_id = ?");
        $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
        $message = 'Donation deleted successfully.';
    } catch (PDOException $e) {
        $message = 'Error deleting donation.';
    }
}

try {
    $pdo = getDBConnection();
    $donor_id = $_SESSION['user_id'];
    
    // Get all donations by this donor
    $stmt = $pdo->prepare("
        SELECT fd.*, 
               COUNT(pr.id) as request_count,
               COUNT(CASE WHEN pr.status = 'pending' THEN 1 END) as pending_requests
        FROM food_donations fd 
        LEFT JOIN pickup_requests pr ON fd.id = pr.donation_id 
        WHERE fd.donor_id = ? 
        GROUP BY fd.id
        ORDER BY fd.created_at DESC
    ");
    $stmt->execute([$donor_id]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Donations - FoodSave</title>
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
                    <h1 class="h2">My Donations</h1>
                    <a href="add_donation.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New Donation
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Expiration Date</th>
                                        <th>Status</th>
                                        <th>Requests</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($donations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No donations found. <a href="add_donation.php">Add your first donation</a></td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($donation['title']); ?></strong>
                                                <?php if ($donation['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($donation['description'], 0, 50)) . (strlen($donation['description']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?></td>
                                            <td><?php echo htmlspecialchars($donation['quantity']); ?></td>
                                            <td>
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
                                                    <?php echo date('M j, Y', $exp_date); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $donation['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $donation['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($donation['request_count'] > 0): ?>
                                                    <span class="badge bg-info"><?php echo $donation['request_count']; ?> total</span>
                                                    <?php if ($donation['pending_requests'] > 0): ?>
                                                        <br><span class="badge bg-warning"><?php echo $donation['pending_requests']; ?> pending</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No requests</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($donation['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="edit_donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($donation['status'] === 'available'): ?>
                                                    <a href="?delete=<?php echo $donation['id']; ?>" class="btn btn-outline-danger" title="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this donation?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo count($donations); ?></h5>
                                <p class="card-text">Total Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <?php echo count(array_filter($donations, function($d) { return $d['status'] === 'available'; })); ?>
                                </h5>
                                <p class="card-text">Available</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <?php echo count(array_filter($donations, function($d) { return $d['status'] === 'requested'; })); ?>
                                </h5>
                                <p class="card-text">Requested</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <?php echo count(array_filter($donations, function($d) { return $d['status'] === 'picked_up'; })); ?>
                                </h5>
                                <p class="card-text">Completed</p>
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

