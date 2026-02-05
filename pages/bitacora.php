<?php 
require_once '../includes/auth.php';
require_once '../config/db.php';

// Pagination Logic
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

// Filter Logic
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "1";
if (!empty($search)) {
    $where .= " AND (u.nombre LIKE '%$search%' OR b.accion LIKE '%$search%' OR b.detalles LIKE '%$search%')";
}

// Get Total Records
$sql_count = "SELECT COUNT(*) as total FROM bitacora b LEFT JOIN usuarios u ON b.usuario_id = u.id WHERE $where";
$total_results = $conn->query($sql_count)->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);

// Get Logs
$sql = "SELECT b.*, u.nombre as usuario_nombre, r.nombre as rol_nombre 
        FROM bitacora b 
        LEFT JOIN usuarios u ON b.usuario_id = u.id 
        LEFT JOIN roles r ON u.rol_id = r.id 
        WHERE $where 
        ORDER BY b.fecha DESC 
        LIMIT $start, $limit";
$logs = $conn->query($sql);

include '../includes/header.php'; 
include '../includes/sidebar.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1 text-primary fw-bold"><i class="fas fa-history me-2"></i>Bitácora del Sistema</h2>
        <p class="text-muted mb-0">Registro histórico de todas las actividades y eventos.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 border-0 d-flex flex-column flex-md-row justify-content-between align-items-center rounded-top-4">
        <div class="d-flex align-items-center mb-3 mb-md-0">
            <div class="bg-primary bg-opacity-10 p-2 rounded-circle text-primary me-3">
                <i class="fas fa-list-ul fa-lg"></i>
            </div>
            <h5 class="mb-0 fw-bold text-primary">Listado de Eventos</h5>
        </div>
        <form class="d-flex" method="GET">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted rounded-start-pill ps-3"><i class="fas fa-search"></i></span>
                <input class="form-control bg-light border-start-0 me-2" type="search" name="search" placeholder="Buscar usuario, acción..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" type="submit">Buscar</button>
            <?php if(!empty($search)): ?>
                <a href="bitacora.php" class="btn btn-light text-secondary rounded-pill ms-2" data-bs-toggle="tooltip" title="Limpiar filtro"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalles</th>
                        <th class="text-end pe-4">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></div>
                                    <div class="small text-muted"><i class="far fa-clock me-1"></i><?php echo date('H:i', strtotime($row['fecha'])); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 0.8rem; font-weight: bold;">
                                            <?php 
                                            $name_parts = explode(' ', $row['usuario_nombre'] ?? 'U');
                                            echo strtoupper(substr($name_parts[0], 0, 1)); 
                                            if (count($name_parts) > 1) echo strtoupper(substr($name_parts[1], 0, 1));
                                            ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['usuario_nombre'] ?? 'Usuario Eliminado'); ?></div>
                                            <?php if($row['rol_nombre']): ?>
                                                <span class="badge bg-light text-secondary border rounded-pill fw-normal" style="font-size: 0.75em;"><?php echo htmlspecialchars($row['rol_nombre']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $action_text = htmlspecialchars($row['accion']);
                                        $badge_class = 'bg-info text-info';
                                        $icon = 'fa-info-circle';
                                        
                                        if (stripos($row['accion'], 'eliminar') !== false) {
                                            $badge_class = 'bg-danger text-danger';
                                            $icon = 'fa-trash-alt';
                                        } elseif (stripos($row['accion'], 'crear') !== false || stripos($row['accion'], 'registro') !== false || stripos($row['accion'], 'import') !== false) {
                                            $badge_class = 'bg-success text-success';
                                            $icon = 'fa-plus-circle';
                                        } elseif (stripos($row['accion'], 'editar') !== false || stripos($row['accion'], 'actualizar') !== false) {
                                            $badge_class = 'bg-warning text-warning';
                                            $icon = 'fa-edit';
                                        } elseif (stripos($row['accion'], 'login') !== false || stripos($row['accion'], 'acceso') !== false) {
                                            $badge_class = 'bg-primary text-primary';
                                            $icon = 'fa-sign-in-alt';
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> bg-opacity-10 rounded-pill px-3 py-2 border border-opacity-25 border-<?php echo str_replace(['bg-', 'text-'], '', explode(' ', $badge_class)[0]); ?>">
                                        <i class="fas <?php echo $icon; ?> me-1"></i><?php echo $action_text; ?>
                                    </span>
                                </td>
                                <td class="text-secondary small"><?php echo htmlspecialchars($row['detalles']); ?></td>
                                <td class="text-end pe-4 small font-monospace text-muted"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="mb-3"><i class="fas fa-clipboard-list fa-3x text-light-gray"></i></div>
                                <h6 class="fw-bold">No hay registros encontrados</h6>
                                <p class="small">Intenta ajustar los filtros de búsqueda.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 border-0 rounded-bottom-4">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 mx-1 border-0 shadow-sm" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left me-1"></i>Anterior</a>
                </li>
                
                <?php
                // Simple pagination range logic to avoid too many buttons
                $range = 2;
                for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++): 
                ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link rounded-circle mx-1 border-0 shadow-sm d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-pill px-3 mx-1 border-0 shadow-sm" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Siguiente<i class="fas fa-chevron-right ms-1"></i></a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php include '../includes/footer.php'; ?>