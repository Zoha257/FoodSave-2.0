<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$message = '';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = $_GET['id'];
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = 'User activated successfully.';
        } elseif ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = 'User deactivated successfully.';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'");
            $stmt->execute([$user_id]);
            $message = 'User deleted successfully.';
        }
    } catch (PDOException $e) {
        $message = 'Error processing request.';
    }
}

try {
    $pdo = getDBConnection();
    
    // Get filter parameters
    $user_type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    
    // Build query
    $where_conditions = ["user_type != 'admin'"];
    if (!in_array($user_type, ['all','donor','ngo','volunteer','driver'], true)) $user_type = 'all';
    $params = [];
    
    if ($user_type !== 'all') {
        $where_conditions[] = "user_type = ?";
        $params[] = $user_type;
    }
    
    if ($status !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(fd.id) as donation_count,
               COUNT(pr.id) as request_count
        FROM users u 
        LEFT JOIN food_donations fd ON u.id = fd.donor_id 
        LEFT JOIN pickup_requests pr ON u.id = pr.ngo_id 
        WHERE $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT user_type, status, COUNT(*) as count FROM users WHERE user_type != 'admin' GROUP BY user_type, status");
    $stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['user_type']][$row['status']] = $row['count'];
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
    <title>Manage Users - FoodSave Admin</title>
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
                    <h1 class="h2">Manage Users</h1>
                    <a href="user_form.php" class="btn btn-success"><i class="fas fa-user-plus me-1"></i>Add Account</a>
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
                                <h4 style="color:white;"><?php echo ($stats['donor']['active'] ?? 0) + ($stats['donor']['pending'] ?? 0) + ($stats['donor']['inactive'] ?? 0); ?></h4>
                                <p class="mb-0">Total Donors</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4 style="color:white;"><?php echo ($stats['ngo']['active'] ?? 0) + ($stats['ngo']['pending'] ?? 0) + ($stats['ngo']['inactive'] ?? 0); ?></h4>
                                <p class="mb-0">Total NGOs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4 style="color:white;"><?php echo ($stats['donor']['pending'] ?? 0) + ($stats['ngo']['pending'] ?? 0); ?></h4>
                                <p class="mb-0">Pending Approval</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h4 style="color:white;"><?php echo ($stats['donor']['active'] ?? 0) + ($stats['ngo']['active'] ?? 0); ?></h4>
                                <p class="mb-0">Active Users</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="type" class="form-label">User Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="all" <?php echo $user_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="donor" <?php echo $user_type === 'donor' ? 'selected' : ''; ?>>Donors</option>
                                    <option value="ngo" <?php echo $user_type === 'ngo' ? 'selected' : ''; ?>>NGOs</option>
                                    <option value="driver" <?php echo $user_type === 'driver' ? 'selected' : ''; ?>>Drivers</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="manage_users.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Contact</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Activity</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No users found.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                                <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['user_type'] === 'donor' ? 'primary' : ($user['user_type'] === 'driver' ? 'dark' : 'info'); ?>">
                                                    <?php echo ucfirst($user['user_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['email']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['phone'] ?: 'No phone'); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($user['city'] || $user['state']): ?>
                                                    <?php echo htmlspecialchars($user['city']); ?><?php echo $user['city'] && $user['state'] ? ', ' : ''; ?><?php echo htmlspecialchars($user['state']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not provided</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo $user['status']; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['user_type'] === 'donor'): ?>
                                                    <small><?php echo $user['donation_count']; ?> donations</small>
                                                <?php else: ?>
                                                    <small><?php echo $user['request_count']; ?> requests</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($user['status'] === 'pending'): ?>
                                                        <a href="manage_users.php?action=activate&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-success" title="Approve"
                                                           onclick="return confirm('Are you sure you want to approve this user?');">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php elseif ($user['status'] === 'active'): ?>
                                                        <a href="manage_users.php?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-warning" title="Deactivate"
                                                           onclick="return confirm('Are you sure you want to deactivate this user?');">
                                                            <i class="fas fa-pause"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="manage_users.php?action=activate&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-success" title="Activate"
                                                           onclick="return confirm('Are you sure you want to activate this user?');">
                                                            <i class="fas fa-play"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="view_user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <a href="user_form.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this user?')">
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

