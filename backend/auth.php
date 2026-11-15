<?php
/**
 * Autenticación de administradores - GMG Repuestos
 * Maneja la sesión de inicio de sesión para el panel /admin.
 * Este login es SOLO para el equipo (gestión de productos y pedidos);
 * los clientes de la tienda nunca necesitan iniciar sesión para comprar.
 */

require_once __DIR__ . '/config.php';
gmgIniciarSesion(); // cookie HttpOnly, Secure y SameSite (F3-1)

// Sin actividad por 30 minutos, la sesión del panel se cierra (F3-1, RNF-003).
const GMG_ADMIN_INACTIVIDAD = 30 * 60;

/**
 * ¿Hay un administrador con sesión iniciada?
 */
function adminLogueado() {
    return isset($_SESSION['admin_id']);
}

/**
 * Corta la ejecución y redirige a login.php si no hay sesión activa.
 * Debe llamarse al inicio de cada página dentro de /admin.
 * $prefijo es la ruta relativa hacia la raíz del sitio (ej: '../' dentro de /admin).
 */
function requerirAdmin($prefijo = '') {
    if (adminLogueado() && time() - (int)($_SESSION['admin_ultimo'] ?? 0) > GMG_ADMIN_INACTIVIDAD) {
        gmgCerrarSesion();
        header('Location: ' . $prefijo . 'login.php?expirada=1');
        exit;
    }
    if (!adminLogueado()) {
        header('Location: ' . $prefijo . 'login.php');
        exit;
    }
    $_SESSION['admin_ultimo'] = time();
}

/** Cierra la sesión y borra su cookie del navegador. */
function gmgCerrarSesion() {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
                                   'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax']);
    session_destroy();
}

/**
 * Nombre del administrador con sesión iniciada (para mostrar en el panel).
 */
function nombreAdminActual() {
    return isset($_SESSION['admin_nombre']) ? $_SESSION['admin_nombre'] : 'Administrador';
}
