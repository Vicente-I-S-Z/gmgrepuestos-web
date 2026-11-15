<?php
/**
 * F2-1 (E2-H6, E2-H1, RF-013): disponibilidad en las tarjetas de la tienda.
 * Recorre listados reales de index.php (php -S, puerto 8089, base gmg_local, sin leer el .env)
 * y compara cada tarjeta con el stock de ese repuesto en la base:
 *   - etiqueta "Disponible", "Últimas unidades" (3 o menos) o "Sin stock" (la de chatDisponibilidad);
 *   - con stock: "Añadir al carrito" y "Comprar ahora" activos y con su data-id;
 *   - sin stock: un solo botón deshabilitado "Sin stock", sin "Comprar ahora".
 * Requisitos: MariaDB encendido con gmg_local (con stock variado para ver los 3 estados).
 * Uso: php tests/tienda_disponibilidad_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/../backend/chat_busqueda.php';

$raiz = dirname(__DIR__);
$sitio = 'http://localhost:8089';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gmg_disp_' . uniqid();
mkdir($tmp);
file_put_contents("$tmp/config.txt", "APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME=gmg_local\n");

$fallas = 0;
function revisar(bool $ok, string $nombre, string $detalle = ''): void {
    global $fallas;
    if (!$ok) {
        $fallas++;
    }
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . ($detalle !== '' ? "  ($detalle)" : '') . "\n";
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'gmg_local');
$stock = array_column($db->query("SELECT id_repuesto, stock FROM repuestos")->fetch_all(MYSQLI_ASSOC), 'stock', 'id_repuesto');

$servidor = proc_open([PHP_BINARY, '-d', "error_log=$tmp/php.log", '-S', 'localhost:8089', '-t', $raiz],
                      [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $tuberias, null, getenv() + ['GMG_ENV_FILE' => "$tmp/config.txt"]);
try {
    for ($i = 0; $i < 40 && @file_get_contents("$sitio/login.php") === false; $i++) {
        usleep(250000);
    }
    $estados = [];
    foreach (['index.php?modelo=swift', 'index.php?q=filtro', 'index.php?categoria=Frenos'] as $pagina) {
        // El listado va paginado (F2-6): se recorren todas las páginas siguiendo rel="next".
        $tarjetas = [];
        $xps = [];
        for ($url = "$sitio/$pagina"; $url !== ''; ) {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8">' . file_get_contents($url));
            $xp = new DOMXPath($dom);
            foreach ($xp->query("//div[contains(@class,'producto-card')]") as $t) {
                $tarjetas[] = $t;
                $xps[] = $xp;
            }
            $siguiente = $xp->query("//a[@rel='next']/@href")->item(0);
            $url = $siguiente ? "$sitio/" . preg_replace('/#.*$/', '', $siguiente->value) : '';
        }
        $malas = [];
        foreach ($tarjetas as $n => $t) {
            $xp = $xps[$n];
            $id = (int)$t->getAttribute('data-producto-id');
            $esperada = chatDisponibilidad((int)($stock[$id] ?? -1));
            $etiqueta = trim((string)($xp->query(".//span[contains(@class,'gmg-disponibilidad')]", $t)->item(0)->textContent ?? ''));
            $activos = $xp->query(".//button[contains(@class,'btn-add-cart') and not(@disabled)]", $t);
            $comprar = $xp->query(".//button[contains(@class,'btn-comprar-ahora')]", $t)->length;
            $deshabilitados = $xp->query(".//button[@disabled]", $t);
            $estados[$etiqueta] = ($estados[$etiqueta] ?? 0) + 1;
            if ($etiqueta !== $esperada) {
                $malas[] = "id $id: dice \"$etiqueta\", stock {$stock[$id]}";
            } elseif ($esperada === 'Sin stock') {
                if ($activos->length !== 0 || $comprar !== 0 || $deshabilitados->length !== 1 || trim($deshabilitados->item(0)->textContent) !== 'Sin stock') {
                    $malas[] = "id $id: agotado con botones de compra";
                }
            } elseif ($activos->length !== 1 || (int)$activos->item(0)->getAttribute('data-id') !== $id || $comprar !== 1 || $deshabilitados->length !== 0) {
                $malas[] = "id $id: con stock pero sin sus botones";
            }
        }
        revisar(count($tarjetas) > 0 && $malas === [], "$pagina: etiqueta y botones correctos en las " . count($tarjetas) . " tarjetas",
                $malas ? implode('; ', array_slice($malas, 0, 3)) : '');
    }
    ksort($estados);
    revisar(count(array_intersect_key($estados, array_flip(['Disponible', 'Últimas unidades', 'Sin stock']))) === 3,
            'aparecen los 3 estados', json_encode($estados, JSON_UNESCAPED_UNICODE));
    $errores = is_file("$tmp/php.log") ? preg_grep('/PHP (Fatal|Warning|Notice|Deprecated)/', file("$tmp/php.log")) : [];
    revisar(!$errores, 'registro de PHP sin errores', $errores ? trim(reset($errores)) : '');
} finally {
    proc_terminate($servidor);
    array_map('unlink', glob("$tmp/*"));
    @rmdir($tmp);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
