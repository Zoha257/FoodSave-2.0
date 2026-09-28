<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Guideline.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$pdo = getDBConnection();
$guidelineManager = new Guideline($pdo);

$message = '';
$error = '';
$editing = null;

// Handle deletion
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    if ($guidelineManager->delete((int) $_GET['delete'])) {
        $message = 'Guideline removed successfully.';
    } else {
        $error = 'Failed to remove guideline. Please try again.';
    }
}

// Load guideline for editing if requested
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editing = $guidelineManager->find((int) $_GET['edit']);
    if (!$editing) {
        $error = 'Guideline not found or already removed.';
    }
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $isActive = isset($_POST['is_active']);
    $guidelineId = isset($_POST['guideline_id']) && ctype_digit($_POST['guideline_id']) ? (int) $_POST['guideline_id'] : null;

    if ($title === '' || $content === '') {
        $error = 'Title and guideline content are both required.';
    } else {
        if ($guidelineId) {
            if ($guidelineManager->update($guidelineId, $title, $content, $isActive)) {
                $message = 'Guideline updated successfully.';
                $editing = null;
            } else {
                $error = 'Failed to update guideline. Please try again.';
            }
        } else {
            if ($guidelineManager->create($title, $content, $isActive)) {
                $message = 'Guideline created successfully.';
            } else {
                $error = 'Failed to save guideline. Please try again.';
            }
        }
    }
}

$guidelines = $guidelineManager->listAll(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Safety Guidelines - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">FoodSave Admin</h5>
                        <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_donations.php">
                                <i class="fas fa-utensils"></i> Manage Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="guidelines.php">
                                <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Food Safety Guidelines</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3 text-dark"><?php echo $editing ? 'Edit Guideline' : 'Create New Guideline'; ?></h5>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="guideline_id" value="<?php echo $editing['id'] ?? ''; ?>">
                            <div class="col-12">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label for="content" class="form-label">Guideline Details</label>
                                <textarea class="form-control" id="content" name="content" rows="5" required><?php echo htmlspecialchars($editing['content'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12 form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?php echo !isset($editing['is_active']) || (int)($editing['is_active']) === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Publish guideline immediately
                                </label>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?php echo $editing ? 'Update Guideline' : 'Save Guideline'; ?>
                                </button>
                                <?php if ($editing): ?>
                                    <a href="guidelines.php" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">Published Guidelines</h5>
                        <span class="text-muted small">Manage all safety guidance shared with donors</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($guidelines)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>No guidelines published yet. Create one using the form above.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($guidelines as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <div class="text-muted small mt-1">Last updated <?php echo date('M j, Y g:i A', strtotime($item['updated_at'])); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo (int)$item['is_active'] === 1 ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo (int)$item['is_active'] === 1 ? 'Published' : 'Draft'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($item['updated_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="guidelines.php?edit=<?php echo $item['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="guidelines.php?delete=<?php echo $item['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this guideline? This action cannot be undone.');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
