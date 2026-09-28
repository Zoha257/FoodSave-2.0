<?php
require_once(__DIR__ . '/../includes/config.php');
$message = '';
$show_form = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reset) {
            $show_form = true;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'];
                $confirm = $_POST['confirm_password'];
                if (strlen($password) < 6) {
                    $message = '<div class="alert alert-danger">Password must be at least 6 characters.</div>';
                } elseif ($password !== $confirm) {
                    $message = '<div class="alert alert-danger">Passwords do not match.</div>';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashed, $reset['email']]);
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
                    $message = '<div class="alert alert-success">Password updated! <a href="login.php">Login</a></div>';
                    $show_form = false;
                }
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid or expired token.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
    }
} else {
    $message = '<div class="alert alert-danger">No token provided.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
    <link href="../assets/css/auth.css?v=5" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <h2>Reset Password</h2>
        <?php if ($message) echo $message; ?>
        <?php if ($show_form): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Reset Password</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-3">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
