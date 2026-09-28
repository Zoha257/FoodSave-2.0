<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Driver.php';
require_once __DIR__ . '/../includes/classes/Notification.php';

if (!isLoggedIn() || getUserType() !== 'ngo') redirectTo('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirectTo('my_requests.php');

$pickupRequestId=(int)($_POST['pickup_request_id']??0);
$driverUserId=(int)($_POST['driver_user_id']??0);
$pickupTime=trim($_POST['pickup_time']??'');
if ($pickupRequestId<=0 || $driverUserId<=0) { $_SESSION['error_message']='Select a pickup request and driver.'; redirectTo('my_requests.php'); }

try {
    $pdo=getDBConnection();
    $stmt=$pdo->prepare("SELECT id,status FROM pickup_requests WHERE id=? AND ngo_id=?");
    $stmt->execute([$pickupRequestId,$_SESSION['user_id']]);
    $request=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) { $_SESSION['error_message']='Invalid pickup request.'; redirectTo('my_requests.php'); }
    $dt=null;
    if ($pickupTime!=='') { $dt=DateTime::createFromFormat('Y-m-d\\TH:i',$pickupTime); if(!$dt){$_SESSION['error_message']='Invalid pickup time.';redirectTo('my_requests.php');} $pickupTime=$dt->format('Y-m-d H:i:s'); }
    $driver=new Driver($pdo);
    $driver->assignPickup($pickupRequestId,$driverUserId,$pickupTime);
    $notification=new Notification($pdo);
    $notification->create($driverUserId,'New Delivery Assignment','A food pickup has been assigned to you. Please open your delivery dashboard for details.','info','pickup',$pickupRequestId);
    $_SESSION['success_message']='Driver assigned successfully.';
} catch (Exception $e) { error_log('Assign driver error: '.$e->getMessage()); $_SESSION['error_message']=$e->getMessage(); }
redirectTo('my_requests.php');
