<?php
/**
 * F2-5 (E2-H3, E2-H7, INC-07, RNF-005): buscador por palabras de la tienda y sus sugerencias.
 * Casos del Plan de Pruebas v2: CP-03 (nombre, SKU y OEM), CP-04 (varias palabras), CP-05
 * (autocompletado: 2 caracteres y 6 resultados) y CP-06 (entradas maliciosas).
 * Contra el sitio local (php -S, puerto 8092, base gmg_local, sin leer el .env). Pone por un
 * momento un código OEM y un nombre con HTML en la base y los restaura al final.
 * Requisitos: MariaDB con gmg_local (catálogo de BD_v2), Edge o Chrome.
 * Uso: php tests/buscador_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'gmg_local');
$db->set_charset('utf8mb4');
$idOptica = (int)($db->query("SELECT id_repuesto FROM repuestos WHERE sku = 'CAR-SWI3-OPD' AND activo = 1")->fetch_row()[0] ?? 0);
$terminalesSwift = array_map('intval', array_column($db->query(
    "SELECT r.id_repuesto FROM repuestos r WHERE r.activo = 1 AND r.nombre LIKE '%terminal%'
        AND EXISTS (SELECT 1 FROM compatibilidades co JOIN vehiculos v ON v.id_vehiculo = co.id_vehiculo
                      JOIN modelos m ON m.id_modelo = v.id_modelo WHERE co.id_repuesto = r.id_repuesto AND m.slug = 'swift')")->fetch_all(), 0));
$totalActivos = (int)$db->query("SELECT COUNT(*) FROM repuestos WHERE activo = 1")->fetch_row()[0];
if (!$idOptica || count($terminalesSwift) < 3) {
    exit("gmg_local necesita el catálogo de BD_v2 (CAR-SWI3-OPD y los terminales del Swift).\n");
}
$original = $db->query("SELECT id_repuesto, nombre, codigo_oem FROM repuestos WHERE id_repuesto = $idOptica")->fetch_assoc();

/** IDs de las tarjetas de index.php (todas las páginas, siguiendo rel="next") y código HTTP de la primera. */
function listado(array $s, string $query): array {
    $ids = [];
    $codigo = 0;
    $primera = '';
    for ($url = $s['url'] . '/index.php?' . $query; $url !== ''; ) {
        $html = (string)@file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true]]));
        if ($primera === '') {
            preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $m);
            $codigo = (int)($m[1] ?? 0);
            $primera = $html;
        }
        preg_match_all('~class="producto-card" data-producto-id="(\d+)"~', $html, $encontrados);
        $ids = array_merge($ids, array_map('intval', $encontrados[1]));
        $url = preg_match('~<a class="paginacion-flecha" href="([^"#]+)[^"]*" rel="next"~', $html, $sig) ? $s['url'] . '/' . html_entity_decode($sig[1]) : '';
    }
    return ['codigo' => $codigo, 'ids' => $ids, 'html' => $primera];
}
function sugerencias(array $s, string $q): ?array {
    $r = json_decode((string)@file_get_contents($s['url'] . '/backend/buscar_sugerencias.php?q=' . rawurlencode($q)), true);
    return is_array($r) && isset($r['sugerencias']) ? $r['sugerencias'] : null;
}
/** Nombre, categoría y SKU de los ids, para revisar que calcen. */
function filas(mysqli $db, array $ids): array {
    if (!$ids) {
        return [];
    }
    $lista = implode(',', array_map('intval', $ids));
    return $db->query("SELECT r.id_repuesto, r.nombre, r.sku, c.nombre AS categoria FROM repuestos r
                         JOIN categorias c ON c.id_categoria = r.id_categoria WHERE r.id_repuesto IN ($lista)")->fetch_all(MYSQLI_ASSOC);
}

$s = sitioIniciar(8092);
try {
    echo "== CP-03 nombre, SKU y OEM ==\n";
    $r = listado($s, 'q=' . rawurlencode('óptica'));
    $sinOptica = array_filter(filas($db, $r['ids']), function ($f) { return stripos(strtr($f['nombre'], ['Ó' => 'O', 'ó' => 'o']), 'optica') === false; });
    revisar(count($r['ids']) > 0 && !$sinOptica, '"óptica" (con tilde) lista solo ópticas', count($r['ids']) . ' resultados');
    revisar(count(listado($s, 'q=optica')['ids']) === count($r['ids']), '"optica" sin tilde da lo mismo');
    $sug = sugerencias($s, 'CAR-SWI3-OPD');
    revisar(($sug[0]['id'] ?? 0) === $idOptica, 'SKU exacto: ese producto va primero en las sugerencias', $sug[0]['sku'] ?? '');
    revisar((listado($s, 'q=CAR-SWI3-OPD')['ids'][0] ?? 0) === $idOptica, 'SKU exacto: también va primero en el listado');
    revisar((sugerencias($s, 'car-swi3-opd')[0]['id'] ?? 0) === $idOptica, 'el SKU en minúsculas también lo encuentra');
    $db->query("UPDATE repuestos SET codigo_oem = '35120-74P00-PRUEBA' WHERE id_repuesto = $idOptica");
    revisar(in_array($idOptica, listado($s, 'q=35120-74P00')['ids'], true) && (sugerencias($s, '35120-74P00')[0]['id'] ?? 0) === $idOptica,
            'código OEM parcial: aparece en el listado y en las sugerencias');

    echo "\n== CP-04 varias palabras (INC-07) ==\n";
    $r = listado($s, 'q=' . rawurlencode('terminal swift'));
    revisar(!array_diff($terminalesSwift, $r['ids']), '"terminal swift" trae los 3 terminales del Swift (2.ª, 3.ª gen. y Sport)', implode(',', $r['ids']));
    $otros = array_filter(filas($db, $r['ids']), function ($f) { return stripos($f['nombre'], 'terminal') === false; });
    revisar(!$otros, 'y nada que no sea un terminal', implode('; ', array_column($otros, 'nombre')));
    $sug = sugerencias($s, 'terminal swift');
    revisar($sug && count($sug) <= 6 && !array_diff(array_column($sug, 'id'), $terminalesSwift), 'las sugerencias de "terminal swift" son esos terminales', implode(',', array_column($sug ?: [], 'id')));
    revisar(listado($s, 'q=' . rawurlencode('swift terminal'))['ids'] == $r['ids'], 'el orden de las palabras no importa');
    $pastilla = listado($s, 'q=pastilla')['ids'];
    revisar($pastilla && listado($s, 'q=pastillas')['ids'] == $pastilla, 'plural: "pastillas" da lo mismo que "pastilla"');
    $balatas = listado($s, 'q=balatas')['ids'];
    revisar(!array_diff($pastilla, $balatas), 'sinónimo: "balatas" incluye las pastillas', count($balatas) . ' resultados');
    $conCategoria = listado($s, 'q=pastilla&categoria=Frenos');
    $fueraCategoria = array_filter(filas($db, $conCategoria['ids']), function ($f) { return $f['categoria'] !== 'Frenos'; });
    revisar($conCategoria['ids'] && !$fueraCategoria, 'búsqueda junto con categoría (parámetros en orden)');
    $conModelo = listado($s, 'modelo=swift&q=terminal')['ids'];
    revisar(!array_diff($terminalesSwift, $conModelo), 'búsqueda junto con el modelo');

    echo "\n== CP-05 autocompletado ==\n";
    revisar(sugerencias($s, 'ó') === [], '1 carácter: sin sugerencias');
    $sug = sugerencias($s, 'óp');
    revisar($sug && count($sug) <= 6, '2 caracteres: hay sugerencias, máximo 6', (string)count($sug ?: []));
    revisar(count(sugerencias($s, 'filtro') ?: []) === 6, 'búsqueda amplia: exactamente 6');
    $completas = $sug && !array_filter($sug, function ($x) { return empty($x['imagen']) || empty($x['sku']) || !isset($x['precio']); });
    revisar($completas, 'cada sugerencia trae imagen, SKU y precio');

    echo "\n== CP-06 entradas maliciosas ==\n";
    foreach (["' OR 1=1 --", '%', '_', '<script>alert(1)</script>'] as $malo) {
        $r = listado($s, 'q=' . rawurlencode($malo));
        $sug = sugerencias($s, $malo);
        revisar($r['codigo'] === 200 && count($r['ids']) < $totalActivos && $sug !== null && count($sug) <= 6,
                "\"$malo\": responde normal y no trae el catálogo completo", count($r['ids']) . ' resultados');
    }
    $r = listado($s, 'q=' . rawurlencode('<script>alert(1)</script>'));
    revisar(strpos($r['html'], '<script>alert(1)') === false && strpos($r['html'], '&lt;script&gt;alert(1)') !== false, 'el texto buscado se muestra escapado');
    revisar(listado($s, 'q[]=x')['codigo'] === 200 && listado($s, 'categoria[]=x')['codigo'] === 200, 'un filtro enviado como arreglo no rompe la página');
    revisar(sugerencias($s, str_repeat('pastilla ', 200)) !== null, 'texto muy largo (1.800 caracteres): responde');

    echo "\n== Navegador ==\n";
    $malicioso = 'Óptica <b>negrita</b><img src=x onerror="window.__xss=1"> Swift';
    $st = $db->prepare("UPDATE repuestos SET nombre = ? WHERE id_repuesto = ?");
    $st->bind_param('si', $malicioso, $idOptica);
    $st->execute();
    if (!navegadorRuta()) {
        revisar(false, 'Edge o Chrome instalado');
    } else {
        $nav = navegadorResultado($s, '/tests/buscador_prueba.html?q=CAR-SWI3-OPD', 15000);
        revisar($nav !== null, 'la página de prueba entregó resultado');
        if ($nav) {
            revisar($nav['conUno'] === 0, 'escribir 1 carácter no muestra sugerencias');
            revisar($nav['cantidad'] >= 1 && $nav['primerNombre'] === $malicioso && $nav['htmlInyectado'] === 0 && !$nav['xss'],
                    'el nombre con HTML se muestra como texto y no ejecuta nada', $nav['primerNombre']);
            revisar($nav['primerHref'] === "producto.php?id=$idOptica", 'la sugerencia abre la ficha del producto', $nav['primerHref']);
        }
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    $st = $db->prepare("UPDATE repuestos SET nombre = ?, codigo_oem = ? WHERE id_repuesto = ?");
    $st->bind_param('ssi', $original['nombre'], $original['codigo_oem'], $idOptica);
    $st->execute();
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
