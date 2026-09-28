<?php
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config.php';
}

if (!function_exists('timeAgo')) {
    require_once __DIR__ . '/helpers.php';
}

require_once __DIR__ . '/classes/Notification.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'] ?? null;
$notificationSystem = new Notification($pdo);
$unreadNotifications = $userId ? $notificationSystem->getUnreadNotifications($userId) : [];
$notificationCount = count($unreadNotifications);
?>

<!-- Notification Bell Icon -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span class="fs-nav-label">Notifications</span>
        <?php if ($notificationCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $notificationCount; ?>
                <span class="visually-hidden">unread notifications</span>
            </span>
        <?php endif; ?>
    </a>
    <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" style="max-height: 420px; overflow-y: auto;">
        <h6 class="dropdown-header">Notifications</h6>
        <?php if ($notificationCount > 0): ?>
            <?php foreach ($unreadNotifications as $notification): ?>
                <a class="dropdown-item notification-item" href="#" 
                   data-notification-id="<?php echo $notification['id']; ?>"
                   onclick="markNotificationRead(<?php echo $notification['id']; ?>, '<?php echo $notification['related_to']; ?>', <?php echo $notification['related_id']; ?>)">
                    <div class="d-flex align-items-center">
                        <div class="notification-icon me-3">
                            <?php
                            $iconClass = 'text-info';
                            $icon = 'info-circle';
                            
                            switch ($notification['type']) {
                                case 'alert':
                                    $iconClass = 'text-danger';
                                    $icon = 'exclamation-circle';
                                    break;
                                case 'warning':
                                    $iconClass = 'text-warning';
                                    $icon = 'exclamation-triangle';
                                    break;
                                case 'success':
                                    $iconClass = 'text-success';
                                    $icon = 'check-circle';
                                    break;
                            }
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> fa-lg <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-text small text-muted"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <div class="notification-time small text-muted">
                                <?php echo timeAgo($notification['created_at']); ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-center small text-muted" href="#" onclick="markAllNotificationsRead()">
                Mark all as read
            </a>
        <?php else: ?>
            <div class="dropdown-item text-center text-muted">
                No new notifications
            </div>
        <?php endif; ?>
    </div>
</li>

<script>
function markNotificationRead(notificationId, relatedTo, relatedId) {
    // Send AJAX request to mark notification as read
    fetch('../includes/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Handle redirect based on notification type
            switch (relatedTo) {
                case 'donation':
                    window.location.href = `view_donation.php?id=${relatedId}`;
                    break;
                case 'pickup':
                    window.location.href = `pickup_requests.php?id=${relatedId}`;
                    break;
                case 'expiration':
                    window.location.href = `browse_donations.php?highlight=${relatedId}`;
                    break;
                default:
                    // Refresh the notifications
                    location.reload();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsRead() {
    fetch('../includes/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Check for new notifications every minute
setInterval(() => {
    fetch('../includes/check_notifications.php')
    .then(response => response.json())
    .then(data => {
        if (data.count > 0) {
            // Update notification badge
            const badge = document.querySelector('#notificationDropdown .badge');
            if (badge) {
                badge.textContent = data.count;
            } else {
                // Create new badge if it doesn't exist
                const span = document.createElement('span');
                span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                span.textContent = data.count;
                document.querySelector('#notificationDropdown').appendChild(span);
            }
        }
    });
}, 60000);
</script>
