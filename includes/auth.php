<?php
session_start();

// Include config to get BASE_URL
require_once __DIR__ . '/../config/db.php';

// Define security constant
define('SECURE_ACCESS', true);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/auth/login.php");
    exit();
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>