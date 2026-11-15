<?php
/**
 * Pruebas de la herramienta buscar_productos tal como la usa el chat (F1-3):
 * chatValidarBusqueda() y chatEjecutarBusquedaProductos() de backend/chat_helpers.php,
 * contra MariaDB real. Corre en una transacción que se deshace: la base queda igual.
 *
 * Requisitos: MariaDB encendido y una base cargada desde BD_v2 (01 a 06).
 * Uso: php tests/chat_herramienta_test.php [base]   (por defecto gmg_local; termina en "TODO OK")
 */
require __DIR__ . '/../backend/datos.php';
require __DIR__ . '/../backend/chat_helpers.php';

$base = $argv[1] ?? 'gmg_local';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion = new mysqli('localhost', 'root', '', $base);
$conexion->set_charset('utf8mb4');

$fallas = 0;
function revisar(bool $ok, string $nombre, string $detalle = ''): void {
    global $fallas;
    if (!$ok) {
        $fallas++;
    }
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . ($detalle !== '' ? "  ($detalle)" : '') . "\n";
}

$firmaStock = fn() => $conexion->query("SELECT CONCAT(COUNT(*), '/', SUM(stock), '/', SUM(stock * id_repuesto)) FROM repuestos")->fetch_row()[0];
$stockAntes = $firmaStock();
$conexion->begin_transaction();
try {
    // Stock de prueba: 1 de cada 4 en 0, uno con 2 unidades (se deshace al final).
    $conexion->query("UPDATE repuestos SET stock = IF(id_repuesto % 4 = 0, 0, 5)");
    $conexion->query("UPDATE repuestos SET stock = 2 WHERE sku = 'FRE-SWI3-PDL'");

    echo "== Validación antes de buscar ==\n";
    $v = fn(array $a) => chatValidarBusqueda($a, $conexion);
    revisar(strpos((string)$v(['texto' => 'pastillas']), 'falta el MODELO y el AÑO') !== false, 'sin modelo ni año: pide los dos');
    revisar(strpos((string)$v(['modelo' => 'Swift', 'texto' => 'pastillas']), 'falta el AÑO') !== false, 'sin año: pide el año');
    revisar($v(['modelo' => 'Swift', 'anio_desconocido' => true, 'texto' => 'pastillas']) === null, 'año desconocido declarado: deja buscar');
    revisar($v(['sku_o_codigo_oem' => 'FRE-SWI3-PDL']) === null, 'con SKU no pide vehículo');
    revisar($v(['modelo' => 'Suzuki Swift', 'anio' => 2019]) === null, '"Suzuki Swift" se reconoce');
    revisar($v(['modelo' => 'spresso', 'anio' => 2021]) === null, '"spresso" se reconoce como S-Presso');
    revisar($v(['modelo' => 'Grand Vitara', 'anio' => 2010]) === null, 'Grand Vitara se reconoce');
    $aviso = (string)$v(['modelo' => 'Corolla', 'anio' => 2018, 'texto' => 'pastillas']);
    revisar(strpos($aviso, 'no hay repuestos cargados para el modelo "Corolla"') !== false && strpos($aviso, 'Swift') !== false,
            'modelo inexistente: aviso con la lista de modelos');
    revisar(strpos((string)$v(['modelo' => 'Swift Sport', 'anio' => 2019]), 'versión de uno de estos') !== false,
            '"Swift Sport": aviso que sugiere buscar como Swift');

    echo "\n== Búsqueda (primer resultado y datos de cada fila) ==\n";
    $casos = [
        'EV-01 óptica delantera derecha Swift 2019' => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'óptica delantera derecha'], 'CAR-SWI3-OPD'],
        'EV-02 terminal de dirección Swift 2014'    => [['modelo' => 'Swift', 'anio' => 2014, 'texto' => 'terminal de dirección'], 'DIR-SWI2-TER'],
        'EV-03 alternador Swift Boosterjet 2018'    => [['modelo' => 'Swift', 'anio' => 2018, 'texto' => 'alternador boosterjet'], 'ELE-K10CT-ALT'],
        'EV-04 óptica Swift 2010 (no hay)'          => [['modelo' => 'Swift', 'anio' => 2010, 'texto' => 'óptica'], null],
        'EV-06 balatas Alto 2015'                   => [['modelo' => 'Alto', 'anio' => 2015, 'texto' => 'balatas'], 'FRE-ALTO'],
        'EV-07 SKU exacto'                          => [['sku_o_codigo_oem' => 'CAR-SWI3-OPD'], 'CAR-SWI3-OPD'],
        'CP-28 filtros de aceite Celerio 2018'      => [['modelo' => 'Celerio', 'anio' => 2018, 'texto' => 'filtros de aceite'], 'FIL-'],
        'EV-10 filtro de aire Baleno 2020'          => [['modelo' => 'Baleno', 'anio' => 2020, 'texto' => 'filtro de aire'], 'FIL-'],
        'categoría Frenos + "pastillas" Swift 2019' => [['modelo' => 'Swift', 'anio' => 2019, 'categoria' => 'Frenos', 'texto' => 'pastillas'], 'FRE-SWI3-PDL'],
        'foco delantero Swift 2019 (sinónimo)'      => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'foco delantero'], 'CAR-SWI3-OP'],
        'pastillas de freno Swift 2019'             => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'pastillas de freno'], 'FRE-SWI3-PDL'],
        'Grand Vitara no trae repuestos de Vitara'  => [['modelo' => 'Grand Vitara', 'anio' => 2010, 'texto' => 'radiador'], 'REF-GV3'],
    ];
    $columnas = ['id', 'nombre', 'modelo', 'ano', 'motor', 'compatible_con', 'categoria', 'sku', 'codigo_oem', 'precio', 'stock', 'imagen_url', 'disponibilidad'];
    foreach ($casos as $nombre => [$args, $esperado]) {
        $filas = chatEjecutarBusquedaProductos($conexion, $args);
        $primero = $filas[0]['sku'] ?? 'sin resultados';
        $ok = $esperado === null ? $filas === [] : strpos($primero, $esperado) === 0;
        $columnasOk = $filas === [] || array_keys($filas[0]) === $columnas;
        revisar($ok && $columnasOk && count($filas) <= 8, sprintf('%-44s', $nombre), count($filas) . " res., 1ro: $primero" . ($columnasOk ? '' : ', COLUMNAS DISTINTAS'));
    }

    echo "\n== Disponibilidad, precio y orden ==\n";
    $filas = chatEjecutarBusquedaProductos($conexion, ['modelo' => 'Swift', 'anio_desconocido' => true, 'texto' => 'pastillas de freno']);
    $bien = true;
    foreach ($filas as $f) {
        $bien = $bien && $f['disponibilidad'] === chatDisponibilidad((int)$f['stock']);
    }
    revisar($filas !== [] && $bien, 'cada fila trae la disponibilidad que corresponde a su stock', count($filas) . ' filas');
    $pdl = array_values(array_filter($filas, fn($f) => $f['sku'] === 'FRE-SWI3-PDL'))[0] ?? null;
    revisar($pdl !== null && $pdl['disponibilidad'] === 'Últimas unidades', 'stock 2 se informa como "Últimas unidades"');
    $sinStock = $conexion->query("SELECT sku FROM repuestos WHERE stock = 0 AND activo = 1 LIMIT 1")->fetch_row()[0];
    $f0 = chatEjecutarBusquedaProductos($conexion, ['sku_o_codigo_oem' => $sinStock]);
    revisar(($f0[0]['disponibilidad'] ?? '') === 'Sin stock', 'stock 0 se informa como "Sin stock"', $sinStock);
    $baratos = chatEjecutarBusquedaProductos($conexion, ['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'filtro', 'precio_maximo' => 6000]);
    $topeOk = $baratos !== [] && max(array_map(fn($f) => (float)$f['precio'], $baratos)) <= 6000;
    revisar($topeOk, 'precio_maximo deja solo repuestos bajo el tope', count($baratos) . ' filas');
    $todos = chatEjecutarBusquedaProductos($conexion, ['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'filtro', 'precio_maximo' => 0]);
    revisar(count($todos) >= count($baratos), 'precio_maximo 0 se ignora (no vacía la búsqueda)');

    echo "\n== Seguridad ==\n";
    $iny = chatEjecutarBusquedaProductos($conexion, ['modelo' => 'Swift', 'anio' => 2019, 'texto' => "x' OR '1'='1"]);
    revisar(count($iny) <= 8, 'texto con comillas no rompe la consulta ni trae todo el catálogo', count($iny) . ' filas');
    $iny2 = chatEjecutarBusquedaProductos($conexion, ['sku_o_codigo_oem' => "' UNION SELECT password_hash FROM administradores -- "]);
    revisar($iny2 === [], 'intento de UNION en el SKU no devuelve nada');
    revisar(strpos(chatUltimaConsultaSQL(), 'password_hash') === false, 'el texto del cliente nunca entra al SQL (va como parámetro)');
} finally {
    $conexion->rollback();
}

echo "\n";
revisar($firmaStock() === $stockAntes, 'la base quedó igual (transacción deshecha)', 'stock antes y después: ' . $stockAntes);
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
