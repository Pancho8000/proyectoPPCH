<?php
include 'config/db.php';

// Add 'estado' column to 'vehiculos' if it doesn't exist
$sql = "SHOW COLUMNS FROM vehiculos LIKE 'estado'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE vehiculos ADD COLUMN estado ENUM('Disponible', 'En Taller', 'En Revisión') DEFAULT 'Disponible'");
    echo "Columna 'estado' agregada a tabla 'vehiculos'.<br>";
}

// Add 'licencia_vencimiento' to 'trabajadores' if it doesn't exist
$sql = "SHOW COLUMNS FROM trabajadores LIKE 'licencia_vencimiento'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE trabajadores ADD COLUMN licencia_vencimiento DATE");
    echo "Columna 'licencia_vencimiento' agregada a tabla 'trabajadores'.<br>";
}

// Add some dummy data for testing if tables are empty
$result = $conn->query("SELECT COUNT(*) as count FROM vehiculos");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO vehiculos (patente, marca, modelo, anio, estado) VALUES 
        ('ABCD-12', 'Toyota', 'Hilux', 2020, 'Disponible'),
        ('EFGH-34', 'Nissan', 'Navara', 2021, 'En Taller'),
        ('IJKL-56', 'Mitsubishi', 'L200', 2019, 'Disponible'),
        ('MNOP-78', 'Ford', 'Ranger', 2022, 'En Revisión'),
        ('QRST-90', 'Chevrolet', 'D-Max', 2020, 'Disponible')
    ");
    echo "Datos de prueba agregados a 'vehiculos'.<br>";
}

$result = $conn->query("SELECT COUNT(*) as count FROM trabajadores");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO trabajadores (nombre, rut, fecha_ingreso, licencia_vencimiento) VALUES 
        ('Juan Perez', '12.345.678-9', '2022-01-15', '2025-12-31'),
        ('Maria Gonzalez', '9.876.543-2', '2023-03-20', '2024-01-15'), -- Vencida
        ('Pedro Soto', '11.223.344-5', '2021-11-10', '2026-06-30')
    ");
    echo "Datos de prueba agregados a 'trabajadores'.<br>";
}

// Dummy data for mantenciones
$result = $conn->query("SELECT COUNT(*) as count FROM mantenciones");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO mantenciones (vehiculo_id, descripcion, fecha, costo) VALUES 
        (1, 'Cambio de Aceite', DATE_SUB(NOW(), INTERVAL 1 MONTH), 50000),
        (2, 'Reparación Frenos', DATE_SUB(NOW(), INTERVAL 2 MONTH), 120000),
        (3, 'Alineación', DATE_SUB(NOW(), INTERVAL 3 MONTH), 35000),
        (1, 'Cambio Neumáticos', DATE_SUB(NOW(), INTERVAL 5 MONTH), 400000),
        (4, 'Revisión General', NOW(), 80000)
    ");
    echo "Datos de prueba agregados a 'mantenciones'.<br>";
}

// Dummy data for combustible
$result = $conn->query("SELECT COUNT(*) as count FROM combustible");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO combustible (vehiculo_id, litros, costo, fecha) VALUES 
        (1, 50, 65000, DATE_SUB(NOW(), INTERVAL 5 DAY)),
        (2, 45, 58000, DATE_SUB(NOW(), INTERVAL 10 DAY)),
        (3, 60, 78000, DATE_SUB(NOW(), INTERVAL 2 DAY)),
        (1, 55, 71500, NOW())
    ");
    echo "Datos de prueba agregados a 'combustible'.<br>";
}

echo "Actualización de base de datos completada.";
?>