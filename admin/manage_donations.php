<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$message = '';

// Handle donation actions
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $donation_id = $_GET['id'];
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM food_donations WHERE id = ?");
            $stmt->execute([$donation_id]);
            $message = 'Donation deleted successfully.';
        } elseif ($action === 'mark_expired') {
            $stmt = $pdo->prepare("UPDATE food_donations SET status = 'expired' WHERE id = ?");
            $stmt->execute([$donation_id]);
            $message = 'Donation marked as expired.';
        }
    } catch (PDOException $e) {
        $message = 'Error processing request.';
    }
}

try {
    $pdo = getDBConnection();

    // Automatically mark expired available donations
    $stmt = $pdo->prepare("
        UPDATE food_donations
        SET status = 'expired'
        WHERE status = 'available'
        AND expiration_date < CURDATE()
    ");
    $stmt->execute();
    
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    
    // Build query
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status !== 'all') {
        $where_conditions[] = "fd.status = ?";
        $params[] = $status;
    }
    
    if ($category !== 'all') {
        $where_conditions[] = "fd.category = ?";
        $params[] = $category;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT fd.*, u.full_name as donor_name, u.phone as donor_phone,
               COUNT(pr.id) as request_count
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        LEFT JOIN pickup_requests pr ON fd.id = pr.donation_id 
        WHERE $where_clause
        GROUP BY fd.id
        ORDER BY fd.created_at DESC
    ");
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM food_donations 
        GROUP BY status
    ");
    $stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
    }
    
    // Get expiring donations (next 2 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM food_donations 
        WHERE expiration_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY) 
        AND status = 'available'
    ");
    $expiring_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Donations - FoodSave Admin</title>
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
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_donations.php">
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
                    <h1 class="h2">Manage Donations</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                    <h4><?php echo array_sum($stats); ?></h4>
                                <p class="mb-0">Total Donations</p>
                            </div>
                        </div>
                        </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                    <h4><?php echo $stats['available'] ?? 0; ?></h4>
                                <p class="mb-0">Available</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                    <h4><?php echo $stats['picked_up'] ?? 0; ?></h4>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                    <h4><?php echo $expiring_count; ?></h4>
                                <p class="mb-0">Expiring Soon</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="requested" <?php echo $status === 'requested' ? 'selected' : ''; ?>>Requested</option>
                                    <option value="picked_up" <?php echo $status === 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                    <option value="vegetables" <?php echo $category === 'vegetables' ? 'selected' : ''; ?>>Vegetables</option>
                                    <option value="fruits" <?php echo $category === 'fruits' ? 'selected' : ''; ?>>Fruits</option>
                                    <option value="dairy" <?php echo $category === 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                    <option value="meat" <?php echo $category === 'meat' ? 'selected' : ''; ?>>Meat</option>
                                    <option value="grains" <?php echo $category === 'grains' ? 'selected' : ''; ?>>Grains</option>
                                    <option value="prepared_food" <?php echo $category === 'prepared_food' ? 'selected' : ''; ?>>Prepared Food</option>
                                    <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="manage_donations.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Donations Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Donation</th>
                                        <th>Donor</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Expiration</th>
                                        <th>Status</th>
                                        <th>Requests</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($donations)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No donations found.</td>
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
                                            <td>
                                                <?php echo htmlspecialchars($donation['donor_name']); ?>
                                                <?php if ($donation['donor_phone']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($donation['donor_phone']); ?></small>
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
                                                    <span class="badge bg-info"><?php echo $donation['request_count']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($donation['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view_donation.php?id=<?php echo $donation['id']; ?>" 
                                                       class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($donation['status'] === 'available' && strtotime($donation['expiration_date']) <= strtotime('+1 day')): ?>
                                                        <a href="?action=mark_expired&id=<?php echo $donation['id']; ?>" 
                                                           class="btn btn-warning" title="Mark as Expired">
                                                            <i class="fas fa-clock"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?action=delete&id=<?php echo $donation['id']; ?>" 
                                                       class="btn btn-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this donation?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>

