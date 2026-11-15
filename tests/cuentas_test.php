<?php
/**
 * F3-2 (E1-H5, RF-005) y F4-10 (E1-H6 cuentas, E1-H7 roles, E6-H11 banners, E6-H12 reportes CSV).
 *   - usuarios: crear vendedor, roles (menú y 403), no quitarse el rol propio, siempre un admin,
 *     desactivar corta la sesión abierta;
 *   - restablecer contraseña: misma respuesta exista o no la cuenta, token de un uso, 30 minutos,
 *     validaciones, la clave nueva sirve y la vieja no;
 *   - banners: solo enlaces del sitio, se muestran escapados, pausados o vencidos no;
 *   - reportes: CSV con BOM y ";", y fórmulas neutralizadas.
 * Base temporal desde BD_v2 (incluye el script 09) con tests/chat_entorno.php. No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/cuentas_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('cuentas');
function pedir(array $e, string $jar, string $ruta, ?array $post = null, bool $seguir = true): array {
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $seguir, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => true,
                            CURLOPT_COOKIEJAR => $e['tmp'] . "/$jar.txt", CURLOPT_COOKIEFILE => $e['tmp'] . "/$jar.txt"]);
    if ($post !== null) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $r = (string)curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tam = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['codigo' => $codigo, 'cabeceras' => substr($r, 0, $tam), 'html' => substr($r, $tam)];
}
function csrf(array $e, string $jar, string $ruta): string {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', pedir($e, $jar, $ruta)['html'], $m);
    return $m[1] ?? '';
}
function aviso(string $html): string {
    return preg_match('/class="(?:flash-msg[^"]*|login-error|login-ok)"[^>]*>([^<]*)</', $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES) : '';
}
function entra(array $e, string $jar, string $usuario, string $clave): bool {
    $r = pedir($e, $jar, '/login.php', ['usuario' => $usuario, 'password' => $clave], false);
    return $r['codigo'] === 302 && strpos($r['cabeceras'], 'admin/index.php') !== false;
}
function valor(array $e, string $sql) {
    $f = $e['db']->query($sql)->fetch_row();
    return $f ? $f[0] : null;
}
try {
    $hash = $e['db']->real_escape_string(password_hash('Clave-Admin-1', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo, rol) VALUES ('jefa', 'Jefa', '$hash', 1, 'admin')");
    $idJefa = (int)valor($e, "SELECT id_admin FROM administradores WHERE usuario = 'jefa'");
    revisar(entra($e, 'jefa', 'jefa', 'Clave-Admin-1'), 'la administradora entra');

    echo "\n== Usuarios y roles ==\n";
    $crear = ['accion' => 'crear', 'usuario' => 'vende1', 'nombre' => 'Vendedor Uno', 'email' => 'vende1@example.com', 'rol' => 'vendedor', 'clave' => 'Clave-Vende-1'];
    $msg = aviso(pedir($e, 'jefa', '/admin/usuarios.php', ['csrf' => csrf($e, 'jefa', '/admin/usuarios.php')] + $crear)['html']);
    revisar(strpos($msg, 'creado') !== false && valor($e, "SELECT rol FROM administradores WHERE usuario = 'vende1'") === 'vendedor', 'crea un vendedor con correo', $msg);
    $msg = aviso(pedir($e, 'jefa', '/admin/usuarios.php', ['csrf' => csrf($e, 'jefa', '/admin/usuarios.php')] + $crear)['html']);
    revisar(strpos($msg, 'ya existe') !== false, 'no deja repetir el usuario');
    $cuentasAntes = (int)valor($e, "SELECT COUNT(*) FROM administradores");
    $sinCsrf = aviso(pedir($e, 'jefa', '/admin/usuarios.php', ['accion' => 'crear'] + $crear)['html']);
    revisar(strpos($sinCsrf, 'seguridad del formulario') !== false && (int)valor($e, "SELECT COUNT(*) FROM administradores") === $cuentasAntes, 'sin CSRF no crea nada', $sinCsrf . ' / ' . valor($e, "SELECT COUNT(*) FROM administradores"));
    revisar(entra($e, 'vende', 'vende1', 'Clave-Vende-1'), 'el vendedor entra');
    $menu = pedir($e, 'vende', '/admin/index.php')['html'];
    revisar(strpos($menu, 'href="productos.php"') !== false && strpos($menu, 'href="codigos.php"') === false && strpos($menu, 'href="usuarios.php"') === false,
            'el menú del vendedor no muestra Códigos ni Usuarios');
    $bloqueadas = array_filter(['codigos', 'catalogo', 'conocimiento', 'pagos', 'usuarios', 'banners', 'reportes'], function ($p) use ($e) {
        return pedir($e, 'vende', "/admin/$p.php")['codigo'] !== 403;
    });
    revisar($bloqueadas === [], 'las 7 pantallas de administrador responden 403 al vendedor', implode(',', $bloqueadas));
    revisar(pedir($e, 'vende', '/admin/productos.php')['codigo'] === 200 && pedir($e, 'vende', '/admin/pedidos.php')['codigo'] === 200, 'productos y pedidos sí se abren');
    $editarme = ['accion' => 'editar', 'id' => $idJefa, 'nombre' => 'Jefa', 'email' => '', 'rol' => 'vendedor', 'activo' => '1'];
    $msg = aviso(pedir($e, 'jefa', '/admin/usuarios.php', ['csrf' => csrf($e, 'jefa', '/admin/usuarios.php')] + $editarme)['html']);
    revisar(strpos($msg, 'No puedes quitarte') === 0 && valor($e, "SELECT rol FROM administradores WHERE id_admin = $idJefa") === 'admin', 'nadie puede quitarse el rol de administrador', $msg);
    $idVende = (int)valor($e, "SELECT id_admin FROM administradores WHERE usuario = 'vende1'");
    $desactivar = ['accion' => 'editar', 'id' => $idVende, 'nombre' => 'Vendedor Uno', 'email' => 'vende1@example.com', 'rol' => 'vendedor'];
    pedir($e, 'jefa', '/admin/usuarios.php', ['csrf' => csrf($e, 'jefa', '/admin/usuarios.php')] + $desactivar);
    revisar(pedir($e, 'vende', '/admin/productos.php', null, false)['codigo'] === 302, 'desactivar al vendedor corta su sesión abierta');
    pedir($e, 'jefa', '/admin/usuarios.php', ['csrf' => csrf($e, 'jefa', '/admin/usuarios.php'), 'activo' => '1'] + $desactivar);

    echo "\n== Restablecer contraseña ==\n";
    $msgExiste = aviso(pedir($e, 'anon', '/recuperar.php', ['csrf' => csrf($e, 'anon', '/recuperar.php'), 'usuario' => 'vende1@example.com'])['html']);
    $msgNoExiste = aviso(pedir($e, 'anon', '/recuperar.php', ['csrf' => csrf($e, 'anon', '/recuperar.php'), 'usuario' => 'nadie'])['html']);
    revisar($msgExiste === $msgNoExiste && strpos($msgExiste, 'Si la cuenta existe') === 0, 'la respuesta es la misma exista o no la cuenta');
    revisar((int)valor($e, "SELECT COUNT(*) FROM restablecer_clave WHERE id_admin = $idVende AND usado_en IS NULL") === 1
            && strlen((string)valor($e, "SELECT token_hash FROM restablecer_clave WHERE id_admin = $idVende")) === 64, 'se guarda un enlace pendiente, solo como hash');
    $token = bin2hex(random_bytes(32));
    $e['db']->query("UPDATE restablecer_clave SET usado_en = NOW() WHERE id_admin = $idVende");
    $e['db']->query("INSERT INTO restablecer_clave (id_admin, token_hash, expira_en) VALUES ($idVende, '" . hash('sha256', $token) . "', NOW() + INTERVAL 30 MINUTE)");
    $ruta = "/recuperar.php?token=$token";
    revisar(strpos(pedir($e, 'anon', $ruta)['html'], 'name="clave2"') !== false, 'con un enlace válido aparece el formulario de clave nueva');
    $msg = aviso(pedir($e, 'anon', $ruta, ['csrf' => csrf($e, 'anon', $ruta), 'clave' => 'corta', 'clave2' => 'corta'])['html']);
    revisar(strpos($msg, 'al menos 10') !== false, 'clave corta: se rechaza', $msg);
    $msg = aviso(pedir($e, 'anon', $ruta, ['csrf' => csrf($e, 'anon', $ruta), 'clave' => 'Nueva-Clave-123', 'clave2' => 'Otra-Clave-123'])['html']);
    revisar(strpos($msg, 'no coinciden') !== false, 'claves distintas: se rechaza');
    $msg = aviso(pedir($e, 'anon', $ruta, ['csrf' => csrf($e, 'anon', $ruta), 'clave' => 'Nueva-Clave-123', 'clave2' => 'Nueva-Clave-123'])['html']);
    revisar(strpos($msg, 'Listo') === 0 && entra($e, 'nuevo', 'vende1', 'Nueva-Clave-123') && !entra($e, 'viejo', 'vende1', 'Clave-Vende-1'), 'la clave nueva sirve y la vieja no', $msg);
    revisar(strpos(aviso(pedir($e, 'anon', $ruta)['html']), 'no es válido o ya venció') !== false, 'el enlace no sirve dos veces');
    $vencido = bin2hex(random_bytes(32));
    $e['db']->query("INSERT INTO restablecer_clave (id_admin, token_hash, expira_en) VALUES ($idVende, '" . hash('sha256', $vencido) . "', NOW() - INTERVAL 1 MINUTE)");
    revisar(strpos(aviso(pedir($e, 'anon', "/recuperar.php?token=$vencido")['html']), 'no es válido o ya venció') !== false, 'un enlace vencido no sirve');
    revisar((int)valor($e, "SELECT COUNT(*) FROM auditoria WHERE accion = 'clave_restablecida'") === 1, 'el cambio queda en auditoría');
    revisar(strpos(pedir($e, 'anon', '/login.php')['html'], 'href="recuperar.php"') !== false, 'el login tiene "¿Olvidaste tu contraseña?"');

    echo "\n== Banners ==\n";
    $banner = function (array $datos) use ($e) {
        return aviso(pedir($e, 'jefa', '/admin/banners.php', ['csrf' => csrf($e, 'jefa', '/admin/banners.php'), 'accion' => 'crear'] + $datos)['html']);
    };
    revisar(strpos($banner(['mensaje' => 'Malo', 'enlace' => 'https://otro-sitio.com']), 'página de este sitio') !== false
            && strpos($banner(['mensaje' => 'Malo', 'enlace' => 'javascript:alert(1)']), 'página de este sitio') !== false, 'enlaces a otro sitio o javascript: se rechazan');
    $banner(['mensaje' => 'Llegaron <b>pastillas</b> Swift', 'enlace' => 'index.php?modelo=swift']);
    $banner(['mensaje' => 'Banner vencido', 'vigente_hasta' => date('Y-m-d', strtotime('-1 day'))]);
    $tienda = pedir($e, 'anon', '/index.php')['html'];
    revisar(strpos($tienda, '<div class="banner-tienda" role="note"><a href="index.php?modelo=swift">Llegaron &lt;b&gt;pastillas&lt;/b&gt; Swift →</a></div>') !== false, 'la tienda muestra el banner, escapado y con su enlace');
    revisar(strpos($tienda, 'Banner vencido') === false, 'un banner vencido no se muestra');
    $idBanner = (int)valor($e, "SELECT id_banner FROM banners WHERE mensaje LIKE 'Llegaron%'");
    pedir($e, 'jefa', '/admin/banners.php', ['csrf' => csrf($e, 'jefa', '/admin/banners.php'), 'accion' => 'activar', 'id' => $idBanner, 'activo' => 0]);
    revisar(strpos(pedir($e, 'anon', '/index.php')['html'], 'banner-tienda') === false, 'pausado deja de mostrarse');

    echo "\n== Reportes CSV ==\n";
    $e['db']->query("INSERT INTO clientes (id_cliente, rut, nombre, apellido, email, telefono) VALUES (1, '12345678-5', '=HYPERLINK(\"http://x\")', 'Pérez', 'a@example.com', '+56 9 1111 2222')");
    $e['db']->query("INSERT INTO pedidos (id_pedido, id_cliente, estado, pago_estado, total, fecha) VALUES (77, 1, 'aprobado', 'recibido', 15990, NOW())");
    $r = pedir($e, 'jefa', '/admin/reportes.php?descargar=pedidos&desde=' . date('Y-m-01') . '&hasta=' . date('Y-m-d'));
    revisar(stripos($r['cabeceras'], 'Content-Type: text/csv') !== false && substr($r['html'], 0, 3) === "\xEF\xBB\xBF" && strpos($r['html'], 'Pedido;Fecha;Cliente') !== false, 'pedidos: CSV con BOM y separador ";"');
    revisar(strpos($r['html'], ";\"'=HYPERLINK") !== false && strpos($r['html'], ';"=HYPERLINK') === false, 'un nombre con fórmula sale neutralizado');
    $r = pedir($e, 'jefa', '/admin/reportes.php?descargar=productos');
    revisar(substr_count($r['html'], "\n") === 1 + (int)valor($e, "SELECT COUNT(*) FROM repuestos"), 'catálogo: una fila por repuesto');

    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
