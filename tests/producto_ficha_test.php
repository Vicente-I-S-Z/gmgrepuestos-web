<?php
/**
 * F2-4 (E2-H5, RF-012, E2-H11, E8-H1, E8-H4, E8-H5, E8-H7, E8-H9): ficha de producto.
 * Contra el sitio local (php -S, puerto 8091, base gmg_local, sin leer el .env) revisa:
 *   - contenido: foto, nombre, SKU, precio, disponibilidad, compatibilidad, botones, migas de pan;
 *   - buscadores: title, description, canonical, og:image absoluta y JSON-LD Product/Offer;
 *   - hasta 4 relacionados activos de la misma categoría, sin el propio producto;
 *   - sin stock: botón deshabilitado y OutOfStock; id inválido, inexistente o inactivo: 404 noindex;
 *   - un nombre con HTML y </script> sale escapado (se cambia un momento en la base y se restaura);
 *   - las tarjetas de la tienda enlazan a la ficha;
 *   - en Edge sin ventana: sin scroll horizontal a 360 y 1366 px, "Preguntar al asistente" abre el
 *     chat con la consulta escrita y "Añadir al carrito" agrega el producto.
 * Requisitos: MariaDB con gmg_local (stock variado), Edge o Chrome.
 * Uso: php tests/producto_ficha_test.php   (termina en "TODO OK"; deja la base como estaba)
 */
require __DIR__ . '/navegador.php';
require __DIR__ . '/../backend/chat_busqueda.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'gmg_local');
$db->set_charset('utf8mb4');
$uno = function (string $sql) use ($db) { return $db->query($sql)->fetch_assoc(); };

