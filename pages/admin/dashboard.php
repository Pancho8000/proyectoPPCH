<?php 
require_once '../../includes/auth.php';
require_once '../../config/db.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// KPIs Logic
$kpis = [
    'disponibles' => 0,
    'taller' => 0,
    'revision' => 0,
    'licencias' => 0,
    'gastos_mes' => 0
];

// 1. Vehículos Disponibles
$res = $conn->query("SELECT COUNT(*) as c FROM vehiculos WHERE estado = 'Disponible'");
if($res) $kpis['disponibles'] = $res->fetch_assoc()['c'];

// 2. Vehículos en Taller
$res = $conn->query("SELECT COUNT(*) as c FROM vehiculos WHERE estado = 'En Taller'");
if($res) $kpis['taller'] = $res->fetch_assoc()['c'];

// 3. En Revisión Técnica (Assuming 'En Revisión' status)
$res = $conn->query("SELECT COUNT(*) as c FROM vehiculos WHERE estado = 'En Revisión'");
if($res) $kpis['revision'] = $res->fetch_assoc()['c'];

// 4. Licencias Vencidas
$res = $conn->query("SELECT COUNT(*) as c FROM trabajadores WHERE licencia_vencimiento < CURDATE()");
if($res) $kpis['licencias'] = $res->fetch_assoc()['c'];

// 5. Gastos Mensuales (Mantenciones + Combustible del mes actual)
$current_month_sql = "AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())";
$gastos_mant = 0;
$gastos_comb = 0;

$res = $conn->query("SELECT SUM(costo) as s FROM mantenciones WHERE 1 $current_month_sql");
if($res && $row = $res->fetch_assoc()) $gastos_mant = $row['s'] ?? 0;

$res = $conn->query("SELECT SUM(costo) as s FROM combustible WHERE 1 $current_month_sql");
if($res && $row = $res->fetch_assoc()) $gastos_comb = $row['s'] ?? 0;

$kpis['gastos_mes'] = $gastos_mant + $gastos_comb;

// SAP Report Logic
$current_month_val = date('n');
$current_year_val = date('Y');
$sap_data = [
    'salud' => 0,
    'ambiente' => 0,
    'seguridad' => 0,
    'meta' => 0,
    'fecha_carga' => 'N/A'
];
$sap_sql = "SELECT * FROM reportes_sap WHERE mes = $current_month_val AND anio = $current_year_val LIMIT 1";
$sap_res = $conn->query($sap_sql);
if ($sap_res && $sap_row = $sap_res->fetch_assoc()) {
    $sap_data['salud'] = $sap_row['salud_ocupacional'];
    $sap_data['ambiente'] = $sap_row['medio_ambiente'];
    $sap_data['seguridad'] = $sap_row['seguridad'];
    $sap_data['meta'] = $sap_row['meta_anual'];
    $sap_data['fecha_carga'] = date('d/m/Y H:i', strtotime($sap_row['fecha_carga']));
}

// Data for Charts
// Fleet Status
$fleet_counts = [];
$res = $conn->query("SELECT estado, COUNT(*) as c FROM vehiculos GROUP BY estado");
while($row = $res->fetch_assoc()) {
    $fleet_counts[$row['estado']] = $row['c'];
}

// Fixed order for Chart Consistency (Green, Blue, Yellow, Red)
$statuses_order = ['Disponible', 'En Uso', 'En Taller', 'Baja'];
$fleet_chart_labels = [];
$fleet_chart_data = [];

foreach ($statuses_order as $status) {
    $fleet_chart_labels[] = $status;
    $fleet_chart_data[] = isset($fleet_counts[$status]) ? (int)$fleet_counts[$status] : 0;
}

// Maintenance Expenses Last 6 Months
$maintenance_expenses = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date("Y-m-01", strtotime("-$i months"));
    $month_end = date("Y-m-t", strtotime("-$i months"));
    $month_label = date("M Y", strtotime("-$i months"));
    
    $sql = "SELECT SUM(costo) as s FROM mantenciones WHERE fecha BETWEEN '$month_start' AND '$month_end'";
    $res = $conn->query($sql);
    $cost = 0;
    if($res && $row = $res->fetch_assoc()) {
        $cost = $row['s'] ?? 0;
    }
    $maintenance_expenses[] = ['month' => $month_label, 'cost' => $cost];
}

