<?php
/**
 * F3-4 (E10-H2, RNF-030, CP-06): consultas con sentencias preparadas donde antes se usaba
 * real_escape_string: filtro por modelo de la tienda, productos relacionados del carrito y
 * filtros y búsqueda de admin/pedidos.php. Cada uno con datos normales y con inyección.
 * Base temporal desde BD_v2 (tests/chat_entorno.php), con login real al panel.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/sql_preparado_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('sqlprep');
$cookies = $e['tmp'] . '/cookies.txt';
function get(array $e, string $ruta, ?array $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
function tarjetas(string $html): int {
    return preg_match('/class="listado-resumen">\s*([\d.]+) repuesto/', $html, $m) ? (int)str_replace('.', '', $m[1]) : 0;
}
try {
    echo "== Tienda: filtro por modelo ==\n";
    $swift = tarjetas(get($e, '/index.php?modelo=swift'));
    revisar($swift > 0 && tarjetas(get($e, '/index.php?modelo=Suzuki%20Swift')) === $swift && tarjetas(get($e, '/index.php?modelo=grand-vitara')) > 0,
            'por nombre, con "Suzuki" y por slug', "$swift repuestos");
    $html = get($e, '/index.php?modelo=' . rawurlencode("swift' OR '1'='1"));
    revisar(tarjetas($html) === 0 && strpos($html, 'sin-resultados') !== false, 'inyección en el modelo: sin resultados, sin error');

    echo "\n== Carrito: productos relacionados ==\n";
    $r = json_decode(get($e, '/backend/relacionados.php?categoria=Frenos&excluir=1,2'), true);
    revisar(count($r['productos'] ?? []) === 4, '4 relacionados de Frenos');
    $r = json_decode(get($e, '/backend/relacionados.php?categoria=' . rawurlencode("x' OR '1'='1") . '&excluir=0)%20OR%201=1--'), true);
    revisar(is_array($r['productos'] ?? null) && count($r['productos']) <= 4, 'inyección en categoría y excluir: responde normal (completa con otros al azar)');
    $r = json_decode(get($e, '/backend/relacionados.php?categoria[]=x&excluir[]=1'), true);
    revisar(is_array($r['productos'] ?? null), 'parámetros como arreglo: responde normal');

    echo "\n== Panel: filtros y búsqueda de pedidos ==\n";
    $hash = $e['db']->real_escape_string(password_hash('Clave-F34', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f34', 'Prueba F3-4', '$hash', 1)");
    $e['db']->query("INSERT INTO clientes (id_cliente, rut, nombre, apellido, email, telefono) VALUES (1, '21740488-6', 'Ana', 'Pérez', 'ana@example.com', '+56 9 1111 2222'), (2, '12345678-5', 'Luis', 'Soto', 'luis@example.com', '+56 9 3333 4444')");
    $e['db']->query("INSERT INTO pedidos (id_pedido, id_cliente, estado, pago_estado, total) VALUES (501, 1, 'pendiente', 'recibido', 1000), (502, 2, 'aprobado', 'pendiente', 2000)");
    get($e, '/login.php', ['usuario' => 'prueba_f34', 'password' => 'Clave-F34']);
    $pedidos = function ($q) use ($e) { preg_match_all('/#(50[12])\b/', get($e, '/admin/pedidos.php?' . $q), $m); return array_values(array_unique($m[1])); };
    revisar($pedidos('estado=aprobado') === ['502'], 'filtro por estado');
    revisar($pedidos('pago=recibido') === ['501'], 'filtro por pago');
    revisar($pedidos('q=Ana') === ['501'] && $pedidos('q=' . rawurlencode('21.740.488')) === ['501'] && $pedidos('q=502') === ['502'], 'búsqueda por nombre, RUT con puntos y número');
    revisar($pedidos('q=' . rawurlencode("' OR '1'='1")) === [] && $pedidos('q=%25') === [], 'inyección y "%" en la búsqueda: ningún pedido de más');
    revisar($pedidos('estado=' . rawurlencode("pendiente' OR '1'='1")) === ['501', '502'] || $pedidos('estado=' . rawurlencode("pendiente' OR '1'='1")) === ['502', '501'], 'estado inválido se ignora (muestra todos)');

    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
