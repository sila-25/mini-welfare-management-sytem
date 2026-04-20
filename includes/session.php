<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout (30 mins)
$timeout = 1800;

if (isset($_SESSION['LAST_ACTIVITY']) && 
   (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {

    session_unset();
    session_destroy();
}

$_SESSION['LAST_ACTIVITY'] = time();

?>