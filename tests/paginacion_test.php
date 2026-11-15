<?php
/**
 * F2-6 (E2-H1, RF-008): listado de la tienda paginado de a 24 repuestos.
 * Contra el sitio local (php -S, puerto 8094, base gmg_local, sin leer el .env) revisa:
 *   - ?modelo=swift: 24 por página, todas las páginas juntas son exactamente los repuestos
 *     del Swift en la base, sin repetidos; resumen, flechas y página actual correctos;
 *   - los enlaces conservan modelo, categoría y búsqueda;
 *   - página fuera de rango (999) muestra la última; pagina=0, -1, abc o arreglo, la primera;
 *   - página 2 con título y canonical propios;
 *   - un listado de 24 o menos no muestra paginación;
 *   - en Edge a 360 px: sin scroll horizontal y botones de 44 px o más.
 * Requisitos: MariaDB con gmg_local (catálogo de BD_v2), Edge o Chrome.
 * Uso: php tests/paginacion_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'gmg_local');
$swift = array_map('intval', array_column($db->query(
    "SELECT DISTINCT r.id_repuesto FROM repuestos r JOIN compatibilidades co ON co.id_repuesto = r.id_repuesto
       JOIN vehiculos v ON v.id_vehiculo = co.id_vehiculo JOIN modelos m ON m.id_modelo = v.id_modelo
      WHERE r.activo = 1 AND m.slug = 'swift'")->fetch_all(), 0));
$frenosSwift = (int)$db->query(
    "SELECT COUNT(DISTINCT r.id_repuesto) FROM repuestos r JOIN categorias c ON c.id_categoria = r.id_categoria
       JOIN compatibilidades co ON co.id_repuesto = r.id_repuesto JOIN vehiculos v ON v.id_vehiculo = co.id_vehiculo
       JOIN modelos m ON m.id_modelo = v.id_modelo WHERE r.activo = 1 AND m.slug = 'swift' AND c.nombre = 'Frenos'")->fetch_row()[0];
if (count($swift) <= 48 || $frenosSwift > 24 || $frenosSwift === 0) {
    exit("gmg_local necesita el catálogo de BD_v2 (Swift con más de 48 repuestos y de 1 a 24 de frenos).\n");
}
$paginasSwift = (int)ceil(count($swift) / 24);

/** Descarga una página del listado. */
function pagina(array $s, string $query): array {
    $html = (string)@file_get_contents($s['url'] . '/index.php?' . $query, false, stream_context_create(['http' => ['ignore_errors' => true]]));
    preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $m);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8">' . $html);
    $xp = new DOMXPath($dom);
    $ids = [];
    foreach ($xp->query("//div[contains(@class,'producto-card')]/@data-producto-id") as $a) {
        $ids[] = (int)$a->value;
    }
    $actual = $xp->query("//nav[@class='paginacion']//span[@aria-current='page']")->item(0);
    return [
        'codigo' => (int)($m[1] ?? 0), 'ids' => $ids, 'xp' => $xp,
        'actual' => $actual ? (int)$actual->textContent : 0,
        'prev' => (string)($xp->query("//a[@rel='prev']/@href")->item(0)->value ?? ''),
        'next' => (string)($xp->query("//a[@rel='next']/@href")->item(0)->value ?? ''),
        'resumen' => trim(preg_replace('/\s+/', ' ', (string)($xp->query("//p[@class='listado-resumen']")->item(0)->textContent ?? ''))),
        'titulo' => trim((string)($xp->query('//title')->item(0)->textContent ?? '')),
        'canonical' => (string)($xp->query("//link[@rel='canonical']/@href")->item(0)->value ?? ''),
    ];
}

