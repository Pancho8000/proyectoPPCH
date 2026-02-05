<?php
require_once '../config/db.php';

// Add 'Conductor' role if not exists
$sql = "SELECT id FROM roles WHERE nombre = 'Conductor'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("INSERT INTO roles (nombre) VALUES ('Conductor')");
    echo "Added 'Conductor' role.\n";
} else {
    echo "'Conductor' role already exists.\n";
}

echo "Database update v5 completed.\n";
?>
