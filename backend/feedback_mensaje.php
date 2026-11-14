<?php
// Guarda el feedback (👍/👎) que el cliente deja sobre una respuesta del
// Asistente Virtual. Queda guardado en mensajes_chat.feedback junto al mensaje.
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$mensajeId = (int)($input['mensaje_id'] ?? 0);
$feedback = trim((string)($input['feedback'] ?? ''));

$valores = ['positivo', 'negativo'];
if ($mensajeId <= 0 || !in_array($feedback, $valores, true)) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Datos inválidos.']);
    exit;
}

$stmt = $conexion->prepare("UPDATE mensajes_chat SET feedback = ? WHERE id_mensaje = ? AND rol = 'bot'");
$stmt->bind_param("si", $feedback, $mensajeId);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'mensaje' => 'No se pudo guardar el feedback.']);
}
$stmt->close();
?>
