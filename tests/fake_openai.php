<?php
/**
 * API de OpenAI falsa para pruebas locales (no se publica: tests/ está bloqueada y no se sube).
 * Imita la Responses API lo justo para probar backend/chat.php sin gastar tokens.
 *
 * Uso: FAKE_API_LOG=<archivo.jsonl> php -S localhost:8093 tests/fake_openai.php
 * y en la configuración de pruebas: APP_ENV=pruebas y AI_API_URL=http://localhost:8093/v1/responses
 *
 * Guarda cada petición POST (una línea JSON) en FAKE_API_LOG y responde según el
 * último mensaje del usuario:
 *   - "insiste": pide buscar_productos siempre, aunque ya tenga resultados (prueba el máximo de rondas);
 *   - si la entrada ya trae un function_call_output: un mensaje final de texto;
 *   - "provoca error": HTTP 500 siempre;
 *   - "falla una vez": HTTP 429 la primera vez y texto la segunda (prueba el reintento);
 *   - "incompleta": status "incomplete" con texto cortado;
 *   - "lento": espera FAKE_API_DEMORA segundos (5 por defecto) y responde texto;
 *   - "agrega N": responde con la etiqueta [ADD_TO_CART:N:1];
 *   - "eco interno": responde con guiones largos y la línea interna "[Productos mostrados...]";
 *   - "pastillas": pide la herramienta buscar_productos (Swift 2019, pastillas de freno);
 *   - cualquier otra cosa: un mensaje de texto.
 */
$cuerpo = (string)file_get_contents('php://input');
$peticion = json_decode($cuerpo, true) ?: [];
$entrada = $peticion['input'] ?? [];

$log = getenv('FAKE_API_LOG');
if ($log && $_SERVER['REQUEST_METHOD'] === 'POST') { // un GET de "¿está arriba?" no es una petición del chat
    file_put_contents($log, json_encode([
        'input' => $entrada,
        'herramientas' => isset($peticion['tools']),
        'tool_choice' => $peticion['tool_choice'] ?? null,
        'modelo' => $peticion['model'] ?? null,
        'reasoning' => $peticion['reasoning'] ?? null,
        'text' => $peticion['text'] ?? null,
        'max_output_tokens' => $peticion['max_output_tokens'] ?? null,
        'bytes' => strlen($cuerpo),
        'con_clave' => strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ') === 0,
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

$tieneResultadoHerramienta = false;
$ultimoUsuario = '';
foreach ($entrada as $item) {
    if (($item['type'] ?? '') === 'function_call_output') {
        $tieneResultadoHerramienta = true;
    }
    if (($item['role'] ?? '') === 'user') {
        $contenido = $item['content'] ?? '';
        $ultimoUsuario = is_string($contenido) ? $contenido : (string)($contenido[0]['text'] ?? '');
    }
}

function responder(array $output, int $codigo = 200, string $estado = 'completed'): void {
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode($codigo === 200
        ? ['id' => 'resp_prueba', 'status' => $estado, 'output' => $output]
        : ['error' => ['message' => 'falla simulada por la API falsa']], JSON_UNESCAPED_UNICODE);
    exit;
}
function mensaje(string $texto): array {
    return [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $texto]]]];
}
function pedirBusqueda(): array {
    return [
        ['type' => 'reasoning', 'id' => 'rs_prueba', 'summary' => []],
        ['type' => 'function_call', 'id' => 'fc_prueba', 'call_id' => 'call_prueba', 'name' => 'buscar_productos',
         'arguments' => json_encode(['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'pastillas de freno'])],
    ];
}

if (stripos($ultimoUsuario, 'insiste') !== false) {
    responder(pedirBusqueda());
}
if ($tieneResultadoHerramienta) {
    // F3-12: si lo que volvió es de consultar_pedido, se repite tal cual para que la prueba lo vea.
    foreach ($entrada as $item) {
        if (($item['type'] ?? '') === 'function_call_output' && ($item['call_id'] ?? '') === 'call_pedido') {
            responder(mensaje('Resultado del pedido: ' . ($item['output'] ?? '')));
        }
    }
    responder(mensaje('Tengo estas opciones para tu Swift 2019 (respuesta falsa).'));
}
// F3-12: "consulta pedido N con DATO" pide la herramienta consultar_pedido.
if (preg_match('/consulta pedido (\d+) con (\S+)/i', $ultimoUsuario, $m)) {
    responder([['type' => 'function_call', 'id' => 'fc_pedido', 'call_id' => 'call_pedido', 'name' => 'consultar_pedido',
                'arguments' => json_encode(['numero_pedido' => (int)$m[1], 'correo_o_rut' => $m[2]])]]);
}
if (stripos($ultimoUsuario, 'provoca error') !== false) {
    responder([], 500);
}
if (stripos($ultimoUsuario, 'falla una vez') !== false) {
    $marca = ($log ?: sys_get_temp_dir() . '/fake_openai') . '.falla_una_vez';
    if (!is_file($marca)) {
        touch($marca);
        responder([], 429);
    }
    responder(mensaje('Respuesta tras el reintento (respuesta falsa).'));
}
if (stripos($ultimoUsuario, 'incompleta') !== false) {
    responder(mensaje('Respuesta cort'), 200, 'incomplete');
}
if (stripos($ultimoUsuario, 'lento') !== false) {
    sleep((int)(getenv('FAKE_API_DEMORA') ?: 5));
    responder(mensaje('Respuesta lenta (respuesta falsa).'));
}
if (stripos($ultimoUsuario, 'eco interno') !== false) { // la IA real a veces imita la línea interna del historial
    responder(mensaje("1. **Pastillas** — SKU: FRE-SWI3-PDL — **\$24.000** — Disponible\n[Productos mostrados en esta respuesta: 242]"));
}
if (preg_match('/agrega (\d+)/i', $ultimoUsuario, $m)) {
    responder(mensaje("Listo, lo agrego al carrito. [ADD_TO_CART:{$m[1]}:1]"));
}
if (stripos($ultimoUsuario, 'pastillas') !== false) {
    responder(pedirBusqueda());
}
responder(mensaje('Respuesta falsa a: ' . mb_substr($ultimoUsuario, 0, 60)));
