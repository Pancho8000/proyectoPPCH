<?php if (!defined('SECURE_ACCESS')) die('Direct access not permitted'); ?>
<!-- Sidebar -->
<?php
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($link_page, $current_page) {
    // Exact match
    if ($link_page == $current_page) return 'active';
    
    // Sub-pages mapping
    $mappings = [
        'trabajadores.php' => ['ficha_trabajador.php'],
        'vehiculos.php' => ['ficha_vehiculo.php']
    ];
    
    if (isset($mappings[$link_page]) && in_array($current_page, $mappings[$link_page])) {
        return 'active';
    }
    
    return '';
}
?>
<div class="sidebar d-flex flex-column" id="sidebar-wrapper">
    <div class="sidebar-header d-flex flex-column align-items-center justify-content-center pt-4 pb-3">
        <div class="d-flex align-items-center mb-4">
            <i class="fas fa-building fa-lg text-white me-2"></i>
            <h5 class="text-white m-0 fw-bold">HECSO</h5>
        </div>
        
        <!-- User Profile Section -->
        <div class="d-flex flex-column align-items-center mb-2 w-100">
            <div class="avatar bg-secondary rounded-circle text-white d-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px; font-size: 1.5rem;">
                <i class="fas fa-user"></i>
            </div>
            <h6 class="text-white mb-1"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?></h6>
            <small class="text-muted mb-3">Admin</small>
            <a href="<?php echo BASE_URL; ?>pages/auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
            </a>
        </div>
    </div>
    
    <div class="border-bottom border-secondary border-opacity-25 mb-2"></div>

    <div class="list-group list-group-flush flex-grow-1">
        <div class="sidebar-heading mt-2">Principal</div>
        <a href="<?php echo BASE_URL; ?>pages/admin/dashboard.php" class="nav-link <?php echo isActive('dashboard.php', $current_page); ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/calendario.php" class="nav-link <?php echo isActive('calendario.php', $current_page); ?>">
            <i class="fas fa-calendar-alt"></i> Calendario
        </a>

        <div class="sidebar-heading mt-3">Gestión</div>
        <a href="<?php echo BASE_URL; ?>pages/admin/trabajadores.php" class="nav-link <?php echo isActive('trabajadores.php', $current_page); ?>">
            <i class="fas fa-users"></i> Trabajadores
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/vehiculos.php" class="nav-link <?php echo isActive('vehiculos.php', $current_page); ?>">
            <i class="fas fa-truck"></i> Vehículos
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/mantenciones.php" class="nav-link <?php echo isActive('mantenciones.php', $current_page); ?>">
            <i class="fas fa-tools"></i> Mantenciones
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/combustible.php" class="nav-link <?php echo isActive('combustible.php', $current_page); ?>">
            <i class="fas fa-gas-pump"></i> Combustible
        </a>

        <div class="sidebar-heading mt-3">Sistema</div>
        <a href="<?php echo BASE_URL; ?>pages/admin/bitacora.php" class="nav-link <?php echo isActive('bitacora.php', $current_page); ?>">
            <i class="fas fa-clock"></i> Bitácora
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/documentos_generales.php" class="nav-link <?php echo isActive('documentos_generales.php', $current_page); ?>">
            <i class="fas fa-file-alt"></i> Certificados Gral.
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/carga_masiva_docs.php" class="nav-link <?php echo isActive('carga_masiva_docs.php', $current_page); ?>">
            <i class="fas fa-upload"></i> Carga Masiva
        </a>

        <div class="sidebar-heading mt-3">Configuración</div>
        <a href="<?php echo BASE_URL; ?>pages/admin/usuarios.php" class="nav-link <?php echo isActive('usuarios.php', $current_page); ?>">
            <i class="fas fa-cog"></i> Usuarios de Sistema
        </a>
        <a href="<?php echo BASE_URL; ?>pages/admin/roles_cargos.php" class="nav-link <?php echo isActive('roles_cargos.php', $current_page); ?>">
            <i class="fas fa-tags"></i> Roles y Cargos
        </a>
    </div>
</div>
<!-- /#sidebar-wrapper -->

<!-- Page Content -->
<div id="page-content-wrapper" class="w-100">
    <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle" style="cursor: pointer;"></i>
            <h2 class="fs-2 m-0 text-muted">Panel de Control</h2>
        </div>
    </nav>

    <div class="container-fluid px-4">
