<?php 
require_once '../../includes/auth.php';
require_once '../../config/db.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Handle Actions
$message = '';
$message_type = '';

// 1. Delete Worker
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM trabajadores WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        registrar_actividad('Eliminar Trabajador', 'Trabajador ID: ' . $id . ' eliminado', $_SESSION['user_id']);
        $message = "Trabajador eliminado correctamente.";
        $message_type = "success";
    } else {
        $message = "Error al eliminar: " . $conn->error;
        $message_type = "danger";
    }
}

// 2. Add/Edit Worker
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'edit')) {
    $nombre = $_POST['nombre'];
    $rut = $_POST['rut'];
    $cargo_id = !empty($_POST['cargo_id']) ? $_POST['cargo_id'] : NULL;
    $tipo_contrato = !empty($_POST['tipo_contrato']) ? $_POST['tipo_contrato'] : NULL;
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : NULL;
    $licencia_vencimiento = !empty($_POST['licencia_vencimiento']) ? $_POST['licencia_vencimiento'] : NULL;
    $examen_salud = !empty($_POST['examen_salud']) ? $_POST['examen_salud'] : NULL;
    $induccion_hombre_nuevo = !empty($_POST['induccion_hombre_nuevo']) ? $_POST['induccion_hombre_nuevo'] : NULL;
    $odi_puerto_desaladora = !empty($_POST['odi_puerto_desaladora']) ? $_POST['odi_puerto_desaladora'] : NULL;

    if ($_POST['action'] == 'create') {
        $sql = "INSERT INTO trabajadores (nombre, rut, cargo_id, tipo_contrato, fecha_ingreso, licencia_vencimiento, examen_salud, induccion_hombre_nuevo, odi_puerto_desaladora) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissssss", $nombre, $rut, $cargo_id, $tipo_contrato, $fecha_ingreso, $licencia_vencimiento, $examen_salud, $induccion_hombre_nuevo, $odi_puerto_desaladora);
    } else {
        $id = $_POST['id'];
        $sql = "UPDATE trabajadores SET nombre=?, rut=?, cargo_id=?, tipo_contrato=?, fecha_ingreso=?, licencia_vencimiento=?, examen_salud=?, induccion_hombre_nuevo=?, odi_puerto_desaladora=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissssssi", $nombre, $rut, $cargo_id, $tipo_contrato, $fecha_ingreso, $licencia_vencimiento, $examen_salud, $induccion_hombre_nuevo, $odi_puerto_desaladora, $id);
    }

    try {
        if ($stmt->execute()) {
            $action_log = ($_POST['action'] == 'create') ? 'Crear Trabajador' : 'Editar Trabajador';
            $details_log = "Nombre: $nombre, RUT: $rut";
            registrar_actividad($action_log, $details_log, $_SESSION['user_id']);

            $message = "Trabajador guardado correctamente.";
            $message_type = "success";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $message = "Error: El RUT ya existe en la base de datos.";
        } else {
            $message = "Error al guardar: " . $e->getMessage();
        }
        $message_type = "danger";
    }
}

