<?php
/**
 * API de OpenAI falsa para pruebas locales (no se publica: tests/ está bloqueada y no se sube).
 * Imita la Responses API lo justo para probar backend/chat.php sin gastar tokens.
 *
 * Uso: FAKE_API_LOG=<archivo.jsonl> php -S localhost:8093 tests/fake_openai.php
 * y en la configuración de pruebas: APP_ENV=pruebas y AI_API_URL=http://localhost:8093/v1/responses
 *
 * Guarda cada petición recibida (una línea JSON) en FAKE_API_LOG y responde según el
 * último mensaje del usuario:
 *   - si la entrada ya trae un function_call_output: un mensaje final de texto;
 *   - "provoca error": HTTP 500;
 *   - "lento": espera FAKE_API_DEMORA segundos (5 por defecto) y responde texto;
 *   - "pastillas": pide la herramienta buscar_productos (Swift 2019, pastillas de freno);
 *   - cualquier otra cosa: un mensaje de texto.
 */
$cuerpo = file_get_contents('php://input');
$peticion = json_decode((string)$cuerpo, true) ?: [];
$entrada = $peticion['input'] ?? [];

$log = getenv('FAKE_API_LOG');
if ($log && $_SERVER['REQUEST_METHOD'] === 'POST') { // un GET de "¿está arriba?" no es una petición del chat
    file_put_contents($log, json_encode([
        'input' => $entrada,
        'herramientas' => isset($peticion['tools']),
        'modelo' => $peticion['model'] ?? null,
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

function responder(array $output, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode($codigo === 200
        ? ['id' => 'resp_prueba', 'status' => 'completed', 'output' => $output]
        : ['error' => ['message' => 'falla simulada por la API falsa']], JSON_UNESCAPED_UNICODE);
    exit;
}
function mensaje(string $texto): array {
    return [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $texto]]]];
}

if ($tieneResultadoHerramienta) {
    responder(mensaje('Tengo estas opciones para tu Swift 2019 (respuesta falsa).'));
}
if (stripos($ultimoUsuario, 'provoca error') !== false) {
    responder([], 500);
}
if (stripos($ultimoUsuario, 'lento') !== false) {
    sleep((int)(getenv('FAKE_API_DEMORA') ?: 5));
    responder(mensaje('Respuesta lenta (respuesta falsa).'));
}
if (stripos($ultimoUsuario, 'pastillas') !== false) {
    responder([
        ['type' => 'reasoning', 'id' => 'rs_prueba', 'summary' => []],
        ['type' => 'function_call', 'id' => 'fc_prueba', 'call_id' => 'call_prueba', 'name' => 'buscar_productos',
         'arguments' => json_encode(['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'pastillas de freno'])],
    ]);
}
responder(mensaje('Respuesta falsa a: ' . mb_substr($ultimoUsuario, 0, 60)));
