<?php 
require_once '../includes/auth.php';
require_once '../config/db.php';

// Handle Actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Save User (Create/Edit)
        if ($_POST['action'] == 'save_user') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $rol_id = intval($_POST['rol_id']);
            $trabajador_id = !empty($_POST['trabajador_id']) ? intval($_POST['trabajador_id']) : NULL;
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!empty($nombre) && !empty($email)) {
                // Check Email Uniqueness
                $check = $conn->query("SELECT id FROM usuarios WHERE email = '$email' AND id != $id");
                if ($check && $check->num_rows > 0) {
                    $message = "El email ya está registrado.";
                    $message_type = "danger";
                } else {
                    if ($id > 0) {
                        // Update
                        $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol_id = ?, trabajador_id = ? WHERE id = ?";
                        if (!empty($password)) {
                            $sql = "UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol_id = ?, trabajador_id = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                            $stmt->bind_param("sssiis", $nombre, $email, $hashed_pass, $rol_id, $trabajador_id, $id);
                        } else {
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ssiis", $nombre, $email, $rol_id, $trabajador_id, $id);
                        }
                    } else {
                        // Insert
                        if (empty($password)) {
                            $message = "La contraseña es obligatoria para nuevos usuarios.";
                            $message_type = "danger";
                        } else {
                            $sql = "INSERT INTO usuarios (nombre, email, password, rol_id, trabajador_id) VALUES (?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                            $stmt->bind_param("sssis", $nombre, $email, $hashed_pass, $rol_id, $trabajador_id);
                        }
                    }

                    if (empty($message) && isset($stmt)) {
                        if ($stmt->execute()) {
                            $action_log = ($id > 0) ? 'Editar Usuario' : 'Crear Usuario';
                            $details_log = "Usuario: $nombre, Email: $email";
                            registrar_actividad($action_log, $details_log, $_SESSION['user_id']);

                            $message = "Usuario guardado correctamente.";
                            $message_type = "success";
                        } else {
                            $message = "Error al guardar: " . $conn->error;
                            $message_type = "danger";
                        }
                    }
                }
            }
        }

        // Delete User
        if ($_POST['action'] == 'delete_user') {
            $id = intval($_POST['id']);
            if ($id == 1 || $id == $_SESSION['user_id']) {
                $message = "No puedes eliminar tu propia cuenta ni al administrador principal.";
                $message_type = "warning";
            } else {
                if ($conn->query("DELETE FROM usuarios WHERE id = $id")) {
                    registrar_actividad('Eliminar Usuario', 'Usuario ID: ' . $id . ' eliminado', $_SESSION['user_id']);
                    $message = "Usuario eliminado.";
                    $message_type = "success";
                } else {
                    $message = "Error al eliminar.";
                    $message_type = "danger";
                }
            }
        }
    }
}

// Fetch Users with Role and Worker info
$sql = "SELECT u.*, r.nombre as rol_nombre, t.nombre as trabajador_nombre, t.rut as trabajador_rut 
        FROM usuarios u 
        LEFT JOIN roles r ON u.rol_id = r.id 
        LEFT JOIN trabajadores t ON u.trabajador_id = t.id 
        ORDER BY u.created_at DESC";
$users = $conn->query($sql);

// Fetch Roles
$roles = $conn->query("SELECT * FROM roles ORDER BY nombre ASC");

