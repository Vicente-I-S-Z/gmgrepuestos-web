<?php
/**
 * Funciones compartidas del Asistente Virtual IA - GMG Repuestos
 */

// Búsqueda por palabras, sinónimos y relevancia; historial desde la base (F1-2).
require_once __DIR__ . '/chat_busqueda.php';

// =========================================================
// 1) Límite de mensajes por sesión (anti-abuso / control de costo)
// =========================================================
function chatVerificarLimite() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $ahora = time();
    if (!isset($_SESSION['gmg_chat_timestamps']) || !is_array($_SESSION['gmg_chat_timestamps'])) {
        $_SESSION['gmg_chat_timestamps'] = [];
    }
    // Solo dejar los timestamps dentro de la ventana de tiempo
    $_SESSION['gmg_chat_timestamps'] = array_values(array_filter(
        $_SESSION['gmg_chat_timestamps'],
        function ($t) use ($ahora) { return ($ahora - $t) < CHAT_RATE_LIMIT_WINDOW; }
    ));

    if (count($_SESSION['gmg_chat_timestamps']) >= CHAT_RATE_LIMIT_MAX) {
        return false;
    }
    $_SESSION['gmg_chat_timestamps'][] = $ahora;
    return true;
}

// =========================================================
// 2) Registro de conversaciones y mensajes (para el panel de
//    analítica en Admin)
// =========================================================
function chatObtenerOCrearConversacion($conexion, $sesionId) {
    $sesionId = substr(trim($sesionId), 0, 64);
    if ($sesionId === '') {
        return null;
    }
    $stmt = $conexion->prepare("SELECT id_conversacion AS id FROM conversaciones_chat WHERE sesion_id = ?");
    $stmt->bind_param("s", $sesionId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $id = $res->fetch_assoc()['id'];
        $stmt->close();
        $upd = $conexion->prepare("UPDATE conversaciones_chat SET ultimo_mensaje_en = NOW() WHERE id_conversacion = ?");
        $upd->bind_param("i", $id);
        $upd->execute();
        $upd->close();
        return (int)$id;
    }
    $stmt->close();

    $ins = $conexion->prepare("INSERT INTO conversaciones_chat (sesion_id) VALUES (?)");
    $ins->bind_param("s", $sesionId);
    if ($ins->execute()) {
        $id = $conexion->insert_id;
        $ins->close();
        return (int)$id;
    }
    $ins->close();
    return null;
}