// 3. Import CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file_name = $_FILES['file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext != 'csv') {
            $message = "Error: Formato de archivo no válido. Por favor guarde su Excel como CSV (Delimitado por comas) e intente nuevamente.";
            $message_type = "danger";
        } else {
            $file = $_FILES['file']['tmp_name'];
            $handle = fopen($file, "r");
        
        if ($handle) {
            $headers = null;
            $imported = 0;
            $errors = 0;
            
            $delimiter = ",";
            // Detect headers
            if (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Try to detect delimiter if comma failed (only 1 column found)
                if (count($data) == 1) {
                    rewind($handle);
                    $data = fgetcsv($handle, 1000, ";");
                    $delimiter = ";";
                }
                
                // Map columns (Robust Logic with Debug)
                $map = [];
                $debug_info = []; // To store mapping results for user feedback
                
                foreach ($data as $index => $colRaw) {
                    $col = mb_strtolower(trim($colRaw), 'UTF-8'); // Handle UTF-8
                    
                    // Name
                    if ($col === 'nombre' || strpos($col, 'nombres') !== false) { $map['nombre'] = $index; $debug_info[] = "Nombre -> $colRaw"; continue; }
                    if (strpos($col, 'apellido') !== false && strpos($col, 'paterno') !== false) { $map['ap_paterno'] = $index; $debug_info[] = "Ap. Paterno -> $colRaw"; continue; }
                    if (strpos($col, 'apellido') !== false && strpos($col, 'materno') !== false) { $map['ap_materno'] = $index; $debug_info[] = "Ap. Materno -> $colRaw"; continue; }
                    
                    // RUT
                    if (preg_match('/(rut|dni|run|c[eé]dula|identificador)/i', $col)) { $map['rut'] = $index; $debug_info[] = "RUT -> $colRaw"; continue; }
                    
                    // Fechas / Dates
                    if (strpos($col, 'fecha') !== false && strpos($col, 'ingreso') !== false) { $map['fecha_ingreso'] = $index; $debug_info[] = "F. Ingreso -> $colRaw"; continue; }
                    
                    // Licencias - STRICT ORDER
                    // 1. Licencia Interna MLP
                    if (strpos($col, 'interna') !== false && (strpos($col, 'licencia') !== false || strpos($col, 'mlp') !== false)) {
                        $map['licencia_interna_mlp'] = $index;
                        $debug_info[] = "Lic. Interna -> $colRaw";
                        continue;
                    }
                    
                    // 2. Licencia Municipal / Conducir (Standard)
                    if (strpos($col, 'municipal') !== false || strpos($col, 'conducir') !== false) {
                        $map['licencia_vencimiento'] = $index;
                        $debug_info[] = "Lic. Municipal -> $colRaw";
                        continue;
                    }
                    // Fallback for just "Licencia" or "Vencimiento Licencia" IF it doesn't say "Interna"
                    if ((strpos($col, 'licencia') !== false || strpos($col, 'vencimiento') !== false) && strpos($col, 'interna') === false) {
                         // Only map if not already mapped by a stronger match (like 'municipal')
                         if (!isset($map['licencia_vencimiento'])) {
                             $map['licencia_vencimiento'] = $index;
                             $debug_info[] = "Lic. Vencimiento (Genérico) -> $colRaw";
                         }
                         continue;
                    }

                    // Cargo
                    if (strpos($col, 'cargo') !== false) { $map['cargo'] = $index; $debug_info[] = "Cargo -> $colRaw"; continue; }
                    if (strpos($col, 'tipo') !== false && strpos($col, 'contrato') !== false) { $map['tipo_contrato'] = $index; $debug_info[] = "Tipo Contrato -> $colRaw"; continue; }
                    
                    // Health / Safety
                    // Use 'oximetr' to catch 'oximetria' and 'oximetría'
                    if (strpos($col, 'oximetr') !== false) { $map['oximetria'] = $index; $debug_info[] = "Oximetria -> $colRaw"; continue; }
                    if (strpos($col, 'psicosenso') !== false) { $map['psicosensotecnico'] = $index; $debug_info[] = "Psicosensotecnico -> $colRaw"; continue; }
                    if (strpos($col, 'manejo') !== false && strpos($col, 'defensivo') !== false) { $map['manejo_defensivo'] = $index; $debug_info[] = "Manejo Defensivo -> $colRaw"; continue; }
                    
                    if ((strpos($col, 'examen') !== false || strpos($col, 'salud') !== false) && strpos($col, 'psicosenso') === false) { 
                        $map['examen_salud'] = $index; 
                        $debug_info[] = "Examen Salud -> $colRaw"; 
                        continue; 
                    }
                    if (strpos($col, 'induccion') !== false) { $map['induccion_hombre_nuevo'] = $index; $debug_info[] = "Induccion -> $colRaw"; continue; }
                    if (strpos($col, 'odi') !== false) { $map['odi_puerto_desaladora'] = $index; $debug_info[] = "ODI -> $colRaw"; continue; }
                }
                
                // If RUT or Nombre not found, try default indices 0 and 1
                // Prevent defaulting RUT to index 1 if index 1 is already used for surname
                if (!isset($map['nombre']) && !isset($map['ap_paterno'])) $map['nombre'] = 0;
                
                if (!isset($map['rut'])) {
                     // Only default to 1 if it's NOT used by other fields (like surnames)
                     if (!isset($map['ap_paterno']) && !isset($map['ap_materno'])) {
                         $map['rut'] = 1;
                     }
                }

                // CRITICAL CHECK: If we still don't have a RUT column, we cannot proceed.
                if (!isset($map['rut'])) {
                    $message = "Error: No se encontró una columna de RUT/DNI/RUN en el archivo. Por favor verifique los encabezados.";
                    $message_type = "danger";
                    // Close handle and skip processing
                    fclose($handle);
                    // Break out of the import logic (using a flag or just skipping the while loop)
                    $handle = false; 
                }
            }

            // Helper to get/create cargo
            if ($handle) { // Only proceed if handle is still valid (RUT found)
            function getOrCreateCargo($conn, $cargoName) {
                if (empty($cargoName)) return NULL;
                $cargoName = trim($cargoName);
                
                // Check if exists
                $stmt = $conn->prepare("SELECT id FROM cargos WHERE nombre = ?");
                $stmt->bind_param("s", $cargoName);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    return $row['id'];
                }
                
                // Create
                $stmt = $conn->prepare("INSERT INTO cargos (nombre) VALUES (?)");
                $stmt->bind_param("s", $cargoName);
                if ($stmt->execute()) {
                    return $stmt->insert_id;
                }
                return NULL;
            }

            // Process Rows
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                 // Re-check delimiter for data rows if needed, but usually consistent
                 if (count($data) == 1 && strpos($data[0], ';') !== false) {
                     $data = explode(';', $data[0]);
                 }

                 // Construct Name
                 $nombre_part = isset($map['nombre']) && isset($data[$map['nombre']]) ? trim($data[$map['nombre']]) : '';
                 $ap_paterno = isset($map['ap_paterno']) && isset($data[$map['ap_paterno']]) ? trim($data[$map['ap_paterno']]) : '';
                 $ap_materno = isset($map['ap_materno']) && isset($data[$map['ap_materno']]) ? trim($data[$map['ap_materno']]) : '';
                 
                 $nombre_completo = trim("$nombre_part $ap_paterno $ap_materno");
                 
                 // If no separated names found, maybe it was just one column mapped to 'nombre'
                 if (empty($nombre_completo) && isset($map['nombre']) && isset($data[$map['nombre']])) {
                     $nombre_completo = trim($data[$map['nombre']]);
                 }

                 $rut = isset($map['rut']) && isset($data[$map['rut']]) ? trim($data[$map['rut']]) : '';
                 $fecha_ingreso = isset($map['fecha_ingreso']) && isset($data[$map['fecha_ingreso']]) ? $data[$map['fecha_ingreso']] : NULL;
                 $licencia_vencimiento = isset($map['licencia_vencimiento']) && isset($data[$map['licencia_vencimiento']]) ? $data[$map['licencia_vencimiento']] : NULL;
                 $examen_salud = isset($map['examen_salud']) && isset($data[$map['examen_salud']]) ? $data[$map['examen_salud']] : NULL;
                 $induccion_hombre_nuevo = isset($map['induccion_hombre_nuevo']) && isset($data[$map['induccion_hombre_nuevo']]) ? $data[$map['induccion_hombre_nuevo']] : NULL;
                 $odi_puerto_desaladora = isset($map['odi_puerto_desaladora']) && isset($data[$map['odi_puerto_desaladora']]) ? $data[$map['odi_puerto_desaladora']] : NULL;
                 $licencia_interna_mlp = isset($map['licencia_interna_mlp']) && isset($data[$map['licencia_interna_mlp']]) ? $data[$map['licencia_interna_mlp']] : NULL;
                 $oximetria = isset($map['oximetria']) && isset($data[$map['oximetria']]) ? $data[$map['oximetria']] : NULL;
                 $psicosensotecnico = isset($map['psicosensotecnico']) && isset($data[$map['psicosensotecnico']]) ? $data[$map['psicosensotecnico']] : NULL;
                 $manejo_defensivo = isset($map['manejo_defensivo']) && isset($data[$map['manejo_defensivo']]) ? $data[$map['manejo_defensivo']] : NULL;

                 $cargo_text = isset($map['cargo']) && isset($data[$map['cargo']]) ? $data[$map['cargo']] : '';
                 $tipo_contrato = isset($map['tipo_contrato']) && isset($data[$map['tipo_contrato']]) ? trim($data[$map['tipo_contrato']]) : NULL;

                 // Validate RUT (Must contain at least one number)
                 if (empty($rut) || !preg_match('/\d/', $rut)) {
                     $errors++; // Skip rows without valid RUT
                     continue; 
                 }

                 // Get Cargo ID
                 $cargo_id = getOrCreateCargo($conn, $cargo_text);

                 // Date parsing helper
                 $parseDate = function($dateStr) {
                    if (empty($dateStr)) return NULL;
                    $dateStr = trim($dateStr);
                    
                    // Handle Excel serial date (e.g. 45000)
                    if (is_numeric($dateStr) && $dateStr > 20000 && $dateStr < 60000) {
                        $unixDate = ($dateStr - 25569) * 86400;
                        return date("Y-m-d", $unixDate);
                    }

                    // Try Y-m-d
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) return $dateStr;
                    
                    // Try d/m/Y or d-m-Y (or even d.m.Y)
                    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $dateStr, $matches)) {
                        $y = $matches[3];
                        // Handle 2 digit year
                        if (strlen($y) == 2) {
                            $y = '20' . $y; // Assume 20xx
                        }
                        return $y . '-' . $matches[2] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    }
                    
                    // Try to use strtotime for other formats (like "01-Feb-2025")
                    $ts = strtotime(str_replace('/', '-', $dateStr));
                    if ($ts) return date('Y-m-d', $ts);

                    return NULL; // Default or invalid
                 };

                 // Basic formatting for dates if imported (assuming Y-m-d or trying to parse)
                 $fecha_ingreso = $parseDate($fecha_ingreso);
                 $licencia_vencimiento = $parseDate($licencia_vencimiento);
                 $examen_salud = $parseDate($examen_salud);
                 $induccion_hombre_nuevo = $parseDate($induccion_hombre_nuevo);
                 $odi_puerto_desaladora = $parseDate($odi_puerto_desaladora);
                 $licencia_interna_mlp = $parseDate($licencia_interna_mlp);
                 $oximetria = $parseDate($oximetria);
                 $psicosensotecnico = $parseDate($psicosensotecnico);
                 $manejo_defensivo = $parseDate($manejo_defensivo);

                 if (!empty($rut)) {
                     $stmt = $conn->prepare("INSERT INTO trabajadores (nombre, rut, fecha_ingreso, licencia_vencimiento, cargo_id, tipo_contrato, examen_salud, induccion_hombre_nuevo, odi_puerto_desaladora, licencia_interna_mlp, oximetria, psicosensotecnico, manejo_defensivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), fecha_ingreso = VALUES(fecha_ingreso), licencia_vencimiento = VALUES(licencia_vencimiento), cargo_id = VALUES(cargo_id), tipo_contrato = VALUES(tipo_contrato), examen_salud = VALUES(examen_salud), induccion_hombre_nuevo = VALUES(induccion_hombre_nuevo), odi_puerto_desaladora = VALUES(odi_puerto_desaladora), licencia_interna_mlp = VALUES(licencia_interna_mlp), oximetria = VALUES(oximetria), psicosensotecnico = VALUES(psicosensotecnico), manejo_defensivo = VALUES(manejo_defensivo)");
                     $stmt->bind_param("ssssissssssss", $nombre_completo, $rut, $fecha_ingreso, $licencia_vencimiento, $cargo_id, $tipo_contrato, $examen_salud, $induccion_hombre_nuevo, $odi_puerto_desaladora, $licencia_interna_mlp, $oximetria, $psicosensotecnico, $manejo_defensivo);
                     if ($stmt->execute()) {
                         $imported++;
                     } else {
                         $errors++;
                     }
                 }
            }
            fclose($handle);
            registrar_actividad('Importación Trabajadores', "Importados/Actualizados: $imported, Errores: $errors", $_SESSION['user_id']);
            $debug_str = implode(", ", $debug_info);
            $message = "Importación completada: $imported importados/actualizados, $errors errores (filas sin RUT válido). <br><small>Columnas detectadas: $debug_str</small>";
            $message_type = "success";
            } // End if($handle)
        } else {
            $message = "Error al abrir el archivo.";
            $message_type = "danger";
        }
    }
    } else {
        $message = "Por favor seleccione un archivo CSV válido.";
        $message_type = "warning";
    }
}

