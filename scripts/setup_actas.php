<?php
require_once __DIR__ . '/../config/db.php';

// Create Directory for Signatures
$uploadDir = __DIR__ . '/../assets/uploads/firmas/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "Directorio creado: $uploadDir<br>";
    } else {
        die("Error al crear directorio: $uploadDir");
    }
} else {
    echo "Directorio ya existe: $uploadDir<br>";
}

// Create Table actas_vehiculos
$sql = "CREATE TABLE IF NOT EXISTS actas_vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    trabajador_id INT NOT NULL,
    tipo ENUM('Entrega', 'Devolucion') NOT NULL,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    kilometraje INT NOT NULL,
    nivel_combustible VARCHAR(20),
    limpieza_int BOOLEAN DEFAULT 1,
    limpieza_ext BOOLEAN DEFAULT 1,
    luces BOOLEAN DEFAULT 1,
    neumaticos BOOLEAN DEFAULT 1,
    rueda_repuesto BOOLEAN DEFAULT 1,
    gata_llave BOOLEAN DEFAULT 1,
    documentos BOOLEAN DEFAULT 1,
    observaciones TEXT,
    firma_path VARCHAR(255),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'actas_vehiculos' creada o ya existe.<br>";
} else {
    echo "Error creando tabla: " . $conn->error . "<br>";
}

echo "Setup de Actas completado.";
?>