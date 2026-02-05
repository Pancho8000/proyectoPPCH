<?php
require_once '../../config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    registrar_actividad('Cierre de Sesión', 'El usuario ha cerrado sesión', $_SESSION['user_id']);
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>