// Search Logic
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where = "1";
if (!empty($search)) {
    $search_esc = $conn->real_escape_string($search);
    $where .= " AND (t.nombre LIKE '%$search_esc%' OR t.rut LIKE '%$search_esc%')";
}

if ($filter == 'conductores') {
    // Filter by cargo name (Conductor, Chofer, Operador) or having a license registered
    $where .= " AND (c.nombre LIKE '%conductor%' OR c.nombre LIKE '%chofer%' OR c.nombre LIKE '%operador%' OR t.licencia_vencimiento IS NOT NULL OR t.licencia_interna_mlp IS NOT NULL)";
} elseif ($filter == 'vencidas') {
    // Filter by expired licenses
    $where .= " AND t.licencia_vencimiento < CURDATE()";
}

// Bulk Delete Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $ids_str = implode(',', $ids);
        
        // Use prepared statement NOT possible directly with IN clause efficiently without dynamic binding, 
        // but intval ensures safety for direct query here.
        if ($conn->query("DELETE FROM trabajadores WHERE id IN ($ids_str)")) {
            registrar_actividad('Eliminar Múltiples Trabajadores', "IDs eliminados: " . $ids_str, $_SESSION['user_id']);
            $message = count($ids) . " trabajadores eliminados correctamente.";
            $message_type = "success";
        } else {
            $message = "Error al eliminar: " . $conn->error;
            $message_type = "danger";
        }
    } else {
        $message = "No se seleccionaron trabajadores.";
        $message_type = "warning";
    }
}

