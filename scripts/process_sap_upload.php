<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Check permissions (assuming admin or specific role, but for now just logged in)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $mes = isset($_POST['mes']) ? (int)$_POST['mes'] : date('n');
    $anio = isset($_POST['anio']) ? (int)$_POST['anio'] : date('Y');
    
    // Float values (allow comma or dot)
    $salud = isset($_POST['salud']) ? (float)str_replace(',', '.', $_POST['salud']) : 0;
    $ambiente = isset($_POST['ambiente']) ? (float)str_replace(',', '.', $_POST['ambiente']) : 0;
    $seguridad = isset($_POST['seguridad']) ? (float)str_replace(',', '.', $_POST['seguridad']) : 0;
    $meta = isset($_POST['meta']) ? (float)str_replace(',', '.', $_POST['meta']) : 0;
    
    $detalles_json = isset($_POST['detalles_json']) ? $_POST['detalles_json'] : null;

    // Check if record exists for this month/year
    $check = $conn->prepare("SELECT id FROM reportes_sap WHERE mes = ? AND anio = ?");
    $check->bind_param("ii", $mes, $anio);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update
        $sql = "UPDATE reportes_sap SET salud_ocupacional = ?, medio_ambiente = ?, seguridad = ?, meta_anual = ?, detalles_json = ?, fecha_carga = NOW() WHERE mes = ? AND anio = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddddsii", $salud, $ambiente, $seguridad, $meta, $detalles_json, $mes, $anio);
    } else {
        // Insert
        $sql = "INSERT INTO reportes_sap (mes, anio, salud_ocupacional, medio_ambiente, seguridad, meta_anual, detalles_json) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidddds", $mes, $anio, $salud, $ambiente, $seguridad, $meta, $detalles_json);
    }
    
    if ($stmt->execute()) {
        registrar_actividad('Carga Reporte SAP', "Mes: $mes/$anio updated", $_SESSION['user_id']);
        echo json_encode(['success' => true, 'message' => 'Datos guardados correctamente']);
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>