<?php 
require_once '../includes/auth.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Handle Actions
$message = '';
$message_type = '';

// 1. Delete Maintenance
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Get details for log before deleting
    $stmt_check = $conn->prepare("SELECT m.*, v.patente FROM mantenciones m JOIN vehiculos v ON m.vehiculo_id = v.id WHERE m.id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $log_data = $stmt_check->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM mantenciones WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $log_details = $log_data ? "ID: $id, Vehículo: {$log_data['patente']}, Fecha: {$log_data['fecha']}" : "ID: $id";
        registrar_actividad('Eliminar Mantención', $log_details, $_SESSION['user_id']);
        $message = "Mantención eliminada correctamente.";
        $message_type = "success";
    } else {
        $message = "Error al eliminar: " . $conn->error;
        $message_type = "danger";
    }
}

// 2. Add/Edit Maintenance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'edit')) {
    $vehiculo_id = intval($_POST['vehiculo_id']);
    $fecha = $_POST['fecha'];
    $tipo_mantencion = $_POST['tipo_mantencion'];
    $descripcion = $_POST['descripcion'];
    $taller = $_POST['taller'];
    $kilometraje = !empty($_POST['kilometraje']) ? intval($_POST['kilometraje']) : 0;
    $costo = !empty($_POST['costo']) ? floatval($_POST['costo']) : 0;

    // Get Patente for Log
    $stmt_v = $conn->prepare("SELECT patente FROM vehiculos WHERE id = ?");
    $stmt_v->bind_param("i", $vehiculo_id);
    $stmt_v->execute();
    $vehiculo_patente = $stmt_v->get_result()->fetch_assoc()['patente'] ?? 'Unknown';

    if ($_POST['action'] == 'create') {
        $sql = "INSERT INTO mantenciones (vehiculo_id, fecha, tipo_mantencion, descripcion, taller, kilometraje, costo) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssid", $vehiculo_id, $fecha, $tipo_mantencion, $descripcion, $taller, $kilometraje, $costo);
    } else {
        $id = intval($_POST['id']);
        $sql = "UPDATE mantenciones SET vehiculo_id=?, fecha=?, tipo_mantencion=?, descripcion=?, taller=?, kilometraje=?, costo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssidi", $vehiculo_id, $fecha, $tipo_mantencion, $descripcion, $taller, $kilometraje, $costo, $id);
    }

    try {
        if ($stmt->execute()) {
            // Update Vehicle Status or Mileage if needed?
            // Optionally update vehicle mileage if this maintenance has higher mileage
            if ($kilometraje > 0) {
                $conn->query("UPDATE vehiculos SET kilometraje = GREATEST(COALESCE(kilometraje,0), $kilometraje) WHERE id = $vehiculo_id");
            }
            
            $action_log = ($_POST['action'] == 'create') ? 'Registrar Mantención' : 'Editar Mantención';
            $details_log = "Vehículo: $vehiculo_patente, Fecha: $fecha, Tipo: $tipo_mantencion, Costo: $costo";
            registrar_actividad($action_log, $details_log, $_SESSION['user_id']);

            $message = "Mantención guardada correctamente.";
            $message_type = "success";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $message = "Error al guardar: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Fetch Maintenances
$where_clause = "1";
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
if (!empty($search)) {
    $where_clause .= " AND (v.patente LIKE '%$search%' OR m.descripcion LIKE '%$search%' OR m.taller LIKE '%$search%')";
}

$maintenances = [];
$sql = "SELECT m.*, v.patente, v.marca, v.modelo 
        FROM mantenciones m 
        JOIN vehiculos v ON m.vehiculo_id = v.id 
        WHERE $where_clause 
        ORDER BY m.fecha DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $maintenances[] = $row;
    }
}

// Fetch Vehicles for Dropdown
$vehicles = [];
$res_v = $conn->query("SELECT id, patente, marca, modelo FROM vehiculos ORDER BY patente ASC");
if ($res_v) {
    while ($row = $res_v->fetch_assoc()) {
        $vehicles[] = $row;
    }
}
?>

