<?php
require_once '../includes/config.php';
require_once '../includes/classes/VolunteerOpportunity.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse_opportunities.php');
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'volunteer') {
    $_SESSION['error'] = 'Please login to apply for volunteer opportunities.';
    header('Location: login.php');
    exit();
}

// Get opportunity ID
$opportunityId = $_POST['opportunity_id'] ?? null;
if (!$opportunityId) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: browse_opportunities.php');
    exit();
}

// Initialize VolunteerOpportunity class
$volunteerOpp = new VolunteerOpportunity($pdo);

try {
    // Apply for the opportunity
    $result = $volunteerOpp->apply($opportunityId, $_SESSION['user_id']);
    
    if ($result) {
        $_SESSION['success'] = 'Application submitted successfully!';
        header('Location: my_activities.php');
    } else {
        $_SESSION['error'] = 'Error submitting application.';
        header('Location: browse_opportunities.php');
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: browse_opportunities.php');
}
exit();