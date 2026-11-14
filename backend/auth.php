<?php
/**
 * Autenticación de administradores - GMG Repuestos
 * Maneja la sesión de inicio de sesión para el panel /admin.
 * Este login es SOLO para el equipo (gestión de productos y pedidos);
 * los clientes de la tienda nunca necesitan iniciar sesión para comprar.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    if (!adminLogueado()) {
        header('Location: ' . $prefijo . 'login.php');
        exit;
    }
}

/**
 * Nombre del administrador con sesión iniciada (para mostrar en el panel).
 */
function nombreAdminActual() {
    return isset($_SESSION['admin_nombre']) ? $_SESSION['admin_nombre'] : 'Administrador';
}
