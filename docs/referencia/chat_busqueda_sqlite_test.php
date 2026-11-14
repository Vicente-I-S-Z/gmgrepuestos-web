<?php
/**
 * Prueba de la consulta de chatConsultaBusqueda() contra el catálogo real
 * (BD_v2/catalogo_repuestos.csv) cargado en SQLite. SQLite no ignora tildes
 * como utf8mb4_unicode_ci, así que los textos se guardan sin tildes para
 * imitar esa intercalación.
 *
 * Uso: php docs/referencia/chat_busqueda_sqlite_test.php BD_v2/catalogo_repuestos.csv
 * (el stock se inventa: 1 de cada 4 repuestos queda en 0)
 */
require __DIR__ . '/chat_busqueda_ref.php';

$csv = $argv[1] ?? __DIR__ . '/../../BD_v2/catalogo_repuestos.csv';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE categorias (id_categoria INTEGER PRIMARY KEY, nombre TEXT);
CREATE TABLE repuestos (id_repuesto INTEGER PRIMARY KEY, id_categoria INT, sku TEXT, nombre TEXT, descripcion TEXT, codigo_oem TEXT, precio REAL, stock INT, activo INT);
CREATE TABLE modelos (id_modelo INTEGER PRIMARY KEY, nombre TEXT, slug TEXT);
CREATE TABLE vehiculos (id_vehiculo INTEGER PRIMARY KEY, id_modelo INT, anio_desde INT, anio_hasta INT);
CREATE TABLE compatibilidades (id_repuesto INT, id_vehiculo INT);");

$sinTildes = function ($t) { return strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','ª'=>'a']); };
$cats = []; $modelos = []; $vehiculos = [];
$fh = fopen($csv, 'r');
$cab = fgetcsv($fh, 0, ';');
$cab[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cab[0]);
$id = 0;
while (($f = fgetcsv($fh, 0, ';')) !== false) {
    $r = array_combine($cab, $f);
    $id++;
    $cat = $sinTildes($r['categoria']);
    if (!isset($cats[$cat])) { $cats[$cat] = count($cats) + 1; $db->prepare('INSERT INTO categorias VALUES (?,?)')->execute([$cats[$cat], $cat]); }
    $stock = $id % 4 === 0 ? 0 : 5; // stock de prueba
    $db->prepare('INSERT INTO repuestos VALUES (?,?,?,?,?,?,?,?,1)')->execute([$id, $cats[$cat], $r['sku'], $sinTildes($r['nombre']), '', null, (float)$r['precio_referencial_clp'], $stock]);
    foreach (explode('|', $r['vehiculos_compatibles']) as $v) {
        if (!preg_match('/^\s*(.+?)\s+\d\.\d.*\((\d{4})-(\d{4}|hoy)\)\s*$/u', $v, $m)) { continue; }
        $nombreModelo = $m[1];
        $slug = strtolower(str_replace(' ', '-', $nombreModelo));
        if (!isset($modelos[$slug])) { $modelos[$slug] = count($modelos) + 1; $db->prepare('INSERT INTO modelos VALUES (?,?,?)')->execute([$modelos[$slug], $nombreModelo, $slug]); }
        $claveV = $slug . trim($v);
        if (!isset($vehiculos[$claveV])) { $vehiculos[$claveV] = count($vehiculos) + 1; $db->prepare('INSERT INTO vehiculos VALUES (?,?,?,?)')->execute([$vehiculos[$claveV], $modelos[$slug], (int)$m[2], $m[3] === 'hoy' ? null : (int)$m[3]]); }
        $db->prepare('INSERT INTO compatibilidades VALUES (?,?)')->execute([$id, $vehiculos[$claveV]]);
    }
}
echo "Cargados: $id repuestos, " . count($modelos) . " modelos (" . implode(', ', array_keys($modelos)) . "), " . count($vehiculos) . " vehiculos\n\n";

function buscar(PDO $db, array $args) {
    foreach ([true, false] as $estricto) {
        [$sql, $tipos, $params] = chatConsultaBusqueda($args, 8, $estricto);
        $st = $db->prepare($sql);
        $st->execute($params);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $filas = $db->query("SELECT sku, nombre, stock FROM repuestos WHERE id_repuesto IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
            $porId = [];
            foreach ($db->query("SELECT id_repuesto, sku, nombre, stock FROM repuestos WHERE id_repuesto IN ($in)") as $f) { $porId[$f['id_repuesto']] = $f; }
            return [$estricto ? 'AND' : 'OR', array_map(fn($i) => $porId[$i]['sku'] . ' ' . $porId[$i]['nombre'], $ids)];
        }
    }
    return ['-', []];
}

$casos = [
    'EV-01 optica delantera derecha Swift 2019' => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'óptica delantera derecha'], 'CAR-SWI3-OPD'],
    'EV-02 terminal de direccion Swift 2014'   => [['modelo' => 'Swift', 'anio' => 2014, 'texto' => 'terminal de dirección'], 'DIR-SWI2-TER'],
    'EV-03 alternador Swift Boosterjet 2018'    => [['modelo' => 'Swift', 'anio' => 2018, 'texto' => 'alternador boosterjet'], 'ELE-K10CT-ALT'],
    'EV-04 optica Swift 2010 (no hay)'          => [['modelo' => 'Swift', 'anio' => 2010, 'texto' => 'óptica'], null],
    'EV-06 balatas Alto 2015'                   => [['modelo' => 'Alto', 'anio' => 2015, 'texto' => 'balatas'], 'FRE-ALTO'],
    'EV-07 SKU exacto'                          => [['sku_o_codigo_oem' => 'CAR-SWI3-OPD'], 'CAR-SWI3-OPD'],
    'CP-28 filtros de aceite Celerio 2018'      => [['modelo' => 'Celerio', 'anio' => 2018, 'texto' => 'filtros de aceite'], 'FIL-'],
    'EV-10 filtro de aire Baleno 2020'          => [['modelo' => 'Baleno', 'anio' => 2020, 'texto' => 'filtro de aire'], 'FIL-'],
    'S-Presso escrito "spresso" bujias 2021'    => [['modelo' => 'spresso', 'anio' => 2021, 'texto' => 'bujías'], 'ENC-'],
    'foco delantero Swift 2019 (sinonimo)'      => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'foco delantero'], 'CAR-SWI3-OP'],
    'pastillas de freno Swift 2019'             => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'pastillas de freno'], 'FRE-SWI3-PDL'],
    'Grand Vitara no trae repuestos de Vitara'  => [['modelo' => 'Grand Vitara', 'anio' => 2010, 'texto' => 'radiador'], 'REF-GV3'],
];
$fallas = 0;
foreach ($casos as $nombre => [$args, $esperado]) {
    [$modo, $res] = buscar($db, $args);
    $primero = $res[0] ?? '(sin resultados)';
    $ok = $esperado === null ? empty($res) : (strpos($res[0] ?? '', $esperado) !== false); // exige el 1er lugar
    if (!$ok) $fallas++;
    printf("%s %-45s [%s] %d res. 1ro: %s\n", $ok ? 'ok  ' : 'FALLA', $nombre, $modo, count($res), $primero);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