function chatGuardarMensaje($conexion, $conversacionId, $rol, $contenido, $productos = []) {
    if (!$conversacionId) {
        return null;
    }
    $stmt = $conexion->prepare("INSERT INTO mensajes_chat (id_conversacion, rol, contenido) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $conversacionId, $rol, $contenido);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $id = (int)$conexion->insert_id;
    $stmt->close();

    // Base v2: los repuestos que el bot mostró se guardan en la tabla
    // intermedia mensaje_repuestos (antes era un JSON en productos_json).
    if (!empty($productos)) {
        $insProd = $conexion->prepare("INSERT INTO mensaje_repuestos (id_mensaje, id_repuesto, orden) VALUES (?, ?, ?)");
        $orden = 1;
        $yaGuardados = [];
        foreach ($productos as $p) {
            $idRepuesto = (int)($p['id'] ?? 0);
            if ($idRepuesto <= 0 || isset($yaGuardados[$idRepuesto]) || $orden > 255) {
                continue;
            }
            $insProd->bind_param("iii", $id, $idRepuesto, $orden);
            $insProd->execute();
            $yaGuardados[$idRepuesto] = true;
            $orden++;
        }
        $insProd->close();
    }
    return $id;
}

// =========================================================
// 3) Herramienta "buscar_productos": lo que la IA puede
//    consultar en vivo contra la base de datos real.
// =========================================================
function chatDefinicionHerramientas() {
    return [
        [
            'type' => 'function',
            'name' => 'buscar_productos',
            'description' => 'Busca repuestos reales en el catálogo de GMG Repuestos por modelo y año del vehículo, categoría, SKU/código OEM, texto libre o precio máximo. IMPORTANTE: salvo que se busque por SKU o código OEM, es obligatorio enviar el modelo y el año del vehículo del cliente (si no los sabes, pregúntaselos antes de llamar a esta herramienta). Devuelve hasta 8 resultados ordenados por relevancia, con id, nombre, modelo, año, motor, compatible_con (lista exacta de vehículos: modelo, motor y años), categoría, sku, codigo_oem, precio, stock y disponibilidad ("Disponible", "Últimas unidades" o "Sin stock"). Si falta un dato o el modelo no existe en el catálogo, devuelve un "aviso" con lo que debes hacer.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'modelo' => ['type' => 'string', 'description' => 'Ej: Swift, Alto, S-Presso'],
                    'anio' => ['type' => 'integer', 'description' => 'Año del vehículo del cliente. Ej: 2018'],
                    'anio_desconocido' => ['type' => 'boolean', 'description' => 'Enviar true SOLO si el cliente dijo claramente que no sabe el año de su vehículo'],
                    'categoria' => ['type' => 'string', 'description' => 'Ej: Frenos, Filtros, Suspensión, Motor'],
                    'sku_o_codigo_oem' => ['type' => 'string', 'description' => 'SKU exacto o parcial, o código OEM'],
                    'texto' => ['type' => 'string', 'description' => 'Solo la pieza que busca el cliente, sin modelo ni año. Ej: pastillas de freno delanteras'],
                    'precio_maximo' => ['type' => 'number', 'description' => 'Precio máximo en CLP'],
                ],
                'required' => [],
            ],
        ],
    ];
}

/**
 * Guarda/recupera el último SQL ejecutado por buscar_productos, solo para
 * poder mostrarlo en el modo debug (CHAT_DEBUG en bot_config.php).
 */
function chatUltimaConsultaSQL($nuevoValor = null) {
    static $ultimo = '';
    if ($nuevoValor !== null) {
        $ultimo = $nuevoValor;
    }
    return $ultimo;
}

/**
 * Historia E5-H4: el asistente debe conocer el modelo y el año del vehículo
 * antes de recomendar un repuesto. Esta revisión se hace aquí, en el servidor,
 * para que no dependa solo de que la IA "se acuerde" de preguntar.
 *
 * Devuelve null si la búsqueda puede hacerse, o un mensaje (para la IA)
 * pidiéndole que primero pregunte los datos que faltan, o avisándole que el
 * modelo no está en el catálogo (con la lista de modelos que sí hay).
 */
function chatValidarBusqueda($args, $conexion = null) {
    // Con un SKU o código OEM no hace falta saber el vehículo.
    if (!empty(trim((string)($args['sku_o_codigo_oem'] ?? '')))) {
        return null;
    }
    $modelo = trim((string)($args['modelo'] ?? ''));
    $anio = (int)($args['anio'] ?? 0);
    $anioValido = $anio >= 1970 && $anio <= 2100;
    $anioDesconocido = !empty($args['anio_desconocido']);

    $faltan = [];
    if ($modelo === '') $faltan[] = 'el MODELO';
    if (!$anioValido && !$anioDesconocido) $faltan[] = 'el AÑO';
    if (count($faltan) > 0) {
        return 'BÚSQUEDA NO REALIZADA: falta ' . implode(' y ', $faltan) . ' del vehículo del cliente. '
             . 'Pregúntaselo ahora (una sola pregunta, breve y amable) y no recomiendes ningún producto hasta tenerlo.';
    }

    // El modelo debe ser uno de los que tienen repuestos (Vitara y Grand Vitara son distintos).
    if ($conexion) {
        $modelos = chatModelosCortos($conexion);
        if ($modelos && !in_array(chatModeloClave($modelo), array_map('chatModeloClave', $modelos), true)) {
            return 'BÚSQUEDA NO REALIZADA: no hay repuestos cargados para el modelo "' . mb_substr($modelo, 0, 40) . '". '
                 . 'Modelos con repuestos: ' . implode(', ', $modelos) . '. '
                 . 'Si el modelo del cliente es una versión de uno de estos (por ejemplo "Swift Sport" es Swift), vuelve a buscar con ese nombre. '
                 . 'Si no, dile que por ahora no tenemos repuestos para su modelo y ofrécele WhatsApp.';
        }
    }
    return null;
}