// Upcoming Expirations Logic (Unified)
$expirations = [];

// 1. Worker Licenses
$sql_lic = "SELECT t.id as entity_id, t.nombre as entidad, 'Licencia Conducir' as tipo, t.licencia_vencimiento as fecha, DATEDIFF(t.licencia_vencimiento, CURDATE()) as dias_restantes, 'worker' as source 
            FROM trabajadores t 
            WHERE t.licencia_vencimiento IS NOT NULL 
            HAVING dias_restantes < 45"; // Show upcoming 45 days

// 2. Worker Documents
$sql_wdocs = "SELECT t.id as entity_id, t.nombre as entidad, c.nombre as tipo, c.fecha_vencimiento as fecha, DATEDIFF(c.fecha_vencimiento, CURDATE()) as dias_restantes, 'worker_doc' as source
              FROM certificados c 
              JOIN trabajadores t ON c.trabajador_id = t.id 
              WHERE c.fecha_vencimiento IS NOT NULL
              HAVING dias_restantes < 45";

// 3. Vehicle Documents
$sql_vdocs = "SELECT v.id as entity_id, v.patente as entidad, vd.nombre as tipo, vd.fecha_vencimiento as fecha, DATEDIFF(vd.fecha_vencimiento, CURDATE()) as dias_restantes, 'vehicle_doc' as source
              FROM vehiculos_documentos vd 
              JOIN vehiculos v ON vd.vehiculo_id = v.id 
              WHERE vd.fecha_vencimiento IS NOT NULL
              HAVING dias_restantes < 45";

// Execute and Merge
$queries = [$sql_lic, $sql_wdocs, $sql_vdocs];
foreach ($queries as $q) {
    $res = $conn->query($q);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $expirations[] = $row;
        }
    }
}

// Sort by days remaining (ASC)
usort($expirations, function($a, $b) {
    return $a['dias_restantes'] - $b['dias_restantes'];
});

// Limit to top 10
$expirations = array_slice($expirations, 0, 10);
?>

<h2 class="mb-4">Dashboard</h2>

<!-- KPIs Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <a href="vehiculos.php?estado=Disponible" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-success-gradient text-white">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="icon-circle bg-white bg-opacity-25 mb-3 mx-auto">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h6 class="card-subtitle mb-1 text-white-50 text-uppercase small fw-bold">Disponibles</h6>
                    <h2 class="card-title fw-bold mb-0"><?php echo $kpis['disponibles']; ?></h2>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-2">
        <a href="vehiculos.php?estado=En Taller" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-warning-gradient text-dark">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="icon-circle bg-dark bg-opacity-10 mb-3 mx-auto">
                        <i class="fas fa-wrench fa-2x"></i>
                    </div>
                    <h6 class="card-subtitle mb-1 text-dark-50 text-uppercase small fw-bold">En Taller</h6>
                    <h2 class="card-title fw-bold mb-0"><?php echo $kpis['taller']; ?></h2>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-2">
        <a href="vehiculos.php?estado=En Revisión" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-info-gradient text-white">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="icon-circle bg-white bg-opacity-25 mb-3 mx-auto">
                        <i class="fas fa-clipboard-check fa-2x"></i>
                    </div>
                    <h6 class="card-subtitle mb-1 text-white-50 text-uppercase small fw-bold">En Revisión</h6>
                    <h2 class="card-title fw-bold mb-0"><?php echo $kpis['revision']; ?></h2>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="trabajadores.php?filter=vencidas" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-danger-gradient text-white">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="icon-circle bg-white bg-opacity-25 mb-3 mx-auto">
                        <i class="fas fa-id-card fa-2x"></i>
                    </div>
                    <h6 class="card-subtitle mb-1 text-white-50 text-uppercase small fw-bold">Licencias Vencidas</h6>
                    <h2 class="card-title fw-bold mb-0"><?php echo $kpis['licencias']; ?></h2>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="#" data-bs-toggle="modal" data-bs-target="#gastosModal" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-primary-gradient text-white">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="icon-circle bg-white bg-opacity-25 mb-3 mx-auto">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                    <h6 class="card-subtitle mb-1 text-white-50 text-uppercase small fw-bold">Gastos Mes</h6>
                    <h2 class="card-title fw-bold mb-0">$<?php echo number_format($kpis['gastos_mes'], 0, ',', '.'); ?></h2>
                </div>
            </div>
        </a>
    </div>
