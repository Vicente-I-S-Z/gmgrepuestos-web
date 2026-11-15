<?php
/**
 * F1-5 (E5-H16, E5-H17, INC-01, RNF-012, RNF-014, RNF-015) y la parte de servidor de E5-H6:
 * llamada a la Responses API con presupuesto de 25 s, 1 reintento, máximo 2 rondas de
 * herramientas, sin respaldo con el catálogo completo, y [ADD_TO_CART] solo con productos
 * ya mostrados. De punta a punta con backend/chat.php y la API falsa (tests/fake_openai.php).
 * El último caso espera unos 25 s a propósito (la API falsa tarda 30).
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/chat_api_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('api', ['FAKE_API_DEMORA' => '30']);
$sesion = 'prueba-api-' . uniqid();
/** Envía un mensaje y devuelve [respuesta, peticiones que generó en la API]. */
function turno(array $e, string $mensaje, string $sesion): array {
    $antes = count(peticiones($e));
    $r = chat($e, ['message' => $mensaje, 'sesion_id' => $sesion]);
    return [$r, array_slice(peticiones($e), $antes)];
}
function bots(array $e, string $sesion): int {
    return (int)$e['db']->query("SELECT COUNT(*) FROM mensajes_chat m JOIN conversaciones_chat c ON c.id_conversacion = m.id_conversacion
                                  WHERE c.sesion_id = '$sesion' AND m.rol = 'bot'")->fetch_row()[0];
}
function esWhatsApp(array $r): bool {
    return ($r['status'] ?? '') === 'error' && strpos($r['reply'] ?? '', 'WhatsApp') !== false;
}

try {
    echo "== Petición a la API (causa 7 del fix: sin reasoning.summary ni mode) ==\n";
    [$r, $p] = turno($e, 'Necesito pastillas de freno', $sesion);
    $p1 = $p[0] ?? [];
    revisar(($p1['modelo'] ?? '') === 'gpt-5.6-luna', 'modelo gpt-5.6-luna');
    revisar(($p1['reasoning'] ?? null) === ['effort' => 'low'], 'reasoning solo con effort "low"', json_encode($p1['reasoning'] ?? null));
    revisar(($p1['text']['verbosity'] ?? '') === 'low' && ($p1['text']['format']['type'] ?? '') === 'text', 'text: formato texto y verbosity "low"');
    revisar(($p1['max_output_tokens'] ?? 0) === 1500, 'max_output_tokens 1500');
    revisar(($p1['herramientas'] ?? false) && ($p1['tool_choice'] ?? '') === 'auto', 'con la herramienta y tool_choice "auto"');
    $mayor = max(array_column(peticiones($e), 'bytes'));
    revisar($mayor < 30000, 'ninguna petición lleva el catálogo completo', "la más grande: $mayor bytes");

    echo "\n== Ronda de herramienta ==\n";
    revisar(count($p) === 2, 'pedir pastillas genera 2 peticiones (pregunta y resultado)', count($p) . ' peticiones');
    $tipos = array_column($p[1]['input'] ?? [], 'type');
    revisar(in_array('reasoning', $tipos, true) && in_array('function_call', $tipos, true) && in_array('function_call_output', $tipos, true),
            'la segunda petición reenvía reasoning y function_call junto al resultado');
    revisar(($r['status'] ?? '') === 'success' && ($r['products'][0]['sku'] ?? '') === 'FRE-SWI3-PDL', 'responde con las tarjetas de la búsqueda real');
    $idPastillas = (int)($r['products'][0]['id'] ?? 0);

    echo "\n== [ADD_TO_CART] solo con productos ya mostrados (E5-H6) ==\n";
    [$r] = turno($e, "agrega $idPastillas", $sesion);
    $agregados = array_column($r['auto_add_cart'] ?? [], 'id');
    revisar($agregados === [$idPastillas] && isset($r['auto_add_cart'][0]['nombre'], $r['auto_add_cart'][0]['precio']), 'un producto mostrado se agrega con su nombre y precio', json_encode($agregados));
    revisar(strpos($r['reply'] ?? '', '[ADD_TO_CART') === false, 'la etiqueta no se le muestra al cliente');
    [$r] = turno($e, 'agrega 1', $sesion);
    revisar(($r['auto_add_cart'] ?? null) === [] && strpos($r['reply'] ?? '', '[ADD_TO_CART') === false, 'un id que el bot nunca mostró no agrega nada');
    [$r] = turno($e, "agrega $idPastillas", 'otra-sesion-' . uniqid());
    revisar(($r['auto_add_cart'] ?? null) === [], 'en otra conversación ese mismo id no agrega nada');

    echo "\n== Máximo 2 rondas de herramientas (E5-H17) ==\n";
    $antes = bots($e, $sesion);
    [$r, $p] = turno($e, 'insiste en buscar', $sesion);
    revisar(count($p) === 3, 'si la IA insiste, se corta a las 2 rondas', count($p) . ' peticiones');
    revisar(esWhatsApp($r), 'el cliente recibe la salida a WhatsApp');
    revisar(bots($e, $sesion) === $antes, 'esa respuesta fallida no se guarda');

    echo "\n== Reintento y errores (RNF-012) ==\n";
    [$r, $p] = turno($e, 'provoca error', $sesion);
    revisar(count($p) === 2, 'un 500 se reintenta 1 vez y no hay respaldo sin herramientas', count($p) . ' peticiones');
    revisar(!in_array(false, array_column($p, 'herramientas'), true), 'todas las peticiones con la herramienta (sin respaldo con catálogo)');
    revisar(esWhatsApp($r), 'tras el reintento fallido: salida a WhatsApp');
    [$r, $p] = turno($e, 'falla una vez', $sesion);
    revisar(count($p) === 2 && ($r['status'] ?? '') === 'success' && strpos($r['reply'] ?? '', 'tras el reintento') !== false,
            'un 429 se reintenta y la segunda vez responde', count($p) . ' peticiones');
    [$r] = turno($e, 'respuesta incompleta', $sesion);
    revisar(esWhatsApp($r) && strpos($r['reply'] ?? '', 'Respuesta cort') === false, 'una respuesta "incomplete" no se muestra a medias');

    echo "\n== Presupuesto de 25 s (la API falsa tarda 30) ==\n";
    [$r] = turno($e, 'lento', $sesion);
    revisar($r['_segundos'] <= 27 && esWhatsApp($r), 'el cliente recibe respuesta antes de 27 s, con salida a WhatsApp', $r['_segundos'] . ' s');

    echo "\n== Registro ==\n";
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