/**
 * Herramienta buscar_productos (E5-H3, INC-06): primero exige todas las
 * palabras y, si no hay resultados, basta una; ordena por relevancia y stock.
 * Todo con sentencias preparadas (chatConsultaBusqueda en chat_busqueda.php).
 * Devuelve el detalle de cada repuesto con su disponibilidad (RF-013).
 */
function chatEjecutarBusquedaProductos($conexion, $args) {
    $ids = [];
    foreach ([true, false] as $estricto) {
        [$sql, $tipos, $params] = chatConsultaBusqueda($args, 8, $estricto);
        // Guardada solo para el modo debug (CHAT_DEBUG o sesión de administrador).
        chatUltimaConsultaSQL($sql);
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            error_log('[GMG Chat] No se pudo preparar la búsqueda: ' . $conexion->error);
            return [];
        }
        if ($tipos !== '') {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();
        $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $stmt->close();
        if ($ids) {
            break;
        }
    }
    if (!$ids) {
        return [];
    }

    $lista = implode(',', array_map('intval', $ids));
    $r = $conexion->query("SELECT id, nombre, modelo, ano, motor, compatible_con, categoria, sku, codigo_oem,
                                  precio, stock, imagen_url
                             FROM " . gmgSqlProductos() . " p WHERE p.id IN ($lista)
                            ORDER BY FIELD(p.id, $lista)");
    $filas = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($filas as &$f) {
        $f['disponibilidad'] = chatDisponibilidad((int)$f['stock']);
    }
    unset($f);
    return $filas;
}

// =========================================================
// 4) Extracción de etiquetas de acción en la respuesta del bot
// =========================================================
function chatExtraerAddToCart(&$texto, $productos) {
    $autoAddCart = [];
    if (preg_match_all('/\[ADD_TO_CART:\s*(\d+)\s*:\s*(\d+)\s*\]/i', $texto, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $itemId = (int)$matches[1][$i];
            $cantidad = (int)$matches[2][$i];
            foreach ($productos as $p) {
                if ($p['id'] == $itemId) {
                    $autoAddCart[] = [
                        'id' => $itemId,
                        'quantity' => $cantidad,
                        'nombre' => $p['nombre'],
                        'precio' => $p['precio'],
                        'imagen_url' => $p['imagen_url'],
                    ];
                    break;
                }
            }
        }
        $texto = trim(preg_replace('/\[ADD_TO_CART:\s*\d+\s*:\s*\d+\s*\]/i', '', $texto));
    }
    return $autoAddCart;
}

function chatExtraerStartCheckout(&$texto) {
    $encontrado = (bool)preg_match('/\[START_CHECKOUT\]/i', $texto);
    if ($encontrado) {
        $texto = trim(preg_replace('/\[START_CHECKOUT\]/i', '', $texto));
    }
    return $encontrado;
}

function chatExtraerNotificarStock(&$texto) {
    $productoId = null;
    if (preg_match('/\[NOTIFICAR_STOCK:\s*(\d+)\s*\]/i', $texto, $m)) {
        $productoId = (int)$m[1];
        $texto = trim(preg_replace('/\[NOTIFICAR_STOCK:\s*\d+\s*\]/i', '', $texto));
    }
    return $productoId;
}

function chatFiltrarProductosPorTexto($texto, $productos) {
    $encontrados = [];
    $txtLower = mb_strtolower($texto, 'UTF-8');
    foreach ($productos as $p) {
        if (stripos($txtLower, mb_strtolower($p['sku'], 'UTF-8')) !== false ||
            (stripos($txtLower, mb_strtolower($p['nombre'], 'UTF-8')) !== false && stripos($txtLower, mb_strtolower((string)$p['modelo'], 'UTF-8')) !== false)) {
            $encontrados[] = $p;
        }
    }
    return array_values($encontrados);
}

// =========================================================
// 5) Orquestación de la llamada a la IA (function calling con
//    respaldo). Separado en funciones para poder probarse sin red.
// =========================================================

/**
 * Orquesta la consulta a la IA: intenta primero con "tools" (function
 * calling) para que el modelo consulte el catálogo real en vivo; si la API
 * no soporta ese formato o falla, cae de respaldo al método anterior
 * (catálogo completo pegado como texto, sin tools).
 */
function chatConsultarIA($mensaje, $imagenes, $historial, $productos, $conexion) {
    // Al prompt base se le suman la base de conocimiento, la regla de preguntar
    // modelo y año y la lista actual de modelos (ver backend/conocimiento.php).
    $contextoNegocio = gmgContextoNegocioParaIA($conexion, chatModelosCortos($conexion));
    $mensajesBase = chatArmarMensajesBase(AI_SYSTEM_PROMPT . $contextoNegocio, $historial, $mensaje, $imagenes);
    $herramientas = chatDefinicionHerramientas();

    $resultado = chatLlamarResponsesAPI($mensajesBase, $herramientas);

    if ($resultado['success']) {
        return chatResolverToolCalls($resultado, $mensajesBase, $herramientas, $conexion, 0);
    }

    // El motivo real del primer intento se registra siempre: antes solo quedaba
    // el del intento de respaldo, que podía ocultar la causa verdadera.
    error_log('[GMG Chat] Falló la consulta a la IA (con herramientas): ' . ($resultado['error'] ?? 'desconocido'));

    // Si el problema es de conexión, de la clave o de la cuenta, repetir la
    // llamada sin herramientas fallaría igual y solo haría esperar al cliente
    // el doble. El respaldo se usa solo cuando el fallo puede venir del formato.
    if (chatErrorSinRespaldo($resultado)) {
        return $resultado;
    }

    // --- Respaldo: sin tools, catálogo completo como texto (comportamiento anterior) ---
    $catalogoJson = json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $systemPromptRespaldo = str_replace('{CATALOGO_JSON}', $catalogoJson, AI_SYSTEM_PROMPT_FALLBACK) . $contextoNegocio;
    $mensajesRespaldo = chatArmarMensajesBase($systemPromptRespaldo, $historial, $mensaje, $imagenes);

    $resultadoRespaldo = chatLlamarResponsesAPI($mensajesRespaldo, null);
    if (!$resultadoRespaldo['success']) {
        return $resultadoRespaldo;
    }
    $texto = chatExtraerTextoDeRespuesta($resultadoRespaldo['data']);
    return ['success' => true, 'reply' => $texto, 'productos_encontrados' => [], 'debug_busquedas' => null, 'debug_modo' => 'respaldo_sin_tools'];
}

/**
 * ¿El fallo de la API es de conexión, de la clave o de la cuenta? Es decir:
 * sin conexión o tiempo agotado (código HTTP 0), clave inválida (401), sin
 * permisos (403) o sin saldo / demasiadas peticiones (429). En esos casos
 * intentar de nuevo sin herramientas no sirve de nada.
 */
function chatErrorSinRespaldo($resultado) {
    $codigo = (int)($resultado['http_code'] ?? 0);
    return $codigo === 0 || in_array($codigo, [401, 403, 429], true);
}

/**
 * Nombres cortos de los modelos que hoy tienen repuestos activos en el
 * catálogo (ej: ['Alto', 'S-Presso', 'Swift']). Se arma desde la base de
 * datos, así un modelo nuevo aparece en el chatbot sin cambiar código.
 */
function chatModelosCortos($conexion) {
    $lista = [];
    if (!$conexion) {
        return $lista;
    }
    $r = $conexion->query("SELECT DISTINCT mo.nombre, mo.orden
                             FROM modelos mo
                             JOIN vehiculos v ON v.id_modelo = mo.id_modelo
                             JOIN compatibilidades co ON co.id_vehiculo = v.id_vehiculo
                             JOIN repuestos r ON r.id_repuesto = co.id_repuesto
                            WHERE mo.activo = 1 AND r.activo = 1
                            ORDER BY mo.orden, mo.nombre");
    if ($r) {
        while ($f = $r->fetch_assoc()) {
            $lista[] = $f['nombre'];
        }
    }
    return $lista;
}

function chatArmarMensajesBase($systemPrompt, $historial, $mensaje, $imagenes) {
    $mensajes = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];

    // Historial leído de la base con chatHistorialDesdeBD(): ya viene en orden,
    // recortado y con los productos que mostró cada respuesta. Solo texto.
    foreach ($historial as $turno) {
        if (in_array($turno['role'] ?? '', ['user', 'assistant'], true) && is_string($turno['content'] ?? null) && $turno['content'] !== '') {
            $mensajes[] = ['role' => $turno['role'], 'content' => $turno['content']];
        }
    }

    // Mensaje actual (con imágenes si las hay)
    if (!empty($imagenes)) {
        $contenido = [];
        $contenido[] = ['type' => 'input_text', 'text' => $mensaje !== '' ? $mensaje : 'Por favor identifica qué repuesto es el de esta foto y si lo tienen disponible en la tienda para Suzuki.'];
        foreach ($imagenes as $img) {
            $contenido[] = ['type' => 'input_image', 'image_url' => "data:{$img['mime']};base64,{$img['base64']}"];
        }
        $mensajes[] = ['role' => 'user', 'content' => $contenido];
    } else {
        $mensajes[] = ['role' => 'user', 'content' => $mensaje];
    }

    return $mensajes;
}

function chatLlamarResponsesAPI($mensajes, $herramientas) {
    $payload = [
        'model' => AI_MODEL,
        'input' => $mensajes,
        'text' => [
            'format' => ['type' => 'text'],
            'verbosity' => 'medium',
        ],
        'reasoning' => [
            'effort' => 'medium',
            'mode' => 'standard',
            'summary' => 'auto',
        ],
    ];
    if (!empty($herramientas)) {
        $payload['tools'] = $herramientas;
        $payload['tool_choice'] = 'auto';
    }

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_SSL_VERIFYPEER => gmgCurlVerificarSsl(),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || empty($response)) {
        $detalle = '';
        if (!empty($response)) {
            $j = json_decode($response, true);
            if (isset($j['error']['message'])) $detalle = ' - ' . $j['error']['message'];
        }
        return ['success' => false, 'error' => ($curlError ?: "HTTP $httpCode") . $detalle, 'http_code' => (int)$httpCode];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'error' => 'Respuesta no válida de la API', 'http_code' => (int)$httpCode];
    }
    return ['success' => true, 'data' => $decoded];
}

