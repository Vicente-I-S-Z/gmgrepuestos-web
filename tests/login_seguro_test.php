<?php
/**
 * F3-1 (E1-H1, E1-H2, INC-08, RNF-002, RNF-003, CP-37, CP-38): login seguro del panel.
 *   - cookie de sesión HttpOnly, SameSite=Lax y Secure cuando la visita es HTTPS;
 *   - 5 intentos fallidos bloquean 15 minutos (por IP y por usuario), con el mismo mensaje
 *     exista o no el usuario; entrar bien borra los fallos;
 *   - 30 minutos sin actividad cierran la sesión; logout borra la cookie y la sesión.
 * Base temporal desde BD_v2 (tests/chat_entorno.php) y un segundo sitio (puerto 8096) que guarda
 * las sesiones en una carpeta propia, para simular la inactividad editando el archivo de sesión.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092, 8093 y 8096 libres.
 * Uso: php tests/login_seguro_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('login');
$sesiones = $e['tmp'] . '/sesiones';
mkdir($sesiones);
$sitio = 'http://localhost:8096';
$e['procesos'][] = entornoLevantar([PHP_BINARY, '-d', "session.save_path=$sesiones", '-d', 'error_log=' . $e['log_php'], '-S', 'localhost:8096', '-t', $e['raiz']],
                                   ['GMG_ENV_FILE' => $e['tmp'] . '/config.txt']);
entornoEsperar("$sitio/login.php");

/** Petición con su propio archivo de cookies. Devuelve [código, cabeceras, cuerpo]. */
function pedir(string $url, string $cookies, ?array $post = null, array $cabeceras = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30,
                            CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_HTTPHEADER => $cabeceras]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $r = (string)curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamCabecera = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$codigo, substr($r, 0, $tamCabecera), substr($r, $tamCabecera)];
}
function entrar(string $sitio, string $cookies, string $usuario, string $clave): array {
    return pedir("$sitio/login.php", $cookies, ['usuario' => $usuario, 'password' => $clave]);
}
function errorLogin(string $html): string {
    return preg_match('/class="login-error">([^<]*)</', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
}
function contadores(array $e): int {
    return (int)$e['db']->query("SELECT COUNT(*) FROM limites_solicitudes WHERE clave LIKE 'login%'")->fetch_row()[0];
}

try {
    $hash = $e['db']->real_escape_string(password_hash('Clave-Buena-1', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('admin_a', 'Admin A', '$hash', 1), ('admin_b', 'Admin B', '$hash', 1)");
    $c = $e['tmp'] . '/c1.txt';

    echo "== Cookie de sesión ==\n";
    [, $cab] = pedir("$sitio/login.php", "{$e['tmp']}/c0.txt");
    preg_match('/^Set-Cookie: PHPSESSID=[^\r\n]*/mi', $cab, $m);
    $cookie = $m[0] ?? '';
    revisar(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false && stripos($cookie, 'secure') === false,
            'por HTTP: HttpOnly y SameSite=Lax (sin Secure, si no el navegador no la enviaría)', $cookie);
    [, $cab] = pedir("$sitio/login.php", "{$e['tmp']}/c0b.txt", null, ['X-Forwarded-Proto: https']);
    preg_match('/^Set-Cookie: PHPSESSID=[^\r\n]*/mi', $cab, $m);
    revisar(stripos($m[0] ?? '', '; secure') !== false, 'por HTTPS: además Secure', $m[0] ?? '');

    echo "\n== Bloqueo tras 5 intentos fallidos (CP-38) ==\n";
    for ($i = 1; $i <= 4; $i++) {
        entrar($sitio, $c, 'admin_a', 'mala');
    }
    [, , $html] = entrar($sitio, $c, 'admin_a', 'mala');
    revisar(errorLogin($html) === 'Usuario o contraseña incorrectos.', 'los 5 primeros fallos dicen "incorrectos"');
    [$codigo, $cab, $html] = entrar($sitio, $c, 'admin_a', 'Clave-Buena-1');
    revisar($codigo === 200 && strpos(errorLogin($html), 'Demasiados intentos fallidos') === 0, 'el sexto, aun con la clave correcta, queda bloqueado', errorLogin($html));
    [$codigo] = pedir("$sitio/admin/index.php", $c);
    revisar($codigo === 302, 'y no entra al panel');
    [, , $html] = entrar($sitio, "{$e['tmp']}/c2.txt", 'no_existe', 'x');
    revisar(strpos(errorLogin($html), 'Demasiados intentos') === 0, 'desde la misma IP también se bloquea otro usuario (bloqueo por IP)');
    $e['db']->query("DELETE FROM limites_solicitudes WHERE clave LIKE 'login_ip:%'"); // como si viniera de otra IP
    [, , $html] = entrar($sitio, "{$e['tmp']}/c3.txt", 'admin_a', 'Clave-Buena-1');
    revisar(strpos(errorLogin($html), 'Demasiados intentos') === 0, 'desde otra IP, admin_a sigue bloqueado (bloqueo por usuario)');
    [$codigo, $cab] = entrar($sitio, "{$e['tmp']}/c4.txt", 'admin_b', 'Clave-Buena-1');
    revisar($codigo === 302 && strpos($cab, 'admin/index.php') !== false, 'admin_b sí entra');
    $e['db']->query("UPDATE limites_solicitudes SET ventana_inicio = ventana_inicio - 901 WHERE clave LIKE 'login%'");
    [$codigo] = entrar($sitio, $c, 'admin_a', 'Clave-Buena-1');
    revisar($codigo === 302, 'pasados 15 minutos, admin_a vuelve a entrar');
    revisar(contadores($e) === 0, 'entrar bien borra los intentos fallidos');
    for ($i = 1; $i <= 4; $i++) {
        entrar($sitio, "{$e['tmp']}/c5.txt", 'usuario_x', 'mala');
    }
    [, , $html] = entrar($sitio, "{$e['tmp']}/c5.txt", 'admin_b', 'mala');
    revisar(errorLogin($html) === 'Usuario o contraseña incorrectos.', 'mismo mensaje con usuario existente o inexistente');
    $e['db']->query("DELETE FROM limites_solicitudes WHERE clave LIKE 'login%'");

    echo "\n== Inactividad (30 minutos) ==\n";
    $c6 = "{$e['tmp']}/c6.txt";
    entrar($sitio, $c6, 'admin_a', 'Clave-Buena-1');
    [$codigo] = pedir("$sitio/admin/index.php", $c6);
    revisar($codigo === 200, 'con actividad reciente el panel abre');
    preg_match('/PHPSESSID\s+(\S+)/', file_get_contents($c6), $m);
    $archivo = "$sesiones/sess_" . ($m[1] ?? '');
    $datos = (string)@file_get_contents($archivo);
    file_put_contents($archivo, preg_replace('/admin_ultimo\|i:\d+;/', 'admin_ultimo|i:' . (time() - 31 * 60) . ';', $datos));
    [$codigo, $cab] = pedir("$sitio/admin/index.php", $c6);
    revisar($codigo === 302 && strpos($cab, 'login.php?expirada=1') !== false, '31 minutos sin actividad: vuelve al login', trim(strtok(strstr($cab, 'Location') ?: '-', "\r\n")));
    [, , $html] = pedir("$sitio/login.php?expirada=1", $c6);
    revisar(strpos(errorLogin($html), '30 minutos sin actividad') !== false, 'y explica por qué');
    [$codigo] = pedir("$sitio/admin/index.php", $c6);
    revisar($codigo === 302, 'la sesión vencida ya no sirve');

    echo "\n== Cerrar sesión (CP-37) ==\n";
    $c7 = "{$e['tmp']}/c7.txt";
    entrar($sitio, $c7, 'admin_a', 'Clave-Buena-1');
    [, $cab] = pedir("$sitio/logout.php", $c7);
    revisar(preg_match('/^Set-Cookie: PHPSESSID=deleted|^Set-Cookie: PHPSESSID=;/mi', $cab) === 1, 'logout borra la cookie en el navegador');
    [$codigo] = pedir("$sitio/admin/index.php", $c7);
    revisar($codigo === 302, 'después de salir, el panel redirige al login');

    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    array_map('unlink', glob("$sesiones/*"));
    @rmdir($sesiones);
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
