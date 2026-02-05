<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Get ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view'; // view | edit

// Fetch Vehicle Data
$vehicle = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vehiculos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $vehicle = $res->fetch_assoc();
}

// Redirect if not found
if (!$vehicle) {
    echo "<script>window.location.href='vehiculos.php';</script>";
    exit;
}

// Fetch Maintenance History
$maintenances = [];
$stmt_m = $conn->prepare("SELECT * FROM mantenciones WHERE vehiculo_id = ? ORDER BY fecha DESC");
$stmt_m->bind_param("i", $id);
$stmt_m->execute();
$res_m = $stmt_m->get_result();
while ($row = $res_m->fetch_assoc()) {
    $maintenances[] = $row;
}

// Fetch Documents
$documents = [];
$stmt_docs = $conn->prepare("SELECT * FROM vehiculos_documentos WHERE vehiculo_id = ? ORDER BY created_at DESC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();
while ($row = $res_docs->fetch_assoc()) {
    $documents[] = $row;
}

// Handle Document Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_document') {
    $doc_nombre = $_POST['doc_nombre'];
    $doc_emision = !empty($_POST['doc_emision']) ? $_POST['doc_emision'] : NULL;
    $doc_vencimiento = !empty($_POST['doc_vencimiento']) ? $_POST['doc_vencimiento'] : NULL;
    
    if (isset($_FILES['doc_archivo']) && $_FILES['doc_archivo']['error'] == 0) {
        $upload_dir = '../uploads/vehiculos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['doc_archivo']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('veh_doc_') . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['doc_archivo']['tmp_name'], $upload_path)) {
            $sql_doc = "INSERT INTO vehiculos_documentos (nombre, archivo, fecha_emision, fecha_vencimiento, vehiculo_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_doc = $conn->prepare($sql_doc);
            $stmt_doc->bind_param("ssssi", $doc_nombre, $new_filename, $doc_emision, $doc_vencimiento, $id);
            
            if ($stmt_doc->execute()) {
                registrar_actividad('Subir Documento Vehículo', "Documento '$doc_nombre' subido para vehículo ID: $id", $_SESSION['user_id']);
                $message = "Documento subido correctamente.";
                // Refresh docs
                $stmt_docs->execute();
                $res_docs = $stmt_docs->get_result();
                $documents = [];
                while ($row = $res_docs->fetch_assoc()) {
                    $documents[] = $row;
                }
            } else {
                $message = "Error al guardar en BD: " . $stmt_doc->error;
            }
        } else {
            $message = "Error al mover el archivo.";
        }
    } else {
        $message = "Debe seleccionar un archivo válido.";
    }
}

