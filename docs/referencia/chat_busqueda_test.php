<?php
require __DIR__ . '/chat_busqueda_ref.php';

$fallas = 0;
function igual($obtenido, $esperado, $nombre) {
    global $fallas;
    if ($obtenido !== $esperado) {
        $fallas++;
        echo "FALLA $nombre\n  esperado: " . json_encode($esperado, JSON_UNESCAPED_UNICODE) . "\n  obtenido: " . json_encode($obtenido, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "ok   $nombre\n";
    }
}

// Normalización y singular
igual(chatNormalizar('Óptica  DELANTERA, ¿Swift?'), 'optica delantera swift', 'normalizar');
igual(chatSingular('pastillas'), 'pastilla', 'singular -s');
igual(chatSingular('terminales'), 'terminal', 'singular -les');
igual(chatSingular('sensores'), 'sensor', 'singular -res');
igual(chatSingular('luces'), 'luz', 'singular -ces');
igual(chatSingular('bujias'), 'bujia', 'singular bujias');
igual(chatSingular('discos'), 'disco', 'singular discos');
igual(chatSingular('gas'), 'gas', 'palabra corta intacta');
igual(chatRaiz('delantera'), 'delanter', 'raiz sin genero');
igual(chatRaiz('disco'), 'disco', 'raiz corta intacta');

// Grupos de búsqueda (CP-28: sinónimos y plurales)
igual(chatGruposBusqueda('Balatas pal Alto 2015', ['alto']), [['balat', 'pastill']], 'balatas -> balata|pastilla, ignora modelo y año');
igual(chatGruposBusqueda('Filtros de aceite', []), [['filtr'], ['aceite']], 'filtros de aceite');
igual(chatGruposBusqueda('foco delantero derecho', []), [['foco', 'optic'], ['delanter'], ['derech']], 'foco -> optica, sin genero');
igual(chatGruposBusqueda('terminal swift', ['swift']), [['terminal']], 'terminal swift (INC-07)');
igual(chatGruposBusqueda('alternador K10C', []), [['alternador'], ['k10c']], 'codigo de motor');
igual(chatGruposBusqueda('Swift 3ª gen 1.2', ['swift']), [['gen']], 'ignora numeros cortos');

// Modelo
igual(chatModeloClave('Suzuki S-Presso'), 'spresso', 'modelo s-presso');
igual(chatModeloClave('s presso'), 'spresso', 'modelo s presso');
igual(chatModeloClave('Grand Vitara'), 'grandvitara', 'modelo grand vitara');

// Consulta: placeholders y parámetros cuadran
foreach ([
    ['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'óptica delantera derecha'],
    ['sku_o_codigo_oem' => 'CAR-SWI3-OPD'],
    ['modelo' => 'Alto', 'anio' => 2015, 'texto' => 'balatas', 'precio_maximo' => 30000],
    ['modelo' => 'Baleno', 'anio' => 2020, 'categoria' => 'Filtros', 'texto' => 'filtro de aire'],
] as $i => $args) {
    foreach ([true, false] as $estricto) {
        [$sql, $tipos, $params] = chatConsultaBusqueda($args, 8, $estricto);
        igual(substr_count($sql, '?'), strlen($tipos), "consulta $i placeholders = tipos (" . ($estricto ? 'AND' : 'OR') . ')');
        igual(strlen($tipos), count($params), "consulta $i tipos = params");
    }
}
[$sql] = chatConsultaBusqueda(['modelo' => 'Swift', 'anio' => 2019, 'texto' => 'optica delantera'], 8, true);
igual(strpos($sql, ' AND ((r.nombre') !== false || strpos($sql, "AND ((r.nombre") !== false, true, 'modo estricto usa AND entre grupos');

// Historial (CP-29: "agrega el segundo")
$filas = [ // más nuevas primero, como las entrega ORDER BY id DESC
    ['rol' => 'bot', 'contenido' => 'Tengo estas 2 opciones para tu Baleno 2020.', 'productos' => '41|FIL-BAL-AIR|Filtro de aire Baleno;;42|FIL-BAL-CAB|Filtro de cabina Baleno'],
    ['rol' => 'user', 'contenido' => 'Tengo un Baleno 2020, ¿y filtro de aire?', 'productos' => null],
];
$hist = chatHistorialParaAPI($filas);
igual($hist[0]['role'], 'user', 'historial en orden cronologico');
igual(strpos($hist[1]['content'], '2) id 42, SKU FIL-BAL-CAB') !== false, true, 'historial numera productos con id');

// Texto de respuesta: ignora reasoning y function_call
$resp = ['output' => [
    ['type' => 'reasoning', 'summary' => [['type' => 'summary_text', 'text' => 'pensando...']]],
    ['type' => 'function_call', 'name' => 'buscar_productos', 'arguments' => '{}'],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hola, ']], 'role' => 'assistant'],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'tengo 2 opciones.']], 'role' => 'assistant'],
]];
igual(chatTextoRespuesta($resp), 'Hola, tengo 2 opciones.', 'solo texto de items message');

echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