</div>

<style>
/* Dashboard Polish CSS */
.kpi-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 1rem;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.bg-success-gradient { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.bg-warning-gradient { background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%); }
.bg-info-gradient { background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%); }
.bg-danger-gradient { background: linear-gradient(135deg, #dc3545 0%, #f86c6b 100%); }
.bg-primary-gradient { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); }

.icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.75rem;
}
.card-header {
    background-color: transparent;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    font-weight: 600;
    padding: 1rem 1.25rem;
}
</style>

<!-- SAP Report Section -->
<div class="card mb-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom-0 pt-3 px-4">
        <span class="h5 mb-0 fw-bold text-dark"><i class="fas fa-shield-alt me-2 text-primary"></i>Reporte SAP <small class="text-muted fw-normal ms-1">(Salud, Ambiente y Prevención)</small></span>
        <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#sapUploadModal">
            <i class="fas fa-upload me-1"></i> Cargar Reporte
        </button>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row align-items-center">
            <div class="col-md-5 border-end">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="text-uppercase text-muted fw-bold mb-0 small">Resumen del Mes</h6>
                    <span class="badge bg-light text-dark border">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo $sap_data['fecha_carga']; ?>
                    </span>
                </div>

                <!-- Salud Ocupacional -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold text-dark">Salud Ocupacional</span>
                        <span class="small fw-bold text-success"><?php echo $sap_data['salud']; ?>%</span>
                    </div>
                    <div class="progress rounded-pill bg-light" style="height: 8px;">
                        <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?php echo $sap_data['salud']; ?>%" aria-valuenow="<?php echo $sap_data['salud']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Medio Ambiente -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold text-dark">Medio Ambiente</span>
                        <span class="small fw-bold text-info"><?php echo $sap_data['ambiente']; ?>%</span>
                    </div>
                    <div class="progress rounded-pill bg-light" style="height: 8px;">
                        <div class="progress-bar bg-info rounded-pill" role="progressbar" style="width: <?php echo $sap_data['ambiente']; ?>%" aria-valuenow="<?php echo $sap_data['ambiente']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Seguridad -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold text-dark">Seguridad</span>
                        <span class="small fw-bold text-primary"><?php echo $sap_data['seguridad']; ?>%</span>
                    </div>
                    <div class="progress rounded-pill bg-light" style="height: 8px;">
                        <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: <?php echo $sap_data['seguridad']; ?>%" aria-valuenow="<?php echo $sap_data['seguridad']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Meta Anual -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold text-dark">Meta Anual</span>
                        <span class="small fw-bold text-warning"><?php echo $sap_data['meta']; ?>%</span>
                    </div>
                    <div class="progress rounded-pill bg-light" style="height: 8px;">
                        <div class="progress-bar bg-warning rounded-pill" role="progressbar" style="width: <?php echo $sap_data['meta']; ?>%" aria-valuenow="<?php echo $sap_data['meta']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

            </div>
            <div class="col-md-7 ps-md-5">
                <div style="width: 100%; height: 320px;">
                    <canvas id="sapChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3 px-4">
                <h5 class="mb-0 fw-bold text-dark">Estado de la Flota</h5>
            </div>
            <div class="card-body">
                <div style="height: 250px; position: relative;">
                    <canvas id="fleetChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3 px-4">
                <h5 class="mb-0 fw-bold text-dark">Gastos de Mantenciones <small class="text-muted fw-normal ms-1">(Últimos 6 meses)</small></h5>
            </div>
            <div class="card-body">
                <canvas id="maintenanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Expirations and Quick Access -->