// Handle Document Delete
if (isset($_GET['delete_doc'])) {
    $doc_id = intval($_GET['delete_doc']);
    $stmt_check = $conn->prepare("SELECT archivo FROM vehiculos_documentos WHERE id = ? AND vehiculo_id = ?");
    $stmt_check->bind_param("ii", $doc_id, $id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($row = $res_check->fetch_assoc()) {
        $file_path = '../uploads/vehiculos/' . $row['archivo'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $stmt_del = $conn->prepare("DELETE FROM vehiculos_documentos WHERE id = ?");
        $stmt_del->bind_param("i", $doc_id);
        if ($stmt_del->execute()) {
            registrar_actividad('Eliminar Documento Vehículo', "Documento ID: $doc_id eliminado de vehículo ID: $id", $_SESSION['user_id']);
            echo "<script>window.location.href='ficha_vehiculo.php?id=$id&msg=deleted';</script>";
            exit;
        }
    }
}

// Handle Update
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    // Normalize patente
    $patente = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['patente'])));
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $anio = !empty($_POST['anio']) ? $_POST['anio'] : NULL;
    $estado = $_POST['estado'];
    
    $revision_tecnica = !empty($_POST['revision_tecnica']) ? $_POST['revision_tecnica'] : NULL;
    $seguro_vencimiento = !empty($_POST['seguro_vencimiento']) ? $_POST['seguro_vencimiento'] : NULL;
    $permiso_circulacion = !empty($_POST['permiso_circulacion']) ? $_POST['permiso_circulacion'] : NULL;

    $certificacion_mlp = !empty($_POST['certificacion_mlp']) ? $_POST['certificacion_mlp'] : NULL;
    $kilometraje = !empty($_POST['kilometraje']) ? $_POST['kilometraje'] : NULL;
    $proxima_mantencion = !empty($_POST['proxima_mantencion']) ? $_POST['proxima_mantencion'] : NULL;
    $gps = !empty($_POST['gps']) ? $_POST['gps'] : NULL;
    $multiflota = !empty($_POST['multiflota']) ? $_POST['multiflota'] : NULL;

    $update_sql = "UPDATE vehiculos SET patente=?, marca=?, modelo=?, anio=?, estado=?, revision_tecnica=?, seguro_vencimiento=?, permiso_circulacion=?, certificacion_mlp=?, kilometraje=?, proxima_mantencion=?, gps=?, multiflota=? WHERE id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssisssssssssi", $patente, $marca, $modelo, $anio, $estado, $revision_tecnica, $seguro_vencimiento, $permiso_circulacion, $certificacion_mlp, $kilometraje, $proxima_mantencion, $gps, $multiflota, $id);

    try {
        if ($stmt->execute()) {
            registrar_actividad('Editar Ficha Vehículo', "Vehículo ID: $id actualizado desde ficha", $_SESSION['user_id']);
            $message = "Vehículo actualizado correctamente.";
            // Refresh data
            $stmt_refresh = $conn->prepare("SELECT * FROM vehiculos WHERE id = ?");
            $stmt_refresh->bind_param("i", $id);
            $stmt_refresh->execute();
            $vehicle = $stmt_refresh->get_result()->fetch_assoc();
            $mode = 'view'; // Switch back to view mode
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $message = "Error al actualizar: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="vehiculos.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <h2 class="d-inline-block align-middle">Ficha del Vehículo</h2>
    </div>
    <div>
        <?php if($mode == 'view'): ?>
            <a href="ficha_vehiculo.php?id=<?php echo $id; ?>&mode=edit" class="btn btn-warning text-white">
                <i class="fas fa-pen me-2"></i>Editar Ficha
            </a>
        <?php else: ?>
            <a href="ficha_vehiculo.php?id=<?php echo $id; ?>&mode=view" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i>Cancelar Edición
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Sidebar / Avatar Card -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 text-center p-4">
            <div class="card-body">
                <div class="avatar-placeholder bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px;">
                    <i class="fas fa-truck fa-5x text-secondary"></i>
                </div>
                <h4 class="card-title"><?php echo htmlspecialchars($vehicle['patente']); ?></h4>
                <p class="text-muted mb-1"><?php echo htmlspecialchars($vehicle['marca'] . ' ' . $vehicle['modelo']); ?></p>
                
                <?php 
                $badge_class = 'bg-secondary';
                if ($vehicle['estado'] == 'Disponible') $badge_class = 'bg-success';
                if ($vehicle['estado'] == 'En Taller') $badge_class = 'bg-danger';
                if ($vehicle['estado'] == 'En Revisión') $badge_class = 'bg-warning text-dark';
                ?>
                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($vehicle['estado']); ?></span>
                
                <hr class="my-4">
                
                <div class="text-start">
                    <small class="text-muted d-block text-uppercase fw-bold mb-2">Información Rápida</small>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-calendar-alt me-2 text-primary"></i>Año:</span>
                        <span class="fw-bold"><?php echo $vehicle['anio'] ? $vehicle['anio'] : '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="vehicleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">Datos del Vehículo</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">Historial de Mantenciones</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button" role="tab" aria-controls="docs" aria-selected="false">Documentos</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="vehicleTabsContent">
                    <!-- DETAILS TAB -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <?php if ($mode == 'edit'): ?>
                            <!-- EDIT FORM -->
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                <div class="row g-3">
                                    <h5 class="border-bottom pb-2 mb-3">Información General</h5>
                                    <div class="col-md-6">
                                        <label class="form-label">Patente</label>
                                        <input type="text" class="form-control" name="patente" value="<?php echo htmlspecialchars($vehicle['patente']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Marca</label>
                                        <input type="text" class="form-control" name="marca" value="<?php echo htmlspecialchars($vehicle['marca']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Modelo</label>
                                        <input type="text" class="form-control" name="modelo" value="<?php echo htmlspecialchars($vehicle['modelo']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Año</label>
                                        <input type="number" class="form-control" name="anio" value="<?php echo $vehicle['anio']; ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Estado</label>
                                        <select class="form-select" name="estado">
                                            <option value="Disponible" <?php echo $vehicle['estado'] == 'Disponible' ? 'selected' : ''; ?>>Disponible</option>
                                            <option value="En Taller" <?php echo $vehicle['estado'] == 'En Taller' ? 'selected' : ''; ?>>En Taller</option>
                                            <option value="En Revisión" <?php echo $vehicle['estado'] == 'En Revisión' ? 'selected' : ''; ?>>En Revisión</option>
                                        </select>
                                    </div>

                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Datos Operacionales</h5>
                                    <div class="col-md-6">
                                        <label class="form-label">Kilometraje/Horas</label>
                                        <input type="text" class="form-control" name="kilometraje" value="<?php echo htmlspecialchars($vehicle['kilometraje'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Próxima Mantención</label>
                                        <input type="text" class="form-control" name="proxima_mantencion" value="<?php echo htmlspecialchars($vehicle['proxima_mantencion'] ?? ''); ?>">
                                    </div>

                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Vencimientos y Documentación</h5>
                                    <div class="col-md-4">
                                        <label class="form-label">Revisión Técnica</label>
                                        <input type="date" class="form-control" name="revision_tecnica" value="<?php echo $vehicle['revision_tecnica']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Seguro Obligatorio</label>
                                        <input type="date" class="form-control" name="seguro_vencimiento" value="<?php echo $vehicle['seguro_vencimiento']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Permiso Circulación</label>
                                        <input type="date" class="form-control" name="permiso_circulacion" value="<?php echo $vehicle['permiso_circulacion']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Certificación MLP</label>
                                        <input type="date" class="form-control" name="certificacion_mlp" value="<?php echo $vehicle['certificacion_mlp']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">GPS (Fecha)</label>
                                        <input type="date" class="form-control" name="gps" value="<?php echo $vehicle['gps']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Multiflota (Fecha)</label>
                                        <input type="date" class="form-control" name="multiflota" value="<?php echo $vehicle['multiflota']; ?>">
                                    </div>

                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- VIEW MODE -->
                            <div class="row g-4">
                                <h5 class="text-primary mb-3">Detalles Técnicos</h5>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Patente</label>
                                    <p class="fs-5 fw-bold border-bottom pb-2"><?php echo htmlspecialchars($vehicle['patente']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Marca / Modelo</label>
                                    <p class="fs-5 border-bottom pb-2"><?php echo htmlspecialchars($vehicle['marca'] . ' / ' . $vehicle['modelo']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Año</label>
                                    <p class="fs-5 border-bottom pb-2"><?php echo $vehicle['anio'] ? $vehicle['anio'] : '-'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Estado Actual</label>
                                    <p class="fs-5 border-bottom pb-2"><?php echo htmlspecialchars($vehicle['estado']); ?></p>
                                </div>

                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Uso Actual</label>
                                    <p class="fs-5 border-bottom pb-2"><?php echo htmlspecialchars($vehicle['kilometraje'] ?? '-'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Próxima Mantención</label>
                                    <p class="fs-5 border-bottom pb-2"><?php echo htmlspecialchars($vehicle['proxima_mantencion'] ?? '-'); ?></p>
                                </div>

                                <h5 class="text-primary mb-3 mt-4">Control de Vencimientos</h5>
                                
                                <?php
                                function renderExpiration($label, $date) {
                                    echo '<div class="col-md-4 mb-3">';
                                    echo '<label class="text-muted small text-uppercase">' . $label . '</label>';
                                    echo '<div class="p-3 border rounded bg-light mt-2">';
                                    if ($date) {
                                        $venc = strtotime($date);
                                        $days = floor(($venc - time()) / (60 * 60 * 24));
                                        $class = $days < 0 ? 'text-danger' : ($days < 30 ? 'text-warning' : 'text-success');
                                        $icon = $days < 0 ? 'exclamation-triangle' : ($days < 30 ? 'exclamation-circle' : 'check-circle');
                                        
                                        echo "<div class='$class fw-bold fs-5 mb-1'><i class='fas fa-$icon me-2'></i>" . date('d/m/Y', $venc) . "</div>";
                                        if ($days < 0) echo "<small class='text-danger'>Vencido hace " . abs($days) . " días</small>";
                                        elseif ($days == 0) echo "<small class='text-warning'>Vence hoy</small>";
                                        else echo "<small class='text-muted'>Vence en $days días</small>";
                                    } else {
                                        echo '<span class="text-muted">No registrado</span>';
                                    }
                                    echo '</div></div>';
                                }

                                renderExpiration("Revisión Técnica", $vehicle['revision_tecnica']);
                                renderExpiration("Seguro Obligatorio", $vehicle['seguro_vencimiento']);
                                renderExpiration("Permiso Circulación", $vehicle['permiso_circulacion']);
                                renderExpiration("Certificación MLP", $vehicle['certificacion_mlp']);
                                renderExpiration("GPS", $vehicle['gps']);
                                renderExpiration("Multiflota", $vehicle['multiflota']);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- HISTORY TAB -->
                    <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-primary mb-0">Registro de Mantenciones</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Taller</th>
                                        <th>Km</th>
                                        <th>Costo</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($maintenances)): ?>
                                        <tr><td colspan="6" class="text-center text-muted p-4">No hay mantenciones registradas.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($maintenances as $m): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($m['fecha'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($m['tipo_mantencion'] == 'Preventiva') ? 'bg-info' : 'bg-warning'; ?>">
                                                        <?php echo htmlspecialchars($m['tipo_mantencion']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($m['taller']); ?></td>
                                                <td><?php echo number_format($m['kilometraje'], 0, ',', '.'); ?></td>
                                                <td>$<?php echo number_format($m['costo'], 0, ',', '.'); ?></td>
                                                <td><?php echo htmlspecialchars($m['descripcion']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- DOCUMENTS TAB -->
                    <div class="tab-pane fade" id="docs" role="tabpanel" aria-labelledby="docs-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-primary mb-0">Documentos del Vehículo</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                                <i class="fas fa-plus me-2"></i>Subir Documento
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Documento</th>
                                        <th>Fecha Emisión</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documents)): ?>
                                        <tr><td colspan="5" class="text-center text-muted p-4">No hay documentos cargados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <?php
                                            $estado_doc = 'Vigente';
                                            $class_doc = 'success';
                                            if ($doc['fecha_vencimiento']) {
                                                $venc = strtotime($doc['fecha_vencimiento']);
                                                $days = floor(($venc - time()) / (60 * 60 * 24));
                                                if ($days < 0) {
                                                    $estado_doc = 'Vencido';
                                                    $class_doc = 'danger';
                                                } elseif ($days < 30) {
                                                    $estado_doc = 'Por Vencer';
                                                    $class_doc = 'warning';
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                                    <a href="../uploads/vehiculos/<?php echo $doc['archivo']; ?>" target="_blank" class="text-decoration-none fw-bold text-dark">
                                                        <?php echo htmlspecialchars($doc['nombre']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $doc['fecha_emision'] ? date('d/m/Y', strtotime($doc['fecha_emision'])) : '-'; ?></td>
                                                <td><?php echo $doc['fecha_vencimiento'] ? date('d/m/Y', strtotime($doc['fecha_vencimiento'])) : '-'; ?></td>
                                                <td><span class="badge bg-<?php echo $class_doc; ?>"><?php echo $estado_doc; ?></span></td>
                                                <td>
                                                    <a href="ficha_vehiculo.php?id=<?php echo $id; ?>&delete_doc=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Está seguro de eliminar este documento?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Archivo (PDF)</label>
                        <input type="file" class="form-control" name="doc_archivo" id="doc_archivo" accept="application/pdf" required onchange="detectDateFromDoc()">
                        <div id="date_detection_msg" class="form-text" style="display:none;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre del Documento</label>
                        <input type="text" class="form-control" name="doc_nombre" id="doc_nombre" required placeholder="Ej: Permiso de Circulación">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Fecha Emisión</label>
                            <input type="date" class="form-control" name="doc_emision" id="doc_emision">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fecha Vencimiento</label>
                            <input type="date" class="form-control" name="doc_vencimiento" id="doc_vencimiento">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function detectDateFromDoc() {
    const fileInput = document.getElementById('doc_archivo');
    const msgDiv = document.getElementById('date_detection_msg');
    const vencimientoInput = document.getElementById('doc_vencimiento');
    const nombreInput = document.getElementById('doc_nombre');
    const vehiclePatente = "<?php echo isset($vehicle['patente']) ? $vehicle['patente'] : ''; ?>";
    
    if (fileInput.files.length === 0) return;
    
    const file = fileInput.files[0];
    if (file.type !== 'application/pdf') {
        return;
    }

    msgDiv.style.display = 'block';
    msgDiv.className = 'form-text text-primary';
    msgDiv.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Analizando documento (Fecha, Tipo, Patente)...';
    
    const formData = new FormData();
    formData.append('doc_archivo', file);
    formData.append('entity_type', 'vehicle');
    formData.append('vehicle_patente', vehiclePatente);
    
    fetch('../scripts/parse_doc_dates.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msgDiv.style.display = 'block';
        let msgHtml = '';
        
        // 1. Identity
        if (data.success && data.identity_match === false) {
             msgHtml += `<div class="text-danger fw-bold mb-1"><i class="fas fa-exclamation-triangle"></i> Advertencia: ${data.identity_message}</div>`;
        } else if (data.success && data.identity_match === true) {
             msgHtml += `<div class="text-success small mb-1"><i class="fas fa-check-circle"></i> Patente verificada</div>`;
        }

        // 2. Classification
        if (data.success && data.doc_type) {
            nombreInput.value = data.doc_type;
            nombreInput.style.backgroundColor = '#e8f5e9';
            setTimeout(() => { nombreInput.style.backgroundColor = ''; }, 2000);
            msgHtml += `<div class="text-success small mb-1"><i class="fas fa-tag"></i> Tipo: ${data.doc_type}</div>`;
        }

        // 3. Date
        if (data.success && data.date) {
            vencimientoInput.value = data.date;
            vencimientoInput.style.backgroundColor = '#e8f5e9';
            setTimeout(() => { vencimientoInput.style.backgroundColor = ''; }, 2000);
            msgHtml += `<div class="text-success small mb-1"><i class="fas fa-calendar-check"></i> Fecha: ${data.date}</div>`;
        }
        
        if (!data.success) {
            msgHtml += `<div class="text-warning small">${data.message}</div>`;
        }

        msgDiv.innerHTML = msgHtml;
    })
    .catch(error => {
        console.error('Error:', error);
        msgDiv.style.display = 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
