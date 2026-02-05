<?php
require_once __DIR__ . '/../config/db.php';

// Create Directory
$uploadDir = __DIR__ . '/../uploads/general/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "Directorio creado: $uploadDir<br>";
    } else {
        die("Error al crear directorio: $uploadDir");
    }
} else {
    echo "Directorio ya existe: $uploadDir<br>";
}

// Create Table
$sql = "CREATE TABLE IF NOT EXISTS documentos_generales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('carpeta', 'archivo') NOT NULL DEFAULT 'archivo',
    carpeta_id INT DEFAULT NULL,
    archivo VARCHAR(255) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carpeta_id) REFERENCES documentos_generales(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'documentos_generales' creada o ya existe.<br>";
} else {
    echo "Error creando tabla: " . $conn->error . "<br>";
}

echo "Setup completado.";
?>