$workers = [];
$res = $conn->query("SELECT t.*, c.nombre as cargo_nombre FROM trabajadores t LEFT JOIN cargos c ON t.cargo_id = c.id WHERE $where ORDER BY t.nombre ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $workers[] = $row;
    }
}
?>

<style>
/* UI Polish for Trabajadores */
.card { border: none; border-radius: 0.75rem; }
.shadow-hover:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transition: all .2s; }
.table thead th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #6c757d; background-color: #f8f9fa; border-bottom: 2px solid #e9ecef; }
.table tbody td { vertical-align: middle; }
.btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
.nav-pills .nav-link { border-radius: 0.5rem; font-weight: 500; color: #6c757d; transition: all 0.2s; }
.nav-pills .nav-link:hover { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.nav-pills .nav-link.active { background-color: #0d6efd; color: white; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3); }
.avatar-initials { width: 35px; height: 35px; background-color: #e9ecef; color: #495057; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; font-size: 0.9rem; }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
    <div class="mb-3 mb-md-0">
        <h2 class="fw-bold text-dark mb-0">Gestión de Trabajadores</h2>
        <p class="text-muted small mb-0">Administra tu personal, contratos y documentación.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-danger d-none shadow-sm rounded-pill px-3" id="btnBulkDelete" onclick="submitBulkDelete()">
            <i class="fas fa-trash-alt me-2"></i>Eliminar Seleccionados
        </button>
        <button class="btn btn-success shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-excel me-2"></i>Importar
        </button>
        <button class="btn btn-primary shadow-sm rounded-pill px-3" onclick="openModal('create')">
            <i class="fas fa-plus me-2"></i>Nuevo Trabajador
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert">
        <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill">
          <li class="nav-item">
            <a class="nav-link <?php echo $filter == 'all' ? 'active' : ''; ?>" href="trabajadores.php<?php echo !empty($search) ? '?search='.urlencode($search) : ''; ?>">
                <i class="fas fa-users me-2"></i>Todos
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $filter == 'conductores' ? 'active' : ''; ?>" href="trabajadores.php?filter=conductores<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                <i class="fas fa-truck-pickup me-2"></i>Conductores
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $filter == 'vencidas' ? 'active' : ''; ?>" href="trabajadores.php?filter=vencidas<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                <i class="fas fa-exclamation-triangle me-2"></i>Licencias Vencidas
            </a>
          </li>
        </ul>
    </div>
</div>

<div class="card shadow border-0 mb-4">
    <div class="card-body p-4">
        <!-- Search Form -->
        <form method="GET" class="row g-3 mb-4 align-items-center">
            <?php if($filter != 'all'): ?>
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <?php endif; ?>
            <div class="col-md-5">
                <div class="input-group shadow-sm rounded-pill overflow-hidden">
                    <span class="input-group-text border-0 bg-light ps-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-0 bg-light" name="search" placeholder="Buscar por Nombre o RUT..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary px-4" type="submit">Buscar</button>
                </div>
            </div>
            <?php if(!empty($search)): ?>
                <div class="col-md-auto">
                    <a href="trabajadores.php<?php echo $filter != 'all' ? '?filter='.htmlspecialchars($filter) : ''; ?>" class="btn btn-outline-secondary rounded-pill btn-sm">
                        <i class="fas fa-times me-1"></i>Limpiar Filtro
                    </a>
                </div>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <form method="POST" id="bulkDeleteForm">
            <input type="hidden" name="action" value="bulk_delete">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th>Trabajador</th>
                            <th>RUT</th>
                            <th>Cargo</th>
                            <th>Contrato</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="mb-3">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle" style="width: 80px; height: 80px;">
                                            <i class="fas fa-users-slash fa-3x text-secondary opacity-50"></i>
                                        </div>
                                    </div>
                                    <h6 class="fw-bold">No se encontraron trabajadores</h6>
                                    <p class="small mb-0">Intenta ajustar tu búsqueda o filtro.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workers as $w): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input worker-checkbox" name="ids[]" value="<?php echo $w['id']; ?>"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials me-3">
                                                <?php echo strtoupper(substr($w['nombre'], 0, 1)); ?>
                                            </div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($w['nombre']); ?></div>
                                        </div>
                                    </td>
                                    <td class="text-muted font-monospace small"><?php echo htmlspecialchars($w['rut']); ?></td>
                                    <td>
                                        <?php if($w['cargo_nombre']): ?>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($w['cargo_nombre']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Sin Cargo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(!empty($w['tipo_contrato'])): ?>
                                            <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                                <?php echo htmlspecialchars($w['tipo_contrato']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="ficha_trabajador.php?id=<?php echo $w['id']; ?>" class="btn btn-icon btn-outline-info border-0" title="Ver Ficha" data-bs-toggle="tooltip">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="ficha_trabajador.php?id=<?php echo $w['id']; ?>&mode=edit" class="btn btn-icon btn-outline-warning border-0" title="Editar" data-bs-toggle="tooltip">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <button type="button" class="btn btn-icon btn-outline-danger border-0" onclick="deleteWorker(<?php echo $w['id']; ?>)" title="Eliminar" data-bs-toggle="tooltip">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Modal Create/Edit -->
<div class="modal fade" id="workerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-user-plus me-2"></i>Nuevo Trabajador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="workerId">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Nombre Completo</label>
                        <input type="text" class="form-control form-control-lg bg-light" name="nombre" id="inputNombre" required placeholder="Ej. Juan Pérez">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">RUT</label>
                        <input type="text" class="form-control form-control-lg bg-light" name="rut" id="inputRut" required placeholder="12.345.678-9">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-muted">Tipo de Contrato</label>
                        <select class="form-select bg-light" name="tipo_contrato" id="inputTipoContrato">
                            <option value="">Seleccionar...</option>
                            <option value="Plazo Fijo">Plazo Fijo</option>
                            <option value="Indefinido">Indefinido</option>
                            <option value="Por Obra">Por Obra</option>
                        </select>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-clipboard-check me-2"></i>Documentación y Fechas</h6>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Examen Salud</label>
                        <input type="date" class="form-control" name="examen_salud" id="inputExamen">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Inducción H. Nuevo</label>
                        <input type="date" class="form-control" name="induccion_hombre_nuevo" id="inputInduccion">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">ODI Pto. Desaladora</label>
                        <input type="date" class="form-control" name="odi_puerto_desaladora" id="inputODI">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Guardar Trabajador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4" id="importForm">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-csv me-2"></i>Importar Trabajadores</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="import">
                
                <!-- Tabs -->
                <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-pill" id="importTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-pane" type="button" role="tab" aria-selected="true">Subir Archivo</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill" id="paste-tab" data-bs-toggle="tab" data-bs-target="#paste-pane" type="button" role="tab" aria-selected="false">Pegar desde Excel</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Upload Pane -->
                    <div class="tab-pane fade show active" id="upload-pane" role="tabpanel">
                        <div class="text-center p-4 border rounded-3 bg-light border-dashed mb-3">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h6 class="fw-bold">Arrastra tu archivo aquí o haz clic para seleccionar</h6>
                            <p class="text-muted small">Soporta .csv, .xlsx, .xls</p>
                            <input type="file" class="form-control" name="file" id="importFile" accept=".csv, .txt, .xlsx, .xls" style="max-width: 300px; margin: 0 auto;">
                        </div>
                        <div class="alert alert-info small border-0 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-info-circle me-1"></i>
                            El sistema detectará automáticamente las columnas: <strong>Nombre, RUT, Cargo, Fechas, etc.</strong>
                        </div>
                    </div>

                    <!-- Paste Pane -->
                    <div class="tab-pane fade" id="paste-pane" role="tabpanel">
                        <div class="alert alert-secondary small border-0">
                            <i class="fas fa-keyboard me-1"></i>
                            Copia tus celdas desde Excel (Ctrl+C) y pégalas abajo (Ctrl+V).
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control font-monospace small bg-light" id="pasteArea" rows="10" placeholder="Nombre    RUT    Cargo..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm" id="btnImport">Importar Datos</button>
            </div>
        </form>
    </div>
</div>

<script>
// Bulk Delete Logic
const selectAll = document.getElementById('selectAll');
const checkboxes = document.querySelectorAll('.worker-checkbox');
const btnBulkDelete = document.getElementById('btnBulkDelete');

function toggleBulkButton() {
    const checkedCount = document.querySelectorAll('.worker-checkbox:checked').length;
    if (checkedCount > 0) {
        btnBulkDelete.classList.remove('d-none');
        btnBulkDelete.textContent = `Eliminar Seleccionados (${checkedCount})`;
    } else {
        btnBulkDelete.classList.add('d-none');
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        toggleBulkButton();
    });
}

checkboxes.forEach(cb => {
    cb.addEventListener('change', toggleBulkButton);
});

function submitBulkDelete() {
    if (confirm('¿Estás seguro de que deseas eliminar los trabajadores seleccionados? Esta acción no se puede deshacer.')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

// Import Form Handler
document.getElementById('importForm').addEventListener('submit', function(e) {
    const pasteTab = document.getElementById('paste-tab');
    const fileInput = document.getElementById('importFile');
    
    // Check if Paste tab is active
    if (pasteTab.classList.contains('active')) {
        const content = document.getElementById('pasteArea').value;
        if (!content.trim()) {
            e.preventDefault();
            alert('Por favor pega los datos antes de importar.');
            return;
        }

        // Convert TSV (Tab Separated) to CSV
        // Handle quotes inside fields by escaping them
        const rows = content.split(/\r\n|\n|\r/);
        const csvRows = rows.map(row => {
            const columns = row.split('\t');
            const csvColumns = columns.map(col => {
                let cleanCol = col.replace(/"/g, '""'); // Escape double quotes
                if (cleanCol.search(/("|,|\n)/g) >= 0) {
                    cleanCol = `"${cleanCol}"`; // Wrap in quotes if needed
                }
                return cleanCol;
            });
            return csvColumns.join(',');
        });
        const csvContent = csvRows.join('\n');
        
        // Create file object
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const file = new File([blob], "pasted_data.csv", { type: "text/csv" });
        
        // Assign to file input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
    } else {
        // Upload tab active
        if (fileInput.files.length === 0) {
             e.preventDefault();
             alert('Por favor selecciona un archivo.');
             return;
        }
    }
});

// Excel to CSV conversion logic
document.getElementById('importFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'xlsx' || ext === 'xls') {
        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const csv = XLSX.utils.sheet_to_csv(worksheet);
            
            // Create a new Blob and File
            const blob = new Blob([csv], {type: 'text/csv'});
            const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".csv", {type: "text/csv"});
            
            // Replace the file input's files
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(newFile);
            document.getElementById('importFile').files = dataTransfer.files;
            
            console.log('Converted Excel to CSV successfully');
        };
        reader.readAsArrayBuffer(file);
    }
});

const workerModal = new bootstrap.Modal(document.getElementById('workerModal'));

function openModal(mode) {
    document.getElementById('formAction').value = mode;
    document.getElementById('modalTitle').textContent = mode === 'create' ? 'Nuevo Trabajador' : 'Editar Trabajador';
    
    if(mode === 'create') {
        document.getElementById('workerId').value = '';
        document.getElementById('inputNombre').value = '';
        document.getElementById('inputRut').value = '';
        document.getElementById('inputTipoContrato').value = '';
        document.getElementById('inputExamen').value = '';
        document.getElementById('inputInduccion').value = '';
        document.getElementById('inputODI').value = '';
    }
    workerModal.show();
}

function deleteWorker(id) {
    if(confirm('¿Estás seguro de que deseas eliminar este trabajador?')) {
        window.location.href = 'trabajadores.php?action=delete&id=' + id;
    }
}

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})
</script>

<?php include '../../includes/footer.php'; ?>