// Con stock y con compatibilidades; sin stock; con pocas unidades.
$conStock = $uno("SELECT r.id_repuesto id, r.nombre, r.sku, r.precio, r.id_categoria FROM repuestos r
                   WHERE r.activo = 1 AND r.stock > 3 AND EXISTS (SELECT 1 FROM compatibilidades c WHERE c.id_repuesto = r.id_repuesto)
                   ORDER BY r.id_repuesto LIMIT 1");
$sinStock = $uno("SELECT id_repuesto id FROM repuestos WHERE activo = 1 AND stock = 0 ORDER BY id_repuesto LIMIT 1");
$pocas = $uno("SELECT id_repuesto id FROM repuestos WHERE activo = 1 AND stock BETWEEN 1 AND 3 ORDER BY id_repuesto LIMIT 1");
$maxId = (int)$uno("SELECT MAX(id_repuesto) m FROM repuestos")['m'];
if (!$conStock || !$sinStock || !$pocas) {
    exit("gmg_local necesita repuestos con stock mayor a 3, con 0 y con 1 a 3 (ver docs/OTRO_COMPUTADOR.md).\n");
}

/** Descarga una página y devuelve [código HTTP, DOMXPath, html]. */
function pagina(array $s, string $ruta): array {
    $html = @file_get_contents($s['url'] . $ruta, false, stream_context_create(['http' => ['ignore_errors' => true]]));
    preg_match('~HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $m);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8">' . $html);
    return [(int)($m[1] ?? 0), new DOMXPath($dom), (string)$html];
}
function texto(DOMXPath $xp, string $q, ?DOMNode $ctx = null): string {
    $n = $xp->query($q, $ctx)->item(0);
    return $n ? trim($n instanceof DOMAttr ? $n->value : $n->textContent) : '';
}
function jsonLd(DOMXPath $xp): ?array {
    $j = json_decode(texto($xp, "//script[@type='application/ld+json']"), true);
    return is_array($j) ? $j : null;
}

$s = sitioIniciar(8091);
$nombreOriginal = $conStock['nombre'];
try {
    echo "== Ficha con stock (id {$conStock['id']}) ==\n";
    [$codigo, $xp] = pagina($s, "/producto.php?id={$conStock['id']}");
    revisar($codigo === 200, 'responde 200', (string)$codigo);
    revisar(texto($xp, '//h1') === $conStock['nombre'], 'h1 con el nombre');
    revisar(texto($xp, '//title') === "{$conStock['nombre']} ({$conStock['sku']}) - GMG Repuestos", 'title propio con nombre y SKU', texto($xp, '//title'));
    $desc = texto($xp, "//meta[@name='description']/@content");
    revisar($desc !== '' && mb_strlen($desc) <= 160, 'meta description propia de hasta 160 caracteres', mb_strlen($desc) . ' car.');
    revisar(texto($xp, "//link[@rel='canonical']/@href") === "{$s['url']}/producto.php?id={$conStock['id']}", 'canonical absoluta');
    revisar(texto($xp, "//meta[@property='og:type']/@content") === 'product', 'og:type product');
    revisar(strpos(texto($xp, "//meta[@property='og:image']/@content"), "{$s['url']}/") === 0, 'og:image absoluta', texto($xp, "//meta[@property='og:image']/@content"));
    revisar($xp->query("//div[contains(@class,'ficha-galeria')]//img[@alt != '']")->length >= 1, 'foto con alt');
    revisar(strpos(texto($xp, "//dl[@class='ficha-datos']"), $conStock['sku']) !== false, 'muestra el SKU');
    $precioTexto = '$' . number_format((float)$conStock['precio'], 0, ',', '.') . ' CLP';
    revisar(texto($xp, "//p[@class='ficha-precio']") === $precioTexto, 'precio en CLP', texto($xp, "//p[@class='ficha-precio']"));
    revisar(texto($xp, "//div[contains(@class,'ficha-info')]/span[contains(@class,'gmg-disponibilidad')]") === 'Disponible', 'disponibilidad "Disponible"');
    revisar($xp->query("//ul[@class='ficha-compat']/li")->length > 0, 'lista de vehículos compatibles', (string)$xp->query("//ul[@class='ficha-compat']/li")->length);
    revisar((int)texto($xp, "//button[contains(@class,'btn-add-cart') and not(@disabled)]/@data-id") === (int)$conStock['id']
            && $xp->query("//button[contains(@class,'btn-comprar-ahora')]")->length === 1, '"Añadir al carrito" y "Comprar ahora" activos');
    revisar(strpos(texto($xp, "//button[@data-abrir-chat]/@data-chat-pregunta"), $conStock['sku']) !== false, '"Preguntar al asistente" lleva el SKU como contexto');
    revisar(strpos(texto($xp, "//a[contains(@class,'ficha-btn-whatsapp')]/@href"), 'https://wa.me/56911112222?text=') === 0, 'enlace a WhatsApp con mensaje');
    $migas = [];
    foreach ($xp->query("//nav[contains(@class,'ficha-migas')]//li") as $li) {
        $migas[] = trim($li->textContent);
    }
    revisar(count($migas) >= 3 && $migas[0] === 'Inicio' && end($migas) === $conStock['nombre'], 'migas de pan', implode(' > ', $migas));

    $ld = jsonLd($xp);
    revisar($ld && $ld['@type'] === 'Product' && $ld['sku'] === $conStock['sku'] && $ld['name'] === $conStock['nombre'], 'JSON-LD Product con nombre y SKU');
    revisar($ld && $ld['offers']['@type'] === 'Offer' && $ld['offers']['priceCurrency'] === 'CLP'
            && (float)$ld['offers']['price'] === round((float)$conStock['precio'])
            && $ld['offers']['availability'] === 'https://schema.org/InStock', 'JSON-LD Offer con precio CLP e InStock');

    $idsRel = [];
    foreach ($xp->query("//a[@class='ficha-relacionado']/@href") as $href) {
        $idsRel[] = (int)preg_replace('/\D/', '', $href->value);
    }
    $malos = [];
    foreach ($idsRel as $idRel) {
        $f = $uno("SELECT activo, id_categoria FROM repuestos WHERE id_repuesto = " . (int)$idRel);
        if ($idRel === (int)$conStock['id'] || !$f || (int)$f['activo'] !== 1 || (int)$f['id_categoria'] !== (int)$conStock['id_categoria']) {
            $malos[] = $idRel;
        }
    }
    revisar(count($idsRel) >= 1 && count($idsRel) <= 4 && $malos === [], 'de 1 a 4 relacionados activos de la misma categoría', implode(',', $idsRel));

    echo "\n== Sin stock y pocas unidades ==\n";
    [, $xp] = pagina($s, "/producto.php?id={$sinStock['id']}");
    revisar($xp->query("//div[contains(@class,'ficha-acciones')]/button[@disabled]")->length === 1
            && $xp->query("//button[contains(@class,'btn-comprar-ahora')]")->length === 0, 'sin stock: un botón deshabilitado y sin "Comprar ahora"');
    revisar((jsonLd($xp)['offers']['availability'] ?? '') === 'https://schema.org/OutOfStock', 'sin stock: JSON-LD OutOfStock');
    [, $xp] = pagina($s, "/producto.php?id={$pocas['id']}");
    revisar(texto($xp, "//div[contains(@class,'ficha-info')]/span[contains(@class,'gmg-disponibilidad')]") === 'Últimas unidades', 'stock 1 a 3: "Últimas unidades"');

    echo "\n== No encontrado ==\n";
    foreach (['?id=' . ($maxId + 1000), '?id=abc', '?id=-3', ''] as $q) {
        [$codigo, $xp] = pagina($s, "/producto.php$q");
        revisar($codigo === 404 && texto($xp, "//meta[@name='robots']/@content") === 'noindex, follow'
                && $xp->query('//*[@data-abrir-chat]')->length === 1, "producto.php$q: 404 noindex con salida al asistente", (string)$codigo);
    }
    $db->query("UPDATE repuestos SET activo = 0 WHERE id_repuesto = " . (int)$pocas['id']);
    [$codigo] = pagina($s, "/producto.php?id={$pocas['id']}");
    $db->query("UPDATE repuestos SET activo = 1 WHERE id_repuesto = " . (int)$pocas['id']);
    revisar($codigo === 404, 'producto inactivo: 404', (string)$codigo);

    echo "\n== Escape ==\n";
    $malicioso = 'Filtro <b>x</b> "</script><script>window.__xss=1</script>';
    $st = $db->prepare("UPDATE repuestos SET nombre = ? WHERE id_repuesto = ?");
    $st->bind_param('si', $malicioso, $conStock['id']);
    $st->execute();
    [, $xp, $html] = pagina($s, "/producto.php?id={$conStock['id']}");
    revisar(texto($xp, '//h1') === $malicioso && $xp->query('//h1/b')->length === 0, 'el nombre sale como texto en el h1');
    revisar(substr_count($html, '</script><script>window.__xss') === 0 && (jsonLd($xp)['name'] ?? '') === $malicioso, 'el JSON-LD no cierra el <script> y conserva el nombre');
} finally {
    $st = $db->prepare("UPDATE repuestos SET nombre = ? WHERE id_repuesto = ?");
    $st->bind_param('si', $nombreOriginal, $conStock['id']);
    $st->execute();
    $db->query("UPDATE repuestos SET activo = 1 WHERE id_repuesto = " . (int)$pocas['id']);
}

try {
    echo "\n== Tienda ==\n";
    [, $xp] = pagina($s, '/index.php?modelo=swift');
    $tarjetas = $xp->query("//div[contains(@class,'producto-card')]");
    $sinEnlace = 0;
    foreach ($tarjetas as $t) {
        if (texto($xp, ".//h3/a/@href", $t) === '' || $xp->query(".//h3/a[@href='producto.php?id=" . (int)$t->getAttribute('data-producto-id') . "']", $t)->length !== 1) {
            $sinEnlace++;
        }
    }
    revisar($tarjetas->length > 0 && $sinEnlace === 0, "las {$tarjetas->length} tarjetas de ?modelo=swift enlazan a su ficha", "$sinEnlace sin enlace");

    echo "\n== Navegador ==\n";
    if (!navegadorRuta()) {
        revisar(false, 'Edge o Chrome instalado');
    } else {
        $r = navegadorResultado($s, "/tests/producto_ficha_prueba.html?id={$conStock['id']}");
        revisar($r !== null, 'la página de prueba entregó resultado');
        if ($r) {
            revisar($r['angostoSinScroll'] && $r['anchoSinScroll'], 'sin scroll horizontal a 360 y 1366 px');
            revisar($r['angostoColumnas'] === 1 && $r['anchoColumnas'] === 2, 'una columna a 360 px y dos a 1366 px', "{$r['angostoColumnas']} y {$r['anchoColumnas']}");
            revisar($r['chatAbierto'] && $r['chatTexto'] === $r['preguntaEsperada'] && $r['chatFoco'], '"Preguntar al asistente" abre el chat con la consulta escrita', $r['chatTexto']);
            revisar($r['botonAlto'] >= 44, 'botón del asistente de 44 px o más', (string)$r['botonAlto']);
            revisar(($r['carrito'] ?? []) === ["{$conStock['id']}x1"], '"Añadir al carrito" agrega 1 unidad', json_encode($r['carrito'] ?? null));
        }
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
