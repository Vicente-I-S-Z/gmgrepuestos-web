<?php
/**
 * Correos del sitio (pedido del 15-11: "no llega ningún correo"):
 *   - SmtpMailer contra un SMTP falso (tests/fake_smtp.php): inicia sesión, el remitente es el que se
 *     le indica y el HTML viaja en base64 (una línea de 5.000 caracteres llega completa);
 *   - un 535 (contraseña mala) vuelve con el texto exacto del servidor;
 *   - panel > Correo: muestra la configuración sin la contraseña, el remitente es SMTP_USER y una prueba
 *     fallida muestra la respuesta del servidor; Productos avisa "no se pudo enviar" en vez de callarlo.
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092, 8093, 2526 y 2527 libres.
 * Uso: php tests/correo_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';
require_once __DIR__ . '/../backend/SmtpMailer.php';

$e = entornoIniciar('correo');
$logSmtp = $e['tmp'] . '/smtp.log';
$e['procesos'][] = entornoLevantar([PHP_BINARY, __DIR__ . '/fake_smtp.php', '2526'], ['FAKE_SMTP_LOG' => $logSmtp, 'FAKE_SMTP_MODO' => 'ok']);
$e['procesos'][] = entornoLevantar([PHP_BINARY, __DIR__ . '/fake_smtp.php', '2527'], ['FAKE_SMTP_LOG' => $logSmtp . '.falla', 'FAKE_SMTP_MODO' => 'auth_falla']);
usleep(800000);
$cookies = $e['tmp'] . '/cookies.txt';
function panel(array $e, string $ruta, ?array $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
function csrf(array $e, string $ruta): string {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', panel($e, $ruta), $m);
    return $m[1] ?? '';
}
try {
    echo "== Cliente SMTP ==\n";
    $html = '<p>' . str_repeat('x', 5000) . '</p><p>.línea con punto</p>';
    $r = (new SmtpMailer('127.0.0.1', 2526, '', 'ventas2@gmgrepuestos.cl', 'clave-falsa'))
        ->enviar('cliente@example.com', 'Ana', 'Asunto ñandú', $html, 'ventas2@gmgrepuestos.cl', 'GMG Repuestos');
    usleep(300000);
    $sesion = (string)@file_get_contents($logSmtp);
    revisar(!empty($r['success']), 'envía contra el SMTP falso', $r['error'] ?? '');
    revisar(strpos($sesion, 'C: ' . base64_encode('ventas2@gmgrepuestos.cl')) !== false && strpos($sesion, 'MAIL FROM:<ventas2@gmgrepuestos.cl>') !== false,
            'inicia sesión con la casilla y el remitente es esa misma casilla');
    $sinPrefijo = preg_replace('/^C: /m', '', $sesion);
    preg_match('/Content-Transfer-Encoding: base64\r?\n\r?\n(.*?)\r?\n\.\r?\n/s', $sinPrefijo, $m);
    $lineas = array_values(array_filter(preg_split('/\r?\n/', $m[1] ?? '')));
    revisar($lineas && base64_decode(implode('', $lineas)) === $html && max(array_map('strlen', $lineas)) <= 76,
            'el HTML viaja en base64 en líneas de 76 y llega completo (línea de 5.000 caracteres incluida)');
    $r = (new SmtpMailer('127.0.0.1', 2527, '', 'ventas2@gmgrepuestos.cl', 'mala'))
        ->enviar('cliente@example.com', '', 'x', '<p>x</p>', 'ventas2@gmgrepuestos.cl', 'GMG');
    revisar(empty($r['success']) && strpos($r['error'] ?? '', '535') !== false, 'contraseña mala: devuelve el 535 del servidor', $r['error'] ?? '');

    echo "\n== Panel > Correo ==\n";
    file_put_contents($e['tmp'] . '/config.txt', "SMTP_USER=ventas2@gmgrepuestos.cl\n", FILE_APPEND);
    $hash = $e['db']->real_escape_string(password_hash('Clave-Correo-1', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo, rol) VALUES ('jefa', 'Jefa', '$hash', 1, 'admin')");
    panel($e, '/login.php', ['usuario' => 'jefa', 'password' => 'Clave-Correo-1']);
    $pagina = panel($e, '/admin/correo_prueba.php');
    revisar(strpos($pagina, 've***@gmgrepuestos.cl') !== false && strpos($pagina, 'FALTA: completa SMTP_PASS') !== false && strpos($pagina, 'ventas2@') === false,
            'muestra la configuración (casilla oculta, contraseña solo "falta" o "cargada") y el remitente es SMTP_USER');
    revisar(strpos($pagina, 'href="correo_prueba.php"') !== false, 'aparece en el menú del panel');
    $r = panel($e, '/admin/correo_prueba.php', ['csrf' => csrf($e, '/admin/correo_prueba.php'), 'accion' => 'probar', 'destino' => 'yo@example.com']);
    revisar(strpos($r, 'El servidor de correo rechazó el envío') !== false && strpos($r, 'No se pudo conectar') !== false, 'una prueba fallida muestra la respuesta del servidor');

    echo "\n== Productos avisa la falla ==\n";
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 LIMIT 1")->fetch_row()[0];
    $e['db']->query("UPDATE repuestos SET stock = 0 WHERE id_repuesto = $id");
    $e['db']->query("INSERT INTO avisos_stock (id_repuesto, contacto) VALUES ($id, 'cliente@example.com')");
    $r = panel($e, '/admin/productos.php', ['csrf' => csrf($e, '/admin/productos.php'), 'accion' => 'rapido', 'id' => $id, 'precio' => '9000', 'stock' => '4']);
    preg_match('/class="flash-msg[^"]*">([^<]*)</', $r, $a);
    $aviso = html_entity_decode($a[1] ?? '', ENT_QUOTES);
    revisar(strpos($aviso, 'Ojo: no se pudo enviar el aviso de stock por correo') !== false, 'al guardar stock, avisa que el correo falló', $aviso);
    $pagina = panel($e, '/admin/correo_prueba.php');
    revisar(strpos($pagina, '1 aviso por correo de repuestos que ya tienen stock') !== false && strpos($pagina, 'Reintentar avisos pendientes') !== false,
            'Correo ofrece reintentar el aviso pendiente');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
