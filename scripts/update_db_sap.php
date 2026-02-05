<?php
include 'config/db.php';

// Create reportes_sap table
$sql = "CREATE TABLE IF NOT EXISTS reportes_sap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes INT NOT NULL,
    anio INT NOT NULL,
    salud_ocupacional DECIMAL(5,2) DEFAULT 0,
    medio_ambiente DECIMAL(5,2) DEFAULT 0,
    seguridad DECIMAL(5,2) DEFAULT 0,
    meta_anual DECIMAL(5,2) DEFAULT 0,
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (mes, anio)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'reportes_sap' creada o ya existe.<br>";
} else {
    echo "Error creando tabla: " . $conn->error . "<br>";
}

// Insert dummy data for current month
$current_month = date('n');
$current_year = date('Y');

// Check if data exists
$check = $conn->query("SELECT id FROM reportes_sap WHERE mes = $current_month AND anio = $current_year");
if ($check->num_rows == 0) {
    // Insert example data based on user image (approximate values)
    // Salud: 18%, Medio Ambiente: 0%, Seguridad: 13%, Meta: 0% (using slightly different values for demo)
    $sql = "INSERT INTO reportes_sap (mes, anio, salud_ocupacional, medio_ambiente, seguridad, meta_anual) 
            VALUES ($current_month, $current_year, 18.00, 5.00, 13.00, 85.00)";
    
    if ($conn->query($sql) === TRUE) {
        echo "Datos de prueba SAP insertados correctamente.<br>";
    } else {
        echo "Error insertando datos: " . $conn->error . "<br>";
    }
} else {
    echo "Datos SAP para este mes ya existen.<br>";
}

echo "Actualización SAP completada.";
?>