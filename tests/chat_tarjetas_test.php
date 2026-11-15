<?php
/**
 * F1-7 (E5-H5, E5-H6, RF-013, RF-040): tarjetas de producto del chat en un navegador real.
 * Levanta el sitio con php -S (puerto 8091, base gmg_local, sin leer el .env) y abre
 * tests/chat_tarjetas_prueba.html en Edge o Chrome sin ventana (--dump-dom), con movimiento
 * reducido forzado y tiempo virtual (los 30 s de espera pasan al instante).
 * También saca una captura del chat a 360 px de ancho y dice dónde quedó.
 * Requisitos: Edge o Chrome instalados, MariaDB encendido con gmg_local, puerto 8091 libre.
 * Uso: php tests/chat_tarjetas_test.php   (termina en "TODO OK")
 */
$raiz = dirname(__DIR__);
$navegador = null;
foreach (['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
          'C:/Program Files/Google/Chrome/Application/chrome.exe'] as $ruta) {
    if (is_file($ruta)) {
        $navegador = $ruta;
        break;
    }
}
if (!$navegador) {
    exit("No se encontró Edge ni Chrome.\n");
}
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmg_tarjetas_' . uniqid();
mkdir($tmp);
file_put_contents("$tmp/config.txt", "APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME=gmg_local\nWHATSAPP_NUMBER=56911112222\n");
$captura = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmg_chat_tarjetas_360.png';

$fallas = 0;
function revisar(bool $ok, string $nombre, string $detalle = ''): void {
    global $fallas;
    if (!$ok) {
        $fallas++;
    }
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . ($detalle !== '' ? "  ($detalle)" : '') . "\n";
}
function ejecutar(array $comando, int $segundos = 90): string {
    $p = proc_open($comando, [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['file', 'NUL', 'w']], $tuberias);
    stream_set_timeout($tuberias[1], $segundos);
    $salida = stream_get_contents($tuberias[1]);
    proc_close($p);
    return (string)$salida;
}

$servidor = proc_open([PHP_BINARY, '-S', 'localhost:8091', '-t', $raiz], [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']],
                      $tuberias, null, getenv() + ['GMG_ENV_FILE' => "$tmp/config.txt"]);
try {
    for ($i = 0; $i < 40 && @file_get_contents('http://localhost:8091/login.php') === false; $i++) {
        usleep(250000);
    }
    $base = [$navegador, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
             '--force-prefers-reduced-motion', "--user-data-dir=$tmp/perfil"];
    $html = ejecutar(array_merge($base, ['--virtual-time-budget=60000', '--dump-dom', 'http://localhost:8091/tests/chat_tarjetas_prueba.html']));
    preg_match('~<pre id="resultado">(.*?)</pre>~s', $html, $m);
    $r = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5), true);
    if (!is_array($r)) {
        exit("La página de prueba no entregó resultado: " . mb_substr($m[1] ?? $html, 0, 300) . "\n");
    }

    echo "== Tarjetas restauradas del historial ==\n";
    revisar($r['tarjetas'] === 6, 'se pintan las 6 tarjetas', (string)$r['tarjetas']);
    revisar($r['disponibilidad'] === ['Disponible', 'Últimas unidades', 'Sin stock', 'Sin stock', '', 'Disponible'],
            'disponibilidad de cada una (la guardada sin ese dato se calcula con el stock)', implode(' | ', $r['disponibilidad']));
    revisar($r['botones'] === ['🛒 Añadir', '🛒 Añadir', 'Sin stock (deshabilitado)', 'Sin stock (deshabilitado)', '🛒 Añadir', '🛒 Añadir'],
            '"Añadir" deshabilitado cuando no hay stock', implode(' | ', $r['botones']));
    revisar($r['fichas'] === ['producto.php?id=242', '', '', '', '', ''], '"Ver ficha" solo con ruta del sitio (el javascript: se descarta)', implode(' | ', $r['fichas']));
    revisar(strpos($r['fotoCambiada'], 'placeholder-repuesto.svg') !== false, 'una foto que no carga se cambia por la genérica', $r['fotoCambiada']);

    echo "\n== Seguridad ==\n";
    revisar($r['codigoEnLinea'] === 0, 'sin onclick, onerror ni onload en las tarjetas');
    revisar($r['xss'] === 0, 'el nombre, la foto y la ficha maliciosos no ejecutan código', 'window.__xss = ' . $r['xss']);
    revisar($r['atributoInyectado'] === 0, 'un id con comillas no inyecta atributos');
    revisar($r['nombreIntacto'] === true, 'el nombre llega intacto en data-nombre (sin escapes dobles)');

    echo "\n== Carrito ==\n";
    $c = $r['carrito'];
    revisar(count($c) === 2, 'el producto agotado no se agrega (2 clics válidos de 3)', count($c) . ' llamadas');
    revisar(($c[0]['producto'] ?? null) === ['id' => '242', 'nombre' => 'Pastillas de freno delanteras (juego) Swift 3a gen.', 'precio' => 24990, 'imagen' => 'img/placeholder-repuesto.svg', 'stock' => 5]
            && ($c[0]['cantidad'] ?? 0) === 1, 'addToCartFromChat recibe id, nombre, precio numérico, imagen, stock y cantidad 1');

    echo "\n== Mensaje nuevo, movimiento reducido y espera máxima ==\n";
    revisar($r['reducirMovimiento'] === true && $r['textoCompletoAlInstante'] === true, 'con prefers-reduced-motion la respuesta se muestra completa al instante');
    revisar($r['tarjetaNueva'] === true, 'la tarjeta de una respuesta nueva trae "Sin stock" y "Ver ficha"');
    revisar($r['esperaAntesDe30s'] === true && $r['avisoTrasEspera'] === true, 'si el servidor no responde, a los 30 s se corta y se avisa');
    revisar($r['enlaceWhatsApp'] === 'https://wa.me/56911112222?text=Hola%2C%20necesito%20ayuda%20con%20un%20repuesto', 'el aviso trae el enlace de WhatsApp de la tienda', $r['enlaceWhatsApp']);

    echo "\n== Fotos adjuntas en el navegador (F1-8) ==\n";
    revisar($r['acceptFotos'] === 'image/jpeg,image/png,image/webp', 'el selector ofrece solo JPG, PNG y WebP', (string)$r['acceptFotos']);
    revisar($r['gifRechazado'] === true, 'un GIF no se adjunta y se avisa por qué');
    revisar($r['grandeRechazada'] === true, 'una foto de más de 4 MB no se adjunta');
    revisar($r['pngAceptado'] === true, 'un PNG válido sí se adjunta');

    ejecutar(array_merge($base, ['--virtual-time-budget=5000', '--window-size=360,760', "--screenshot=$captura", 'http://localhost:8091/tests/chat_tarjetas_prueba.html?captura=1']));
    echo "\nCaptura a 360 px: $captura\n";
} finally {
    proc_terminate($servidor);
    exec('rmdir /s /q ' . escapeshellarg($tmp));
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
