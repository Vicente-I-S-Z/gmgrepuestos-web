<?php
/**
 * F4-1 (E8-H2, RF-067): sitemap y robots. El sitemap es XML válido con la portada, los modelos,
 * cada repuesto activo (ninguno inactivo) y las páginas informativas; robots.txt excluye el panel,
 * el backend, el checkout y las búsquedas. (La regla /sitemap.xml del .htaccess la prueba CP-47.)
 * Sitio con php -S (puerto 8100) sobre gmg_local. Requisitos: MariaDB con gmg_local.
 * Uso: php tests/seo_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$activos = (int)$db->query("SELECT COUNT(*) FROM repuestos WHERE activo = 1")->fetch_row()[0];
$inactivo = (int)($db->query("SELECT id_repuesto FROM repuestos WHERE activo = 0 LIMIT 1")->fetch_row()[0] ?? 0);
$s = sitioIniciar(8100);
try {
    $xml = @simplexml_load_string((string)file_get_contents($s['url'] . '/sitemap.php'));
    revisar($xml !== false, 'el sitemap es XML válido');
    $locs = [];
    foreach ($xml ? $xml->url : [] as $u) {
        $locs[] = (string)$u->loc;
    }
    $fichas = preg_grep('~/producto\.php\?id=\d+$~', $locs);
    revisar(in_array($s['url'] . '/', $locs, true) && count(preg_grep('~index\.php\?modelo=~', $locs)) >= 10, 'portada y las páginas de cada modelo');
    revisar(count($fichas) === $activos, "una ficha por cada repuesto activo ($activos)", (string)count($fichas));
    revisar(!$inactivo || !in_array($s['url'] . "/producto.php?id=$inactivo", $locs, true), 'ningún repuesto inactivo');
    revisar(count(preg_grep('~(admin|checkout|login|backend|\?q=)~', $locs)) === 0, 'sin panel, checkout, login ni búsquedas');
    $robots = (string)file_get_contents($s['url'] . '/robots.txt');
    revisar(strpos($robots, 'Disallow: /admin/') !== false && strpos($robots, 'Disallow: /backend/') !== false && strpos($robots, 'Disallow: /checkout.php') !== false
            && strpos($robots, 'Sitemap: https://www.gmgrepuestos.cl/sitemap.xml') !== false, 'robots.txt excluye panel, backend y checkout, y anuncia el sitemap');
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
