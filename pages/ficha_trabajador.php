<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Get ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view'; // view | edit

// Fetch Worker Data
$worker = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM trabajadores WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $worker = $res->fetch_assoc();
}

// Redirect if not found
if (!$worker) {
    echo "<script>window.location.href='trabajadores.php';</script>";
    exit;
}

// Fetch Documents
$documents = [];
$stmt_docs = $conn->prepare("SELECT * FROM certificados WHERE trabajador_id = ? ORDER BY fecha_emision DESC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();
while ($row = $res_docs->fetch_assoc()) {
    $documents[] = $row;
}

// Handle Update
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        // Update Worker Logic
        $nombre = $_POST['nombre'];
        $rut = $_POST['rut'];
        $tipo_contrato = !empty($_POST['tipo_contrato']) ? $_POST['tipo_contrato'] : NULL;
        $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : NULL;
        $licencia_vencimiento = !empty($_POST['licencia_vencimiento']) ? $_POST['licencia_vencimiento'] : NULL;
        $examen_salud = !empty($_POST['examen_salud']) ? $_POST['examen_salud'] : NULL;
        $induccion_hombre_nuevo = !empty($_POST['induccion_hombre_nuevo']) ? $_POST['induccion_hombre_nuevo'] : NULL;
        $odi_puerto_desaladora = !empty($_POST['odi_puerto_desaladora']) ? $_POST['odi_puerto_desaladora'] : NULL;

        $licencia_interna_mlp = !empty($_POST['licencia_interna_mlp']) ? $_POST['licencia_interna_mlp'] : NULL;
        $oximetria = !empty($_POST['oximetria']) ? $_POST['oximetria'] : NULL;
        $psicosensotecnico = !empty($_POST['psicosensotecnico']) ? $_POST['psicosensotecnico'] : NULL;
        $manejo_defensivo = !empty($_POST['manejo_defensivo']) ? $_POST['manejo_defensivo'] : NULL;

        $update_sql = "UPDATE trabajadores SET nombre=?, rut=?, tipo_contrato=?, fecha_ingreso=?, licencia_vencimiento=?, examen_salud=?, induccion_hombre_nuevo=?, odi_puerto_desaladora=?, licencia_interna_mlp=?, oximetria=?, psicosensotecnico=?, manejo_defensivo=? WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssissssssssi", $nombre, $rut, $tipo_contrato, $fecha_ingreso, $licencia_vencimiento, $examen_salud, $induccion_hombre_nuevo, $odi_puerto_desaladora, $licencia_interna_mlp, $oximetria, $psicosensotecnico, $manejo_defensivo, $id);

        try {
            if ($stmt->execute()) {
                registrar_actividad('Editar Ficha Trabajador', "Trabajador ID: $id actualizado desde ficha", $_SESSION['user_id']);
                $message = "Trabajador actualizado correctamente.";
                $messageType = "success";
                // Refresh data
                $stmt_refresh = $conn->prepare("SELECT * FROM trabajadores WHERE id = ?");
                $stmt_refresh->bind_param("i", $id);
                $stmt_refresh->execute();
                $worker = $stmt_refresh->get_result()->fetch_assoc();
                $mode = 'view'; // Switch back to view mode
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $message = "Error al actualizar: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'upload_document') {
        // Upload Document Logic
        $doc_nombre = $_POST['doc_nombre'];
        $doc_emision = !empty($_POST['doc_emision']) ? $_POST['doc_emision'] : NULL;
        $doc_vencimiento = !empty($_POST['doc_vencimiento']) ? $_POST['doc_vencimiento'] : NULL;
        
        if (isset($_FILES['doc_archivo']) && $_FILES['doc_archivo']['error'] == 0) {
            $upload_dir = '../uploads/certificados/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['doc_archivo']['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid('cert_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['doc_archivo']['tmp_name'], $upload_path)) {
                $sql_doc = "INSERT INTO certificados (nombre, archivo, fecha_emision, fecha_vencimiento, trabajador_id) VALUES (?, ?, ?, ?, ?)";
                $stmt_doc = $conn->prepare($sql_doc);
                $stmt_doc->bind_param("ssssi", $doc_nombre, $new_filename, $doc_emision, $doc_vencimiento, $id);
                
                if ($stmt_doc->execute()) {
                    registrar_actividad('Subir Documento', "Documento '$doc_nombre' subido para trabajador ID: $id", $_SESSION['user_id']);
                    $message = "Documento subido correctamente.";
                    $messageType = "success";
                    // Refresh documents
                    $stmt_docs->execute();
                    $res_docs = $stmt_docs->get_result();
                    $documents = [];
                    while ($row = $res_docs->fetch_assoc()) {
                        $documents[] = $row;
                    }
                } else {
                    $message = "Error al guardar en base de datos: " . $stmt_doc->error;
                    $messageType = "danger";
                }
            } else {
                $message = "Error al mover el archivo subido.";
                $messageType = "danger";
            }
        } else {
            $message = "Error: Debe seleccionar un archivo válido.";
            $messageType = "danger";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_document') {
        // Delete Document Logic
        $doc_id = intval($_POST['doc_id']);
        
        // Get file name first
        $stmt_get = $conn->prepare("SELECT archivo FROM certificados WHERE id = ? AND trabajador_id = ?");
        $stmt_get->bind_param("ii", $doc_id, $id);
        $stmt_get->execute();
        $res_get = $stmt_get->get_result();
        
        if ($row_doc = $res_get->fetch_assoc()) {
            $file_path = '../uploads/certificados/' . $row_doc['archivo'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $stmt_del = $conn->prepare("DELETE FROM certificados WHERE id = ?");
            $stmt_del->bind_param("i", $doc_id);
            if ($stmt_del->execute()) {
                registrar_actividad('Eliminar Documento', "Documento ID: $doc_id eliminado de trabajador ID: $id", $_SESSION['user_id']);
                $message = "Documento eliminado correctamente.";
                $messageType = "success";
                // Refresh documents
                $stmt_docs->execute();
                $res_docs = $stmt_docs->get_result();
                $documents = [];
                while ($row = $res_docs->fetch_assoc()) {
                    $documents[] = $row;
                }
            } else {
                $message = "Error al eliminar de base de datos.";
                $messageType = "danger";
            }
        }
    }
}
?>

<style>
/* UI Polish for Ficha Trabajador */
.card { border: none; border-radius: 0.75rem; }
.shadow-hover:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transition: all .2s; }
.btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
.nav-pills .nav-link { border-radius: 0.5rem; font-weight: 500; color: #6c757d; transition: all 0.2s; }
.nav-pills .nav-link:hover { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.nav-pills .nav-link.active { background-color: #0d6efd; color: white; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3); }
.avatar-profile { width: 100px; height: 100px; font-size: 2.5rem; font-weight: bold; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: #e9ecef; color: #495057; }
.info-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: #6c757d; margin-bottom: 0.25rem; }
.info-value { font-size: 1rem; font-weight: 500; color: #212529; }
.doc-card { transition: all 0.2s; border: 1px solid #e9ecef; }
.doc-card:hover { border-color: #0d6efd; background-color: #f8f9fa; }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center mb-3 mb-md-0">
        <a href="trabajadores.php" class="btn btn-outline-secondary btn-icon me-3 shadow-sm" data-bs-toggle="tooltip" title="Volver">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="fw-bold text-dark mb-0">Ficha del Trabajador</h2>
            <p class="text-muted small mb-0">Gestión de información y documentación.</p>
        </div>
    </div>
    <div>
        <?php if($mode == 'view'): ?>
            <a href="ficha_trabajador.php?id=<?php echo $id; ?>&mode=edit" class="btn btn-warning text-white rounded-pill shadow-sm px-4">
                <i class="fas fa-pen me-2"></i>Editar Ficha
            </a>
        <?php else: ?>
            <a href="ficha_trabajador.php?id=<?php echo $id; ?>&mode=view" class="btn btn-secondary rounded-pill shadow-sm px-4">
                <i class="fas fa-times me-2"></i>Cancelar Edición
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert">
        <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Sidebar / Avatar Card -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 text-center p-4 border-0 shadow-sm bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <div class="avatar-profile shadow-sm">
                        <?php echo strtoupper(substr($worker['nombre'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($worker['nombre']); ?></h4>
                <p class="text-muted font-monospace mb-2"><?php echo htmlspecialchars($worker['rut']); ?></p>
                <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">Trabajador Activo</span>
                
                <hr class="my-4 opacity-10">
                
                <div class="text-start">
                    <small class="text-muted d-block text-uppercase fw-bold mb-3 ls-1">Información Rápida</small>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded hover-bg-light">
                        <span class="text-muted"><i class="fas fa-file-contract me-2 text-primary"></i>Contrato</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($worker['tipo_contrato'] ?? '-'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded hover-bg-light">
                        <span class="text-muted"><i class="fas fa-briefcase me-2 text-info"></i>Cargo</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($worker['cargo_nombre'] ?? 'Sin Cargo'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-8 mb-4">
        <div class="card h-100 border-0 shadow-sm bg-white">
            <div class="card-body p-4">
                <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded-pill" id="workerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill" id="details-tab" data-bs-toggle="pill" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">Datos Personales</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill" id="docs-tab" data-bs-toggle="pill" data-bs-target="#docs" type="button" role="tab" aria-controls="docs" aria-selected="false">Documentos</button>
                    </li>
                </ul>

                <div class="tab-content" id="workerTabsContent">
                    <!-- DETAILS TAB -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <?php if ($mode == 'edit'): ?>
                            <!-- EDIT FORM -->
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">Nombre Completo</label>
                                        <input type="text" class="form-control bg-light border-0" name="nombre" value="<?php echo htmlspecialchars($worker['nombre']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">RUT</label>
                                        <input type="text" class="form-control bg-light border-0" name="rut" value="<?php echo htmlspecialchars($worker['rut']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">Tipo de Contrato</label>
                                        <select class="form-select bg-light border-0" name="tipo_contrato">
                                            <option value="">Seleccionar...</option>
                                            <option value="Plazo Fijo" <?php echo ($worker['tipo_contrato'] == 'Plazo Fijo') ? 'selected' : ''; ?>>Plazo Fijo</option>
                                            <option value="Indefinido" <?php echo ($worker['tipo_contrato'] == 'Indefinido') ? 'selected' : ''; ?>>Indefinido</option>
                                            <option value="Por Obra" <?php echo ($worker['tipo_contrato'] == 'Por Obra') ? 'selected' : ''; ?>>Por Obra</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Spacer or other field -->
                                    </div>

                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-notes-medical me-2"></i>Salud y Seguridad</h6>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Examen de Salud</label>
                                        <input type="date" class="form-control bg-light border-0" name="examen_salud" value="<?php echo isset($worker['examen_salud']) ? $worker['examen_salud'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Inducción H. Nuevo</label>
                                        <input type="date" class="form-control bg-light border-0" name="induccion_hombre_nuevo" value="<?php echo isset($worker['induccion_hombre_nuevo']) ? $worker['induccion_hombre_nuevo'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">ODI Pto. Desaladora</label>
                                        <input type="date" class="form-control bg-light border-0" name="odi_puerto_desaladora" value="<?php echo isset($worker['odi_puerto_desaladora']) ? $worker['odi_puerto_desaladora'] : ''; ?>">
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-id-card me-2"></i>Documentación y Licencias</h6>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Licencia Municipal</label>
                                        <input type="date" class="form-control bg-light border-0" name="licencia_vencimiento" value="<?php echo isset($worker['licencia_vencimiento']) ? $worker['licencia_vencimiento'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Licencia Interna MLP</label>
                                        <input type="date" class="form-control bg-light border-0" name="licencia_interna_mlp" value="<?php echo isset($worker['licencia_interna_mlp']) ? $worker['licencia_interna_mlp'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Oximetría</label>
                                        <input type="date" class="form-control bg-light border-0" name="oximetria" value="<?php echo isset($worker['oximetria']) ? $worker['oximetria'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Psicosensotécnico</label>
                                        <input type="date" class="form-control bg-light border-0" name="psicosensotecnico" value="<?php echo isset($worker['psicosensotecnico']) ? $worker['psicosensotecnico'] : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted">Manejo Defensivo</label>
                                        <input type="date" class="form-control bg-light border-0" name="manejo_defensivo" value="<?php echo isset($worker['manejo_defensivo']) ? $worker['manejo_defensivo'] : ''; ?>">
                                    </div>
                                    <div class="col-12 mt-4 text-end">
                                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- VIEW MODE -->
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="info-label">Nombre Completo</label>
                                    <div class="info-value border-bottom pb-2"><?php echo htmlspecialchars($worker['nombre']); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="info-label">RUT</label>
                                    <div class="info-value border-bottom pb-2 font-monospace"><?php echo htmlspecialchars($worker['rut']); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="info-label">Tipo de Contrato</label>
                                    <div class="info-value border-bottom pb-2">
                                        <?php echo htmlspecialchars($worker['tipo_contrato'] ?? '-'); ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="info-label">Examen de Salud</label>
                                    <div class="info-value border-bottom pb-2">
                                        <?php echo !empty($worker['examen_salud']) ? date('d/m/Y', strtotime($worker['examen_salud'])) : '<span class="text-muted fst-italic">No registrado</span>'; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="info-label">Inducción Hombre Nuevo</label>
                                    <div class="info-value border-bottom pb-2">
                                        <?php echo !empty($worker['induccion_hombre_nuevo']) ? date('d/m/Y', strtotime($worker['induccion_hombre_nuevo'])) : '<span class="text-muted fst-italic">No registrada</span>'; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="info-label">ODI Puerto Desaladora</label>
                                    <div class="info-value border-bottom pb-2">
                                        <?php echo !empty($worker['odi_puerto_desaladora']) ? date('d/m/Y', strtotime($worker['odi_puerto_desaladora'])) : '<span class="text-muted fst-italic">No registrada</span>'; ?>
                                    </div>
                                </div>
                                
                                <h6 class="col-12 mt-4 text-primary fw-bold border-bottom pb-2"><i class="fas fa-calendar-alt me-2"></i>Control de Vencimientos</h6>
                                
                                <?php
                                function renderDateCard($label, $date) {
                                    echo '<div class="col-md-4 mb-3">';
                                    echo '<div class="p-3 border rounded-3 bg-light h-100 position-relative overflow-hidden shadow-hover">';
                                    
                                    if ($date) {
                                        $venc = strtotime($date);
                                        $days = floor(($venc - time()) / (60 * 60 * 24));
                                        
                                        $statusColor = $days < 0 ? 'danger' : ($days < 30 ? 'warning' : 'success');
                                        $icon = $days < 0 ? 'times-circle' : ($days < 30 ? 'exclamation-circle' : 'check-circle');
                                        
                                        // Status bar on left
                                        echo '<div class="position-absolute top-0 start-0 bottom-0 bg-' . $statusColor . '" style="width: 4px;"></div>';
                                        
                                        echo '<label class="small text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">' . $label . '</label>';
                                        echo '<div class="d-flex align-items-center mt-1">';
                                        echo '<i class="fas fa-' . $icon . ' text-' . $statusColor . ' fs-4 me-2"></i>';
                                        echo '<div>';
                                        echo '<div class="fw-bold text-dark fs-6">' . date('d/m/Y', $venc) . '</div>';
                                        
                                        if ($days < 0) echo '<small class="text-danger fw-bold">Vencido hace ' . abs($days) . ' días</small>';
                                        elseif ($days == 0) echo '<small class="text-warning fw-bold">Vence hoy</small>';
                                        else echo '<small class="text-' . ($days < 30 ? 'warning' : 'muted') . '">Vence en ' . $days . ' días</small>';
                                        echo '</div></div>';
                                        
                                    } else {
                                        echo '<div class="position-absolute top-0 start-0 bottom-0 bg-secondary" style="width: 4px;"></div>';
                                        echo '<label class="small text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">' . $label . '</label>';
                                        echo '<div class="d-flex align-items-center mt-2">';
                                        echo '<i class="fas fa-minus-circle text-muted fs-4 me-2"></i>';
                                        echo '<span class="text-muted small fst-italic">No registrado</span>';
                                        echo '</div>';
                                    }
                                    echo '</div></div>';
                                }
                                
                                renderDateCard("Licencia Municipal", $worker['licencia_vencimiento']);
                                renderDateCard("Licencia Interna MLP", $worker['licencia_interna_mlp']);
                                renderDateCard("Oximetría", $worker['oximetria']);
                                renderDateCard("Psicosensotécnico", $worker['psicosensotecnico']);
                                renderDateCard("Manejo Defensivo", $worker['manejo_defensivo']);
                                renderDateCard("Examen de Salud", $worker['examen_salud']);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- DOCUMENTS TAB -->
                    <div class="tab-pane fade" id="docs" role="tabpanel" aria-labelledby="docs-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold mb-1">Documentos Cargados</h5>
                                <p class="text-muted small mb-0">Gestiona los certificados y archivos del trabajador.</p>
                            </div>
                            <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Subir Documento
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle border-light">
                                <thead class="table-light">
                                    <tr class="text-uppercase small text-muted">
                                        <th class="border-0 rounded-start">Documento</th>
                                        <th class="border-0">Emisión</th>
                                        <th class="border-0">Vencimiento</th>
                                        <th class="border-0 text-end rounded-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <div class="text-muted opacity-50 mb-2"><i class="fas fa-folder-open fa-3x"></i></div>
                                                <p class="text-muted mb-0">No hay documentos registrados.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-light rounded p-2 me-3 text-primary">
                                                            <i class="fas fa-file-pdf fa-lg"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($doc['nombre']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-muted small"><?php echo $doc['fecha_emision'] ? date('d/m/Y', strtotime($doc['fecha_emision'])) : '-'; ?></td>
                                                <td>
                                                    <?php 
                                                    if ($doc['fecha_vencimiento']) {
                                                        $venc = strtotime($doc['fecha_vencimiento']);
                                                        $class = $venc < time() ? 'danger' : ($venc < time() + 30*24*3600 ? 'warning' : 'success');
                                                        $badge = $venc < time() ? 'Vencido' : ($venc < time() + 30*24*3600 ? 'Por Vencer' : 'Vigente');
                                                        echo "<span class='badge bg-$class bg-opacity-10 text-$class border border-$class border-opacity-25 rounded-pill px-3'>" . date('d/m/Y', $venc) . "</span>";
                                                    } else {
                                                        echo '<span class="text-muted small">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <a href="../uploads/certificados/<?php echo htmlspecialchars($doc['archivo']); ?>" target="_blank" class="btn btn-icon btn-outline-info border-0 me-1" data-bs-toggle="tooltip" title="Ver Archivo">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este documento?');">
                                                            <input type="hidden" name="action" value="delete_document">
                                                            <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                            <button type="submit" class="btn btn-icon btn-outline-danger border-0" data-bs-toggle="tooltip" title="Eliminar">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </div>
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
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold" id="uploadModalLabel"><i class="fas fa-cloud-upload-alt me-2"></i>Subir Documento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="upload_document">
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Archivo</label>
                        <div class="text-center p-4 border rounded-3 bg-light border-dashed position-relative" style="border-style: dashed; border-color: #dee2e6;">
                            <i class="fas fa-file-pdf fa-3x text-secondary mb-3 opacity-50"></i>
                            <h6 class="fw-bold text-dark mb-1">Selecciona o arrastra tu archivo</h6>
                            <p class="text-muted small mb-3">Soporta PDF, JPG, PNG</p>
                            <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" id="doc_archivo" name="doc_archivo" required onchange="detectDateFromDoc()" style="cursor: pointer;">
                            <div id="file_name_display" class="badge bg-primary rounded-pill px-3 py-2 d-none"></div>
                        </div>
                        <div id="date_detection_msg" class="form-text mt-2" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="doc_nombre" class="form-label small fw-bold text-muted">Nombre del Documento</label>
                        <input type="text" class="form-control bg-light border-0" id="doc_nombre" name="doc_nombre" required placeholder="Ej: Certificado de Antecedentes">
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="doc_emision" class="form-label small fw-bold text-muted">Fecha Emisión</label>
                            <input type="date" class="form-control bg-light border-0" id="doc_emision" name="doc_emision">
                        </div>
                        <div class="col-md-6">
                            <label for="doc_vencimiento" class="form-label small fw-bold text-muted">Fecha Vencimiento</label>
                            <input type="date" class="form-control bg-light border-0" id="doc_vencimiento" name="doc_vencimiento">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Subir Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize Tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// File Input Name Display
document.getElementById('doc_archivo').addEventListener('change', function(e) {
    var fileName = e.target.files[0].name;
    var display = document.getElementById('file_name_display');
    display.textContent = fileName;
    display.classList.remove('d-none');
});

function detectDateFromDoc() {

    const fileInput = document.getElementById('doc_archivo');
    const msgDiv = document.getElementById('date_detection_msg');
    const vencimientoInput = document.getElementById('doc_vencimiento');
    const nombreInput = document.getElementById('doc_nombre');
    
    // Get worker info for validation (from PHP)
    const workerRut = "<?php echo isset($worker['rut']) ? $worker['rut'] : ''; ?>";
    const workerName = "<?php echo isset($worker['nombre']) ? addslashes($worker['nombre']) : ''; ?>";
    
    if (fileInput.files.length === 0) return;
    
    const file = fileInput.files[0];
    if (file.type !== 'application/pdf') {
        // Only PDF supported for now on server side script
        return;
    }

    msgDiv.style.display = 'block';
    msgDiv.className = 'form-text text-primary';
    msgDiv.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Analizando documento (Fecha, Tipo, Identidad)...';
    
    const formData = new FormData();
    formData.append('doc_archivo', file);
    formData.append('worker_rut', workerRut);
    formData.append('worker_name', workerName);
    
    fetch('../scripts/parse_doc_dates.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msgDiv.style.display = 'block'; // Keep visible to show result
        
        let msgHtml = '';
        
        // 1. Handle Identity Validation
        if (data.success && data.identity_match === false) {
             msgHtml += `<div class="text-danger fw-bold mb-1"><i class="fas fa-exclamation-triangle"></i> Advertencia: ${data.identity_message}</div>`;
        } else if (data.success && data.identity_match === true) {
             msgHtml += `<div class="text-success small mb-1"><i class="fas fa-check-circle"></i> Identidad verificada (RUT/Nombre coincidentes)</div>`;
        }

        // 2. Handle Classification
        if (data.success && data.doc_type) {
            nombreInput.value = data.doc_type;
            nombreInput.style.backgroundColor = '#e8f5e9';
            setTimeout(() => { nombreInput.style.backgroundColor = ''; }, 2000);
            msgHtml += `<div class="text-success small mb-1"><i class="fas fa-tag"></i> Documento clasificado: ${data.doc_type}</div>`;
        }

        // 3. Handle Date
        if (data.success && data.date) {
            vencimientoInput.value = data.date;
            vencimientoInput.style.backgroundColor = '#e8f5e9';
            setTimeout(() => { vencimientoInput.style.backgroundColor = ''; }, 2000);
            msgHtml += `<div class="text-success small mb-1"><i class="fas fa-calendar-check"></i> Fecha detectada: ${data.date}</div>`;
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
