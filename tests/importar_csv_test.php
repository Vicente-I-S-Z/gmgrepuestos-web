<?php
/**
 * F4-5 (E6-H8, RF-057): importar el catálogo desde CSV en el panel.
 *   - "Solo revisar" el catálogo real (538 filas) no cambia nada y cuenta nuevos y actualizados;
 *   - importar actualiza precio y stock (con auditoría) y crea un SKU nuevo con sus vehículos;
 *   - un archivo con errores muestra cada fila con su motivo y no guarda ninguna (todo o nada);
 *   - un CSV guardado por Excel en Windows (Windows-1252) también se lee bien.
 * Base temporal desde BD_v2 (tests/chat_entorno.php), con login real y CSRF.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/importar_csv_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('importar');
$cookies = $e['tmp'] . '/cookies.txt';
function panel(array $e, string $ruta, $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post !== null) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]); }
    $r = (string)curl_exec($ch);
    curl_close($ch);
    return $r;
}
function importar(array $e, string $archivo, bool $soloRevisar = false): array {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', panel($e, '/admin/importar.php'), $m);
    $post = ['csrf' => $m[1] ?? '', 'csv' => new CURLFile($archivo, 'text/csv', 'catalogo.csv')];
    if ($soloRevisar) { $post['solo_revisar'] = '1'; }
    $t = microtime(true);
    $html = panel($e, '/admin/importar.php', $post);
    preg_match('/class="flash-msg[^"]*">([^<]*)</', $html, $a);
    preg_match_all('~<li>([^<]*)</li>~', $html, $errores);
    return ['msg' => html_entity_decode($a[1] ?? '', ENT_QUOTES), 'errores' => array_map(function ($x) { return html_entity_decode($x, ENT_QUOTES); }, $errores[1]), 'seg' => microtime(true) - $t];
}
function valor(array $e, string $sql) {
    $f = $e['db']->query($sql)->fetch_row();
    return $f ? $f[0] : null;
}
try {
    $hash = $e['db']->real_escape_string(password_hash('Clave-F45', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo, rol) VALUES ('prueba_f45', 'Prueba', '$hash', 1, 'admin')");
    panel($e, '/login.php', http_build_query(['usuario' => 'prueba_f45', 'password' => 'Clave-F45']));
    $original = (string)file_get_contents($e['raiz'] . '/BD_v2/catalogo_repuestos.csv');
    $total = (int)valor($e, "SELECT COUNT(*) FROM repuestos");
    $foto = valor($e, "SELECT COUNT(*) FROM repuesto_imagenes");

    echo "== Solo revisar ==\n";
    file_put_contents("$e[tmp]/real.csv", $original);
    $r = importar($e, "$e[tmp]/real.csv", true);
    revisar(strpos($r['msg'], 'se crearían 0 y se actualizarían 538') !== false && (int)valor($e, "SELECT COUNT(*) FROM repuestos") === $total, 'el catálogo real (538 filas) es válido y no cambia nada', $r['msg']);

    echo "\n== Importar ==\n";
    $lineas = explode("\n", $original);
    $columnas = str_getcsv($lineas[1], ';');
    $skuCambiado = $columnas[0];
    $columnas[5] = '77777';
    $columnas[6] = '9';
    $lineas[1] = implode(';', $columnas);
    $vehiculo = valor($e, "SELECT descripcion FROM vw_vehiculos WHERE modelo = 'Swift' LIMIT 1");
    $lineas[] = "PRU-NUEVO-01;Frenos;Repuesto nuevo de prueba;$vehiculo;1;12345;4;OEM-123";
    file_put_contents("$e[tmp]/cambios.csv", implode("\n", $lineas));
    $r = importar($e, "$e[tmp]/cambios.csv");
    revisar(strpos($r['msg'], '1 repuestos nuevos y 538 actualizados') !== false, 'importa: 1 nuevo y 538 actualizados', $r['msg'] . sprintf(' (%.1f s)', $r['seg']));
    revisar((float)valor($e, "SELECT precio FROM repuestos WHERE sku = '$skuCambiado'") === 77777.0 && (int)valor($e, "SELECT stock FROM repuestos WHERE sku = '$skuCambiado'") === 9, 'actualiza precio y stock');
    $nuevo = (int)valor($e, "SELECT id_repuesto FROM repuestos WHERE sku = 'PRU-NUEVO-01'");
    revisar($nuevo > 0 && (int)valor($e, "SELECT COUNT(*) FROM compatibilidades WHERE id_repuesto = $nuevo") === 1 && valor($e, "SELECT codigo_oem FROM repuestos WHERE id_repuesto = $nuevo") === 'OEM-123'
            && (int)valor($e, "SELECT COUNT(*) FROM repuesto_imagenes WHERE id_repuesto = $nuevo") === 1, 'crea el nuevo con su vehículo, OEM y foto genérica');
    revisar((int)valor($e, "SELECT COUNT(*) FROM auditoria WHERE accion = 'precio'") >= 1 && (int)valor($e, "SELECT COUNT(*) FROM auditoria WHERE accion = 'catalogo_importado'") === 1, 'queda en auditoría');
    revisar((int)valor($e, "SELECT COUNT(*) FROM repuesto_imagenes") === $foto + 1, 'las fotos de los existentes no se tocan');

    echo "\n== Errores: todo o nada ==\n";
    $precioAntes = valor($e, "SELECT precio FROM repuestos WHERE sku = '$skuCambiado'");
    $malo = $lineas;
    $malo[2] = 'PRU-X;Categoría Inventada;Algo;;0;1000;1;';
    $malo[3] = "PRU-Y;Frenos;Algo;Swift 9.9 ZZZ (1900-1901);1;1000;1;";
    $malo[4] = 'PRU-NUEVO-01;Frenos;Repetido;;0;1.500;-1;';
    $malo[5] = 'solo;dos';
    file_put_contents("$e[tmp]/malo.csv", implode("\n", $malo));
    $r = importar($e, "$e[tmp]/malo.csv");
    $texto = implode(' / ', $r['errores']);
    revisar(strpos($r['msg'], 'No se guardó nada') !== false && count($r['errores']) === 5, '5 filas con error (el SKU repetido cuenta en su segunda aparición): se informan y no se guarda nada', $r['msg']);
    revisar(strpos($texto, 'Fila 3 (PRU-X): la categoría "Categoría Inventada" no existe') !== false && strpos($texto, 'el vehículo "Swift 9.9 ZZZ (1900-1901)" no existe') !== false
            && strpos($texto, 'SKU repetido') !== false && strpos($texto, 'precio debe ser un número entero') !== false && strpos($texto, 'Fila 6: tiene 2 columnas') !== false, 'cada error dice la fila y el motivo');
    revisar(valor($e, "SELECT precio FROM repuestos WHERE sku = '$skuCambiado'") === $precioAntes && !valor($e, "SELECT 1 FROM repuestos WHERE sku IN ('PRU-X','PRU-Y')"), 'ninguna fila válida del archivo se guardó');
    file_put_contents("$e[tmp]/encabezado.csv", "codigo;nombre\nA;B");
    revisar(strpos(implode(' ', importar($e, "$e[tmp]/encabezado.csv")['errores']), 'el encabezado debe ser exactamente') !== false, 'un encabezado distinto se rechaza');

    echo "\n== Ida y vuelta con Reportes ==\n";
    file_put_contents("$e[tmp]/exportado.csv", panel($e, '/admin/reportes.php?descargar=productos'));
    $r = importar($e, "$e[tmp]/exportado.csv", true);
    $repuestos = (int)valor($e, "SELECT COUNT(*) FROM repuestos");
    revisar($r['errores'] === [] && strpos($r['msg'], "se crearían 0 y se actualizarían $repuestos") !== false, 'el CSV de Reportes se vuelve a subir sin errores', $r['msg']);

    echo "\n== Excel en Windows ==\n";
    $ansi = mb_convert_encoding("sku;categoria;nombre;vehiculos_compatibles;n_vehiculos;precio_referencial_clp;stock;codigo_oem\nPRU-ANSI-1;Frenos;Pastilla Ñandú económica;;0;5000;2;", 'Windows-1252', 'UTF-8');
    file_put_contents("$e[tmp]/ansi.csv", $ansi);
    $r = importar($e, "$e[tmp]/ansi.csv");
    revisar(valor($e, "SELECT nombre FROM repuestos WHERE sku = 'PRU-ANSI-1'") === 'Pastilla Ñandú económica', 'un CSV en Windows-1252 se lee con sus tildes y eñes', $r['msg']);
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
