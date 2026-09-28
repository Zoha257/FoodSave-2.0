<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/classes/Security.php';

$error = '';
$success = '';
$csrfToken = '';
$name = '';
$email = '';
$subject = '';
$message = '';

try {
    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(254) NOT NULL,
        subject VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new', 'read', 'closed') NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact_status_created (status, created_at)
    )");
    $security = new Security($pdo);
    $csrfToken = $security->generateCSRFToken();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nameInput = $_POST['name'] ?? '';
        $emailInput = $_POST['email'] ?? '';
        $subjectInput = $_POST['subject'] ?? '';
        $messageInput = $_POST['message'] ?? '';
        $name = is_string($nameInput) ? trim(strip_tags($nameInput)) : '';
        $email = is_string($emailInput) ? trim($emailInput) : '';
        $subject = is_string($subjectInput) ? trim(strip_tags($subjectInput)) : '';
        $message = is_string($messageInput) ? trim(strip_tags($messageInput)) : '';
        $postedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($postedToken) || !$security->verifyCSRFToken($postedToken)) {
            $error = 'Your session expired. Please try again.';
        } elseif (!empty($_POST['website'])) {
            $success = 'Thanks for contacting FoodSave. Your message has been received.';
        } elseif ($name === '' || $email === '' || $subject === '' || $message === '') {
            $error = 'Please complete all fields.';
        } elseif (strlen($name) > 150 || strlen($email) > 254 || strlen($subject) > 180 || strlen($message) > 10000) {
            $error = 'One or more fields are too long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!$security->checkRateLimit('contact', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 900)) {
            $error = 'Too many messages were sent recently. Please try again later.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $subject, $message]);
            $success = 'Thanks for contacting FoodSave. Your message has been received.';
            $name = $email = $subject = $message = '';
        }
    }
} catch (PDOException $e) {
    error_log('Contact page error: ' . $e->getMessage());
    $error = 'We could not load the contact form right now. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact FoodSave</title>
    <meta name="description" content="Contact the FoodSave team with questions about food donations, pickups, and volunteering.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="assets/css/style.css?v=3" rel="stylesheet">
    <link href="assets/css/landing.css?v=15" rel="stylesheet">
</head>
<body class="home-page">
    <nav class="navbar lp-nav">
        <div class="container">
            <a class="lp-brand" href="index.php"><span class="lp-brand__mark"><i class="fas fa-leaf"></i></span>FoodSave</a>
            <a class="nav-link" href="index.php"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
        </div>
    </nav>

    <main>
        <section class="lp-contact">
            <div class="container">
                <div class="lp-contact__layout">
                    <div class="lp-contact__intro">
                        <span class="lp-eyebrow"><i class="fas fa-message"></i> Contact</span>
                        <h1 class="lp-h2">Talk to the FoodSave team</h1>
                        <p class="lp-muted">Questions about donating food, arranging pickups, or joining as a volunteer? Send us a message.</p>
                        <p class="lp-contact__note">Every good handoff starts with a conversation.</p>
                    </div>

                    <div class="lp-contact__panel">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="lp-contact__form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="lp-contact__honeypot" aria-hidden="true">
                                <label for="website">Leave this field empty</label>
                                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control" type="text" name="name" id="name" maxlength="150" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" type="email" name="email" id="email" maxlength="254" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="subject">Subject</label>
                                <input class="form-control" type="text" name="subject" id="subject" maxlength="180" value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="message">Message</label>
                                <textarea class="form-control" name="message" id="message" rows="5" maxlength="10000" required><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i>Send message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>