<div class="row">
    <!-- Próximos Vencimientos -->
    <div class="col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                <h5 class="mb-0 fw-bold text-danger"><i class="far fa-bell me-2"></i>Centro de Alertas</h5>
                <span class="badge bg-danger rounded-pill"><?php echo count($expirations); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="bg-light px-3 py-2 fw-bold text-uppercase small text-muted">Próximos 45 días</div>
                <div class="list-group list-group-flush" style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($expirations)): ?>
                        <div class="list-group-item border-0 p-5 text-center text-muted">
                            <i class="fas fa-check-circle fa-4x text-success mb-3 opacity-50"></i><br>
                            <span class="h6">Todo al día</span><br>
                            <small>No hay vencimientos próximos.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach($expirations as $exp): ?>
                            <?php 
                                $date = date('d/m/Y', strtotime($exp['fecha']));
                                $days = $exp['dias_restantes'];
                                
                                if ($days < 0) {
                                    $status_text = "Vencido hace " . abs($days) . " días";
                                    $status_color = "text-danger";
                                    $icon_color = "text-danger";
                                    $icon = "fa-exclamation-triangle";
                                } elseif ($days == 0) {
                                    $status_text = "Vence HOY";
                                    $status_color = "text-danger fw-bold";
                                    $icon_color = "text-danger";
                                    $icon = "fa-exclamation-circle";
                                } else {
                                    $status_text = "Vence en $days días";
                                    $status_color = ($days <= 15) ? "text-warning" : "text-secondary";
                                    $icon_color = ($days <= 15) ? "text-warning" : "text-muted";
                                    $icon = "fa-clock";
                                }

                                // Icon based on source and Link Logic
                                $entity_icon = "fa-file";
                                $link = "#";
                                
                                if ($exp['source'] == 'vehicle_doc') {
                                    $entity_icon = "fa-truck";
                                    $link = "ficha_vehiculo.php?id=" . $exp['entity_id'] . "&tab=docs"; 
                                } elseif ($exp['source'] == 'worker') {
                                    $entity_icon = "fa-user";
                                    $link = "ficha_trabajador.php?id=" . $exp['entity_id'];
                                } elseif ($exp['source'] == 'worker_doc') {
                                    $entity_icon = "fa-user";
                                    $link = "ficha_trabajador.php?id=" . $exp['entity_id'] . "&tab=docs";
                                }
                            ?>
                            <a href="<?php echo $link; ?>" class="list-group-item list-group-item-action border-0 border-bottom py-3 px-3">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 text-dark fw-bold">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle me-2" style="width: 32px; height: 32px;">
                                            <i class="fas <?php echo $entity_icon; ?> text-secondary small"></i>
                                        </div>
                                        <?php echo htmlspecialchars($exp['entidad']); ?>
                                    </h6>
                                    <small class="<?php echo $status_color; ?> fw-bold bg-light px-2 py-1 rounded">
                                        <i class="fas <?php echo $icon; ?> me-1"></i><?php echo $status_text; ?>
                                    </small>
                                </div>
                                <div class="d-flex w-100 justify-content-between align-items-center ps-5">
                                    <small class="text-muted"><?php echo htmlspecialchars($exp['tipo']); ?></small>
                                    <small class="text-muted"><?php echo $date; ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3 px-3">
                <h5 class="mb-0 fw-bold text-warning"><i class="fas fa-bolt me-2"></i>Accesos Rápidos</h5>
            </div>
            <div class="card-body">
                <a href="vehiculos.php" class="text-decoration-none">
                    <div class="card mb-3 border-0 shadow-sm hover-card bg-primary-subtle-gradient">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="fas fa-car fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-primary fw-bold text-uppercase">Nuevo Vehículo</h6>
                                <small class="text-muted">Adquisición e ingreso de flota</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-primary opacity-25"></i>
                        </div>
                    </div>
                </a>

                <a href="trabajadores.php" class="text-decoration-none">
                    <div class="card mb-3 border-0 shadow-sm hover-card bg-success-subtle-gradient">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="fas fa-user-plus fa-2x text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-success fw-bold text-uppercase">Nuevo Trabajador</h6>
                                <small class="text-muted">Contratación y registro</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-success opacity-25"></i>
                        </div>
                    </div>
                </a>

                <a href="documentos_generales.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm hover-card bg-secondary-subtle-gradient">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3">
                                <i class="fas fa-folder-plus fa-2x text-secondary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-secondary fw-bold text-uppercase">Documentos Generales</h6>
                                <small class="text-muted">Políticas y Reglamentos</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-secondary opacity-25"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.hover-card:hover {
    transform: translateX(5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
/* Subtle gradients for quick access */
.bg-primary-subtle-gradient { background: linear-gradient(to right, #f8f9fa, #e7f1ff); border-left: 4px solid #0d6efd !important; }
.bg-success-subtle-gradient { background: linear-gradient(to right, #f8f9fa, #e6f8ef); border-left: 4px solid #198754 !important; }
.bg-secondary-subtle-gradient { background: linear-gradient(to right, #f8f9fa, #e9ecef); border-left: 4px solid #6c757d !important; }
</style>

<!-- Modal Gastos -->
<div class="modal fade" id="gastosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de Gastos del Mes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="list-group">
          <div class="list-group-item d-flex justify-content-between align-items-center">
            Mantenciones
            <span class="badge bg-primary rounded-pill">$<?php echo number_format($gastos_mant, 0, ',', '.'); ?></span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            Combustible
            <span class="badge bg-primary rounded-pill">$<?php echo number_format($gastos_comb, 0, ',', '.'); ?></span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center list-group-item-dark">
            <strong>Total</strong>
            <strong>$<?php echo number_format($kpis['gastos_mes'], 0, ',', '.'); ?></strong>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Common Chart Options
Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
Chart.defaults.color = '#6c757d';

// SAP Report Chart
const sapCtx = document.getElementById('sapChart').getContext('2d');
new Chart(sapCtx, {
    type: 'bar',
    data: {
        labels: ['Salud Ocupacional', 'Medio Ambiente', 'Seguridad', 'Meta Anual'],
        datasets: [{
            label: 'Porcentaje %',
            data: [
                <?php echo $sap_data['salud']; ?>,
                <?php echo $sap_data['ambiente']; ?>,
                <?php echo $sap_data['seguridad']; ?>,
                <?php echo $sap_data['meta']; ?>
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',   // Success (Green)
                'rgba(23, 162, 184, 0.8)',  // Info (Cyan)
                'rgba(13, 110, 253, 0.8)',  // Primary (Blue)
                'rgba(255, 193, 7, 0.8)'    // Warning (Yellow)
            ],
            borderRadius: 6,
            borderSkipped: false,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 10,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: { borderDash: [2, 2], drawBorder: false }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// Fleet Status Chart
const fleetCtx = document.getElementById('fleetChart').getContext('2d');
const fleetData = {
    labels: <?php echo json_encode($fleet_chart_labels); ?>,
    datasets: [{
        data: <?php echo json_encode($fleet_chart_data); ?>,
        backgroundColor: [
            '#198754', // Disponible (Verde)
            '#0d6efd', // En Uso (Azul)
            '#ffc107', // En Taller (Amarillo)
            '#dc3545'  // Baja (Rojo)
        ],
        borderWidth: 0,
        hoverOffset: 4
    }]
};
new Chart(fleetCtx, {
    type: 'doughnut',
    data: fleetData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { 
                position: 'top',
                align: 'center',
                labels: { 
                    usePointStyle: false, 
                    boxWidth: 35, 
                    boxHeight: 12,
                    padding: 20,
                    font: { size: 12 }
                }
            }
        }
    }
});

// Maintenance Expenses Chart
const maintCtx = document.getElementById('maintenanceChart').getContext('2d');
// Create gradient
const gradientMaint = maintCtx.createLinearGradient(0, 0, 0, 400);
gradientMaint.addColorStop(0, 'rgba(13, 110, 253, 0.8)');
gradientMaint.addColorStop(1, 'rgba(13, 110, 253, 0.2)');

const maintData = {
    labels: [<?php echo implode(',', array_map(function($i){ return "'".$i['month']."'"; }, $maintenance_expenses)); ?>],
    datasets: [{
        label: 'Costo ($)',
        data: [<?php echo implode(',', array_map(function($i){ return $i['cost']; }, $maintenance_expenses)); ?>],
        backgroundColor: gradientMaint,
        borderRadius: 4,
        barPercentage: 0.5,
        hoverBackgroundColor: '#0b5ed7'
    }]
};
new Chart(maintCtx, {
    type: 'bar',
    data: maintData,
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed.y !== null) {
                            label += new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(context.parsed.y);
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                grid: { borderDash: [2, 2], drawBorder: false },
                ticks: {
                    callback: function(value) {
                        return '$' + value / 1000 + 'k';
                    }
                }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>

<!-- SAP Upload Modal -->
<div class="modal fade" id="sapUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cargar Reporte SAP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sapUploadForm">
                    <!-- Tabs -->
                    <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-pill" id="sapTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill" id="sap-upload-tab" data-bs-toggle="tab" data-bs-target="#sap-upload-pane" type="button" role="tab">Subir Archivo</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill" id="sap-paste-tab" data-bs-toggle="tab" data-bs-target="#sap-paste-pane" type="button" role="tab">Pegar Excel</button>
                        </li>
                    </ul>

                    <div class="tab-content mb-3">
                        <!-- Upload Pane -->
                        <div class="tab-pane fade show active" id="sap-upload-pane" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Seleccionar Archivo Excel/CSV</label>
                                <input type="file" class="form-control" id="sapFile" accept=".xlsx, .xls, .csv, .ods">
                                <div class="form-text">Soporta cualquier formato de Excel o CSV.</div>
                            </div>
                        </div>

                        <!-- Paste Pane -->
                        <div class="tab-pane fade" id="sap-paste-pane" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Pegar datos desde Excel</label>
                                <textarea class="form-control font-monospace small bg-light" id="sapPasteArea" rows="6" placeholder="Copie las celdas en Excel y péguelas aquí..."></textarea>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-sm btn-info text-white rounded-pill" id="btnProcessPaste">
                                    <i class="fas fa-magic me-1"></i>Procesar Texto
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Section (Hidden initially) -->
                    <div id="sapPreview" class="d-none border-top pt-3">
                        <h6 class="border-bottom pb-2 mb-3">Vista Previa de Datos (Primeras 5 filas)</h6>
                        <div class="table-responsive mb-3" style="max-height: 200px;">
                            <table class="table table-bordered table-sm table-striped small" id="previewTable">
                                <!-- JS will populate -->
                            </table>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Extracción de Datos Clave</h6>
                        <p class="small text-muted">El sistema intentará detectar los valores automáticamente. Si no es correcto, corríjalo manualmente.</p>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label text-success">Salud Ocupacional (%)</label>
                                <input type="number" step="0.01" class="form-control" id="valSalud" name="salud" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-info">Medio Ambiente (%)</label>
                                <input type="number" step="0.01" class="form-control" id="valAmbiente" name="ambiente" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-primary">Seguridad (%)</label>
                                <input type="number" step="0.01" class="form-control" id="valSeguridad" name="seguridad" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-warning">Meta Anual (%)</label>
                                <input type="number" step="0.01" class="form-control" id="valMeta" name="meta" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">Mes/Año del Reporte</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" name="mes" id="reportMonth">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <input type="number" class="form-control" name="anio" value="<?php echo date('Y'); ?>" style="width: 100px;">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveSap" disabled>Guardar Datos</button>
            </div>
        </div>
    </div>
</div>

<!-- SheetJS for Excel Parsing -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('sapFile');
    const previewDiv = document.getElementById('sapPreview');
    const btnSave = document.getElementById('btnSaveSap');
    const btnProcessPaste = document.getElementById('btnProcessPaste');
    const pasteArea = document.getElementById('sapPasteArea');
    let fullJsonData = null;

    // Shared function to process and display data
    function handleSapData(jsonData) {
        fullJsonData = jsonData;

        if (jsonData.length > 0) {
            previewDiv.classList.remove('d-none');
            btnSave.disabled = false;
            
            // Render Preview Table
            const table = document.getElementById('previewTable');
            table.innerHTML = '';
            
            // Render first 5 rows
            jsonData.slice(0, 5).forEach((row, index) => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement(index === 0 ? 'th' : 'td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                table.appendChild(tr);
            });

            // Auto-Detect Values
            let foundSalud = null;
            let foundAmbiente = null;
            let foundSeguridad = null;
            let foundMeta = null;

            // Iterate to find keywords
            for (let r = 0; r < jsonData.length; r++) {
                if (!jsonData[r]) continue;
                for (let c = 0; c < jsonData[r].length; c++) {
                    let cellVal = String(jsonData[r][c] || '').toLowerCase();
                    
                    // Helper to get value from right (c+1) or below (r+1)
                    const getNextVal = (row, col) => {
                        // Try right first
                        if (jsonData[row] && jsonData[row][col+1] && !isNaN(parseFloat(jsonData[row][col+1]))) return parseFloat(jsonData[row][col+1]);
                        // Try below
                        if (jsonData.length > row+1 && jsonData[row+1] && jsonData[row+1][col] && !isNaN(parseFloat(jsonData[row+1][col]))) return parseFloat(jsonData[row+1][col]);
                        return null;
                    };

                    if (cellVal.includes('salud') || cellVal.includes('ocupacional')) foundSalud = getNextVal(r, c);
                    if (cellVal.includes('ambiente') || cellVal.includes('environment')) foundAmbiente = getNextVal(r, c);
                    if (cellVal.includes('seguridad') || cellVal.includes('safety') || cellVal.includes('accidentalidad')) foundSeguridad = getNextVal(r, c);
                    if (cellVal.includes('meta') || cellVal.includes('target') || cellVal.includes('objetivo')) foundMeta = getNextVal(r, c);
                }
            }

            // Pre-fill inputs
            if (foundSalud !== null) document.getElementById('valSalud').value = (foundSalud <= 1) ? (foundSalud * 100).toFixed(2) : foundSalud;
            if (foundAmbiente !== null) document.getElementById('valAmbiente').value = (foundAmbiente <= 1) ? (foundAmbiente * 100).toFixed(2) : foundAmbiente;
            if (foundSeguridad !== null) document.getElementById('valSeguridad').value = (foundSeguridad <= 1) ? (foundSeguridad * 100).toFixed(2) : foundSeguridad;
            if (foundMeta !== null) document.getElementById('valMeta').value = (foundMeta <= 1) ? (foundMeta * 100).toFixed(2) : foundMeta;
        }
    }

    // File Upload Handler
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            handleSapData(jsonData);
        };
        reader.readAsArrayBuffer(file);
    });

    // Paste Handler
    btnProcessPaste.addEventListener('click', function() {
        const text = pasteArea.value;
        if (!text.trim()) {
            alert('Por favor pegue los datos primero.');
            return;
        }

        // Use SheetJS to parse the text (it handles TSV/CSV)
        // We create a workbook from the string. 
        // Note: XLSX.read with type 'string' detects delimiter.
        try {
            const workbook = XLSX.read(text, {type: 'string', raw: true});
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            handleSapData(jsonData);
        } catch (e) {
            console.error(e);
            alert('Error al procesar el texto pegado. Asegúrese de copiar desde Excel.');
        }
    });

    // Save Data
    document.getElementById('btnSaveSap').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('sapUploadForm'));
        
        // Append full JSON
        formData.append('detalles_json', JSON.stringify(fullJsonData));

        fetch('../scripts/process_sap_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Datos cargados correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al procesar la solicitud');
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
