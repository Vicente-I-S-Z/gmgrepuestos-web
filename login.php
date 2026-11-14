<?php
require_once 'backend/auth.php';
require_once 'backend/config.php';

// Si ya hay sesión iniciada, ir directo al panel
if (adminLogueado()) {
    header('Location: admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        // Base de datos v2: la tabla administradores usa id_admin (y tiene el campo
        // "activo"). Se lee con SELECT * para que también funcione si el
        // identificador todavía se llama "id".
        $stmt = $conexion->prepare("SELECT * FROM administradores WHERE usuario = ? LIMIT 1");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $admin = $resultado->fetch_assoc();
            $idAdmin = $admin['id_admin'] ?? ($admin['id'] ?? null);
            $adminActivo = !array_key_exists('activo', $admin) || (int)$admin['activo'] === 1;
            if ($idAdmin !== null && $adminActivo && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$idAdmin;
                $_SESSION['admin_nombre'] = $admin['nombre'];
                header('Location: admin/index.php');
                exit;
            }
        }
        $error = 'Usuario o contraseña incorrectos.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso administrador - GMG Repuestos</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">GMG <span>Repuestos</span></div>
            <p class="login-subtitle">Acceso exclusivo para el equipo administrador</p>

            <?php if ($error): ?>
                <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="login-field">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" autocomplete="username" required autofocus>
                </div>
                <div class="login-field">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-login">Iniciar sesión</button>
            </form>

            <a href="index.php" class="login-back">← Volver a la tienda</a>
        </div>
    </div>
</body>
</html>
