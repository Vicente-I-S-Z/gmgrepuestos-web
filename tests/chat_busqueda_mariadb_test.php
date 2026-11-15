<?php
/**
 * Pruebas de backend/chat_busqueda.php y gmgLimiteSuperado() contra MariaDB real
 * (la misma intercalación utf8mb4_unicode_ci de producción, con tildes).
 *
 * 1. Las 12 consultas del set EV/CP: el producto esperado debe salir primero.
 * 2. chatHistorialDesdeBD() y chatIdsMostrados() con una conversación de prueba.
 * 3. gmgLimiteSuperado(): 15 por minuto, el 16 se bloquea, por acción e IP, y la ventana se reinicia.
 *
 * Todo corre dentro de una transacción que se deshace al final: la base queda igual.
 * Requisitos: MariaDB encendido y una base cargada desde BD_v2 (01 a 06).
 * Uso: php tests/chat_busqueda_mariadb_test.php [base]   (por defecto gmg_local; termina en "TODO OK")
 */
require __DIR__ . '/../backend/chat_busqueda.php';
require __DIR__ . '/../backend/seguridad.php';

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

/** Mismo flujo que docs/FIX_ASISTENTE_IA.md 4.4: primero todas las palabras, después basta una. */
function buscar(mysqli $conexion, array $args): array {
    foreach ([true, false] as $estricto) {
        [$sql, $tipos, $params] = chatConsultaBusqueda($args, 8, $estricto);
        $stmt = $conexion->prepare($sql);
        if ($tipos !== '') {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();
        $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $stmt->close();
        if ($ids) {
            $lista = implode(',', array_map('intval', $ids));
            $skus = $conexion->query("SELECT sku FROM repuestos WHERE id_repuesto IN ($lista) ORDER BY FIELD(id_repuesto, $lista)")
                             ->fetch_all(MYSQLI_NUM);
            return [$estricto ? 'AND' : 'OR', array_column($skus, 0)];
        }
    }
    return ['-', []];
}

$conexion->begin_transaction();
try {
    $total = (int)$conexion->query("SELECT COUNT(*) FROM repuestos WHERE activo = 1")->fetch_row()[0];
    echo "Base: $base ($total repuestos activos)\n\n== 1. Búsqueda (el esperado debe salir primero) ==\n";
    // Mismo stock de prueba que la prueba con SQLite: 1 de cada 4 en 0 (se deshace al final).
    $conexion->query("UPDATE repuestos SET stock = IF(id_repuesto % 4 = 0, 0, 5)");

    $casos = [
        'EV-01 óptica delantera derecha Swift 2019' => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'óptica delantera derecha'], 'CAR-SWI3-OPD'],
        'EV-02 terminal de dirección Swift 2014'    => [['modelo' => 'Swift', 'anio' => 2014, 'texto' => 'terminal de dirección'], 'DIR-SWI2-TER'],
        'EV-03 alternador Swift Boosterjet 2018'    => [['modelo' => 'Swift', 'anio' => 2018, 'texto' => 'alternador boosterjet'], 'ELE-K10CT-ALT'],
        'EV-04 óptica Swift 2010 (no hay)'          => [['modelo' => 'Swift', 'anio' => 2010, 'texto' => 'óptica'], null],
        'EV-06 balatas Alto 2015'                   => [['modelo' => 'Alto', 'anio' => 2015, 'texto' => 'balatas'], 'FRE-ALTO'],
        'EV-07 SKU exacto'                          => [['sku_o_codigo_oem' => 'CAR-SWI3-OPD'], 'CAR-SWI3-OPD'],
        'CP-28 filtros de aceite Celerio 2018'      => [['modelo' => 'Celerio', 'anio' => 2018, 'texto' => 'filtros de aceite'], 'FIL-'],
        'EV-10 filtro de aire Baleno 2020'          => [['modelo' => 'Baleno', 'anio' => 2020, 'texto' => 'filtro de aire'], 'FIL-'],
        'S-Presso escrito "spresso", bujías 2021'   => [['modelo' => 'spresso', 'anio' => 2021, 'texto' => 'bujías'], 'ENC-'],
        'foco delantero Swift 2019 (sinónimo)'      => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'foco delantero'], 'CAR-SWI3-OP'],
        'pastillas de freno Swift 2019'             => [['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'pastillas de freno'], 'FRE-SWI3-PDL'],
        'Grand Vitara no trae repuestos de Vitara'  => [['modelo' => 'Grand Vitara', 'anio' => 2010, 'texto' => 'radiador'], 'REF-GV3'],
    ];
    foreach ($casos as $nombre => [$args, $esperado]) {
        [$modo, $skus] = buscar($conexion, $args);
        $ok = $esperado === null ? $skus === [] : strpos($skus[0] ?? '', $esperado) === 0;
        revisar($ok, sprintf('%-44s', $nombre), "[$modo] " . count($skus) . ' res., 1ro: ' . ($skus[0] ?? 'sin resultados'));
    }

    echo "\n== 2. Historial desde la base y productos mostrados ==\n";
    $p = $conexion->query("SELECT id_repuesto, sku FROM repuestos WHERE sku IN ('FIL-BAL2-K14B-AIR', 'FRE-SWI3-PDL') ORDER BY sku")->fetch_all(MYSQLI_ASSOC);
    revisar(count($p) === 2, 'repuestos de prueba encontrados');
    $conexion->query("INSERT INTO conversaciones_chat (sesion_id) VALUES ('prueba-mariadb-f12')");
    $idConv = (int)$conexion->insert_id;
    $mensajes = [
        ['user', 'Tengo un Baleno 2020, ¿tienen filtro de aire?'],
        ['bot', 'Tengo estas 2 opciones para tu Baleno 2020.'],
        ['user', 'Agrega el segundo'],
    ];
    $idsMensaje = [];
    $stmt = $conexion->prepare("INSERT INTO mensajes_chat (id_conversacion, rol, contenido) VALUES (?, ?, ?)");
    foreach ($mensajes as [$rol, $texto]) {
        $stmt->bind_param('iss', $idConv, $rol, $texto);
        $stmt->execute();
        $idsMensaje[] = (int)$conexion->insert_id;
    }
    $stmt->close();
    $stmt = $conexion->prepare("INSERT INTO mensaje_repuestos (id_mensaje, id_repuesto, orden) VALUES (?, ?, ?)");
    foreach ($p as $i => $fila) {
        $orden = $i + 1;
        $idRep = (int)$fila['id_repuesto'];
        $stmt->bind_param('iii', $idsMensaje[1], $idRep, $orden);
        $stmt->execute();
    }
    $stmt->close();

    $hist = chatHistorialDesdeBD($conexion, $idConv);
    revisar(count($hist) === 3, 'historial trae los 3 mensajes', count($hist) . ' mensajes');
    revisar(array_column($hist, 'role') === ['user', 'assistant', 'user'], 'orden cronológico y roles user/assistant');
    $esperadoLinea = '2) id ' . $p[1]['id_repuesto'] . ', SKU ' . $p[1]['sku'];
    revisar(strpos($hist[1]['content'], '[Productos mostrados en esta respuesta: 1) id ' . $p[0]['id_repuesto']) !== false
            && strpos($hist[1]['content'], $esperadoLinea) !== false, 'la respuesta del bot numera sus productos con id y SKU', $esperadoLinea);
    revisar(mb_strpos($hist[0]['content'], '¿tienen filtro') !== false, 'texto con tildes y signos intacto');
    $ultimos = chatHistorialDesdeBD($conexion, $idConv, 2);
    revisar(count($ultimos) === 2 && $ultimos[1]['content'] === 'Agrega el segundo', 'límite de mensajes toma los más recientes');
    $ids = chatIdsMostrados($conexion, $idConv);
    sort($ids);
    $esperadosIds = array_map('intval', array_column($p, 'id_repuesto'));
    sort($esperadosIds);
    revisar($ids === $esperadosIds, 'chatIdsMostrados devuelve solo lo que el bot mostró', implode(',', $ids));
    revisar(chatIdsMostrados($conexion, $idConv + 999999) === [], 'otra conversación no ve esos productos');

    echo "\n== 3. Límite por IP (15 por minuto) ==\n";
    $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    $bloqueos = [];
    for ($i = 1; $i <= 16; $i++) {
        $bloqueos[] = gmgLimiteSuperado($conexion, 'chat', 15, 60);
    }
    revisar(!in_array(true, array_slice($bloqueos, 0, 15), true), 'las primeras 15 solicitudes pasan');
    revisar($bloqueos[15] === true, 'la solicitud 16 se bloquea');
    revisar(gmgLimiteSuperado($conexion, 'codigo', 15, 60) === false, 'otra acción tiene su propio contador');
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    revisar(gmgLimiteSuperado($conexion, 'chat', 15, 60) === false, 'otra IP tiene su propio contador');
    $claves = array_column($conexion->query("SELECT clave FROM limites_solicitudes")->fetch_all(MYSQLI_ASSOC), 'clave');
    revisar(!preg_grep('/203\.0\.113\.7|198\.51\.100\.9/', $claves), 'la tabla guarda el hash, nunca la IP');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    $conexion->query("UPDATE limites_solicitudes SET ventana_inicio = ventana_inicio - 61 WHERE clave LIKE 'chat:%'");
    revisar(gmgLimiteSuperado($conexion, 'chat', 15, 60) === false, 'pasado el minuto, la ventana se reinicia');
    $contador = (int)$conexion->query("SELECT contador FROM limites_solicitudes WHERE clave = 'chat:" . hash('sha256', '203.0.113.7') . "'")->fetch_row()[0];
    revisar($contador === 1, 'el contador vuelve a 1', "contador $contador");
} finally {
    $conexion->rollback();
}

$residuo = (int)$conexion->query("SELECT COUNT(*) FROM conversaciones_chat WHERE sesion_id = 'prueba-mariadb-f12'")->fetch_row()[0]
         + (int)$conexion->query("SELECT COUNT(*) FROM limites_solicitudes WHERE clave LIKE 'chat:%' OR clave LIKE 'codigo:%'")->fetch_row()[0];
echo "\n";
revisar($residuo === 0, 'la base quedó igual (transacción deshecha)');
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
