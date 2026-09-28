<?php
require_once '../includes/config.php';

session_destroy();
redirectTo('login.php');
?>
