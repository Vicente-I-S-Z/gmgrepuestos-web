<?php
/**
 * F3-10 (E2-H10, RF-017): búsquedas sin resultado. Se registra el término (con modelo y categoría,
 * sin IP), se cuenta cuántas veces se repite, la página muestra alternativas, el asistente y WhatsApp,
 * y el panel "Avisos de stock" lista lo más buscado. Una búsqueda con resultados no se registra.
 * Base temporal desde BD_v2 (incluye el script 08) con tests/chat_entorno.php.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/busquedas_vacias_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('busquedas');
$cookies = $e['tmp'] . '/cookies.txt';
function pagina(array $e, string $ruta, ?array $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
function filas(array $e): array {
    return $e['db']->query("SELECT termino, modelo, categoria, veces FROM busquedas_sin_resultado ORDER BY id_busqueda")->fetch_all(MYSQLI_ASSOC);
}
try {
    echo "== Registro ==\n";
    pagina($e, '/index.php?q=' . rawurlencode('Turbo   Cohete') . '&modelo=swift');
    pagina($e, '/index.php?q=' . rawurlencode('turbo cohete') . '&modelo=swift');
    pagina($e, '/index.php?q=filtro');
    pagina($e, '/index.php?modelo=swift&categoria=NoExiste');
    $f = filas($e);
    revisar(count($f) === 1 && $f[0]['termino'] === 'turbo cohete' && $f[0]['modelo'] === 'swift' && (int)$f[0]['veces'] === 2,
            'se guarda en minúsculas con su modelo y cuenta las repeticiones; con resultados o sin texto no se guarda', json_encode($f));
    $columnas = array_column($e['db']->query("SHOW COLUMNS FROM busquedas_sin_resultado")->fetch_all(), 0);
    revisar(!array_intersect($columnas, ['ip', 'sesion_id', 'contacto']), 'no guarda IP ni datos personales');

    echo "\n== Página sin resultados ==\n";
    $html = pagina($e, '/index.php?q=' . rawurlencode('turbo cohete') . '&modelo=swift');
    revisar(strpos($html, 'index.php?q=turbo+cohete"') !== false && strpos($html, 'index.php?modelo=swift"') !== false, 'alternativas: el término en todo el catálogo y todo el modelo');
    revisar(substr_count($html, 'class="sin-resultados-alternativas"') === 1 && preg_match('/index\.php\?modelo=swift&amp;categoria=/', $html) === 1, 'y categorías dentro del modelo');
    revisar(strpos($html, 'data-chat-pregunta="Busco turbo cohete para mi Suzuki') !== false, 'el asistente abre con lo que buscó');
    revisar(preg_match('~wa\.me/\d+\?text=[^"]*turbo%20cohete~i', $html) === 1, 'WhatsApp con lo que buscó');

    echo "\n== Panel ==\n";
    $hash = $e['db']->real_escape_string(password_hash('Clave-F310', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f310', 'Prueba', '$hash', 1)");
    pagina($e, '/login.php', ['usuario' => 'prueba_f310', 'password' => 'Clave-F310']);
    $html = pagina($e, '/admin/avisos.php');
    revisar(strpos($html, 'Lo que buscaron y no encontraron') !== false && strpos($html, '<td>turbo cohete</td>') !== false && strpos($html, '<td>3</td>') !== false,
            '"Avisos de stock" lista el término con sus 3 búsquedas');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
