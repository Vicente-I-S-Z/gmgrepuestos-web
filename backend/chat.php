<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'bot_config.php';
require_once 'config.php';
require_once 'datos.php';
require_once 'chat_helpers.php';
require_once 'conocimiento.php';

// 0. Límite de mensajes por sesión (anti-abuso / control de costo de la API)
if (!chatVerificarLimite()) {
    echo json_encode([
        'status' => 'error',
        'reply' => 'Estás enviando mensajes muy rápido 🙂. Espera unos segundos y vuelve a intentar.'
    ]);
    exit;
}

// 1. Obtener catálogo de productos (se usa como respaldo si la API no
//    soporta herramientas, y como universo válido de IDs para las etiquetas)
$productos = [];
if (isset($conexion) && !$conexion->connect_error) {
    $sql = "SELECT id, nombre, modelo, ano, motor, compatible_con, categoria, sku, codigo_oem, precio, stock, imagen_url FROM " . gmgSqlProductos() . " p WHERE p.activo = 1";
    $resultado = $conexion->query($sql);
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $productos[] = $fila;
        }
    }
}

// 2. Extraer mensaje, imágenes adjuntas, historial y sesión
$mensaje = '';
$imagenes = []; // cada una: ['base64' => ..., 'mime' => ...]
$historial = [];
$sesionId = '';
$atajo = ''; // botón rápido del chat: buscar, despacho o pagos

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        $mensaje = trim($_POST['message']);
    }
    if (isset($_POST['sesion_id'])) {
        $sesionId = trim($_POST['sesion_id']);
    }
    if (isset($_POST['atajo'])) {
        $atajo = trim($_POST['atajo']);
    }
    if (isset($_POST['historial'])) {
        $decodedHist = json_decode($_POST['historial'], true);
        if (is_array($decodedHist)) {
            $historial = $decodedHist;
        }
    }

    // Hasta 3 fotos por mensaje: campos images[] (nuevo) o image (compatibilidad)
    if (isset($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmpPath) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK && count($imagenes) < 3) {
                $mime = mime_content_type($tmpPath) ?: 'image/jpeg';
                $imagenes[] = ['base64' => base64_encode(file_get_contents($tmpPath)), 'mime' => $mime];
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['image']['tmp_name'];
        $mime = mime_content_type($tmpPath) ?: 'image/jpeg';
        $imagenes[] = ['base64' => base64_encode(file_get_contents($tmpPath)), 'mime' => $mime];
    }

    if (empty($mensaje) && empty($imagenes)) {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (isset($jsonData['message'])) {
                $mensaje = trim($jsonData['message']);
            }
            if (isset($jsonData['sesion_id'])) {
                $sesionId = trim($jsonData['sesion_id']);
            }
            if (isset($jsonData['historial']) && is_array($jsonData['historial'])) {
                $historial = $jsonData['historial'];
            }
            if (isset($jsonData['image_base64'])) {
                $imagenes[] = ['base64' => $jsonData['image_base64'], 'mime' => 'image/jpeg'];
            }
        }
    }
}

if (empty($mensaje) && empty($imagenes)) {
    echo json_encode([
        'status' => 'error',
        'reply' => 'Por favor escribe un mensaje o sube una imagen de la pieza que buscas.'
    ]);
    exit;
}

// 3. Conversación (para el panel de analítica en Admin)
$conversacionId = (isset($conexion) && !$conexion->connect_error && !empty($sesionId))
    ? chatObtenerOCrearConversacion($conexion, $sesionId)
    : null;

if ($conversacionId) {
    chatGuardarMensaje($conexion, $conversacionId, 'user', $mensaje !== '' ? $mensaje : '(foto adjunta)');
}

