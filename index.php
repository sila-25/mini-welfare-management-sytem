<?php

require_once 'includes/session.php';

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['role_type'] === 'super_admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard/home.php");
    }

} else {
    header("Location: pages/login.php");
}