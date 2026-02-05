<?php 
require_once '../../includes/auth.php';
require_once '../../config/db.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Handle Actions
$message = '';
$message_type = '';

// 1. Delete Vehicle
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Start Transaction
    $conn->begin_transaction();
    
    try {
        // Delete dependencies first
        $stmt = $conn->prepare("DELETE FROM combustible WHERE vehiculo_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM mantenciones WHERE vehiculo_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Delete vehicle
        $stmt = $conn->prepare("DELETE FROM vehiculos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
        
        $conn->commit();
        registrar_actividad('Eliminar Vehículo', 'Vehículo ID: ' . $id . ' eliminado', $_SESSION['user_id']);
        $message = "Vehículo eliminado correctamente.";
        $message_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error al eliminar: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 2. Add/Edit Vehicle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'edit')) {
    // Normalize patente: remove non-alphanumeric, uppercase
    $patente = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['patente'])));
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $anio = !empty($_POST['anio']) ? intval($_POST['anio']) : NULL;
    $estado = $_POST['estado'];

    if ($_POST['action'] == 'create') {
        $sql = "INSERT INTO vehiculos (patente, marca, modelo, anio, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssds", $patente, $marca, $modelo, $anio, $estado);
    } else {
        $id = $_POST['id'];
        $sql = "UPDATE vehiculos SET patente=?, marca=?, modelo=?, anio=?, estado=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdsi", $patente, $marca, $modelo, $anio, $estado, $id);
    }

    try {
        if ($stmt->execute()) {
            $action_log = ($_POST['action'] == 'create') ? 'Crear Vehículo' : 'Editar Vehículo';
            $details_log = "Patente: $patente, Marca: $marca, Modelo: $modelo";
            registrar_actividad($action_log, $details_log, $_SESSION['user_id']);

            $message = "Vehículo guardado correctamente.";
            $message_type = "success";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $message = "Error: La patente ya existe en la base de datos.";
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
                
                // Map columns (Simple fuzzy logic)
                $map = [];
                foreach ($data as $index => $col) {
                    $col = strtolower(trim($col));
                    if (strpos($col, 'patente') !== false || strpos($col, 'placa') !== false) $map['patente'] = $index;
                    if (strpos($col, 'movil') !== false) $map['movil'] = $index;
                    if (strpos($col, 'marca') !== false) $map['marca'] = $index;
                    if (strpos($col, 'modelo') !== false) $map['modelo'] = $index;
                    if (strpos($col, 'año') !== false || strpos($col, 'anio') !== false || strpos($col, 'year') !== false) $map['anio'] = $index;
                    if (strpos($col, 'estado') !== false || strpos($col, 'status') !== false) $map['estado'] = $index;
                    
                    // Vencimientos mapping
                    if (strpos($col, 'revis') !== false && strpos($col, 'tecnic') !== false) $map['revision_tecnica'] = $index;
                    if (strpos($col, 'seguro') !== false) $map['seguro_vencimiento'] = $index;
                    if (strpos($col, 'permiso') !== false) $map['permiso_circulacion'] = $index;
                    
                    // Nuevos campos
                    if (strpos($col, 'certificacion') !== false) $map['certificacion_mlp'] = $index;
                    if (strpos($col, 'kilometraje') !== false) $map['kilometraje'] = $index;
                    if (strpos($col, 'mantencion') !== false) $map['proxima_mantencion'] = $index;
                    if (strpos($col, 'gps') !== false) $map['gps'] = $index;
                    if (strpos($col, 'multiflota') !== false) $map['multiflota'] = $index;
                }
                
                // Defaults if not found
                if (!isset($map['marca']) && !isset($map['movil'])) $map['marca'] = 1;

                if (!isset($map['patente'])) {
                    $message = "Error: No se encontró una columna de 'Patente' en el archivo. Verifique los encabezados.";
                    $message_type = "danger";
                    fclose($handle);
                    $handle = false;
                }
            }
            
            if ($handle) {
            // Process Rows
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                 // Re-check delimiter for data rows if needed
                 if (count($data) == 1 && strpos($data[0], ';') !== false) {
                     $data = explode(';', $data[0]);
                 }

                 // Normalize patente: remove non-alphanumeric, uppercase
                 $patente_raw = isset($map['patente']) && isset($data[$map['patente']]) ? $data[$map['patente']] : '';
                 $patente = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($patente_raw)));
                 
                 $marca = isset($map['marca']) && isset($data[$map['marca']]) ? trim($data[$map['marca']]) : '';
                 $modelo = isset($map['modelo']) && isset($data[$map['modelo']]) ? trim($data[$map['modelo']]) : '';
                 
                 // Handle MOVIL if marca/modelo are empty
                 if (empty($marca) && isset($map['movil']) && isset($data[$map['movil']])) {
                     $movil = trim($data[$map['movil']]);
                     $parts = explode(' ', $movil, 2);
                     $marca = isset($parts[0]) ? $parts[0] : '';
                     $modelo = isset($parts[1]) ? $parts[1] : '';
                 }

                 $anio = isset($map['anio']) && isset($data[$map['anio']]) ? intval($data[$map['anio']]) : NULL;
                 $estado = isset($map['estado']) && isset($data[$map['estado']]) ? trim($data[$map['estado']]) : 'Disponible';
                 
                 // Date parsing helper
                 $parseDate = function($dateStr) {
                    if (empty($dateStr)) return NULL;
                    $dateStr = trim($dateStr);
                    // Try Y-m-d
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) return $dateStr;
                    // Try d/m/Y or d-m-Y
                    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $matches)) {
                        return $matches[3] . '-' . $matches[2] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    }
                    return NULL; // Default or invalid
                 };

                 $revision_tecnica = isset($map['revision_tecnica']) && isset($data[$map['revision_tecnica']]) ? $parseDate(trim($data[$map['revision_tecnica']])) : NULL;
                 $seguro_vencimiento = isset($map['seguro_vencimiento']) && isset($data[$map['seguro_vencimiento']]) ? $parseDate(trim($data[$map['seguro_vencimiento']])) : NULL;
                 $permiso_circulacion = isset($map['permiso_circulacion']) && isset($data[$map['permiso_circulacion']]) ? $parseDate(trim($data[$map['permiso_circulacion']])) : NULL;

                 // New fields
                 $certificacion_mlp = isset($map['certificacion_mlp']) && isset($data[$map['certificacion_mlp']]) ? $parseDate(trim($data[$map['certificacion_mlp']])) : NULL;
                 $kilometraje = isset($map['kilometraje']) && isset($data[$map['kilometraje']]) ? trim($data[$map['kilometraje']]) : NULL;
                 $proxima_mantencion = isset($map['proxima_mantencion']) && isset($data[$map['proxima_mantencion']]) ? trim($data[$map['proxima_mantencion']]) : NULL;
                 $gps = isset($map['gps']) && isset($data[$map['gps']]) ? $parseDate(trim($data[$map['gps']])) : NULL;
                 $multiflota = isset($map['multiflota']) && isset($data[$map['multiflota']]) ? $parseDate(trim($data[$map['multiflota']])) : NULL;

                 // Validate state enum
                 $valid_states = ['Disponible', 'En Taller', 'En Revisión'];
                 if (!in_array($estado, $valid_states)) {
                     // Try to map similar statuses
                     if (stripos($estado, 'taller') !== false) $estado = 'En Taller';
                     elseif (stripos($estado, 'revis') !== false) $estado = 'En Revisión';
                     else $estado = 'Disponible';
                 }

                 if (!empty($patente)) {
                     $stmt = $conn->prepare("INSERT INTO vehiculos (patente, marca, modelo, anio, estado, revision_tecnica, seguro_vencimiento, permiso_circulacion, certificacion_mlp, kilometraje, proxima_mantencion, gps, multiflota) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE marca = VALUES(marca), modelo = VALUES(modelo), anio = VALUES(anio), estado = VALUES(estado), revision_tecnica = VALUES(revision_tecnica), seguro_vencimiento = VALUES(seguro_vencimiento), permiso_circulacion = VALUES(permiso_circulacion), certificacion_mlp = VALUES(certificacion_mlp), kilometraje = VALUES(kilometraje), proxima_mantencion = VALUES(proxima_mantencion), gps = VALUES(gps), multiflota = VALUES(multiflota)");
                     $stmt->bind_param("sssdsssssssss", $patente, $marca, $modelo, $anio, $estado, $revision_tecnica, $seguro_vencimiento, $permiso_circulacion, $certificacion_mlp, $kilometraje, $proxima_mantencion, $gps, $multiflota);
                     if ($stmt->execute()) {
                         $imported++;
                     } else {
                         $errors++;
                     }
                 }
            }
            fclose($handle);
            registrar_actividad('Importación Vehículos', "Importados/Actualizados: $imported, Errores: $errors", $_SESSION['user_id']);
            $message = "Importación completada: $imported vehículos importados/actualizados, $errors errores.";
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

