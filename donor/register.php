<?php
require_once '../includes/config.php';

if (isLoggedIn()) {
    redirectTo('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip_code = sanitizeInput($_POST['zip_code']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, user_type, full_name, phone, address, city, state, zip_code, status) 
                    VALUES (?, ?, ?, 'donor', ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address, $city, $state, $zip_code]);
                
                $success = 'Registration successful! You can now login.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
    <link href="../assets/css/auth.css?v=5" rel="stylesheet">
</head>
<body class="foodsave-registration">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card mt-5 mb-4 shadow-lg">
                    <div class="card-header py-3">
                        <h4 class="mb-1">Donor Registration</h4>
                        <small class="fs-6">Register as a Food Donor (Restaurant, Grocery Store, Individual)</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <input type="text" class="form-control" id="username" name="username" required placeholder="Username *">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="Email *">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Password *">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm Password *">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Full Name / Organization Name *">
                            </div>
                            
                            <div class="mb-4">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number">
                            </div>
                            
                            <div class="mb-4">
                                <textarea class="form-control" id="address" name="address" rows="3" placeholder="Address"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <input type="text" class="form-control" id="city" name="city" placeholder="City">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <input type="text" class="form-control" id="state" name="state" placeholder="State">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <input type="text" class="form-control" id="zip_code" name="zip_code" placeholder="ZIP Code">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                            
                            <button type="submit" class="btn btn-success w-100 py-3 mt-4">Register</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <a href="../index.php" class="text-dark">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

