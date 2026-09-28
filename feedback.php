<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/classes/Feedback.php';

if (!isLoggedIn()) {
    redirectTo('login.php');
}

$error = '';
$success = '';
$pickupRequestId = $_GET['request_id'] ?? null;

if (!$pickupRequestId) {
    redirectTo('dashboard.php');
}

try {
    $pdo = getDBConnection();
    $feedback = new Feedback($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rating = (int)$_POST['rating'];
        $comments = sanitizeInput($_POST['comments']);
        $feedbackType = getUserType(); // 'donor' or 'ngo'
        
        if ($rating < 1 || $rating > 5) {
            $error = 'Invalid rating value.';
        } else {
            if ($feedback->create($pickupRequestId, $rating, $comments, $feedbackType)) {
                $success = 'Thank you for your feedback!';
            } else {
                $error = 'Error submitting feedback.';
            }
        }
    }
    
    // Get pickup request details
    $stmt = $pdo->prepare("
        SELECT pr.*, fd.title as donation_title, u.full_name as donor_name 
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON fd.donor_id = u.id 
        WHERE pr.id = ?
    ");
    $stmt->execute([$pickupRequestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        redirectTo('dashboard.php');
    }
    
} catch (Exception $e) {
    $error = 'Server error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="assets/css/style.css?v=3" rel="stylesheet">
    <style>
    .rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }
    .rating > input {
        display: none;
    }
    .rating > label {
        position: relative;
        width: 1.1em;
        font-size: 2.5rem;
        color: #FFD700;
        cursor: pointer;
    }
    .rating > label::before {
        content: "\2605";
        position: absolute;
        opacity: 0;
    }
    .rating > label:hover:before,
    .rating > label:hover ~ label:before {
        opacity: 1 !important;
    }
    .rating > input:checked ~ label:before {
        opacity: 1;
    }
    .rating:hover > input:checked ~ label:before {
        opacity: 0.4;
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mt-5 shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Submit Feedback</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <h5>Donation Details</h5>
                                <p class="mb-1"><strong>Donation:</strong> <?php echo htmlspecialchars($request['donation_title']); ?></p>
                                <p class="mb-1"><strong>Donor:</strong> <?php echo htmlspecialchars($request['donor_name']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M j, Y', strtotime($request['created_at'])); ?></p>
                            </div>
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="form-label">Rating</label>
                                    <div class="rating">
                                        <input type="radio" name="rating" value="5" id="5" required>
                                        <label for="5">☆</label>
                                        <input type="radio" name="rating" value="4" id="4">
                                        <label for="4">☆</label>
                                        <input type="radio" name="rating" value="3" id="3">
                                        <label for="3">☆</label>
                                        <input type="radio" name="rating" value="2" id="2">
                                        <label for="2">☆</label>
                                        <input type="radio" name="rating" value="1" id="1">
                                        <label for="1">☆</label>
                                    </div>
                                    <div class="invalid-feedback">Please select a rating.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="comments" class="form-label">Comments</label>
                                    <textarea class="form-control" name="comments" rows="4" required></textarea>
                                    <div class="invalid-feedback">Please provide your feedback.</div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                    <a href="dashboard.php" class="btn btn-link">Back to Dashboard</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
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