// Bulk Delete Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $ids_str = implode(',', $ids);
        
        $conn->begin_transaction();
        
        try {
            // Delete dependencies first
            $conn->query("DELETE FROM combustible WHERE vehiculo_id IN ($ids_str)");
            $conn->query("DELETE FROM mantenciones WHERE vehiculo_id IN ($ids_str)");
            
            // Delete vehicles
            if (!$conn->query("DELETE FROM vehiculos WHERE id IN ($ids_str)")) {
                 throw new Exception($conn->error);
            }
            
            $conn->commit();
            registrar_actividad('Eliminar Múltiples Vehículos', "IDs eliminados: " . $ids_str, $_SESSION['user_id']);
            $message = count($ids) . " vehículos eliminados correctamente.";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error al eliminar: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "No se seleccionaron vehículos.";
        $message_type = "warning";
    }
}

// Fetch Vehicles
$vehicles = [];
$where_clause = "";
$filter_msg = "";

if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $estado_filter = $conn->real_escape_string($_GET['estado']);
    $where_clause = "WHERE estado = '$estado_filter'";
    $filter_msg = '<div class="alert alert-info border-0 shadow-sm rounded-3 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-filter me-2"></i>Filtrando por estado: <strong>' . htmlspecialchars($estado_filter) . '</strong></span>
                        <a href="vehiculos.php" class="btn btn-sm btn-light text-dark shadow-sm rounded-pill px-3">Limpiar filtro</a>
                   </div>';
}

