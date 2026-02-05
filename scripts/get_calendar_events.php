<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$events = [];

// Helper function to add event
function addEvent(&$events, $title, $date, $color, $url = '#') {
    if (!empty($date) && $date != '0000-00-00') {
        $events[] = [
            'title' => $title,
            'start' => $date,
            'color' => $color,
            'url' => $url,
            'allDay' => true
        ];
    }
}

// 1. VEHICLES
$sql_veh = "SELECT id, patente, marca, modelo, revision_tecnica, seguro_vencimiento, permiso_circulacion, certificacion_mlp, proxima_mantencion, gps, multiflota FROM vehiculos";
$res_veh = $conn->query($sql_veh);

if ($res_veh) {
    while ($row = $res_veh->fetch_assoc()) {
        $patente = $row['patente'];
        $link = "ficha_vehiculo.php?id=" . $row['id'];
        
        // Document Colors: Blue #0d6efd
        addEvent($events, "Rev. Téc: $patente", $row['revision_tecnica'], '#0d6efd', $link);
        addEvent($events, "Seguro: $patente", $row['seguro_vencimiento'], '#0d6efd', $link);
        addEvent($events, "Permiso: $patente", $row['permiso_circulacion'], '#0d6efd', $link);
        addEvent($events, "Cert. MLP: $patente", $row['certificacion_mlp'], '#0d6efd', $link);
        addEvent($events, "GPS: $patente", $row['gps'], '#0dcaf0', $link); // Cyan
        addEvent($events, "Multiflota: $patente", $row['multiflota'], '#0dcaf0', $link);

        // Maintenance: Orange #fd7e14
        // Check if proxima_mantencion looks like a date (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['proxima_mantencion'])) {
             addEvent($events, "Mantención: $patente", $row['proxima_mantencion'], '#fd7e14', $link);
        }
    }
}

// 2. WORKERS
$sql_trab = "SELECT id, nombres, apellidos, licencia_vencimiento, licencia_interna_mlp, examen_salud, induccion_hombre_nuevo, odi_puerto_desaladora FROM trabajadores";
$res_trab = $conn->query($sql_trab);

if ($res_trab) {
    while ($row = $res_trab->fetch_assoc()) {
        $name = $row['nombres'] . ' ' . explode(' ', $row['apellidos'])[0]; // First name + First Lastname
        $link = "ficha_trabajador.php?id=" . $row['id'];

        // License: Purple #6f42c1
        addEvent($events, "Licencia: $name", $row['licencia_vencimiento'], '#6f42c1', $link);
        addEvent($events, "Lic. Int: $name", $row['licencia_interna_mlp'], '#6f42c1', $link);

        // Health/Safety: Green #198754
        addEvent($events, "Examen: $name", $row['examen_salud'], '#198754', $link);
        addEvent($events, "Inducción: $name", $row['induccion_hombre_nuevo'], '#198754', $link);
        addEvent($events, "ODI: $name", $row['odi_puerto_desaladora'], '#198754', $link);
    }
}

echo json_encode($events);
?>
