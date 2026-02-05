<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['doc_archivo']) || $_FILES['doc_archivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
    exit;
}

$entityType = $_POST['entity_type']; // worker | vehicle
$entityId = (int)$_POST['entity_id'];
$docType = $_POST['doc_type'];
$docDate = !empty($_POST['doc_date']) ? $_POST['doc_date'] : NULL;

if (!$entityId || !$docType) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Prepare paths and SQL
$uploadDir = '';
$sql = '';
$stmt = null;
$newFilename = '';

try {
    if ($entityType === 'worker') {
        $uploadDir = '../uploads/certificados/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExt = strtolower(pathinfo($_FILES['doc_archivo']['name'], PATHINFO_EXTENSION));
        $newFilename = uniqid('cert_') . '.' . $fileExt;
        
        if (move_uploaded_file($_FILES['doc_archivo']['tmp_name'], $uploadDir . $newFilename)) {
            $sql = "INSERT INTO certificados (trabajador_id, nombre, archivo, fecha_vencimiento) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $entityId, $docType, $newFilename, $docDate);
            
            if ($stmt->execute()) {
                registrar_actividad('Carga Masiva Docs', "Documento '$docType' subido para trabajador ID: $entityId", $_SESSION['user_id']);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($stmt->error);
            }
        } else {
            throw new Exception("Error al mover archivo");
        }
        
    } elseif ($entityType === 'vehicle') {
        $uploadDir = '../uploads/vehiculos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExt = strtolower(pathinfo($_FILES['doc_archivo']['name'], PATHINFO_EXTENSION));
        $newFilename = uniqid('veh_doc_') . '.' . $fileExt;
        
        if (move_uploaded_file($_FILES['doc_archivo']['tmp_name'], $uploadDir . $newFilename)) {
            $sql = "INSERT INTO vehiculos_documentos (vehiculo_id, nombre, archivo, fecha_vencimiento) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $entityId, $docType, $newFilename, $docDate);
            
            if ($stmt->execute()) {
                registrar_actividad('Carga Masiva Docs', "Documento '$docType' subido para vehículo ID: $entityId", $_SESSION['user_id']);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($stmt->error);
            }
        } else {
            throw new Exception("Error al mover archivo");
        }
    } else {
        throw new Exception("Tipo de entidad desconocido");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
