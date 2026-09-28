<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/classes/Security.php';

if (isLoggedIn()) {
    switch ($user['user_type']) {
                            case 'admin': redirectTo('admin/dashboard.php'); break;
                            case 'donor': redirectTo('donor/dashboard.php'); break;
                            case 'ngo': redirectTo('ngo/dashboard.php'); break;
                            case 'volunteer': redirectTo('volunteer/dashboard.php'); break;
                            case 'driver': redirectTo('driver/dashboard.php'); break;
                            default: redirectTo('index.php');
                        }
}

$error = '';
$pdo = getDBConnection();
restoreRememberedUser($pdo);
$security = new Security($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request token. Please try again.';
    } else {
        $username = $security->sanitize($_POST['username']);
        $password = $_POST['password'];
        
        // Check rate limit (5 attempts per 15 minutes)
        $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $username;
        if (!$security->checkRateLimit('login', $identifier, 5, 900)) {
            $error = 'Too many login attempts. Please try again later.';
            $security->logSecurityEvent('login_rate_limit', null, $username);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'active') {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['full_name'] = $user['full_name'];
                        rememberUser($pdo, $user, !empty($_POST['remember_me']));
                        
                        // Log successful login
                        $security->logSecurityEvent('login_success', $user['id']);
                        
                        switch ($user['user_type']) {
                            case 'admin': redirectTo('admin/dashboard.php'); break;
                            case 'donor': redirectTo('donor/dashboard.php'); break;
                            case 'ngo': redirectTo('ngo/dashboard.php'); break;
                            case 'volunteer': redirectTo('volunteer/dashboard.php'); break;
                            case 'driver': redirectTo('driver/dashboard.php'); break;
                            default: redirectTo('index.php');
                        }
                    } else {
                        $error = 'Your account is not active. Please contact support.';
                        $security->logSecurityEvent('login_inactive', $user['id']);
                    }
                } else {
                    $error = 'Invalid username or password.';
                    $security->logSecurityEvent('login_failed', null, $username);
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=3" rel="stylesheet">
<link href="assets/css/auth.css?v=5" rel="stylesheet">
</head>
<body class="auth-page bg-light">
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card auth-card mt-5 shadow">
                    <div class="card-header text-center py-3">
                        <h4 class="mb-0">Login</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" autocomplete="username" aria-label="Username" required placeholder="Username">
                                <div class="invalid-feedback">Please enter your username.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" aria-label="Password" required placeholder="Password">
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>

                            <div class="remember-row d-flex justify-content-between align-items-center mb-3">
                                <label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" name="remember_me" value="1"> Remember me</label>
                                <a href="admin/forgot_password.php" class="forgot-link">Forgot password?</a>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <div class="text-center mt-3">

                            <a href="index.php">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html>