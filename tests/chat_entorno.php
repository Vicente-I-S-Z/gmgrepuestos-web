<?php
/**
 * Entorno común de las pruebas del chat de punta a punta (chat_historial_test.php, chat_api_test.php):
 * base temporal desde BD_v2, API de OpenAI falsa (tests/fake_openai.php, puerto 8093) y el sitio
 * con php -S (puerto 8092). No usa ni lee el .env. Requisitos: MariaDB de XAMPP encendido.
 */

$fallas = 0;
function revisar(bool $ok, string $nombre, string $detalle = ''): void {
    global $fallas;
    if (!$ok) {
        $fallas++;
    }
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . ($detalle !== '' ? "  ($detalle)" : '') . "\n";
}

function entornoLevantar(array $comando, array $variables) {
    $tuberias = [];
    return proc_open($comando, [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $tuberias, null, getenv() + $variables);
}

function entornoEsperar(string $url): void {
    for ($i = 0; $i < 40; $i++) {
        if (@file_get_contents($url) !== false) {
            return;
        }
        usleep(250000);
    }
}

/** Crea la base temporal y levanta la API falsa y el sitio. $variablesApi: p. ej. ['FAKE_API_DEMORA' => '30']. */
function entornoIniciar(string $nombre, array $variablesApi = []): array {
    $raiz = dirname(__DIR__);
    $e = [
        'raiz' => $raiz,
        'base' => "gmg_prueba_$nombre",
        'tmp' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . "gmg_$nombre" . '_' . uniqid(),
        'sitio' => 'http://localhost:8092',
        'procesos' => [],
    ];
    mkdir($e['tmp']);
    $e['log_api'] = $e['tmp'] . '/peticiones.jsonl';
    $e['log_php'] = $e['tmp'] . '/php.log';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $e['db'] = new mysqli('localhost', 'root', '');
    $e['db']->query("DROP DATABASE IF EXISTS {$e['base']}");
    $e['db']->query("CREATE DATABASE {$e['base']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $e['db']->select_db($e['base']);
    foreach (glob("$raiz/BD_v2/[0-9][0-9]_*.sql") as $script) {
        $e['db']->multi_query(file_get_contents($script));
        while ($e['db']->more_results() && $e['db']->next_result()) {
        }
    }
    $e['db']->query("UPDATE repuestos SET stock = 5");

    file_put_contents($e['tmp'] . '/config.txt', "APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME={$e['base']}\n"
        . "AI_API_KEY=clave-falsa-de-prueba\nAI_API_URL=http://localhost:8093/v1/responses\nSMTP_HOST=127.0.0.1\n");
    $e['procesos'][] = entornoLevantar([PHP_BINARY, '-S', 'localhost:8093', "$raiz/tests/fake_openai.php"], ['FAKE_API_LOG' => $e['log_api']] + $variablesApi);
    $e['procesos'][] = entornoLevantar([PHP_BINARY, '-d', 'error_log=' . $e['log_php'], '-S', 'localhost:8092', '-t', $raiz], ['GMG_ENV_FILE' => $e['tmp'] . '/config.txt']);
    entornoEsperar('http://localhost:8093/');
    entornoEsperar($e['sitio'] . '/login.php');
    return $e;
}

function entornoTerminar(array $e): void {
    foreach ($e['procesos'] as $proceso) {
        proc_terminate($proceso);
    }
    $e['db']->query("DROP DATABASE IF EXISTS {$e['base']}");
    array_map('unlink', glob($e['tmp'] . '/*'));
    @rmdir($e['tmp']);
}

/** POST a backend/chat.php (con CURLFile en $campos se envía multipart). Devuelve la respuesta JSON más '_segundos'. */
function chat(array $e, array $campos, bool $json = false): array {
    $conArchivos = !$json && array_filter($campos, fn($v) => $v instanceof CURLFile) !== [];
    $ch = curl_init($e['sitio'] . '/backend/chat.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_POSTFIELDS => $json ? json_encode($campos, JSON_UNESCAPED_UNICODE) : ($conArchivos ? $campos : http_build_query($campos)),
        CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [],
    ]);
    $inicio = microtime(true);
    $r = json_decode((string)curl_exec($ch), true) ?: [];
    $r['_segundos'] = round(microtime(true) - $inicio, 1);
    curl_close($ch);
    return $r;
}

/** Peticiones que recibió la API falsa (una por línea). */
function peticiones(array $e): array {
    return is_file($e['log_api']) ? array_map(fn($l) => json_decode($l, true), file($e['log_api'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
}

/** Roles y textos de la entrada enviada a la API (sin el system). */
function turnos(array $peticion): array {
    $t = [];
    foreach ($peticion['input'] ?? [] as $item) {
        if (in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
            $t[] = $item['role'] . ': ' . (is_string($item['content']) ? $item['content'] : '(contenido con imagen)');
        }
    }
    return $t;
}

/** Errores de PHP en el registro del sitio. */
function erroresPhp(array $e): array {
    return is_file($e['log_php']) ? array_values(preg_grep('/PHP (Fatal|Warning|Notice|Deprecated|Parse)/', file($e['log_php']))) : [];
}
