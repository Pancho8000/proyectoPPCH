<?php
session_start();
require_once '../../config/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/admin/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT u.id, u.nombre, u.password, u.rol_id, u.trabajador_id, r.nombre as rol_nombre 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.email = '$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_role'] = $user['rol_id'];
            $_SESSION['user_role_name'] = $user['rol_nombre'];
            $_SESSION['trabajador_id'] = $user['trabajador_id'];
            
            // Get worker details if linked
            if ($user['trabajador_id']) {
                $t_sql = "SELECT cargo_id, c.nombre as cargo_nombre 
                          FROM trabajadores t 
                          LEFT JOIN cargos c ON t.cargo_id = c.id 
                          WHERE t.id = " . $user['trabajador_id'];
                $t_result = $conn->query($t_sql);
                if ($t_result && $t_row = $t_result->fetch_assoc()) {
                    $_SESSION['cargo_id'] = $t_row['cargo_id'];
                    $_SESSION['cargo_nombre'] = $t_row['cargo_nombre'];
                }
            }

            // Registrar en Bitácora
            registrar_actividad('Inicio de Sesión', 'Usuario inició sesión exitosamente', $user['id']);
            
            // Redirect based on role or worker status
            // Assuming role_id 1 is Admin
            if ($user['rol_id'] == 1) {
                header("Location: " . BASE_URL . "pages/admin/dashboard.php");
            } else {
                header("Location: " . BASE_URL . "pages/worker/portal_trabajador.php");
            }
            exit();
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hecso Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            background: white;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h3>HECSO Admin</h3>
            <p class="text-muted">Inicie sesión para continuar</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Ingresar</button>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>