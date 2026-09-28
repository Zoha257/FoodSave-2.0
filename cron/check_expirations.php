<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/classes/Notification.php');

try {
    $pdo = getDBConnection();
    $notification = new Notification($pdo);
    
    // Check for expiring donations and create notifications
    $notification->checkAndCreateExpirationAlerts();
    
    // Update expired donations status
    $stmt = $pdo->prepare("
        UPDATE food_donations 
        SET status = 'expired' 
        WHERE status = 'available' 
        AND expiration_date < NOW()
    ");
    $stmt->execute();
    
    echo "Expiration check completed successfully\n";
} catch (Exception $e) {
    error_log("Error in expiration check cron: " . $e->getMessage());
    echo "Error in expiration check: " . $e->getMessage() . "\n";
}