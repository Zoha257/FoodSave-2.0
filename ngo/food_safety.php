<?php
require_once '../includes/config.php';
require_once '../includes/classes/Guideline.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'guidelines';

$pdo = getDBConnection();
$guidelineManager = new Guideline($pdo);
$guidelines = $guidelineManager->listAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Safety Guidelines - FoodSave NGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 text-white">Food Safety Guidelines</h1>
                </div>

                <?php if (empty($guidelines)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No published guidelines are available right now.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($guidelines as $guide): ?>
                            <div class="col-lg-6">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title text-">
                                            <?php echo htmlspecialchars($guide['title']); ?>
                                        </h5>
                                        <p class="card-text text-muted small">
                                            Updated <?php echo date('M j, Y', strtotime($guide['updated_at'])); ?>
                                        </p>
                                        <p class="card-text" style="white-space: pre-line;">
                                            <?php echo htmlspecialchars($guide['content']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
