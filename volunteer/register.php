<?php
require_once '../includes/config.php';

if (isLoggedIn() && getUserType() === 'volunteer') {
    redirectTo('browse_opportunities.php');
}

$error = '';
$success = '';

$usernameValue = '';
$emailValue = '';
$fullNameValue = '';
$phoneValue = '';
$cityValue = '';
$stateValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameValue = sanitizeInput($_POST['username']);
    $emailValue = sanitizeInput($_POST['email']);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullNameValue = sanitizeInput($_POST['full_name']);
    $phoneValue = sanitizeInput($_POST['phone']);
    $cityValue = sanitizeInput($_POST['city']);
    $stateValue = sanitizeInput($_POST['state']);

    if ($usernameValue === '' || $emailValue === '' || $password === '' || $fullNameValue === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$usernameValue, $emailValue]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_type, full_name, phone, city, state, status) VALUES (?, ?, ?, 'volunteer', ?, ?, ?, ?, 'active')");
                $stmt->execute([$usernameValue, $emailValue, $hashedPassword, $fullNameValue, $phoneValue, $cityValue, $stateValue]);
                $success = 'Registration successful! You can now log in.';
                $usernameValue = '';
                $emailValue = '';
                $fullNameValue = '';
                $phoneValue = '';
                $cityValue = '';
                $stateValue = '';
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
    <title>Volunteer Registration - FoodSave</title>
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
                        <h4 class="mb-1">Volunteer Registration</h4>
                        <small class="fs-6">Join FoodSave as a community volunteer</small>
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
                                    <input type="text" class="form-control" name="username" required placeholder="Username *" value="<?php echo htmlspecialchars($usernameValue); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="email" class="form-control" name="email" required placeholder="Email *" value="<?php echo htmlspecialchars($emailValue); ?>">
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
                                <input type="text" class="form-control" name="full_name" required placeholder="Full Name *" value="<?php echo htmlspecialchars($fullNameValue); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <input type="tel" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($phoneValue); ?>">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo htmlspecialchars($cityValue); ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <input type="text" class="form-control" name="state" placeholder="State" value="<?php echo htmlspecialchars($stateValue); ?>">
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3 mt-2">Register</button>
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
