<?php
require_once '../config/db.php';

// 1. Add 'es_conductor' to 'cargos'
$sql = "SHOW COLUMNS FROM cargos LIKE 'es_conductor'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE cargos ADD COLUMN es_conductor TINYINT(1) DEFAULT 0");
    echo "Added 'es_conductor' to 'cargos' table.\n";
} else {
    echo "'es_conductor' already exists in 'cargos'.\n";
}

echo "Database update v4 completed.\n";
?>
