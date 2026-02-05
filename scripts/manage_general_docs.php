<?php
require_once '../config/db.php';
require_once '../includes/auth.php'; // Ensure user is logged in

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_folder':
        createFolder($conn);
        break;
    case 'upload_file':
        uploadFile($conn);
        break;
    case 'delete':
        deleteItem($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function createFolder($conn) {
    $name = trim($_POST['folder_name']);
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }

    $sql = "INSERT INTO documentos_generales (nombre, tipo, carpeta_id) VALUES (?, 'carpeta', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $name, $parentId);

    if ($stmt->execute()) {
        registrar_actividad('Documentos Generales', "Creó carpeta: $name", $_SESSION['user_id']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function uploadFile($conn) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
        return;
    }

    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_gen_') . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/general/';

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $originalName = $file['name'];
        $sql = "INSERT INTO documentos_generales (nombre, tipo, carpeta_id, archivo) VALUES (?, 'archivo', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $originalName, $parentId, $filename);

        if ($stmt->execute()) {
            registrar_actividad('Documentos Generales', "Subió archivo: $originalName", $_SESSION['user_id']);
            echo json_encode(['success' => true]);
        } else {
            // Rollback file
            unlink($uploadDir . $filename);
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor']);
    }
}

function deleteItem($conn) {
    $id = intval($_POST['id']);
    
    // Get item info
    $stmt = $conn->prepare("SELECT * FROM documentos_generales WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['tipo'] == 'archivo') {
            // Delete single file
            $filepath = __DIR__ . '/../uploads/general/' . $row['archivo'];
            if (file_exists($filepath)) unlink($filepath);
            
            $conn->query("DELETE FROM documentos_generales WHERE id = $id");
            registrar_actividad('Documentos Generales', "Eliminó archivo: {$row['nombre']}", $_SESSION['user_id']);
            
        } else {
            // Delete folder (recursive)
            // Note: DB ON DELETE CASCADE handles sub-rows, but we must delete physical files first
            deleteFolderRecursive($conn, $id);
            $conn->query("DELETE FROM documentos_generales WHERE id = $id");
            registrar_actividad('Documentos Generales', "Eliminó carpeta: {$row['nombre']}", $_SESSION['user_id']);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Elemento no encontrado']);
    }
}

function deleteFolderRecursive($conn, $folderId) {
    // Find all sub-items
    $stmt = $conn->prepare("SELECT id, tipo, archivo FROM documentos_generales WHERE carpeta_id = ?");
    $stmt->bind_param("i", $folderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['tipo'] == 'archivo') {
            $filepath = __DIR__ . '/../uploads/general/' . $row['archivo'];
            if (file_exists($filepath)) unlink($filepath);
        } else {
            // Recursive call for sub-folder
            deleteFolderRecursive($conn, $row['id']);
        }
    }
    // No need to delete rows here if ON DELETE CASCADE is set on DB foreign key
    // But if not, we would need to delete them here.
    // Given the setup script used ON DELETE CASCADE, DB will handle row deletion.
}
?>