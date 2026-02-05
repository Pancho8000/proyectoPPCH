<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- FullCalendar CSS & JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>

<style>
/* Custom Calendar Styles */
:root {
    --fc-border-color: #f0f2f5;
    --fc-button-text-color: #fff;
    --fc-button-bg-color: #0d6efd;
    --fc-button-border-color: #0d6efd;
    --fc-button-hover-bg-color: #0b5ed7;
    --fc-button-hover-border-color: #0a58ca;
    --fc-button-active-bg-color: #0a58ca;
    --fc-button-active-border-color: #0a53be;
    --fc-event-bg-color: #3788d8;
    --fc-event-border-color: #3788d8;
    --fc-today-bg-color: rgba(13, 110, 253, 0.05);
}

.fc .fc-toolbar-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #343a40;
}

.fc .fc-button {
    border-radius: 50rem; /* Pill shape */
    padding: 0.4rem 1.2rem;
    font-weight: 500;
    text-transform: capitalize;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.fc .fc-button-primary:not(:disabled).fc-button-active, 
.fc .fc-button-primary:not(:disabled):active {
    box-shadow: inset 0 3px 5px rgba(0,0,0,0.125);
}

.fc-theme-standard .fc-scrollgrid {
    border: none;
}

.fc-theme-standard td, .fc-theme-standard th {
    border-color: #f0f2f5;
}

.fc .fc-col-header-cell-cushion {
    padding: 10px;
    color: #6c757d;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.fc-daygrid-day-number {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    padding: 8px 12px;
}

.fc-event {
    border: none;
    border-radius: 4px;
    padding: 2px 4px;
    font-size: 0.85rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.1s;
    cursor: pointer;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.fc-daygrid-event {
    white-space: normal !important;
    align-items: flex-start;
}

.legend-item {
    transition: all 0.2s;
}
.legend-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Calendario de Vencimientos</h2>
        <p class="text-muted small mb-0">Visualiza y gestiona las fechas importantes de la flota y personal.</p>
    </div>
    <div class="mt-3 mt-md-0">
         <span class="badge bg-white text-dark shadow-sm border px-3 py-2 rounded-pill">
            <i class="fas fa-calendar-alt me-2 text-primary"></i>
            <?php 
            $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
            echo $meses[date('n') - 1] . " " . date('Y'); 
            ?>
         </span>
    </div>
</div>

<div class="row">
    <div class="col-lg-9 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-layer-group me-2 text-primary"></i>Leyenda</h5>
            </div>
            <div class="card-body px-2">
                <div class="list-group list-group-flush">
                    <div class="legend-item list-group-item border-0 rounded-3 d-flex align-items-center px-3 py-2 mb-1">
                        <span class="badge bg-primary rounded-circle p-2 me-3 shadow-sm"> </span>
                        <div>
                            <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Vehículos</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Documentación</small>
                        </div>
                    </div>
                    <div class="legend-item list-group-item border-0 rounded-3 d-flex align-items-center px-3 py-2 mb-1">
                        <span class="badge bg-info rounded-circle p-2 me-3 shadow-sm"> </span>
                        <div>
                            <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Vehículos</span>
                            <small class="text-muted" style="font-size: 0.75rem;">GPS / Multiflota</small>
                        </div>
                    </div>
                    <div class="legend-item list-group-item border-0 rounded-3 d-flex align-items-center px-3 py-2 mb-1">
                        <span class="badge rounded-circle p-2 me-3 shadow-sm" style="background-color: #fd7e14;"> </span>
                        <div>
                            <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Mantenciones</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Programadas</small>
                        </div>
                    </div>
                    <div class="legend-item list-group-item border-0 rounded-3 d-flex align-items-center px-3 py-2 mb-1">
                        <span class="badge rounded-circle p-2 me-3 shadow-sm" style="background-color: #6f42c1;"> </span>
                        <div>
                            <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Conductores</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Licencias</small>
                        </div>
                    </div>
                    <div class="legend-item list-group-item border-0 rounded-3 d-flex align-items-center px-3 py-2 mb-1">
                        <span class="badge bg-success rounded-circle p-2 me-3 shadow-sm"> </span>
                        <div>
                            <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Salud y Seguridad</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Exámenes / Inducciones</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 bg-primary bg-opacity-10">
            <div class="card-body d-flex align-items-start p-4">
                <i class="fas fa-info-circle text-primary fs-4 me-3 mt-1"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Información</h6>
                    <p class="small text-muted mb-0">
                        Haz clic en cualquier evento del calendario para ser redirigido a la ficha detallada correspondiente.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            list: 'Lista'
        },
        events: '../scripts/get_calendar_events.php',
        eventClick: function(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault(); // don't let the browser navigate
                window.location.href = info.event.url;
            }
        },
        height: 'auto',
        contentHeight: 700,
        aspectRatio: 1.8,
        dayMaxEvents: true, // allow "more" link when too many events
        views: {
            dayGrid: {
                dayMaxEvents: 3 // adjust to 3
            }
        }
    });
    calendar.render();
});
</script>

<?php include '../includes/footer.php'; ?>