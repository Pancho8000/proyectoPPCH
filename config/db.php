<?php
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'hecso2_db';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// Define Base URL and Root Path
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/hecso2/');
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

// Include Logger Helper
require_once ROOT_PATH . 'includes/logger.php';

// Include Composer Autoloader
if (file_exists(ROOT_PATH . 'vendor/autoload.php')) {
    require_once ROOT_PATH . 'vendor/autoload.php';
}
?>