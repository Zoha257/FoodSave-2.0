<?php
if (!isset($activePage)) {
    $activePage = '';
}
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <h5 class="text-white">FoodSave Volunteer</h5>
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Volunteer'); ?></small>
        </div>
        <ul class="nav flex-column">
            <?php include __DIR__ . '/../../includes/notification_header.php'; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'browse' ? 'active' : ''; ?>" href="browse_opportunities.php">
                    <i class="fas fa-search"></i> Find Opportunities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'deliveries' ? 'active' : ''; ?>" href="active_deliveries.php">
                    <i class="fas fa-route"></i> Active Deliveries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'activities' ? 'active' : ''; ?>" href="my_activities.php">
                    <i class="fas fa-hands-helping"></i> My Activities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'profile' ? 'active' : ''; ?>" href="profile.php">
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
