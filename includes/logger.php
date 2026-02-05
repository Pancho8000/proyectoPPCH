<?php
/**
 * Registra una actividad en la bitácora del sistema.
 * 
 * @param int|null $usuario_id ID del usuario que realiza la acción. Si es null, intenta usar $_SESSION['user_id'].
 * @param string $accion Título corto de la acción (ej: 'Inicio de Sesión', 'Crear Vehículo').
 * @param string $detalles Descripción detallada de la acción.
 * @param mysqli $conn Conexión a la base de datos.
 * @return bool True si se registró correctamente, False si hubo error.
 */
function registrar_actividad($accion, $detalles, $usuario_id = null, $conn = null) {
    // Si no se pasa conexión, intentar usar la global $conn
    if ($conn === null) {
        global $conn;
    }

    if (!$conn) {
        return false;
    }

    // Si no se pasa usuario_id, intentar obtenerlo de la sesión
    if ($usuario_id === null) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    // Si aún no tenemos usuario (ej: login fallido con usuario inexistente, o acción de sistema), 
    // podríamos dejarlo null o usar un ID por defecto si la columna lo permite. 
    // Nuestra tabla requiere usuario_id NOT NULL.
    // Si no hay usuario logueado, no podemos registrar en esta tabla tal cual está definida (FK a usuarios).
    // Para login fallido, necesitamos saber qué usuario intentó. Si el usuario existe, usamos su ID.
    if ($usuario_id === null) {
        return false; 
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Preparar statement
    $stmt = $conn->prepare("INSERT INTO bitacora (usuario_id, accion, detalles, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $usuario_id, $accion, $detalles, $ip_address);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}
?>