/**
 * Revisa si la respuesta de la API pidió ejecutar la herramienta
 * buscar_productos. Si es así, la ejecuta contra la base de datos real,
 * devuelve el resultado a la API y repite (hasta 3 rondas) para obtener la
 * respuesta final en texto.
 */
function chatResolverToolCalls($resultado, $mensajes, $herramientas, $conexion, $ronda) {
    $output = $resultado['data']['output'] ?? null;
    $llamadasFuncion = [];

    if (is_array($output)) {
        foreach ($output as $item) {
            if (isset($item['type']) && $item['type'] === 'function_call') {
                $llamadasFuncion[] = $item;
            }
        }
    }

    if (empty($llamadasFuncion) || $ronda >= 3) {
        $texto = chatExtraerTextoDeRespuesta($resultado['data']);
        return ['success' => true, 'reply' => $texto, 'productos_encontrados' => [], 'debug_busquedas' => [], 'debug_modo' => 'tools'];
    }

    $productosEncontrados = [];
    $debugBusquedas = [];
    $mensajesActualizados = $mensajes;

    // IMPORTANTE: hay que reenviar TODO el array "output" tal cual lo
    // devolvió la API (no solo los items "function_call" sueltos). Los
    // modelos con razonamiento incluyen un item "reasoning" junto a cada
    // function_call, y la API lo exige de vuelta en la siguiente llamada:
    // si se omite, responde HTTP 400 "was provided without its required
    // 'reasoning' item".
    foreach ($output as $item) {
        $mensajesActualizados[] = $item;
    }

    foreach ($llamadasFuncion as $llamada) {
        $args = [];
        if (isset($llamada['arguments'])) {
            $decodedArgs = json_decode($llamada['arguments'], true);
            if (is_array($decodedArgs)) $args = $decodedArgs;
        }

        $avisoFaltanDatos = ($llamada['name'] ?? '') === 'buscar_productos' ? chatValidarBusqueda($args, $conexion) : null;
        $resultadosBusqueda = ($llamada['name'] ?? '') === 'buscar_productos' && $conexion && $avisoFaltanDatos === null
            ? chatEjecutarBusquedaProductos($conexion, $args)
            : [];

        $debugBusquedas[] = [
            'nombre' => $llamada['name'] ?? '',
            'args' => $args,
            'num_resultados' => count($resultadosBusqueda),
        ];

        foreach ($resultadosBusqueda as $p) {
            $productosEncontrados[$p['id']] = $p; // dedup por id
        }

        $mensajesActualizados[] = [
            'type' => 'function_call_output',
            'call_id' => $llamada['call_id'] ?? '',
            'output' => $avisoFaltanDatos !== null
                ? json_encode(['aviso' => $avisoFaltanDatos], JSON_UNESCAPED_UNICODE)
                : json_encode($resultadosBusqueda, JSON_UNESCAPED_UNICODE),
        ];
    }

    $siguiente = chatLlamarResponsesAPI($mensajesActualizados, $herramientas);
    if (!$siguiente['success']) {
        return $siguiente;
    }

    $final = chatResolverToolCalls($siguiente, $mensajesActualizados, $herramientas, $conexion, $ronda + 1);
    $final['debug_busquedas'] = array_merge($debugBusquedas, $final['debug_busquedas'] ?? []);
    if ($final['success'] && empty($final['productos_encontrados'])) {
        $final['productos_encontrados'] = array_values($productosEncontrados);
    }
    return $final;
}

function chatExtraerTextoDeRespuesta($decoded) {
    if (isset($decoded['choices'][0]['message']['content'])) {
        return trim($decoded['choices'][0]['message']['content']);
    }
    if (isset($decoded['text']['content'])) {
        return trim($decoded['text']['content']);
    }
    if (isset($decoded['choices'][0]['text'])) {
        return trim($decoded['choices'][0]['text']);
    }
    if (isset($decoded['output'])) {
        if (is_string($decoded['output'])) {
            return trim($decoded['output']);
        }
        if (is_array($decoded['output'])) {
            $extraido = '';
            foreach ($decoded['output'] as $item) {
                if (($item['type'] ?? '') === 'function_call') continue;
                if (isset($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $c) {
                        if (isset($c['text'])) $extraido .= $c['text'];
                    }
                } elseif (isset($item['text'])) {
                    $extraido .= $item['text'];
                }
            }
            if (trim($extraido) !== '') {
                return trim($extraido);
            }
        }
    }
    return 'Disculpa, no pude generar una respuesta en este momento.';
}
?>
