<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip_code = sanitizeInput($_POST['zip_code']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        $pdo = getDBConnection();
        
        // Check if email is already used by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = 'Email is already used by another user.';
        } else {
            // Update basic profile information
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $address, $city, $state, $zip_code, $_SESSION['user_id']]);
            
            // Handle password change if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Current password is required to change password.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (password_verify($current_password, $user['password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        $message = 'Profile and password updated successfully.';
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                }
            } else {
                $message = 'Profile updated successfully.';
            }
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
        }
    } catch (PDOException $e) {
        $error = 'Database error. Please try again.';
    }
}

// Get current user data
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_donations FROM food_donations WHERE donor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_donations'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_donations FROM food_donations WHERE donor_id = ? AND status = 'picked_up'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['completed_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['completed_donations'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_requests 
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        WHERE fd.donor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_requests'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FoodSave</title>
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
                            <a class="nav-link active" href="profile.php">
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
                    <h1 class="h2">My Profile</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Statistics -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark"><i class="fas fa-chart-bar"></i> My Impact</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <h3 class="text-success"><?php echo $stats['total_donations']; ?></h3>
                                        <p class="mb-0">Total Donations</p>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h3 class="text-primary"><?php echo $stats['completed_donations']; ?></h3>
                                        <p class="mb-0">Successfully Donated</p>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h3 class="text-info"><?php echo $stats['total_requests']; ?></h3>
                                        <p class="mb-0">Total Requests Received</p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark"><i class="fas fa-info-circle"></i> Account Info</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                <p><strong>User Type:</strong> <span class="badge bg-success">Donor</span></p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                <p class="mb-0"><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0 text-dark"><i class="fas fa-edit"></i> Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="full_name" class="form-label">Full Name / Organization Name *</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?php echo htmlspecialchars($user['city']); ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="state" class="form-label">State</label>
                                            <input type="text" class="form-control" id="state" name="state" 
                                                   value="<?php echo htmlspecialchars($user['state']); ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="zip_code" class="form-label">ZIP Code</label>
                                            <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                                   value="<?php echo htmlspecialchars($user['zip_code']); ?>">
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h6 class="mb-3">Change Password (Optional)</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Update Profile
                                        </button>
                                    </div>
                                </form>
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
        // Password validation
        document.getElementById('new_password').addEventListener('input', function() {
            const currentPassword = document.getElementById('current_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (this.value) {
                currentPassword.required = true;
                confirmPassword.required = true;
            } else {
                currentPassword.required = false;
                confirmPassword.required = false;
            }
        });
        
        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            
            if (this.value !== newPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

