<?php
// Registra la solicitud de un cliente para que se le avise cuando un
// repuesto sin stock vuelva a estar disponible. El bot activa este flujo
// agregando la etiqueta [NOTIFICAR_STOCK:ID] en su respuesta (ver
// bot_config.php); el frontend (chat.js) le pide el contacto al cliente y
// llama a este endpoint.
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$productoId = (int)($input['producto_id'] ?? 0);
$contacto = trim((string)($input['contacto'] ?? ''));
$sesionId = trim((string)($input['sesion_id'] ?? ''));

if ($productoId <= 0 || $contacto === '') {
    echo json_encode(['status' => 'error', 'mensaje' => 'Faltan datos para registrar el aviso.']);
    exit;
}

if (mb_strlen($contacto) > 150) {
    $contacto = mb_substr($contacto, 0, 150);
}
if (mb_strlen($sesionId) > 64) {
    $sesionId = mb_substr($sesionId, 0, 64);
}

// Verificar que el producto exista
$check = $conexion->prepare("SELECT id_repuesto AS id, nombre FROM repuestos WHERE id_repuesto = ? LIMIT 1");
$check->bind_param("i", $productoId);
$check->execute();
$resCheck = $check->get_result();
if (!$resCheck || $resCheck->num_rows === 0) {
    $check->close();
    echo json_encode(['status' => 'error', 'mensaje' => 'El producto indicado no existe.']);
    exit;
}
$producto = $resCheck->fetch_assoc();
$check->close();

$stmt = $conexion->prepare("INSERT INTO avisos_stock (id_repuesto, contacto, sesion_id) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $productoId, $contacto, $sesionId);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'mensaje' => '¡Listo! Te avisaremos apenas "' . $producto['nombre'] . '" vuelva a tener stock.',
    ]);
} else {
    echo json_encode(['status' => 'error', 'mensaje' => 'No se pudo registrar el aviso. Intenta de nuevo.']);
}
$stmt->close();
?>
