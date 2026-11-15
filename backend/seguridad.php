<?php
/**
 * Protecciones de conexión - GMG Repuestos (historia E10-H1)
 *
 * El archivo .htaccess de la raíz ya obliga a usar HTTPS y agrega las
 * cabeceras de seguridad. Este archivo hace lo mismo desde PHP como segundo
 * seguro: si por cualquier razón el hosting no lee el .htaccess, las páginas
 * PHP igual salen protegidas.
 *
 * Qué hace:
 *  1. Si alguien entra por http:// redirige a https:// con código 301.
 *     (No se aplica en el computador local de desarrollo.)
 *  2. Envía las cabeceras:
 *       Strict-Transport-Security  -> el navegador recuerda usar siempre HTTPS (HSTS)
 *       X-Content-Type-Options     -> nosniff
 *       X-Frame-Options            -> SAMEORIGIN
 *       Referrer-Policy            -> strict-origin-when-cross-origin
 */

/** ¿La visita actual llegó por una conexión cifrada (HTTPS)? */
function gmgEsHttps() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // Hostinger / proxies: avisan por esta cabecera que el visitante usó HTTPS.
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/**
 * Dirección pública base del sitio, sin barra final. Usa SITE_URL del .env
 * si existe; si no, la deduce de la visita (https://dominio/subcarpeta).
 */
function gmgUrlBase() {
    $configurada = trim((string)gmgEnv('SITE_URL', ''));
    if ($configurada !== '') {
        return rtrim($configurada, '/');
    }
    $esquema = gmgEsHttps() ? 'https' : 'http';
    $host = gmgHostValido() ? $_SERVER['HTTP_HOST'] : 'localhost';
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $esquema . '://' . $host . $carpeta;
}

/** ¿La dirección con que visitan el sitio tiene un formato normal (letras, números, puntos, guiones y puerto)? */
function gmgHostValido() {
    return !empty($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $_SERVER['HTTP_HOST']) === 1;
}

/** Redirige a HTTPS (301) y envía las cabeceras de seguridad. */
function gmgAplicarSeguridadConexion() {
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    if (!gmgEsHttps() && !gmgEsLocal() && gmgHostValido()) {
        // Solo se conserva el camino de la dirección (sin saltos de línea ni caracteres raros).
        $camino = preg_replace('/[\x00-\x1F\x7F]/', '', $_SERVER['REQUEST_URI'] ?? '/');
        $destino = 'https://' . $_SERVER['HTTP_HOST'] . $camino;
        header('Location: ' . $destino, true, 301);
        exit;
    }

    if (gmgEsHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Límite de solicitudes por IP con ventana fija (RNF-035, E10-H3).
 * Requiere la tabla limites_solicitudes (BD_v2/05_tablas_panel.sql).
 * Devuelve true si la IP ya superó el máximo en la ventana actual.
 */
function gmgLimiteSuperado(mysqli $conexion, string $accion, int $maximo, int $segundos): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'sin-ip';
    $clave = substr($accion, 0, 30) . ':' . hash('sha256', $ip);
    $ahora = time();
    $limiteVentana = $ahora - $segundos;
    $stmt = $conexion->prepare("INSERT INTO limites_solicitudes (clave, ventana_inicio, contador) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            contador = IF(ventana_inicio <= ?, 1, contador + 1),
            ventana_inicio = IF(ventana_inicio <= ?, ?, ventana_inicio)");
    if (!$stmt) {
        // Sin la tabla no se puede contar: se deja pasar y se avisa en el log.
        error_log('[GMG Límites] No se pudo registrar la solicitud: ' . $conexion->error);
        return false;
    }
    $stmt->bind_param('siiii', $clave, $ahora, $limiteVentana, $limiteVentana, $ahora);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare("SELECT contador FROM limites_solicitudes WHERE clave = ?");
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_row();
    $contador = (int)($fila[0] ?? 0);
    $stmt->close();
    return $contador > $maximo;
}

gmgAplicarSeguridadConexion();
