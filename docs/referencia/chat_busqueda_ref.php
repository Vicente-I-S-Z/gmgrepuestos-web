<?php
/**
 * Referencia para el fix del Asistente IA (GMG Repuestos), auditoría 13-11-2026.
 * Ver docs/FIX_ASISTENTE_IA.md.
 *
 * F1-2 (14-11-2026): las funciones de búsqueda e historial ya están en
 * backend/chat_busqueda.php y gmgLimiteSuperado() en backend/seguridad.php;
 * sus pruebas están en tests/. Aquí queda solo chatLlamarResponsesAPI(), que
 * reemplaza a la versión antigua de backend/chat_helpers.php en F1-5 (no se
 * movió antes porque las dos tienen el mismo nombre). Necesita red: solo se
 * revisó su sintaxis.
 */

/**
 * Llamada a la Responses API con presupuesto de tiempo y 1 reintento
 * (RNF-012). $limiteHasta = microtime(true) + 25 al empezar el mensaje.
 * Sin reasoning.summary ni reasoning.mode: el código nunca los lee.
 */
function chatLlamarResponsesAPI(array $entrada, ?array $herramientas, float $limiteHasta): array {
    $payload = [
        'model' => AI_MODEL,
        'input' => $entrada,
        'reasoning' => ['effort' => 'low'],
        'text' => ['format' => ['type' => 'text'], 'verbosity' => 'low'],
        'max_output_tokens' => 1500,
    ];
    if ($herramientas) {
        $payload['tools'] = $herramientas;
        $payload['tool_choice'] = 'auto';
    }
    $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $resultado = ['success' => false, 'error' => 'Sin tiempo disponible', 'http_code' => 0];

    for ($intento = 1; $intento <= 2; $intento++) {
        $restante = (int)floor($limiteHasta - microtime(true));
        if ($restante < 3) {
            return $resultado;
        }
        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => min(20, $restante),
            CURLOPT_SSL_VERIFYPEER => gmgCurlVerificarSsl(),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY],
        ]);
        $respuesta = curl_exec($ch);
        $errorCurl = curl_error($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errorCurl === '' && $codigo >= 200 && $codigo < 300) {
            $datos = json_decode((string)$respuesta, true);
            return is_array($datos)
                ? ['success' => true, 'data' => $datos]
                : ['success' => false, 'error' => 'Respuesta no válida de la API', 'http_code' => $codigo];
        }
        $detalle = json_decode((string)$respuesta, true)['error']['message'] ?? '';
        $resultado = ['success' => false, 'error' => trim(($errorCurl ?: "HTTP $codigo") . ' ' . $detalle), 'http_code' => $codigo];

        $reintentable = $codigo === 0 || $codigo === 429 || $codigo >= 500;
        if (!$reintentable) {
            return $resultado;
        }
        usleep(800000);
    }
    return $resultado;
}
