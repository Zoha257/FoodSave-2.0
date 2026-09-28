<?php
require_once '../includes/config.php';
$pdo = getDBConnection();
restoreRememberedUser($pdo, 'donor');

if (isLoggedIn() && getUserType() === 'donor') {
    redirectTo('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, user_type, full_name, status FROM users WHERE username = ? AND user_type = 'donor'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    rememberUser($pdo, $user, !empty($_POST['remember_me']));
                    redirectTo('dashboard.php');
                } else {
                    $error = 'Your account is not active. Please contact administrator.';
                }
            } else {
                $error = 'Invalid username or password.';
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
    <title>Donor Login - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
<link href="../assets/css/auth.css?v=5" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="auth-container">
                    <h2><i class="fas fa-hand-holding-heart me-2"></i>Donor Login</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                        
                        <form method="POST" class="mb-4">
                            <div class="mb-4">
                                <input type="text" class="form-control" id="username" name="username" autocomplete="username" aria-label="Username" required placeholder="Username">
                            </div>
                            <div class="mb-4">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" aria-label="Password" required placeholder="Password">
                            </div>
                            <div class="remember-row d-flex justify-content-between align-items-center mb-3">
                                <label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" name="remember_me" value="1"> Remember me</label>
                                <a href="../admin/forgot_password.php" class="forgot-link">Forgot password?</a>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-sign-in-alt me-2"></i>Login</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p><i class="fas fa-user-plus me-2"></i>Don't have an account? <a href="register.php">Register here</a></p>
                            <a href="../index.php" class="d-block mt-2"><i class="fas fa-home me-2"></i>Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const icon = document.querySelector(`[data-for="${inputId}"]`);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

