<?php
/**
 * F3-8 (E6-H9, RF-058): bienvenida del chat y número de WhatsApp editables en el panel "Asistente".
 *   - se guardan con CSRF y auditoría; el celular se valida y se guarda como +56 9 1234 5678;
 *   - la tienda muestra la bienvenida (escapada) y todos los enlaces usan el número del panel;
 *   - estos dos temas no se envían a la IA; vacíos, vuelve el saludo y el número del .env.
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/config_chatbot_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('configchat');
$cookies = $e['tmp'] . '/cookies.txt';
function pagina(array $e, string $ruta, ?array $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
function guardar(array $e, array $cambios): string {
    $html = pagina($e, '/admin/conocimiento.php');
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m);
    $post = ['csrf' => $m[1] ?? ''];
    preg_match_all('/name="(t_[a-z_]+)"[^>]*?(?:value="([^"]*)")?>/', $html, $campos, PREG_SET_ORDER);
    foreach ($campos as $c) {
        $post[$c[1]] = html_entity_decode($c[2] ?? '', ENT_QUOTES);
    }
    preg_match_all('~<textarea[^>]*name="(t_[a-z_]+)"[^>]*>(.*?)</textarea>~s', $html, $areas, PREG_SET_ORDER);
    foreach ($areas as $a) {
        $post[$a[1]] = html_entity_decode($a[2], ENT_QUOTES);
    }
    foreach ($cambios as $clave => $valor) {
        $post["t_$clave"] = $valor;
    }
    $r = pagina($e, '/admin/conocimiento.php', $post);
    return preg_match('/class="flash-msg[^"]*">([^<]*)</', $r, $a) ? html_entity_decode($a[1], ENT_QUOTES) : '';
}
function valor(array $e, string $clave): string {
    return (string)($e['db']->query("SELECT contenido FROM base_conocimiento WHERE clave = '$clave'")->fetch_row()[0] ?? '');
}
try {
    $hash = $e['db']->real_escape_string(password_hash('Clave-F38', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f38', 'Prueba', '$hash', 1)");
    pagina($e, '/login.php', ['usuario' => 'prueba_f38', 'password' => 'Clave-F38']);
    $html = pagina($e, '/admin/conocimiento.php');
    revisar(strpos($html, 'Mensaje de bienvenida del chat') !== false && strpos($html, 'WhatsApp de la tienda') !== false, 'el panel "Asistente" tiene los dos campos nuevos');

    echo "\n== Validación ==\n";
    $msg = guardar($e, ['whatsapp' => '1234', 'bienvenida' => 'No debería guardarse']);
    revisar(strpos($msg, 'No se guardó') !== false && valor($e, 'whatsapp') === '' && valor($e, 'bienvenida') === '', 'celular inválido: no se guarda nada', $msg);

    echo "\n== Guardar y usar ==\n";
    $bienvenida = "¡Hola! Soy el asistente de GMG <b>Repuestos</b>.\nCuéntame tu modelo y año.";
    guardar($e, ['whatsapp' => '9 8765 4321', 'bienvenida' => $bienvenida]);
    revisar(valor($e, 'whatsapp') === '+56 9 8765 4321' && valor($e, 'bienvenida') === $bienvenida, 'se guardan (celular normalizado)');
    $auditados = (int)$e['db']->query("SELECT COUNT(*) FROM auditoria WHERE accion = 'conocimiento'")->fetch_row()[0];
    revisar($auditados === 2 && (int)$e['db']->query("SELECT COUNT(*) FROM auditoria WHERE detalle LIKE '%bienvenida%' OR detalle LIKE '%WhatsApp de la tienda%'")->fetch_row()[0] === 2,
            'solo los 2 cambios quedan en auditoría (los demás textos no se tocan)', (string)$auditados);
    $tienda = pagina($e, '/index.php?modelo=swift');
    revisar(strpos($tienda, '¡Hola! Soy el asistente de GMG &lt;b&gt;Repuestos&lt;/b&gt;.<br />') !== false && strpos($tienda, 'Soy tu <strong>Asistente Virtual GMG</strong>') === false,
            'el chat muestra la bienvenida nueva, escapada y con su salto de línea');
    preg_match_all('~wa\.me/(\d+)~', $tienda, $numeros);
    revisar(count($numeros[1]) >= 3 && count(array_unique($numeros[1])) === 1 && $numeros[1][0] === '56987654321', 'todos los enlaces de WhatsApp usan el número del panel', implode(',', array_unique($numeros[1])));

    putenv('GMG_ENV_FILE=' . $e['tmp'] . '/config.txt');
    require __DIR__ . '/../backend/config.php';
    require_once __DIR__ . '/../backend/conocimiento.php';
    $contexto = gmgContextoNegocioParaIA($conexion, ['Swift']);
    revisar(strpos($contexto, 'Mensaje de bienvenida') === false && strpos($contexto, 'Cuéntame tu modelo') === false && strpos($contexto, 'WhatsApp de la tienda') === false,
            'la bienvenida y el número no se envían como información a la IA');
    revisar(strpos($contexto, 'wa.me/56987654321') !== false, 'pero el enlace que ofrece la IA usa el número nuevo');

    echo "\n== Vacíos ==\n";
    guardar($e, ['whatsapp' => '', 'bienvenida' => '']);
    $tienda = pagina($e, '/index.php');
    preg_match('~wa\.me/(\d+)~', $tienda, $n);
    revisar(strpos($tienda, 'Soy tu <strong>Asistente Virtual GMG</strong>') !== false && ($n[1] ?? '') !== '56987654321', 'vacíos: vuelve el saludo de siempre y el número del .env', $n[1] ?? '');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
