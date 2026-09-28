<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

// Restrict browser API access to configured origins.
// For a same-origin PHP deployment, leave API_ALLOWED_ORIGINS as the site's origin.
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('API_ALLOWED_ORIGINS') ?: '')));
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'AuthController.php';
require_once 'DonationController.php';
require_once 'PickupController.php';
require_once 'VolunteerController.php';
require_once 'InventoryController.php';
require_once 'DriverController.php';

// Parse the request URI
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
// Strip the actual script directory instead of assuming the project is named FoodSave.
$apiBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($apiBasePath !== '' && strpos($path, $apiBasePath) === 0) {
    $path = substr($path, strlen($apiBasePath));
}
$segments = explode('/', trim($path, '/'));

try {
    // Route the request
    switch ($segments[0]) {
        case 'auth':
            $authController = new AuthController();
            switch ($segments[1] ?? '') {
                case 'login':
                    $authController->login();
                    break;
                case 'register':
                    $authController->register();
                    break;
                case 'refresh':
                    $authController->refreshToken();
                    break;
                default:
                    throw new Exception('Invalid auth endpoint');
            }
            break;
            
        case 'donations':
            $donationController = new DonationController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if (isset($segments[1])) {
                        $donationController->getDonation($segments[1]);
                    } else {
                        $donationController->listDonations();
                    }
                    break;
                case 'POST':
                    $donationController->createDonation();
                    break;
                case 'PUT':
                    if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
                        $donationController->updateStatus($segments[1]);
                    } else {
                        throw new Exception('Invalid donation endpoint');
                    }
                    break;
                default:
                    throw new Exception('Method not allowed');
            }
            break;
            
        case 'pickup':
            $pickupController = new PickupController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $pickupController->listRequests();
                    break;
                case 'POST':
                    $pickupController->requestPickup();
                    break;
                case 'PUT':
                    if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
                        $pickupController->updateRequestStatus($segments[1]);
                    } else {
                        throw new Exception('Invalid pickup endpoint');
                    }
                    break;
                default:
                    throw new Exception('Method not allowed');
            }
            break;
            
        case 'volunteer':
            $volunteerController = new VolunteerController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $volunteerController->listOpportunities();
                    break;
                case 'POST':
                    if (isset($segments[1]) && $segments[1] === 'apply') {
                        $volunteerController->applyForOpportunity();
                    } else {
                        $volunteerController->createOpportunity();
                    }
                    break;
                case 'PUT':
                    if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
                        $volunteerController->updateApplicationStatus($segments[1]);
                    } else {
                        throw new Exception('Invalid volunteer endpoint');
                    }
                    break;
                default:
                    throw new Exception('Method not allowed');
            }
            break;
            
        case 'inventory':
            $inventoryController = new InventoryController();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $inventoryController->summary();
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'driver':
            $driverController = new DriverController();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (isset($segments[1]) && $segments[1] === 'tasks') $driverController->listDeliveryTasks();
                else throw new Exception('Invalid driver endpoint');
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($segments[1]) && $segments[1] === 'tasks' && isset($segments[2]) && $segments[2] === 'accept') {
                $driverController->acceptTask();
            } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($segments[1]) && $segments[1] === 'tasks' && isset($segments[2])) {
                $driverController->updateTaskStatus($segments[2]);
            } else {
                throw new Exception('Invalid driver endpoint');
            }
            break;
            
        default:
            throw new Exception('Invalid endpoint');
    }
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}