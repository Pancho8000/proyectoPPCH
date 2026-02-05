<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

// Fetch Workers for JS lookup
$workers = [];
$resW = $conn->query("SELECT id, nombre, rut FROM trabajadores ORDER BY nombre ASC");
while($r = $resW->fetch_assoc()) {
    $workers[] = $r;
}

// Fetch Vehicles for JS lookup
$vehicles = [];
$resV = $conn->query("SELECT id, patente, marca, modelo FROM vehiculos ORDER BY patente ASC");
while($r = $resV->fetch_assoc()) {
    $vehicles[] = $r;
}

// Document Types
$workerDocTypes = [
    'Antecedentes', 'Licencia de Conducir', 'Hoja de Vida', 'Cédula de Identidad', 
    'Contrato', 'Finiquito', 'Examen Ocupacional', 'Curso/Capacitación'
];
$vehicleDocTypes = [
    'Permiso de Circulación', 'Revisión Técnica', 'Seguro Obligatorio (SOAP)', 
    'Padrón / Inscripción', 'Certificación MLP', 'Gases'
];
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Carga Masiva Inteligente de Documentos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Carga Masiva Docs</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-robot me-1"></i>
            Análisis y Clasificación Automática
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Arrastre múltiples archivos PDF aquí. El sistema intentará detectar automáticamente a qué Trabajador o Vehículo pertenece cada documento, su tipo y fecha de vencimiento.
            </div>

            <!-- Drop Zone -->
            <div id="drop-zone" class="border border-2 border-dashed border-primary rounded p-5 text-center mb-4" style="background-color: #f8f9fa; cursor: pointer; transition: all 0.3s;">
                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                <h5>Arrastre y suelte archivos PDF aquí</h5>
                <p class="text-muted">o haga clic para seleccionar archivos</p>
                <input type="file" id="file-input" multiple accept=".pdf" style="display: none;">
            </div>

            <!-- Progress Bar -->
            <div id="analysis-progress-container" class="mb-4" style="display: none;">
                <label class="mb-1">Analizando archivos...</label>
                <div class="progress">
                    <div id="analysis-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
            </div>

            <!-- Files Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="files-table" style="display: none;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%">Archivo</th>
                            <th style="width: 25%">Entidad Detectada (Trabajador/Vehículo)</th>
                            <th style="width: 20%">Tipo Documento</th>
                            <th style="width: 15%">Fecha Vencimiento</th>
                            <th style="width: 10%">Estado</th>
                            <th style="width: 10%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="files-table-body">
                        <!-- Rows added via JS -->
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button id="btn-process-all" class="btn btn-success btn-lg" disabled>
                    <i class="fas fa-save me-2"></i> Guardar Todo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JS Data -->
<script>
    const WORKERS = <?php echo json_encode($workers); ?>;
    const VEHICLES = <?php echo json_encode($vehicles); ?>;
    const WORKER_DOC_TYPES = <?php echo json_encode($workerDocTypes); ?>;
    const VEHICLE_DOC_TYPES = <?php echo json_encode($vehicleDocTypes); ?>;
</script>

<!-- Logic -->
<script src="../assets/js/bulk_upload.js"></script>

<?php require_once '../../includes/footer.php'; ?>