// 3.1 Botones rápidos del chat (E5-H1): se responden de inmediato con la
//     información configurada, sin pasar por la IA.
if ($atajo !== '' && empty($imagenes)) {
    $respuestaAtajo = gmgRespuestaAtajo($conexion, $atajo);
    if ($respuestaAtajo !== null) {
        $mensajeIdAtajo = $conversacionId ? chatGuardarMensaje($conexion, $conversacionId, 'bot', $respuestaAtajo) : null;
        echo json_encode([
            'status' => 'success',
            'reply' => $respuestaAtajo,
            'products' => [],
            'auto_add_cart' => [],
            'start_checkout' => false,
            'notificar_stock_producto_id' => null,
            'mensaje_id' => $mensajeIdAtajo,
            'model_used' => 'respuesta_configurada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 4. Procesamiento con la IA
$respuestaTexto = '';
$repuestosSugeridos = [];
$autoAddCart = [];
$startCheckout = false;
$notificarStockProductoId = null;
$errorIA = false;       // true si la IA no pudo responder (el chat no ofrece 👍/👎 sobre ese error)
$sufijoDebugIA = '';    // detalle técnico del error: solo se muestra a administradores o con CHAT_DEBUG

if (!empty(AI_API_KEY)) {
    $respuestaAPI = chatConsultarIA($mensaje, $imagenes, $historial, $productos, $conexion);

    if ($respuestaAPI['success']) {
        $respuestaTexto = $respuestaAPI['reply'];

        $autoAddCart = chatExtraerAddToCart($respuestaTexto, $productos);
        $startCheckout = chatExtraerStartCheckout($respuestaTexto);
        $notificarStockProductoId = chatExtraerNotificarStock($respuestaTexto);

        // Si la IA usó la herramienta de búsqueda, esos resultados reales son
        // las tarjetas de producto a mostrar (mucho más confiable que adivinar
        // por texto). Si no la usó, se recurre al matching de texto anterior.
        if (!empty($respuestaAPI['productos_encontrados'])) {
            $repuestosSugeridos = $respuestaAPI['productos_encontrados'];
        } else {
            $repuestosSugeridos = chatFiltrarProductosPorTexto($respuestaTexto . ' ' . $mensaje, $productos);
        }

        if (defined('CHAT_DEBUG') && CHAT_DEBUG) {
            $modo = $respuestaAPI['debug_modo'] ?? 'desconocido';
            $detalleDebug = "modo=$modo";
            if (!empty($respuestaAPI['debug_busquedas'])) {
                foreach ($respuestaAPI['debug_busquedas'] as $b) {
                    $detalleDebug .= ' | buscar_productos(' . json_encode($b['args'], JSON_UNESCAPED_UNICODE) . ') -> ' . $b['num_resultados'] . ' resultado(s)';
                }
                $detalleDebug .= ' | SQL: ' . chatUltimaConsultaSQL();
            } elseif ($modo === 'tools') {
                $detalleDebug .= ' | la IA no llamó a buscar_productos';
            }
            $respuestaTexto .= "\n\n[DEBUG] " . $detalleDebug;
        }
    } else {
        $detalleError = $respuestaAPI['error'] ?? 'desconocido';
        error_log('[GMG Chat] Error IA: ' . $detalleError);
        $errorIA = true;
        $respuestaTexto = 'Lo siento, estoy teniendo problemas de conexión en este momento. Intenta de nuevo en un momento o [escríbenos por WhatsApp](' . gmgEnlaceWhatsApp('Hola, necesito ayuda con un repuesto') . ') si es urgente.';
        // El detalle técnico (error real de la API) se muestra solo si hay una
        // sesión de administrador abierta en este navegador, o con CHAT_DEBUG
        // en true. Los clientes nunca lo ven y tampoco se guarda en la base.
        if ((defined('CHAT_DEBUG') && CHAT_DEBUG) || !empty($_SESSION['admin_id'])) {
            $sufijoDebugIA = "\n\n[DEBUG] " . $detalleError;
        }
    }
} else {
    // Sin la clave de la IA el asistente no puede conversar; en vez de mostrar
    // un mensaje técnico, se le ofrece al cliente seguir por WhatsApp.
    error_log('[GMG Chat] AI_API_KEY no está configurada en el archivo .env');
    $respuestaTexto = 'Por ahora no puedo responder por aquí. Escríbenos por WhatsApp y te ayudamos enseguida: ' . gmgEnlaceWhatsApp('Hola, necesito ayuda con un repuesto');
}

// 5. Si el producto sugerido con stock 0 no trajo la etiqueta de aviso pero
//    el cliente aceptó implícitamente, no forzamos nada: solo se activa si
//    la IA agregó [NOTIFICAR_STOCK:ID] explícitamente (ver bot_config.php).

$mensajeId = null;
if ($conversacionId) {
    $mensajeId = chatGuardarMensaje($conexion, $conversacionId, 'bot', $respuestaTexto, $repuestosSugeridos);
}

// 6. Retornar respuesta estructurada
echo json_encode([
    'status' => $errorIA ? 'error' : 'success',
    'reply' => $respuestaTexto . $sufijoDebugIA,
    'products' => $repuestosSugeridos,
    'auto_add_cart' => $autoAddCart,
    'start_checkout' => $startCheckout,
    'notificar_stock_producto_id' => $notificarStockProductoId,
    'mensaje_id' => $errorIA ? null : $mensajeId,
    'model_used' => AI_MODEL
], JSON_UNESCAPED_UNICODE);
exit;
?>