// Fetch Workers (Available + Currently Linked ones will be handled in UI)
$workers = $conn->query("SELECT id, nombre, rut FROM trabajadores ORDER BY nombre ASC");
$all_workers = [];
while($w = $workers->fetch_assoc()) {
    $all_workers[] = $w;
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

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
        <h2 class="fw-bold text-primary mb-1">Usuarios de Sistema</h2>
        <p class="text-muted mb-0">Gestión de accesos y roles de la plataforma</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openUserModal()">
        <i class="fas fa-plus me-2"></i>Nuevo Usuario
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
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0 text-muted small text-uppercase fw-bold">Listado de Usuarios</h5>
            </div>
            <div class="col-auto">
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                    <i class="fas fa-users me-1"></i> Total: <?php echo $users ? $users->num_rows : 0; ?>
                </span>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Trabajador Vinculado</th>
                        <th>Fecha Creación</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initials me-3 bg-light text-primary shadow-sm">
                                        <?php 
                                        $initials = strtoupper(substr($u['nombre'], 0, 1));
                                        if (strpos($u['nombre'], ' ') !== false) {
                                            $parts = explode(' ', $u['nombre']);
                                            $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                                        }
                                        echo $initials;
                                        ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($u['nombre']); ?></div>
                                        <small class="text-muted">ID: #<?php echo $u['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" class="text-decoration-none text-secondary">
                                    <i class="far fa-envelope me-1"></i><?php echo htmlspecialchars($u['email']); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $badgeClass = 'bg-secondary';
                                if (stripos($u['rol_nombre'], 'admin') !== false) $badgeClass = 'bg-danger';
                                elseif (stripos($u['rol_nombre'], 'conductor') !== false) $badgeClass = 'bg-success';
                                elseif (stripos($u['rol_nombre'], 'supervisor') !== false) $badgeClass = 'bg-warning text-dark';
                                ?>
                                <span class="badge rounded-pill <?php echo $badgeClass; ?> bg-opacity-10 text-<?php echo str_replace('bg-', '', $badgeClass) == 'bg-warning' ? 'dark' : str_replace('bg-', '', $badgeClass); ?> border border-<?php echo str_replace('bg-', '', $badgeClass); ?> border-opacity-25 px-3 py-2">
                                    <?php echo htmlspecialchars($u['rol_nombre']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['trabajador_id']): ?>
                                    <div class="d-flex align-items-center text-info">
                                        <i class="fas fa-link me-2"></i>
                                        <div>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($u['trabajador_nombre']); ?></div>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo $u['trabajador_rut']; ?></small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-light text-muted border fw-normal">No vinculado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <i class="far fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-icon btn-outline-primary btn-sm me-1 shadow-sm" onclick='openUserModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)' data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <?php if($u['id'] != 1 && $u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-icon btn-outline-danger btn-sm shadow-sm" data-bs-toggle="tooltip" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-5">
                                <div class="mb-3"><i class="fas fa-users-slash fa-3x text-light"></i></div>
                                <h5>No hay usuarios registrados</h5>
                                <p class="small">Crea un nuevo usuario para comenzar a gestionar el acceso.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal User -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="userModalTitle">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-4">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" id="userId" value="0">
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Nombre de Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control bg-light border-0" name="nombre" id="userNombre" required placeholder="Nombre completo">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control bg-light border-0" name="email" id="userEmail" required placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase" id="passLabel">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control bg-light border-0" name="password" id="userPassword" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="form-text small ms-1" id="passHelp">Obligatoria para nuevos usuarios.</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Rol de Acceso</label>
                        <select class="form-select bg-light border-0" name="rol_id" id="userRol" required>
                            <option value="">Seleccione...</option>
                            <?php 
                            $roles->data_seek(0);
                            while($r = $roles->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Vinculación (Opcional)</label>
                        <select class="form-select bg-light border-0" name="trabajador_id" id="userTrabajador">
                            <option value="">-- Sin vincular --</option>
                            <?php foreach($all_workers as $w): ?>
                            <option value="<?php echo $w['id']; ?>">
                                <?php echo htmlspecialchars($w['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fas fa-save me-2"></i>Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Initialize Modal
const userModal = new bootstrap.Modal(document.getElementById('userModal'));

function openUserModal(data = null) {
    if (data) {
        document.getElementById('userModalTitle').textContent = 'Editar Usuario';
        document.getElementById('userId').value = data.id;
        document.getElementById('userNombre').value = data.nombre;
        document.getElementById('userEmail').value = data.email;
        document.getElementById('userPassword').value = '';
        document.getElementById('passLabel').textContent = 'Nueva Contraseña';
        document.getElementById('passHelp').textContent = 'Dejar en blanco para mantener la actual.';
        document.getElementById('userPassword').required = false;
        document.getElementById('userRol').value = data.rol_id;
        document.getElementById('userTrabajador').value = data.trabajador_id || '';
    } else {
        document.getElementById('userModalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('userId').value = 0;
        document.getElementById('userNombre').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userPassword').value = '';
        document.getElementById('passLabel').textContent = 'Contraseña';
        document.getElementById('passHelp').textContent = 'Obligatoria para nuevos usuarios.';
        document.getElementById('userPassword').required = true;
        document.getElementById('userRol').value = '';
        document.getElementById('userTrabajador').value = '';
    }
    userModal.show();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
