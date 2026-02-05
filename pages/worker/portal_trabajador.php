<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/auth/login.php");
    exit();
}

$trabajador_id = $_SESSION['trabajador_id'] ?? null;
$cargo_nombre = $_SESSION['cargo_nombre'] ?? '';

// If no worker linked, show error
if (!$trabajador_id) {
    die('<div class="container mt-5"><div class="alert alert-danger">Acceso denegado. No hay perfil de trabajador asociado a su cuenta de usuario. Contacte al administrador. <a href="' . BASE_URL . 'pages/auth/logout.php">Cerrar Sesión</a></div></div>');
}

$message = '';
$message_type = '';

// Handle Route Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_ruta'])) {
    $vehiculo_id = $_POST['vehiculo_id'];
    $kilometraje = $_POST['kilometraje'];
    $tipo_movimiento = 'Salida'; // Default as requested "registrar salida"
    
    // Handle Image Upload
    $foto_path = '';
    if (isset($_FILES['foto_tablero']) && $_FILES['foto_tablero']['error'] == 0) {
        $target_dir = "../../assets/uploads/tableros/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES["foto_tablero"]["name"], PATHINFO_EXTENSION);
        $filename = "tablero_" . time() . "_" . $trabajador_id . "." . $file_extension;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["foto_tablero"]["tmp_name"], $target_file)) {
            $foto_path = "assets/uploads/tableros/" . $filename;
        } else {
            $message = "Error al subir la imagen.";
            $message_type = "danger";
        }
    }

    if (empty($message)) {
        $sql = "INSERT INTO rutas (trabajador_id, vehiculo_id, kilometraje, foto_tablero, tipo_movimiento) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiss", $trabajador_id, $vehiculo_id, $kilometraje, $foto_path, $tipo_movimiento);
        
        if ($stmt->execute()) {
            // Update vehicle mileage
            $conn->query("UPDATE vehiculos SET kilometraje = $kilometraje WHERE id = $vehiculo_id");
            
            // Log activity
            registrar_actividad('Registrar Ruta', "Vehículo ID: $vehiculo_id, Kilometraje: $kilometraje, Tipo: $tipo_movimiento", $_SESSION['user_id']);

            $message = "Ruta registrada exitosamente.";
            $message_type = "success";
        } else {
            $message = "Error al registrar ruta: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Handle Vehicle Return (Acta de Devolución)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_devolucion'])) {
    $vehiculo_id = $_POST['vehiculo_id'];
    $kilometraje = $_POST['kilometraje'];
    $combustible = $_POST['combustible'];
    $obs = $_POST['observaciones'];
    
    // Checkboxes (isset = 1, else 0)
    $limpieza_int = isset($_POST['limpieza_int']) ? 1 : 0;
    $limpieza_ext = isset($_POST['limpieza_ext']) ? 1 : 0;
    $luces = isset($_POST['luces']) ? 1 : 0;
    $neumaticos = isset($_POST['neumaticos']) ? 1 : 0;
    $rueda_repuesto = isset($_POST['rueda_repuesto']) ? 1 : 0;
    $gata = isset($_POST['gata']) ? 1 : 0;
    $docs = isset($_POST['docs']) ? 1 : 0;
    
    // Handle Signature (Base64)
    $firma_path = '';
    if (!empty($_POST['firma_base64'])) {
        $data_uri = $_POST['firma_base64'];
        $encoded_image = explode(",", $data_uri)[1];
        $decoded_image = base64_decode($encoded_image);
        
        $filename = "firma_" . time() . "_" . $trabajador_id . ".png";
        $target_file = "../../assets/uploads/firmas/" . $filename;
        
        if (file_put_contents($target_file, $decoded_image)) {
            $firma_path = "assets/uploads/firmas/" . $filename;
        }
    }
    
    $sql = "INSERT INTO actas_vehiculos (vehiculo_id, trabajador_id, tipo, kilometraje, nivel_combustible, limpieza_int, limpieza_ext, luces, neumaticos, rueda_repuesto, gata_llave, documentos, observaciones, firma_path) 
            VALUES (?, ?, 'Devolucion', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiissiiiiiiiss", $vehiculo_id, $trabajador_id, $kilometraje, $combustible, $limpieza_int, $limpieza_ext, $luces, $neumaticos, $rueda_repuesto, $gata, $docs, $obs, $firma_path);
    
    if ($stmt->execute()) {
        // Update vehicle mileage
        $conn->query("UPDATE vehiculos SET kilometraje = GREATEST(COALESCE(kilometraje,0), $kilometraje) WHERE id = $vehiculo_id");
        
        registrar_actividad('Acta Devolución', "Vehículo ID: $vehiculo_id devuelto por Trabajador ID: $trabajador_id", $_SESSION['user_id']);
        $message = "Devolución registrada correctamente.";
        $message_type = "success";
    } else {
        $message = "Error al registrar devolución: " . $conn->error;
        $message_type = "danger";
    }
}

// Fetch Worker Info
$sql_worker = "SELECT t.*, c.nombre as cargo_nombre_db 
               FROM trabajadores t 
               LEFT JOIN cargos c ON t.cargo_id = c.id 
               WHERE t.id = $trabajador_id";
$worker = $conn->query($sql_worker)->fetch_assoc();

// Use DB cargo name if session one is missing
if (empty($cargo_nombre) && isset($worker['cargo_nombre_db'])) {
    $cargo_nombre = $worker['cargo_nombre_db'];
}

// Fetch Certificates
$sql_certs = "SELECT * FROM certificados WHERE trabajador_id = $trabajador_id ORDER BY fecha_emision DESC";
$certs = $conn->query($sql_certs);

// Check if driver
$user_role_name = $_SESSION['user_role_name'] ?? '';
$is_driver = (stripos($cargo_nombre, 'Conductor') !== false || stripos($cargo_nombre, 'Chofer') !== false || stripos($cargo_nombre, 'Operador') !== false || stripos($user_role_name, 'Conductor') !== false);

// Fetch Vehicles (for dropdown)
$vehicles = $conn->query("SELECT id, patente, marca, modelo FROM vehiculos ORDER BY marca, modelo");

// Fetch Recent Routes (if driver)
$recent_routes = null;
if ($is_driver) {
    $sql_routes = "SELECT r.*, v.patente, v.marca, v.modelo 
                   FROM rutas r 
                   JOIN vehiculos v ON r.vehiculo_id = v.id 
                   WHERE r.trabajador_id = $trabajador_id 
                   ORDER BY r.fecha_registro DESC LIMIT 5";
    $recent_routes = $conn->query($sql_routes);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Trabajador - HECSO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-bottom: 80px; } /* Padding for bottom nav */
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        
        /* Mobile App Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            z-index: 1050;
            box-shadow: 0 -2px 15px rgba(0,0,0,0.1);
            padding: 0.5rem 0.5rem 1rem 0.5rem; /* Extra padding at bottom for iOS home bar */
            display: flex;
            justify-content: space-around;
            border-top-left-radius: 1.5rem;
            border-top-right-radius: 1.5rem;
        }
        
        .bottom-nav .nav-link {
            color: #adb5bd;
            background: transparent;
            border: none;
            border-radius: 1rem;
            padding: 0.5rem;
            font-size: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            transition: all 0.2s ease;
        }
        
        .bottom-nav .nav-link i {
            font-size: 1.4rem;
            margin-bottom: 4px;
            margin-right: 0 !important;
            transition: transform 0.2s;
        }
        
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background-color: transparent;
            font-weight: 600;
        }
        
        .bottom-nav .nav-link.active i {
            transform: translateY(-2px);
        }

        .form-control, .form-select { 
            border-radius: 0.8rem; 
            padding: 0.8rem 1rem; 
            border-color: #f1f3f5;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .form-control:focus, .form-select:focus { 
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); 
            border-color: #86b7fe; 
        }
        
        .btn-app {
            border-radius: 1rem;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .avatar-circle { width: 80px; height: 80px; background-color: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #0d6efd; margin: 0 auto 1rem; }
        
        /* Mobile List Item Style */
        .mobile-list-item {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border: 1px solid #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- App Header -->
    <nav class="navbar navbar-light bg-white shadow-sm sticky-top mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                    <i class="fas fa-hard-hat text-primary"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark lh-1">Portal Trabajador</h6>
                    <small class="text-muted" style="font-size: 0.7rem;">HECSO App</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($worker['nombre']); ?></div>
                </div>
                <a href="logout.php" class="btn btn-light btn-sm rounded-circle shadow-sm" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-sign-out-alt text-danger"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2 fa-lg"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Bottom Navigation (Replaces Tabs) -->
        <div class="bottom-nav nav nav-pills" id="workerTabs" role="tablist">
            <button class="nav-link active" id="home-tab" data-bs-toggle="pill" data-bs-target="#home" type="button" role="tab">
                <i class="fas fa-home"></i> Inicio
            </button>
            <button class="nav-link" id="docs-tab" data-bs-toggle="pill" data-bs-target="#docs" type="button" role="tab">
                <i class="fas fa-file-alt"></i> Docs
            </button>
            <?php if ($is_driver): ?>
            <button class="nav-link" id="route-tab" data-bs-toggle="pill" data-bs-target="#route" type="button" role="tab">
                <i class="fas fa-route"></i> Ruta
            </button>
            <button class="nav-link" id="return-tab" data-bs-toggle="pill" data-bs-target="#return" type="button" role="tab">
                <i class="fas fa-undo"></i> Devolver
            </button>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="workerTabsContent">
            
            <!-- HOME TAB (Profile) -->
            <div class="tab-pane fade show active" id="home" role="tabpanel">
                <div class="card mb-4 text-center py-4 bg-white shadow-sm">
                    <div class="card-body">
                        <div class="avatar-circle shadow-sm mx-auto mb-3">
                            <span class="fw-bold"><?php echo strtoupper(substr($worker['nombre'], 0, 1)); ?></span>
                        </div>
                        <h4 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($worker['nombre']); ?></h4>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-4">
                            <?php echo htmlspecialchars($cargo_nombre); ?>
                        </span>
                        
                        <div class="row g-3 text-start mt-2">
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-light h-100">
                                    <label class="small text-muted d-block text-uppercase fw-bold mb-1">RUT</label>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($worker['rut']); ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-light h-100">
                                    <label class="small text-muted d-block text-uppercase fw-bold mb-1">Ingreso</label>
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($worker['fecha_ingreso'])); ?></div>
                                </div>
                            </div>
                            <?php if(isset($worker['licencia_vencimiento'])): ?>
                            <div class="col-12">
                                <div class="p-3 rounded-4 bg-light border border-2 <?php echo (strtotime($worker['licencia_vencimiento']) < time() + 2592000) ? 'border-danger bg-danger bg-opacity-10' : 'border-success bg-success bg-opacity-10'; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <label class="small text-muted d-block text-uppercase fw-bold mb-1">Venc. Licencia</label>
                                            <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($worker['licencia_vencimiento'])); ?></div>
                                        </div>
                                        <i class="fas fa-id-card fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-pane fade" id="docs" role="tabpanel">
                <h5 class="fw-bold mb-3 px-2">Mis Documentos</h5>
                
                <?php if ($certs && $certs->num_rows > 0): ?>
                    <div class="row g-3">
                        <?php while($cert = $certs->fetch_assoc()): ?>
                            <div class="col-12 col-md-6">
                                <div class="mobile-list-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3 flex-shrink-0 text-danger">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <div class="text-truncate">
                                            <h6 class="mb-1 fw-bold text-dark text-truncate"><?php echo htmlspecialchars($cert['nombre']); ?></h6>
                                            <div class="small text-muted">
                                                Vence: 
                                                <?php if($cert['fecha_vencimiento']): ?>
                                                    <?php 
                                                    $days_diff = (strtotime($cert['fecha_vencimiento']) - time()) / (60 * 60 * 24);
                                                    $color_class = ($days_diff < 0) ? 'text-danger fw-bold' : (($days_diff < 30) ? 'text-warning fw-bold' : 'text-success');
                                                    ?>
                                                    <span class="<?php echo $color_class; ?>"><?php echo date('d/m/Y', strtotime($cert['fecha_vencimiento'])); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="<?php echo BASE_URL . $cert['archivo']; ?>" target="_blank" class="btn btn-light btn-sm rounded-circle shadow-sm ms-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle p-4 d-inline-block mb-3">
                            <i class="fas fa-file-invoice fa-2x text-muted opacity-50"></i>
                        </div>
                        <p class="text-muted fw-bold">No tienes documentos asignados</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_driver): ?>
            <!-- Route Registration Tab -->
            <div class="tab-pane fade" id="route" role="tabpanel">
                <h5 class="fw-bold mb-3 px-2">Nueva Ruta</h5>
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">

                                    <input type="hidden" name="registrar_ruta" value="1">
                                    <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Vehículo</label>
                                    <select class="form-select" name="vehiculo_id" required>
                                        <option value="">Seleccione...</option>
                                        <?php 
                                        $vehicles->data_seek(0);
                                        while($v = $vehicles->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $v['id']; ?>">
                                            <?php echo $v['patente'] . ' - ' . $v['marca'] . ' ' . $v['modelo']; ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Kilometraje Actual</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="kilometraje" required min="0" placeholder="0">
                                        <span class="input-group-text border-start-0">km</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Foto Tablero</label>
                                    <div class="d-grid">
                                        <input type="file" class="d-none" id="foto_tablero" name="foto_tablero" accept="image/*" capture="environment" required onchange="document.getElementById('foto_label_text').textContent = this.files[0].name; document.getElementById('foto_label').classList.remove('btn-outline-primary', 'border-dashed'); document.getElementById('foto_label').classList.add('btn-success', 'text-white');">
                                        <label for="foto_tablero" class="btn btn-outline-primary btn-lg border-2 border-dashed d-flex flex-column align-items-center justify-content-center py-4 rounded-4" id="foto_label">
                                            <i class="fas fa-camera fa-2x mb-2"></i>
                                            <span class="small fw-bold" id="foto_label_text">Tomar Foto</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-success btn-app text-white shadow">
                                        Registrar Salida
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($recent_routes && $recent_routes->num_rows > 0): ?>
                <h5 class="fw-bold mb-3 mt-4 px-2">Últimas Rutas</h5>
                <div class="row g-3">
                    <?php while($route = $recent_routes->fetch_assoc()): ?>
                    <div class="col-12">
                        <div class="mobile-list-item d-flex justify-content-between align-items-center p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 flex-shrink-0 text-success">
                                    <i class="fas fa-route"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark"><?php echo $route['patente']; ?></h6>
                                    <div class="small text-muted">
                                        <?php echo date('d/m H:i', strtotime($route['fecha_registro'])); ?> • 
                                        <?php echo number_format($route['kilometraje'], 0, ',', '.'); ?> km
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-light text-success border">Salida</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Return Vehicle Tab -->
            <div class="tab-pane fade" id="return" role="tabpanel">
                <h5 class="fw-bold mb-3 px-2">Devolver Vehículo</h5>
                <div class="card shadow-sm mb-5">
                    <div class="card-body p-4">
                        <form method="POST" id="returnForm">
                            <input type="hidden" name="registrar_devolucion" value="1">
                            <input type="hidden" name="firma_base64" id="firma_base64">
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Vehículo</label>
                                    <select class="form-select" name="vehiculo_id" required>
                                        <option value="">Seleccione...</option>
                                        <?php 
                                        $vehicles->data_seek(0);
                                        while($v = $vehicles->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $v['id']; ?>">
                                            <?php echo $v['patente'] . ' - ' . $v['marca']; ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Km Llegada</label>
                                    <input type="number" class="form-control" name="kilometraje" required min="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Combustible</label>
                                    <select class="form-select" name="combustible" required>
                                        <option value="Lleno">Lleno</option>
                                        <option value="3/4">3/4</option>
                                        <option value="1/2">1/2</option>
                                        <option value="1/4">1/4</option>
                                        <option value="Reserva">Reserva</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-dark">Checklist Estado</h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="limpieza_int" id="limpieza_int" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="limpieza_int">
                                                <i class="fas fa-broom"></i> Interior
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="limpieza_ext" id="limpieza_ext" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="limpieza_ext">
                                                <i class="fas fa-car-wash"></i> Exterior
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="luces" id="luces" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="luces">
                                                <i class="fas fa-lightbulb"></i> Luces
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="neumaticos" id="neumaticos" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="neumaticos">
                                                <i class="fas fa-circle-notch"></i> Neumáticos
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="docs" id="docs_check" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="docs_check">
                                                <i class="fas fa-file-contract"></i> Papeles
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="checkbox" class="btn-check" name="gata" id="gata" checked autocomplete="off">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex align-items-center justify-content-center gap-2" for="gata">
                                                <i class="fas fa-tools"></i> Gata/Kit
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Observaciones</label>
                                    <textarea class="form-control" name="observaciones" rows="2" placeholder="Daños, ruidos, etc..."></textarea>
                                </div>
                                
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Firma</label>
                                    <div class="border rounded-3 bg-white shadow-sm overflow-hidden" style="height: 150px; background-color: #fff;">
                                        <canvas id="signature-pad" class="w-100 h-100" style="touch-action: none;"></canvas>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0 mt-1" id="clear-signature">
                                        <i class="fas fa-eraser me-1"></i>Limpiar Firma
                                    </button>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary btn-app text-white shadow">
                                        Firmar y Finalizar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div> <!-- End Tab Content -->
    </div> <!-- End Container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var canvas = document.getElementById('signature-pad');
            if (canvas) {
                function resizeCanvas() {
                    var ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                }
                window.onresize = resizeCanvas;
                resizeCanvas();

                var signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: 'rgb(0, 0, 0)'
                });

                document.getElementById('clear-signature').addEventListener('click', function() {
                    signaturePad.clear();
                });

                document.getElementById('returnForm').addEventListener('submit', function(e) {
                    if (signaturePad.isEmpty()) {
                        e.preventDefault();
                        alert("Por favor firme el documento antes de enviar.");
                    } else {
                        var data = signaturePad.toDataURL('image/png');
                        document.getElementById('firma_base64').value = data;
                    }
                });
            }
        });
    </script>
</body>
</html>
