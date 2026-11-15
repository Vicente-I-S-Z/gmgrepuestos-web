<?php
/**
 * F3-5 (RNF-031, RNF-038): cabeceras de seguridad con CSP básica y Permissions-Policy.
 *   - todas las páginas públicas y el panel envían las cabeceras;
 *   - en Edge sin ventana, ninguna página principal registra un bloqueo de la CSP en la consola
 *     (fuentes, fotos, mapa de Contacto, scripts del sitio y del panel siguen funcionando);
 *   - una prueba de control (un script de otro dominio) sí queda bloqueada.
 * Sitio con php -S (puerto 8099) sobre gmg_local; entra al panel con un administrador temporal.
 * Requisitos: Edge o Chrome, MariaDB con gmg_local.
 * Uso: php tests/csp_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$s = sitioIniciar(8099);

/** Abre una página en Edge sin ventana y devuelve los mensajes de consola sobre la CSP. */
function bloqueosCsp(array $s, string $ruta, array $cookies = []): array {
    $perfil = $s['tmp'] . '/perfil_' . uniqid();
    $comando = [navegadorRuta(), '--headless=new', '--disable-gpu', '--no-first-run', "--user-data-dir=$perfil",
                '--enable-logging=stderr', '--v=0', '--virtual-time-budget=6000', '--dump-dom', $s['url'] . $ruta];
    $p = proc_open($comando, [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['pipe', 'w']], $tuberias);
    $log = (string)stream_get_contents($tuberias[2]);
    proc_close($p);
    return array_values(array_filter(explode("\n", $log), function ($l) { return stripos($l, 'Content Security Policy') !== false; }));
}
try {
    echo "== Cabeceras ==\n";
    foreach (['/index.php', '/producto.php?id=1', '/contacto.php', '/login.php', '/backend/buscar_sugerencias.php?q=filtro'] as $ruta) {
        $cab = implode("\n", get_headers($s['url'] . $ruta));
        revisar(stripos($cab, "Content-Security-Policy: default-src 'self'") !== false && stripos($cab, 'Permissions-Policy: camera=()') !== false, "$ruta envía CSP y Permissions-Policy");
    }
    echo "\n== Sin bloqueos en el navegador ==\n";
    if (!navegadorRuta()) {
        revisar(false, 'Edge o Chrome instalado');
    } else {
        foreach (['/index.php', '/index.php?modelo=swift', '/producto.php?id=255', '/contacto.php', '/index.php?q=zzzz'] as $ruta) {
            $bloqueos = bloqueosCsp($s, $ruta);
            revisar($bloqueos === [], "$ruta: ningún bloqueo de la CSP", $bloqueos ? trim(substr($bloqueos[0], 0, 200)) : '');
        }
        $bloqueos = bloqueosCsp($s, '/tests/csp_control.php');
        revisar($bloqueos !== [], 'control: un script de otro dominio sí se bloquea', count($bloqueos) . ' mensajes');
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
