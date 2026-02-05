<?php
require_once '../config/db.php';

// 1. Add trabajador_id to usuarios
$sql = "SHOW COLUMNS FROM usuarios LIKE 'trabajador_id'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN trabajador_id INT NULL");
    $conn->query("ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_trabajadores FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id) ON DELETE SET NULL");
    echo "Added trabajador_id to usuarios table.\n";
}

// 2. Add trabajador_id to certificados
$sql = "SHOW COLUMNS FROM certificados LIKE 'trabajador_id'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE certificados ADD COLUMN trabajador_id INT NULL");
    $conn->query("ALTER TABLE certificados ADD CONSTRAINT fk_certificados_trabajadores FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id) ON DELETE CASCADE");
    echo "Added trabajador_id to certificados table.\n";
}

// 3. Create rutas table
$sql = "CREATE TABLE IF NOT EXISTS rutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trabajador_id INT NOT NULL,
    vehiculo_id INT NOT NULL,
    kilometraje INT NOT NULL,
    foto_tablero VARCHAR(255),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('Salida', 'Llegada', 'Ruta') DEFAULT 'Salida',
    FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'rutas' created or already exists.\n";
} else {
    echo "Error creating table 'rutas': " . $conn->error . "\n";
}

// 4. Ensure vehiculos has kilometraje
$sql = "SHOW COLUMNS FROM vehiculos LIKE 'kilometraje'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE vehiculos ADD COLUMN kilometraje INT DEFAULT 0");
    echo "Added kilometraje to vehiculos table.\n";
}

// 5. Ensure roles exist (Trabajador)
$sql = "SELECT id FROM roles WHERE nombre = 'Trabajador'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("INSERT INTO roles (nombre) VALUES ('Trabajador')");
    echo "Added 'Trabajador' role.\n";
}

echo "Database update v3 completed.\n";
?>
