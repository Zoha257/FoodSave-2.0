<?php
if (!isset($activePage)) {
    $activePage = '';
}
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <h5 class="text-white">FoodSave NGO</h5>
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></small>
        </div>

        <ul class="nav flex-column">
            <?php include __DIR__ . '/../../includes/notification_header.php'; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'browse' ? 'active' : ''; ?>" href="browse_donations.php">
                    <i class="fas fa-search"></i> Browse Donations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'inventory' ? 'active' : ''; ?>" href="inventory.php">
                    <i class="fas fa-chart-line"></i> Inventory Tracker
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'requests' ? 'active' : ''; ?>" href="my_requests.php">
                    <i class="fas fa-truck"></i> My Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'volunteers' ? 'active' : ''; ?>" href="manage_volunteers.php">
                    <i class="fas fa-users"></i> Manage Volunteers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'tracking' ? 'active' : ''; ?>" href="delivery_tracking.php">
                    <i class="fas fa-route"></i> Delivery Tracking
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activePage === 'guidelines' ? 'active' : ''; ?>" href="food_safety.php">
                    <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
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
