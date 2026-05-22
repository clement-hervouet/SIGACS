<?php
// Initialize session
session_start();

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
	header('location: login.php');
	exit;
} else {
    header('location: dashboard.php');
    exit;
}
?>