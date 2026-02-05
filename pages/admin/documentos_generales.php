<?php 
require_once '../../includes/auth.php';
require_once '../../config/db.php';

// Get current folder ID
$current_folder_id = isset($_GET['folder']) ? intval($_GET['folder']) : null;
$breadcrumbs = [];

// Build Breadcrumbs
if ($current_folder_id) {
    $temp_id = $current_folder_id;
    while ($temp_id) {
        $stmt = $conn->prepare("SELECT id, nombre, carpeta_id FROM documentos_generales WHERE id = ?");
        $stmt->bind_param("i", $temp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            array_unshift($breadcrumbs, $row);
            $temp_id = $row['carpeta_id'];
        } else {
            break;
        }
    }
}

// Fetch Items (Folders first, then Files)
$sql = "SELECT * FROM documentos_generales WHERE " . ($current_folder_id ? "carpeta_id = $current_folder_id" : "carpeta_id IS NULL") . " ORDER BY tipo ASC, nombre ASC";
$result = $conn->query($sql);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-primary fw-bold"><i class="fas fa-folder-open me-2"></i>Documentos Generales</h2>
            <p class="text-muted mb-0">Repositorio central de archivos y documentación.</p>
        </div>
        <div>
            <button class="btn btn-light text-primary rounded-pill shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                <i class="fas fa-folder-plus me-1"></i> Nueva Carpeta
            </button>
            <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                <i class="fas fa-cloud-upload-alt me-1"></i> Subir Archivo
            </button>
        </div>
    </div>

    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="bg-white p-3 rounded-4 shadow-sm mb-4 border border-light">
        <ol class="breadcrumb mb-0 align-items-center">
            <li class="breadcrumb-item"><a href="documentos_generales.php" class="text-decoration-none text-secondary"><i class="fas fa-home me-1"></i>Inicio</a></li>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <li class="breadcrumb-item <?php echo ($crumb['id'] == $current_folder_id) ? 'active' : ''; ?>">
                    <?php if ($crumb['id'] != $current_folder_id): ?>
                        <a href="?folder=<?php echo $crumb['id']; ?>" class="text-decoration-none fw-bold text-primary"><?php echo htmlspecialchars($crumb['nombre']); ?></a>
                    <?php else: ?>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($crumb['nombre']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <!-- File Browser -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4" id="file-browser">
        <?php if ($current_folder_id && $result->num_rows == 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <div class="mb-3">
                    <i class="fas fa-folder-open fa-4x text-light-gray opacity-25"></i>
                </div>
                <h5 class="fw-bold">Carpeta vacía</h5>
                <p class="small">Usa los botones superiores para agregar contenido.</p>
            </div>
        <?php elseif (!$current_folder_id && $result->num_rows == 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <div class="mb-3">
                    <i class="fas fa-cloud-upload-alt fa-4x text-light-gray opacity-25"></i>
                </div>
                <h5 class="fw-bold">Repositorio vacío</h5>
                <p class="small">Comienza creando una carpeta o subiendo archivos.</p>
            </div>
        <?php else: ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 file-item rounded-4 position-relative overflow-hidden group-action">
                        <div class="card-body text-center d-flex flex-column justify-content-center align-items-center cursor-pointer p-4" 
                             onclick="<?php echo ($row['tipo'] == 'carpeta') ? "window.location.href='?folder={$row['id']}'" : "window.open('../uploads/general/{$row['archivo']}', '_blank')"; ?>">
                            
                            <?php if ($row['tipo'] == 'carpeta'): ?>
                                <div class="mb-3 position-relative">
                                    <i class="fas fa-folder fa-4x text-warning drop-shadow"></i>
                                </div>
                            <?php else: ?>
                                <?php 
                                    $ext = pathinfo($row['archivo'], PATHINFO_EXTENSION);
                                    $icon = 'fa-file';
                                    $color = 'text-secondary';
                                    $bg_color = 'bg-secondary';
                                    switch(strtolower($ext)) {
                                        case 'pdf': $icon = 'fa-file-pdf'; $color = 'text-danger'; $bg_color = 'bg-danger'; break;
                                        case 'doc': case 'docx': $icon = 'fa-file-word'; $color = 'text-primary'; $bg_color = 'bg-primary'; break;
                                        case 'xls': case 'xlsx': $icon = 'fa-file-excel'; $color = 'text-success'; $bg_color = 'bg-success'; break;
                                        case 'jpg': case 'jpeg': case 'png': $icon = 'fa-file-image'; $color = 'text-info'; $bg_color = 'bg-info'; break;
                                        case 'zip': case 'rar': $icon = 'fa-file-archive'; $color = 'text-warning'; $bg_color = 'bg-warning'; break;
                                    }
                                ?>
                                <div class="mb-3 position-relative">
                                    <i class="fas <?php echo $icon; ?> fa-4x <?php echo $color; ?> drop-shadow"></i>
                                    <span class="position-absolute top-100 start-50 translate-middle badge rounded-pill <?php echo $bg_color; ?> bg-opacity-75" style="font-size: 0.6em; margin-top: -10px;">
                                        <?php echo strtoupper($ext); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <h6 class="card-title text-truncate w-100 fw-bold text-dark mb-1" title="<?php echo htmlspecialchars($row['nombre']); ?>">
                                <?php echo htmlspecialchars($row['nombre']); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="far fa-calendar-alt me-1"></i><?php echo date('d/m/Y', strtotime($row['fecha_creacion'])); ?>
                            </small>
                        </div>
                        
                        <!-- Actions Dropdown -->
                        <div class="position-absolute top-0 end-0 p-2 action-btn" style="opacity: 0; transition: opacity 0.2s;">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                                    <i class="fas fa-ellipsis-v text-muted"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-3">
                                    <?php if($row['tipo'] != 'carpeta'): ?>
                                    <li><a class="dropdown-item" href="../uploads/general/<?php echo $row['archivo']; ?>" download>
                                        <i class="fas fa-download me-2 text-primary"></i>Descargar
                                    </a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem(<?php echo $row['id']; ?>, '<?php echo $row['tipo']; ?>'); return false;">
                                        <i class="fas fa-trash-alt me-2"></i>Eliminar
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal fade" id="newFolderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus me-2"></i>Nueva Carpeta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="newFolderForm">
                    <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">
                    <input type="hidden" name="action" value="create_folder">
                    <div class="mb-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Nombre de la Carpeta</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-folder"></i></span>
                            <input type="text" class="form-control bg-light border-start-0 ps-0" name="folder_name" required placeholder="Ej: Contratos 2024">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary rounded-pill shadow-sm">Crear Carpeta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt me-2"></i>Subir Archivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="uploadFileForm">
                    <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">
                    <input type="hidden" name="action" value="upload_file">
                    
                    <div class="mb-4">
                        <div class="upload-area p-5 border-2 border-dashed rounded-4 text-center bg-light position-relative" id="drop-area">
                            <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" name="file" required id="fileInput">
                            <div class="mb-3">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary opacity-50"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Arrastra tu archivo aquí</h6>
                            <p class="text-muted small mb-0">o haz clic para seleccionar</p>
                            <div id="fileNameDisplay" class="mt-3 badge bg-primary rounded-pill d-none"></div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary rounded-pill shadow-sm">
                            <i class="fas fa-upload me-2"></i>Subir Archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// File Input Display Logic
document.getElementById('fileInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const display = document.getElementById('fileNameDisplay');
    if (fileName) {
        display.textContent = fileName;
        display.classList.remove('d-none');
    } else {
        display.classList.add('d-none');
    }
});

// Drag and Drop Visual Feedback
const dropArea = document.getElementById('drop-area');
['dragenter', 'dragover'].forEach(eventName => {
    dropArea.addEventListener(eventName, highlight, false);
});
['dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropArea.classList.add('bg-white', 'border-primary');
    dropArea.classList.remove('bg-light');
}

function unhighlight(e) {
    dropArea.classList.remove('bg-white', 'border-primary');
    dropArea.classList.add('bg-light');
}

// Handle New Folder
document.getElementById('newFolderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../scripts/manage_general_docs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
});

// Handle Upload File
document.getElementById('uploadFileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Show loading
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
    btn.disabled = true;

    fetch('../scripts/manage_general_docs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            Swal.fire('Error', data.message, 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Error de conexión', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});

// Handle Delete
function deleteItem(id, type) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: type === 'carpeta' ? "Se eliminarán también todos los archivos dentro de esta carpeta." : "No podrás revertir esto.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch('../scripts/manage_general_docs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Eliminado', 'El elemento ha sido eliminado.', 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}
</script>

<style>
.file-item {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.file-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.group-action:hover .action-btn {
    opacity: 1 !important;
}
.drop-shadow {
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.2));
}
.cursor-pointer {
    cursor: pointer;
}
.upload-area {
    transition: all 0.2s ease;
}
</style>

<?php include '../../includes/footer.php'; ?>