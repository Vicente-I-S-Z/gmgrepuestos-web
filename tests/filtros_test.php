<?php
/**
 * F4-4 (E2-H8, RF-015): filtros (precio, solo con stock) y orden (precio, nombre) del listado.
 * Recorre todas las páginas de cada combinación y compara con la base. Sitio con php -S (puerto 8105)
 * sobre gmg_local. Requisitos: MariaDB con gmg_local.
 * Uso: php tests/filtros_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$db->set_charset('utf8mb4');
$swift = "SELECT DISTINCT r.id_repuesto, r.nombre, r.precio, r.stock FROM repuestos r JOIN compatibilidades co ON co.id_repuesto = r.id_repuesto
          JOIN vehiculos v ON v.id_vehiculo = co.id_vehiculo JOIN modelos m ON m.id_modelo = v.id_modelo WHERE r.activo = 1 AND m.slug = 'swift'";
$datos = [];
foreach ($db->query($swift)->fetch_all(MYSQLI_ASSOC) as $f) {
    $datos[(int)$f['id_repuesto']] = $f;
}
$s = sitioIniciar(8105);
/** IDs de todas las páginas, siguiendo "Siguiente". Devuelve [ids, html de la primera]. */
function listado(array $s, string $query): array {
    $ids = [];
    $primera = '';
    for ($url = $s['url'] . '/index.php?' . $query; $url !== ''; ) {
        $html = (string)file_get_contents($url);
        $primera = $primera ?: $html;
        preg_match_all('~class="producto-card" data-producto-id="(\d+)"~', $html, $m);
        $ids = array_merge($ids, array_map('intval', $m[1]));
        $url = preg_match('~<a class="paginacion-flecha" href="([^"#]+)[^"]*" rel="next"~', $html, $sig) ? $s['url'] . '/' . html_entity_decode($sig[1]) : '';
    }
    return [$ids, $primera];
}
$precios = function (array $ids) use ($datos) { return array_map(function ($id) use ($datos) { return (float)$datos[$id]['precio']; }, $ids); };
try {
    [$todos] = listado($s, 'modelo=swift');
    [$ids, $html] = listado($s, 'modelo=swift&orden=precio_asc');
    $p = $precios($ids);
    $ordenado = $p;
    sort($ordenado);
    revisar(count($ids) === count($datos) && $p === $ordenado, 'precio de menor a mayor en las ' . count($ids) . ' tarjetas (todas las páginas)');
    revisar(strpos($html, 'orden=precio_asc&amp;pagina=2') !== false || strpos($html, 'orden=precio_asc&pagina=2') !== false, 'la paginación conserva el orden');
    revisar(strpos($html, '<meta name="robots" content="noindex, follow">') !== false, 'un listado ordenado o filtrado no se indexa');
    [$ids] = listado($s, 'modelo=swift&orden=precio_desc');
    $p = $precios($ids);
    $ordenado = $p;
    rsort($ordenado);
    revisar($p === $ordenado, 'precio de mayor a menor');
    [$ids] = listado($s, 'modelo=swift&orden=nombre');
    $esperado = array_map('intval', array_column($db->query($swift . " ORDER BY r.nombre ASC")->fetch_all(), 0));
    revisar($ids === $esperado, 'por nombre (A-Z), igual que la base');
    [$ids] = listado($s, 'modelo=swift&disponible=1');
    $conStock = array_keys(array_filter($datos, function ($f) { return (int)$f['stock'] > 0; }));
    sort($conStock);
    $obtenidos = $ids;
    sort($obtenidos);
    revisar($obtenidos === $conStock, 'solo con stock: exactamente los ' . count($conStock) . ' con stock');
    [$ids] = listado($s, 'modelo=swift&precio_min=20000&precio_max=60000&orden=precio_asc');
    $fuera = array_filter($precios($ids), function ($pr) { return $pr < 20000 || $pr > 60000; });
    $esperados = count(array_filter($datos, function ($f) { return $f['precio'] >= 20000 && $f['precio'] <= 60000; }));
    revisar(count($ids) === $esperados && !$fuera, "entre \$20.000 y \$60.000: los $esperados que corresponden");
    [$ids] = listado($s, 'modelo=swift&orden=' . rawurlencode('precio_asc; DROP TABLE x') . '&precio_min=abc&precio_max[]=1&disponible[]=1');
    $a = $ids;
    sort($a);
    $b = $todos;
    sort($b);
    revisar($a === $b, 'valores raros o inyección en los filtros se ignoran');
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
