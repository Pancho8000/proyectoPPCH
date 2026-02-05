<?php 
require_once '../includes/auth.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Handle Actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add/Edit Cargo
        if ($_POST['action'] == 'save_cargo') {
            $nombre = trim($_POST['nombre']);
            $es_conductor = isset($_POST['es_conductor']) ? 1 : 0;
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!empty($nombre)) {
                if ($id > 0) {
                    // Update
                    $stmt = $conn->prepare("UPDATE cargos SET nombre = ?, es_conductor = ? WHERE id = ?");
                    $stmt->bind_param("sii", $nombre, $es_conductor, $id);
                } else {
                    // Insert
                    $stmt = $conn->prepare("INSERT INTO cargos (nombre, es_conductor) VALUES (?, ?)");
                    $stmt->bind_param("si", $nombre, $es_conductor);
                }

                if ($stmt->execute()) {
                    $action_log = ($id > 0) ? 'Editar Cargo' : 'Crear Cargo';
                    registrar_actividad($action_log, "Cargo: $nombre", $_SESSION['user_id']);

                    $message = "Cargo guardado correctamente.";
                    $message_type = "success";
                } else {
                    $message = "Error al guardar: " . $conn->error;
                    $message_type = "danger";
                }
            }
        }
        
        // Delete Cargo
        if ($_POST['action'] == 'delete_cargo') {
            $id = intval($_POST['id']);
            // Check if used
            $check = $conn->query("SELECT COUNT(*) as c FROM trabajadores WHERE cargo_id = $id");
            if ($check && $check->fetch_assoc()['c'] > 0) {
                $message = "No se puede eliminar este cargo porque hay trabajadores asignados a él.";
                $message_type = "warning";
            } else {
                if ($conn->query("DELETE FROM cargos WHERE id = $id")) {
                    registrar_actividad('Eliminar Cargo', 'Cargo ID: ' . $id . ' eliminado', $_SESSION['user_id']);
                    $message = "Cargo eliminado.";
                    $message_type = "success";
                } else {
                    $message = "Error al eliminar.";
                    $message_type = "danger";
                }
            }
        }

        // Add/Edit Role
        if ($_POST['action'] == 'save_role') {
            $nombre = trim($_POST['nombre']);
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!empty($nombre)) {
                if ($id > 0) {
                    // Update
                    $stmt = $conn->prepare("UPDATE roles SET nombre = ? WHERE id = ?");
                    $stmt->bind_param("si", $nombre, $id);
                } else {
                    // Insert
                    $stmt = $conn->prepare("INSERT INTO roles (nombre) VALUES (?)");
                    $stmt->bind_param("s", $nombre);
                }

                if ($stmt->execute()) {
                    $action_log = ($id > 0) ? 'Editar Rol' : 'Crear Rol';
                    registrar_actividad($action_log, "Rol: $nombre", $_SESSION['user_id']);

                    $message = "Rol guardado correctamente.";
                    $message_type = "success";
                } else {
                    $message = "Error al guardar rol: " . $conn->error;
                    $message_type = "danger";
                }
            }
        }

        // Delete Role
        if ($_POST['action'] == 'delete_role') {
            $id = intval($_POST['id']);
            // Check if used
            $check = $conn->query("SELECT COUNT(*) as c FROM usuarios WHERE rol_id = $id");
            if ($check && $check->fetch_assoc()['c'] > 0) {
                $message = "No se puede eliminar este rol porque hay usuarios asignados a él.";
                $message_type = "warning";
            } else {
                // Prevent deleting ID 1 (Admin) or other critical roles if needed
                if ($id == 1) {
                    $message = "No se puede eliminar el rol de Administrador principal.";
                    $message_type = "danger";
                } else {
                    if ($conn->query("DELETE FROM roles WHERE id = $id")) {
                        registrar_actividad('Eliminar Rol', 'Rol ID: ' . $id . ' eliminado', $_SESSION['user_id']);
                        $message = "Rol eliminado.";
                        $message_type = "success";
                    } else {
                        $message = "Error al eliminar rol.";
                        $message_type = "danger";
                    }
                }
            }
        }
    }
}

// Fetch Cargos
$cargos = [];
$res = $conn->query("SELECT * FROM cargos ORDER BY nombre ASC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $cargos[] = $row;
    }
}

