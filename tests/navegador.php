<?php
/**
 * Apoyo para las pruebas en un navegador real (Edge o Chrome sin ventana) contra el sitio local.
 * El sitio corre con php -S y una configuración de prueba sin claves (no lee el .env).
 */

$fallas = 0;
function revisar(bool $ok, string $nombre, string $detalle = ''): void {
    global $fallas;
    if (!$ok) {
        $fallas++;
    }
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . ($detalle !== '' ? "  ($detalle)" : '') . "\n";
}

function navegadorRuta(): ?string {
    foreach (['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
              'C:/Program Files/Google/Chrome/Application/chrome.exe'] as $ruta) {
        if (is_file($ruta)) {
            return $ruta;
        }
    }
    return null;
}

/** Levanta el sitio en el puerto dado con la base indicada. Devuelve ['proceso', 'tmp', 'url']. */
function sitioIniciar(int $puerto, string $base = 'gmg_local'): array {
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmg_nav_' . uniqid();
    mkdir($tmp);
    file_put_contents("$tmp/config.txt", "APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME=$base\nWHATSAPP_NUMBER=56911112222\nSMTP_HOST=127.0.0.1\n");
    $tuberias = [];
    $proceso = proc_open([PHP_BINARY, '-d', "error_log=$tmp/php.log", '-S', "localhost:$puerto", '-t', dirname(__DIR__)],
                         [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $tuberias, null,
                         getenv() + ['GMG_ENV_FILE' => "$tmp/config.txt"]);
    for ($i = 0; $i < 40 && @file_get_contents("http://localhost:$puerto/login.php") === false; $i++) {
        usleep(250000);
    }
    return ['proceso' => $proceso, 'tmp' => $tmp, 'url' => "http://localhost:$puerto"];
}

function sitioTerminar(array $s): void {
    proc_terminate($s['proceso']);
    exec('rmdir /s /q ' . escapeshellarg($s['tmp']));
}

/** Errores de PHP del sitio de prueba. */
function sitioErrores(array $s): array {
    return is_file($s['tmp'] . '/php.log') ? array_values(preg_grep('/PHP (Fatal|Warning|Notice|Deprecated|Parse)/', file($s['tmp'] . '/php.log'))) : [];
}

/** Abre la página sin ventana y devuelve el JSON que dejó en <pre id="resultado">, o null. */
function navegadorResultado(array $s, string $ruta, int $tiempoVirtualMs = 10000, array $opciones = []): ?array {
    $comando = array_merge([navegadorRuta(), '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
                            '--user-data-dir=' . $s['tmp'] . '/perfil_' . uniqid(), "--virtual-time-budget=$tiempoVirtualMs", '--dump-dom'],
                           $opciones, [$s['url'] . $ruta]);
    $tuberias = [];
    $p = proc_open($comando, [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['file', 'NUL', 'w']], $tuberias);
    $html = (string)stream_get_contents($tuberias[1]);
    proc_close($p);
    if (!preg_match('~<pre id="resultado">(.*?)</pre>~s', $html, $m)) {
        return null;
    }
    $r = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
    return is_array($r) ? $r : null;
}
