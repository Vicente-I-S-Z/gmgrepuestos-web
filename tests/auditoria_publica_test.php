<?php
/**
 * Auditoría del 15-11 (endpoints públicos):
 *   - calificar una respuesta del chat solo funciona desde la sesión dueña del mensaje;
 *   - "Mi pedido" limita a 10 consultas cada 10 minutos por IP (no se pueden probar números y RUT sin freno, CP-23).
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Uso: php tests/auditoria_publica_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('auditoria');
function post(array $e, string $ruta, $datos, bool $json = false): string {
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
                            CURLOPT_POSTFIELDS => $json ? json_encode($datos) : http_build_query($datos),
                            CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : []]);
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
try {
    echo "== Calificar respuestas del chat ==\n";
    $e['db']->query("INSERT INTO conversaciones_chat (id_conversacion, sesion_id) VALUES (1, 'sesion-duena')");
    $e['db']->query("INSERT INTO mensajes_chat (id_mensaje, id_conversacion, rol, contenido) VALUES (10, 1, 'bot', 'respuesta')");
    $feedback = function () use ($e) { return $e['db']->query("SELECT feedback FROM mensajes_chat WHERE id_mensaje = 10")->fetch_row()[0]; };
    $r = json_decode(post($e, '/backend/feedback_mensaje.php', ['mensaje_id' => 10, 'feedback' => 'negativo', 'sesion_id' => 'otra-sesion'], true), true);
    revisar(($r['status'] ?? '') === 'error' && $feedback() === null, 'desde otra sesión no se puede calificar');
    $r = json_decode(post($e, '/backend/feedback_mensaje.php', ['mensaje_id' => 10, 'feedback' => 'positivo'], true), true);
    revisar(($r['status'] ?? '') === 'error' && $feedback() === null, 'sin sesión tampoco');
    $r = json_decode(post($e, '/backend/feedback_mensaje.php', ['mensaje_id' => 10, 'feedback' => 'positivo', 'sesion_id' => 'sesion-duena'], true), true);
    revisar(($r['status'] ?? '') === 'success' && $feedback() === 'positivo', 'la sesión dueña sí');

    echo "\n== Mi pedido ==\n";
    $bloqueada = 0;
    for ($i = 1; $i <= 11; $i++) {
        $html = post($e, '/mi-pedido.php', ['numero' => (string)(1000 + $i), 'rut' => '12.345.678-5']);
        if (strpos($html, 'muchas consultas seguidas') !== false) {
            $bloqueada = $i;
            break;
        }
    }
    revisar($bloqueada === 11, 'la consulta 11 en 10 minutos se frena', "se frenó en la $bloqueada");
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