$res = $conn->query("SELECT * FROM vehiculos $where_clause ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $vehicles[] = $row;
    }
}
?>

<style>
/* Polished UI Styles */
.card { border: none; border-radius: 0.75rem; }
.shadow-hover:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transition: all .2s; }
.btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
.nav-pills .nav-link { border-radius: 0.5rem; font-weight: 500; color: #6c757d; transition: all 0.2s; }
.nav-pills .nav-link:hover { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.nav-pills .nav-link.active { background-color: #0d6efd; color: white; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3); }
.table-hover tbody tr:hover { background-color: rgba(0,0,0,0.02); }
.badge { font-weight: 500; letter-spacing: 0.5px; padding: 0.5em 0.8em; }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Gestión de Vehículos</h2>
        <p class="text-muted small mb-0">Administración de flota, documentación y mantenciones.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button class="btn btn-danger rounded-pill shadow-sm d-none" id="btnBulkDelete" onclick="submitBulkDelete()">
            <i class="fas fa-trash-alt me-2"></i>Eliminar (<span id="selectedCount">0</span>)
        </button>
        <button class="btn btn-success rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-excel me-2"></i>Importar
        </button>
        <button class="btn btn-primary rounded-pill shadow-sm px-4" onclick="openModal('create')">
            <i class="fas fa-plus me-2"></i>Nuevo Vehículo
        </button>
    </div>
</div>

<?php echo $filter_msg; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert">
        <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4 rounded-4">
    <div class="card-body p-0">
        <form method="POST" id="bulkDeleteForm">
            <input type="hidden" name="action" value="bulk_delete">
            <div class="table-responsive rounded-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-uppercase small text-muted">
                            <th class="ps-4 py-3" style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th class="py-3">Patente</th>
                            <th class="py-3">Marca/Modelo</th>
                            <th class="py-3">Año</th>
                            <th class="py-3">Estado</th>
                            <th class="py-3">Cert. MLP</th>
                            <th class="py-3">Uso Actual</th>
                            <th class="py-3">Prox. Mant.</th>
                            <th class="text-end pe-4 py-3">Acciones</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted opacity-50 mb-2"><i class="fas fa-truck fa-3x"></i></div>
                                <p class="text-muted mb-0">No se encontraron vehículos registrados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td class="ps-4"><input type="checkbox" class="form-check-input vehicle-checkbox" name="ids[]" value="<?php echo $v['id']; ?>"></td>
                                <td class="fw-bold text-dark font-monospace"><?php echo htmlspecialchars($v['patente']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($v['marca']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($v['modelo']); ?></small>
                                </td>
                                <td><?php echo $v['anio'] ? '<span class="badge bg-light text-dark border">' . $v['anio'] . '</span>' : '-'; ?></td>
                                <td>
                                    <?php 
                                        $badge_class = 'bg-secondary';
                                        $icon_class = 'fa-question-circle';
                                        
                                        if($v['estado'] == 'Disponible') { 
                                            $badge_class = 'bg-success'; 
                                            $icon_class = 'fa-check-circle';
                                        } elseif($v['estado'] == 'En Taller') { 
                                            $badge_class = 'bg-danger';
                                            $icon_class = 'fa-tools';
                                        } elseif($v['estado'] == 'En Revisión') { 
                                            $badge_class = 'bg-warning text-dark';
                                            $icon_class = 'fa-clipboard-check';
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> rounded-pill bg-opacity-75">
                                        <i class="fas <?php echo $icon_class; ?> me-1"></i><?php echo $v['estado']; ?>
                                    </span>
                                </td>
                                <td><?php echo $v['certificacion_mlp'] ? date('d/m/Y', strtotime($v['certificacion_mlp'])) : '<span class="text-muted small">-</span>'; ?></td>
                                <td><?php echo $v['kilometraje'] ? htmlspecialchars($v['kilometraje']) : '<span class="text-muted small">-</span>'; ?></td>
                                <td><?php echo $v['proxima_mantencion'] ? htmlspecialchars($v['proxima_mantencion']) : '<span class="text-muted small">-</span>'; ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="ficha_vehiculo.php?id=<?php echo $v['id']; ?>" class="btn btn-icon btn-outline-info border-0" data-bs-toggle="tooltip" title="Ver Ficha">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="ficha_vehiculo.php?id=<?php echo $v['id']; ?>&mode=edit" class="btn btn-icon btn-outline-warning border-0" data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <button type="button" class="btn btn-icon btn-outline-danger border-0" onclick="deleteVehicle(<?php echo $v['id']; ?>)" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
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
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-car me-2"></i>Nuevo Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="vehicleId">
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Patente</label>
                        <input type="text" class="form-control bg-light border-0 fs-5 fw-bold text-center" name="patente" id="inputPatente" required style="text-transform: uppercase; letter-spacing: 2px;" placeholder="ABCD-12">
                        <div class="form-text text-center">Debe ser única y válida.</div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Marca</label>
                        <input type="text" class="form-control bg-light border-0" name="marca" id="inputMarca">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Modelo</label>
                        <input type="text" class="form-control bg-light border-0" name="modelo" id="inputModelo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Año</label>
                        <input type="number" class="form-control bg-light border-0" name="anio" id="inputAnio" min="1900" max="2100">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Estado</label>
                        <select class="form-select bg-light border-0" name="estado" id="inputEstado">
                            <option value="Disponible">Disponible</option>
                            <option value="En Taller">En Taller</option>
                            <option value="En Revisión">En Revisión</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Kilometraje / Horas</label>
                        <input type="text" class="form-control bg-light border-0" name="kilometraje" id="inputKilometraje">
                    </div>

                    <div class="col-12 mt-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-calendar-alt me-2"></i>Vencimientos y Fechas</h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Certificación MLP</label>
                        <input type="date" class="form-control bg-light border-0" name="certificacion_mlp" id="inputCertificacionMLP">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Próxima Mantención</label>
                        <input type="text" class="form-control bg-light border-0" name="proxima_mantencion" id="inputProximaMantencion" placeholder="Ej: 50,000 km o 10/10/2025">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">GPS (Fecha)</label>
                        <input type="date" class="form-control bg-light border-0" name="gps" id="inputGPS">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Multiflota (Fecha)</label>
                        <input type="date" class="form-control bg-light border-0" name="multiflota" id="inputMultiflota">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Guardar Vehículo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4" id="importForm">
            <div class="modal-header bg-success text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-csv me-2"></i>Importar Vehículos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="import">
                
                <!-- Pills -->
                <ul class="nav nav-pills nav-fill mb-4 bg-light rounded-pill p-1" id="importTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill" id="upload-tab" data-bs-toggle="pill" data-bs-target="#upload-pane" type="button" role="tab" aria-selected="true">Subir Archivo</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill" id="paste-tab" data-bs-toggle="pill" data-bs-target="#paste-pane" type="button" role="tab" aria-selected="false">Pegar desde Excel</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Upload Pane -->
                    <div class="tab-pane fade show active" id="upload-pane" role="tabpanel">
                        <div class="text-center p-4 border rounded-3 bg-light border-dashed position-relative mb-3" style="border-style: dashed; border-color: #dee2e6;">
                            <i class="fas fa-cloud-upload-alt fa-3x text-secondary mb-3 opacity-50"></i>
                            <h6 class="fw-bold text-dark mb-1">Selecciona o arrastra tu archivo</h6>
                            <p class="text-muted small mb-0">Soporta CSV y Excel (.xlsx, .xls)</p>
                            <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" name="file" id="importFile" accept=".csv, .txt, .xlsx, .xls" style="cursor: pointer;">
                        </div>
                        <div id="fileNameDisplay" class="text-center mb-3 d-none">
                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check me-2"></i><span id="fileNameText"></span></span>
                        </div>
                        <div class="alert alert-info small border-0 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Columnas esperadas: <em>Patente, Marca, Modelo, Año, Estado</em>...
                        </div>
                    </div>

                    <!-- Paste Pane -->
                    <div class="tab-pane fade" id="paste-pane" role="tabpanel">
                        <div class="alert alert-secondary small border-0">
                            <i class="fas fa-clipboard me-1"></i>
                            Copia tus celdas desde Excel (Ctrl+C) y pégalas aquí (Ctrl+V).
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control bg-light border-0" id="pasteArea" rows="8" placeholder="Pega aquí tus datos..."></textarea>
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
// Global variables
let vehicleModal;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap Modal
    if (typeof bootstrap !== 'undefined') {
        vehicleModal = new bootstrap.Modal(document.getElementById('vehicleModal'));
    }
    
    // Initialize Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // --- Import Logic ---
    const importForm = document.getElementById('importForm');
    if (importForm) {
        importForm.addEventListener('submit', function(e) {
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
                const rows = content.split(/\r\n|\n|\r/);
                const csvRows = rows.map(row => {
                    const columns = row.split('\t');
                    const csvColumns = columns.map(col => {
                        let cleanCol = col.replace(/"/g, '""');
                        if (cleanCol.search(/("|,|\n)/g) >= 0) {
                            cleanCol = `"${cleanCol}"`;
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
    }

    // Excel to CSV conversion logic & File Name Display
    const importFileInput = document.getElementById('importFile');
    if (importFileInput) {
        importFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Show filename
            document.getElementById('fileNameText').textContent = file.name;
            document.getElementById('fileNameDisplay').classList.remove('d-none');

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
                    importFileInput.files = dataTransfer.files;
                    
                    console.log('Converted Excel to CSV successfully');
                };
                reader.readAsArrayBuffer(file);
            }
        });
    }

    // --- Bulk Delete Logic ---
    const selectAll = document.getElementById('selectAll');
    const btnBulkDelete = document.getElementById('btnBulkDelete');
    const selectedCountSpan = document.getElementById('selectedCount');

    // Helper: Get all checkboxes
    function getCheckboxes() {
        return document.querySelectorAll('.vehicle-checkbox');
    }

    // Helper: Toggle button visibility
    function updateButtonVisibility() {
        const checkboxes = getCheckboxes();
        const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
        const count = checkedBoxes.length;
        
        if (btnBulkDelete) {
            if (count > 0) {
                btnBulkDelete.classList.remove('d-none');
                selectedCountSpan.textContent = count;
            } else {
                btnBulkDelete.classList.add('d-none');
            }
        }
    }

    // Helper: Sync "Select All" checkbox state
    function updateSelectAllState() {
        if (!selectAll) return;
        const checkboxes = getCheckboxes();
        if (checkboxes.length === 0) {
            selectAll.checked = false;
            return;
        }
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        selectAll.checked = allChecked;
    }

    // Event Delegation
    document.addEventListener('change', function(e) {
        // Handle "Select All" click
        if (e.target && e.target.id === 'selectAll') {
            const isChecked = e.target.checked;
            const checkboxes = getCheckboxes();
            checkboxes.forEach(cb => {
                cb.checked = isChecked;
            });
            updateButtonVisibility();
        }

        // Handle Individual Checkbox click
        if (e.target && e.target.classList.contains('vehicle-checkbox')) {
            updateSelectAllState();
            updateButtonVisibility();
        }
    });

    // Initial check
    updateSelectAllState();
    updateButtonVisibility();
});

// Global functions exposed to HTML
function openModal(mode) {
    document.getElementById('formAction').value = mode;
    document.getElementById('modalTitle').innerHTML = mode === 'create' ? '<i class="fas fa-car me-2"></i>Nuevo Vehículo' : '<i class="fas fa-edit me-2"></i>Editar Vehículo';
    
    if(mode === 'create') {
        document.getElementById('vehicleId').value = '';
        document.getElementById('inputPatente').value = '';
        document.getElementById('inputMarca').value = '';
        document.getElementById('inputModelo').value = '';
        document.getElementById('inputAnio').value = '';
        document.getElementById('inputEstado').value = 'Disponible';
        document.getElementById('inputKilometraje').value = '';
        document.getElementById('inputCertificacionMLP').value = '';
        document.getElementById('inputProximaMantencion').value = '';
        document.getElementById('inputGPS').value = '';
        document.getElementById('inputMultiflota').value = '';
    }
    
    if (vehicleModal) {
        vehicleModal.show();
    }
}

function deleteVehicle(id) {
    if(confirm('¿Estás seguro de que deseas eliminar este vehículo?')) {
        window.location.href = 'vehiculos.php?action=delete&id=' + id;
    }
}

function submitBulkDelete() {
    const checkedCount = document.querySelectorAll('.vehicle-checkbox:checked').length;
    if (checkedCount === 0) return;
    
    if (confirm(`¿Estás seguro de que deseas eliminar ${checkedCount} vehículos seleccionados? Esta acción no se puede deshacer.`)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>