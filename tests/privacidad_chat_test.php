<?php
/**
 * F3-7 (E5-H18, RNF-017): privacidad del chat.
 *   - el chat muestra el aviso de 12 meses con enlace a la política, y la política lo dice;
 *   - backend/limpiar_chat.php (cron) borra las conversaciones sin mensajes en 12 meses, con sus
 *     mensajes y repuestos (cascada), y deja las que tuvieron actividad reciente;
 *   - desde el navegador ese script no hace nada (404).
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/privacidad_chat_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('privacidad');
function contar(array $e, string $tabla): int {
    return (int)$e['db']->query("SELECT COUNT(*) FROM $tabla")->fetch_row()[0];
}
try {
    echo "== Aviso ==\n";
    $html = (string)file_get_contents($e['sitio'] . '/index.php');
    revisar(strpos($html, 'class="gmg-chat-privacidad"') !== false && strpos($html, 'hasta 12 meses') !== false && strpos($html, 'href="politica-de-privacidad.php"') !== false,
            'el chat muestra el aviso de 12 meses con enlace a la política');
    revisar(strpos((string)file_get_contents($e['sitio'] . '/politica-de-privacidad.php'), '12 meses después del último mensaje') !== false, 'la política de privacidad lo explica');

    echo "\n== Limpieza ==\n";
    $d = $e['db'];
    $id = (int)$d->query("SELECT id_repuesto FROM repuestos LIMIT 1")->fetch_row()[0];
    $d->query("INSERT INTO conversaciones_chat (id_conversacion, sesion_id, creado_en) VALUES
               (1, 'vieja', NOW() - INTERVAL 14 MONTH), (2, 'vieja-con-actividad', NOW() - INTERVAL 14 MONTH),
               (3, 'nueva', NOW() - INTERVAL 2 DAY), (4, 'vieja-vacia', NOW() - INTERVAL 13 MONTH)");
    $d->query("INSERT INTO mensajes_chat (id_mensaje, id_conversacion, rol, contenido, creado_en) VALUES
               (1, 1, 'user', 'hola', NOW() - INTERVAL 14 MONTH), (2, 1, 'bot', 'respuesta', NOW() - INTERVAL 13 MONTH),
               (3, 2, 'user', 'antes', NOW() - INTERVAL 14 MONTH), (4, 2, 'user', 'ayer', NOW() - INTERVAL 1 DAY),
               (5, 3, 'user', 'hoy', NOW())");
    $d->query("INSERT INTO mensaje_repuestos (id_mensaje, id_repuesto) VALUES (2, $id), (5, $id)");

    putenv('GMG_ENV_FILE=' . $e['tmp'] . '/config.txt'); // el script del cron usa la base temporal, nunca el .env
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($e['raiz'] . '/backend/limpiar_chat.php') . ' 2>&1');
    $quedan = array_map('intval', array_column($d->query("SELECT id_conversacion FROM conversaciones_chat ORDER BY id_conversacion")->fetch_all(), 0));
    revisar(strpos((string)$salida, 'borradas: 2') !== false, 'el script informa 2 conversaciones borradas', trim((string)$salida));
    revisar($quedan === [2, 3], 'borra la vieja y la vieja vacía; deja la que tuvo actividad reciente y la nueva', json_encode($quedan));
    revisar(contar($e, 'mensajes_chat') === 3 && contar($e, 'mensaje_repuestos') === 1, 'sus mensajes y repuestos se borran en cascada');
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($e['raiz'] . '/backend/limpiar_chat.php') . ' 2>&1');
    revisar(strpos((string)$salida, 'borradas: 0') !== false, 'repetirlo no borra nada más');

    echo "\n== Desde el navegador ==\n";
    $ch = curl_init($e['sitio'] . '/backend/limpiar_chat.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $cuerpo = (string)curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    revisar($codigo === 404 && $cuerpo === '', 'responde 404 sin hacer nada');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