<style>
    .shadow-hover { transition: all 0.3s ease; }
    .shadow-hover:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .card { border: none; border-radius: 1rem; }
    .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
    .table thead th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
    .modal-content { border: none; border-radius: 1rem; }
    .form-control, .form-select { border-radius: 0.5rem; padding: 0.6rem 1rem; }
    .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); border-color: #86b7fe; }
    .avatar-initials {
        width: 40px; height: 40px; background-color: #e9ecef; color: #495057;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; font-weight: 600; font-size: 0.9rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-primary mb-1">Gestión de Mantenciones</h2>
        <p class="text-muted mb-0">Historial y programación de servicios de la flota</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openModal('create')">
        <i class="fas fa-plus me-2"></i>Nueva Mantención
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-0">
        <form action="" method="GET" class="row g-3 align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-pill ps-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control bg-light border-start-0 rounded-end-pill" name="search" placeholder="Buscar por patente, taller..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if(!empty($search)): ?>
                        <a href="mantenciones.php" class="btn btn-outline-secondary rounded-pill ms-2" title="Limpiar filtro"><i class="fas fa-times"></i></a>
                    <?php else: ?>
                        <button class="btn btn-primary rounded-pill ms-2 px-3" type="submit">Buscar</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Vehículo</th>
                        <th>Tipo</th>
                        <th>Taller / Proveedor</th>
                        <th>Kilometraje</th>
                        <th>Costo</th>
                        <th>Descripción</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($maintenances)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted p-5">
                                <div class="mb-3"><i class="fas fa-tools fa-3x text-light"></i></div>
                                <h5>No se encontraron registros</h5>
                                <p class="small">Intenta ajustar los filtros de búsqueda o crea una nueva mantención.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($maintenances as $m): ?>
                        <tr>
                            <td class="ps-4 text-nowrap"><?php echo date('d/m/Y', strtotime($m['fecha'])); ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initials me-2 bg-light text-primary">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($m['patente']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($m['marca'] . ' ' . $m['modelo']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($m['tipo_mantencion'] == 'Preventiva'): ?>
                                    <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                        <i class="fas fa-shield-alt me-1"></i>Preventiva
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                        <i class="fas fa-wrench me-1"></i>Correctiva
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><i class="fas fa-store text-muted me-2"></i><?php echo htmlspecialchars($m['taller']); ?></td>
                            <td><?php echo number_format($m['kilometraje'], 0, ',', '.'); ?> km</td>
                            <td class="fw-bold text-success">$<?php echo number_format($m['costo'], 0, ',', '.'); ?></td>
                            <td class="text-muted small">
                                <span class="d-inline-block text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($m['descripcion']); ?>">
                                    <?php echo htmlspecialchars($m['descripcion']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-icon btn-outline-warning btn-sm me-1 shadow-sm" onclick='openModal("edit", <?php echo json_encode($m); ?>)' title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="mantenciones.php?action=delete&id=<?php echo $m['id']; ?>" class="btn btn-icon btn-outline-danger btn-sm shadow-sm" onclick="return confirm('¿Está seguro de eliminar esta mantención?')" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!empty($maintenances)): ?>
    <div class="card-footer bg-white border-0 py-3">
        <small class="text-muted">Mostrando <?php echo count($maintenances); ?> registros</small>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalTitle">Nueva Mantención</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="mantenciones.php">
          <div class="modal-body pt-4">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="id" id="modalId">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Vehículo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-truck"></i></span>
                        <select class="form-select bg-light border-0" name="vehiculo_id" id="vehiculo_id" required>
                            <option value="">Seleccione Vehículo...</option>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['patente'] . ' - ' . $v['marca'] . ' ' . $v['modelo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Fecha</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-alt"></i></span>
                        <input type="date" class="form-control bg-light border-0" name="fecha" id="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Tipo de Mantención</label>
                    <select class="form-select bg-light border-0" name="tipo_mantencion" id="tipo_mantencion" required>
                        <option value="Preventiva">Preventiva</option>
                        <option value="Correctiva">Correctiva</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Taller / Proveedor</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-store"></i></span>
                        <input type="text" class="form-control bg-light border-0" name="taller" id="taller" placeholder="Ej: Taller Central" required>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Kilometraje Actual</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-tachometer-alt"></i></span>
                        <input type="number" class="form-control bg-light border-0" name="kilometraje" id="kilometraje" min="0" placeholder="0" required>
                        <span class="input-group-text bg-light border-0">km</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted text-uppercase">Costo ($)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-dollar-sign"></i></span>
                        <input type="number" class="form-control bg-light border-0" name="costo" id="costo" min="0" step="100" placeholder="0" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small text-muted text-uppercase">Descripción Detallada</label>
                <textarea class="form-control bg-light border-0" name="descripcion" id="descripcion" rows="3" placeholder="Detalles del trabajo realizado..." required></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0 pb-4">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-save me-2"></i>Guardar Mantención
            </button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(action, data = null) {
    const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
    document.getElementById('modalAction').value = action;
    document.getElementById('modalTitle').textContent = action === 'create' ? 'Nueva Mantención' : 'Editar Mantención';
    
    if (action === 'edit' && data) {
        document.getElementById('modalId').value = data.id;
        document.getElementById('vehiculo_id').value = data.vehiculo_id;
        document.getElementById('fecha').value = data.fecha;
        document.getElementById('tipo_mantencion').value = data.tipo_mantencion;
        document.getElementById('taller').value = data.taller;
        document.getElementById('kilometraje').value = data.kilometraje;
        document.getElementById('costo').value = data.costo;
        document.getElementById('descripcion').value = data.descripcion;
    } else {
        document.getElementById('modalId').value = '';
        document.getElementById('vehiculo_id').value = '';
        document.getElementById('fecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('tipo_mantencion').value = 'Preventiva';
        document.getElementById('taller').value = '';
        document.getElementById('kilometraje').value = '';
        document.getElementById('costo').value = '';
        document.getElementById('descripcion').value = '';
    }
    
    modal.show();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>

<?php include '../includes/footer.php'; ?>