// Fetch Roles (Read-only for now or simple list)
$roles = [];
$res = $conn->query("SELECT * FROM roles ORDER BY nombre ASC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $roles[] = $row;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1 text-primary fw-bold"><i class="fas fa-id-card-clip me-2"></i>Gestión de Roles y Cargos</h2>
        <p class="text-muted mb-0">Administra los permisos de sistema y cargos laborales.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Roles Section -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center rounded-top-4">
                <div class="d-flex align-items-center">
                    <div class="bg-secondary bg-opacity-10 p-2 rounded-circle text-secondary me-3">
                        <i class="fas fa-user-shield fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-secondary">Roles de Sistema</h5>
                        <small class="text-muted">Niveles de acceso</small>
                    </div>
                </div>
                <button class="btn btn-secondary rounded-pill btn-sm shadow-sm" onclick="openRoleModal()">
                    <i class="fas fa-plus me-1"></i>Nuevo
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nombre del Rol</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $r): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['nombre']); ?></div>
                                    <?php if($r['id'] == 1): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill mt-1" style="font-size: 0.7em;">Super Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-icon btn-outline-secondary btn-sm me-1 shadow-sm" onclick='openRoleModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>)' data-bs-toggle="tooltip" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php if($r['id'] != 1): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este rol?');">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button class="btn btn-icon btn-outline-danger btn-sm shadow-sm" data-bs-toggle="tooltip" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Cargos Section -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center rounded-top-4">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-circle text-primary me-3">
                        <i class="fas fa-briefcase fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-primary">Cargos de Trabajadores</h5>
                        <small class="text-muted">Puestos laborales y atributos</small>
                    </div>
                </div>
                <button class="btn btn-primary rounded-pill btn-sm shadow-sm" onclick="openCargoModal()">
                    <i class="fas fa-plus me-1"></i>Nuevo
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nombre del Cargo</th>
                                <th class="text-center">Atributos</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cargos)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">No hay cargos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cargos as $c): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($c['nombre']); ?></td>
                                    <td class="text-center">
                                        <?php if ($c['es_conductor']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill border border-success border-opacity-25 px-3">
                                                <i class="fas fa-steering-wheel me-1"></i>Conductor
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border rounded-pill px-3">Estándar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-icon btn-outline-primary btn-sm me-1 shadow-sm" onclick='openCargoModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)' data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este cargo?');">
                                            <input type="hidden" name="action" value="delete_cargo">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button class="btn btn-icon btn-outline-danger btn-sm shadow-sm" data-bs-toggle="tooltip" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </form>
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

<!-- Modal Cargo -->
<div class="modal fade" id="cargoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="cargoModalTitle"><i class="fas fa-briefcase me-2"></i>Nuevo Cargo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="save_cargo">
                <input type="hidden" name="id" id="cargoId" value="0">
                
                <div class="mb-4">
                    <label class="form-label small text-muted text-uppercase fw-bold">Nombre del Cargo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-tag"></i></span>
                        <input type="text" class="form-control bg-light border-start-0 ps-0" name="nombre" id="cargoNombre" required placeholder="Ej: Operador Maquinaria">
                    </div>
                </div>

                <div class="mb-2">
                    <div class="form-check form-switch p-0">
                        <label class="form-check-label d-flex align-items-center border rounded-3 p-3 cursor-pointer shadow-sm hover-shadow transition" for="cargoEsConductor" style="cursor: pointer; transition: all 0.2s;">
                            <div class="bg-success bg-opacity-10 p-2 rounded-circle text-success me-3">
                                <i class="fas fa-steering-wheel"></i>
                            </div>
                            <div class="me-auto">
                                <span class="fw-bold d-block text-dark">Permiso de Conducción</span>
                                <small class="text-muted">Habilita asignación de vehículos</small>
                            </div>
                            <div class="form-check form-switch ms-3">
                                <input class="form-check-input" type="checkbox" name="es_conductor" id="cargoEsConductor" style="transform: scale(1.4);">
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Role -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-secondary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="roleModalTitle"><i class="fas fa-user-shield me-2"></i>Nuevo Rol</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="save_role">
                <input type="hidden" name="id" id="roleId" value="0">
                
                <div class="mb-2">
                    <label class="form-label small text-muted text-uppercase fw-bold">Nombre del Rol</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-signature"></i></span>
                        <input type="text" class="form-control bg-light border-start-0 ps-0" name="nombre" id="roleNombre" required placeholder="Ej: Supervisor">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-secondary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Initialize Modals after Bootstrap is loaded
const cargoModal = new bootstrap.Modal(document.getElementById('cargoModal'));
const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

function openCargoModal(data = null) {
    if (data) {
        document.getElementById('cargoModalTitle').textContent = 'Editar Cargo';
        document.getElementById('cargoId').value = data.id;
        document.getElementById('cargoNombre').value = data.nombre;
        document.getElementById('cargoEsConductor').checked = (data.es_conductor == 1);
    } else {
        document.getElementById('cargoModalTitle').textContent = 'Nuevo Cargo';
        document.getElementById('cargoId').value = 0;
        document.getElementById('cargoNombre').value = '';
        document.getElementById('cargoEsConductor').checked = false;
    }
    cargoModal.show();
}

function openRoleModal(data = null) {
    if (data) {
        document.getElementById('roleModalTitle').textContent = 'Editar Rol';
        document.getElementById('roleId').value = data.id;
        document.getElementById('roleNombre').value = data.nombre;
    } else {
        document.getElementById('roleModalTitle').textContent = 'Nuevo Rol';
        document.getElementById('roleId').value = 0;
        document.getElementById('roleNombre').value = '';
    }
    roleModal.show();
}
</script>