$s = sitioIniciar(8094);
try {
    echo "== Swift: " . count($swift) . " repuestos, $paginasSwift páginas ==\n";
    $todos = [];
    $malas = [];
    for ($n = 1; $n <= $paginasSwift; $n++) {
        $p = pagina($s, 'modelo=swift' . ($n > 1 ? "&pagina=$n" : ''));
        $esperados = $n < $paginasSwift ? 24 : count($swift) - 24 * ($paginasSwift - 1);
        if (count($p['ids']) !== $esperados || $p['actual'] !== $n || ($n === 1) !== ($p['prev'] === '') || ($n === $paginasSwift) !== ($p['next'] === '')) {
            $malas[] = "página $n: " . count($p['ids']) . " tarjetas, actual {$p['actual']}";
        }
        $todos = array_merge($todos, $p['ids']);
    }
    revisar($malas === [], '24 por página (la última con el resto), página actual marcada, sin "Anterior" en la 1 ni "Siguiente" en la última', implode('; ', $malas));
    sort($todos);
    $esperado = $swift;
    sort($esperado);
    revisar($todos === $esperado, 'todas las páginas juntas son exactamente los repuestos del Swift, sin repetidos', count($todos) . ' de ' . count($swift));

    $p1 = pagina($s, 'modelo=swift');
    revisar($p1['resumen'] === count($swift) . ' repuestos · mostrando del 1 al 24', 'resumen de la página 1', $p1['resumen']);
    revisar($p1['next'] === 'index.php?modelo=swift&pagina=2#listado', '"Siguiente" conserva el modelo', $p1['next']);
    $p2 = pagina($s, 'modelo=swift&pagina=2');
    revisar($p2['prev'] === 'index.php?modelo=swift#listado', '"Anterior" vuelve a la página 1 sin "pagina"', $p2['prev']);
    revisar(strpos($p2['titulo'], '(página 2)') !== false && $p1['titulo'] !== $p2['titulo'], 'la página 2 tiene título propio', $p2['titulo']);
    revisar(substr($p2['canonical'], -26) === '?modelo=Swift&pagina=2' || substr($p2['canonical'], -8) === 'pagina=2', 'la página 2 tiene canonical propia', $p2['canonical']);
    revisar(strpos($p1['canonical'], 'pagina') === false, 'la página 1 no lleva "pagina" en la canonical', $p1['canonical']);

    echo "\n== Filtros y valores raros ==\n";
    $q = pagina($s, 'q=filtro&categoria=Filtros');
    $enlaces = array_map(function ($a) { return $a->value; }, iterator_to_array($q['xp']->query("//nav[@class='paginacion']//a/@href")));
    $sinFiltros = array_filter($enlaces, function ($h) { return strpos($h, 'q=filtro') === false || strpos($h, 'categoria=Filtros') === false; });
    revisar($enlaces && !$sinFiltros, 'los enlaces de página conservan la búsqueda y la categoría', $enlaces[0] ?? 'sin paginación');
    $ultima = pagina($s, 'modelo=swift&pagina=999');
    revisar($ultima['codigo'] === 200 && $ultima['actual'] === $paginasSwift && count($ultima['ids']) > 0, 'pagina=999 muestra la última página', (string)$ultima['actual']);
    foreach (['pagina=0', 'pagina=-1', 'pagina=abc', 'pagina[]=2', 'pagina=2.5'] as $raro) {
        $r = pagina($s, "modelo=swift&$raro");
        revisar($r['codigo'] === 200 && $r['actual'] === 1 && count($r['ids']) === 24, "$raro muestra la página 1");
    }
    $chico = pagina($s, 'modelo=swift&categoria=Frenos');
    revisar(count($chico['ids']) === $frenosSwift && $chico['xp']->query("//nav[@class='paginacion']")->length === 0 && $chico['resumen'] === ($frenosSwift === 1 ? '1 repuesto' : "$frenosSwift repuestos"),
            "un listado de $frenosSwift repuestos no muestra paginación", $chico['resumen']);
    $vacio = pagina($s, 'q=zzzznoexiste&pagina=3');
    revisar($vacio['codigo'] === 200 && $vacio['ids'] === [] && $vacio['xp']->query("//div[contains(@class,'sin-resultados')]")->length === 1, 'sin resultados (aunque pida la página 3): mensaje de sin resultados');

    echo "\n== Navegador a 360 px ==\n";
    if (!navegadorRuta()) {
        revisar(false, 'Edge o Chrome instalado');
    } else {
        $nav = navegadorResultado($s, '/tests/paginacion_prueba.html');
        revisar($nav !== null, 'la página de prueba entregó resultado');
        if ($nav) {
            revisar($nav['sinScroll'], 'sin scroll horizontal a 360 px');
            revisar($nav['botones'] > 0 && $nav['menorAlto'] >= 44 && $nav['menorAncho'] >= 44, 'botones de página de 44 x 44 px o más', "{$nav['menorAncho']} x {$nav['menorAlto']}");
        }
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
