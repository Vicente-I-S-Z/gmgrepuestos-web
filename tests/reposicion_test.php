<?php
/**
 * F3-9 (E7-H3, RF-064): correo de reposición. Al guardar stock mayor a 0 en un repuesto con avisos
 * pendientes, se escribe a los contactos que son correo y se marcan notificados; los celulares quedan
 * pendientes (se avisan por WhatsApp desde el panel) y un correo que falla también queda pendiente.
 * La función se prueba con un enviador falso; el panel, por HTTP con el SMTP apagado (no debe romperse).
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/reposicion_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('reposicion');
putenv('GMG_ENV_FILE=' . $e['tmp'] . '/config.txt');
$_SERVER['HTTP_HOST'] = 'localhost:8092';
require __DIR__ . '/../backend/config.php';
require __DIR__ . '/../backend/email_helpers.php';

$enviados = [];
$enviadorOk = function ($para, $nombre, $asunto, $html) use (&$enviados) {
    $enviados[] = compact('para', 'asunto', 'html');
    return ['success' => true];
};
$enviadorFalla = function () { return ['success' => false, 'error' => 'SMTP caído (prueba)']; };
function pendientes(array $e, int $id): array {
    return array_column($e['db']->query("SELECT contacto FROM avisos_stock WHERE id_repuesto = $id AND notificado = 0 ORDER BY id_aviso")->fetch_all(), 0);
}
try {
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 ORDER BY id_repuesto LIMIT 1")->fetch_row()[0];
    $nombre = (string)$e['db']->query("SELECT nombre FROM repuestos WHERE id_repuesto = $id")->fetch_row()[0];
    $e['db']->query("UPDATE repuestos SET stock = 0 WHERE id_repuesto = $id");
    $e['db']->query("INSERT INTO avisos_stock (id_repuesto, contacto) VALUES ($id, 'ana@example.com'), ($id, '+56 9 1111 2222'), ($id, 'luis@example.com')");

    echo "== Función ==\n";
    revisar(gmgNotificarReposicion($conexion, $id, $enviadorOk) === 0 && $enviados === [], 'con stock 0 no avisa a nadie');
    $e['db']->query("UPDATE repuestos SET stock = 4 WHERE id_repuesto = $id");
    $n = gmgNotificarReposicion($conexion, $id, $enviadorOk);
    revisar($n === 2 && array_column($enviados, 'para') === ['ana@example.com', 'luis@example.com'], 'con stock: escribe a los 2 correos', json_encode(array_column($enviados, 'para')));
    revisar(pendientes($e, $id) === ['+56 9 1111 2222'], 'los correos quedan notificados y el celular sigue pendiente (WhatsApp)');
    $html = $enviados[0]['html'] ?? '';
    revisar(strpos($enviados[0]['asunto'] ?? '', $nombre) !== false && strpos($html, "producto.php?id=$id") !== false && strpos($html, 'wa.me/') !== false,
            'el correo nombra el repuesto y enlaza a su ficha y a WhatsApp', $enviados[0]['asunto'] ?? '');
    $enviados = [];
    revisar(gmgNotificarReposicion($conexion, $id, $enviadorOk) === 0 && $enviados === [], 'al guardar de nuevo no repite el correo');
    $e['db']->query("INSERT INTO avisos_stock (id_repuesto, contacto) VALUES ($id, 'pedro@example.com')");
    revisar(gmgNotificarReposicion($conexion, $id, $enviadorFalla) === 0 && in_array('pedro@example.com', pendientes($e, $id), true), 'si el correo falla, el aviso queda pendiente');

    echo "\n== Panel (SMTP apagado) ==\n";
    $hash = $e['db']->real_escape_string(password_hash('Clave-F39', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f39', 'Prueba F3-9', '$hash', 1)");
    $cookies = $e['tmp'] . '/cookies.txt';
    $panel = function ($ruta, $post = null) use ($e, $cookies) {
        $ch = curl_init($e['sitio'] . $ruta);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
        if ($post) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
        $r = (string)curl_exec($ch);
        curl_close($ch);
        return $r;
    };
    $panel('/login.php', ['usuario' => 'prueba_f39', 'password' => 'Clave-F39']);
    $e['db']->query("UPDATE repuestos SET stock = 0 WHERE id_repuesto = $id");
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $panel('/admin/productos.php'), $m);
    $html = $panel('/admin/productos.php', ['csrf' => $m[1] ?? '', 'accion' => 'rapido', 'id' => $id, 'precio' => '5000', 'stock' => '3']);
    preg_match('/class="flash-msg[^"]*">([^<]*)</', $html, $aviso);
    $mensaje = html_entity_decode($aviso[1] ?? '', ENT_QUOTES);
    revisar(strpos($mensaje, 'Guardado:') === 0 && strpos($mensaje, 'Se avisó') === false && (int)$e['db']->query("SELECT stock FROM repuestos WHERE id_repuesto = $id")->fetch_row()[0] === 3,
            'la edición rápida guarda aunque el correo falle', $mensaje);
    revisar(in_array('pedro@example.com', pendientes($e, $id), true), 'y el aviso queda pendiente para el próximo intento');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
