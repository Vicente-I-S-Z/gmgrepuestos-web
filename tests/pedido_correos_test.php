<?php
/**
 * F4-6 (E3-H8, E7-H1, RF-026, RF-062): correos de pedido.
 *   - al aprobar, el aviso interno llega al correo de la tienda con cliente, productos, total y un
 *     botón de WhatsApp al celular del cliente (se prueba con un enviador falso);
 *   - el aviso de pedido nuevo se envía después de responder al navegador (revisión del código:
 *     en Hostinger lo hace litespeed_finish_request; php -S no lo tiene, se prueba en el servidor).
 * Base temporal desde BD_v2 (tests/chat_entorno.php). No usa ni lee el .env.
 * Uso: php tests/pedido_correos_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('correospedido');
putenv('GMG_ENV_FILE=' . $e['tmp'] . '/config.txt');
require __DIR__ . '/../backend/config.php';
require __DIR__ . '/../backend/email_helpers.php';
try {
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos LIMIT 1")->fetch_row()[0];
    $e['db']->query("INSERT INTO clientes (id_cliente, rut, nombre, apellido, email, telefono) VALUES (1, '12345678-5', 'Ana', 'Pérez <b>', 'ana@example.com', '+56 9 8765 4321')");
    $e['db']->query("INSERT INTO pedidos (id_pedido, id_cliente, estado, total) VALUES (90, 1, 'aprobado', 31980)");
    $e['db']->query("INSERT INTO pedido_detalle (id_pedido, id_repuesto, sku, nombre_repuesto, cantidad, precio_unitario) SELECT 90, id_repuesto, sku, nombre, 2, 15990 FROM repuestos WHERE id_repuesto = $id");
    $enviado = [];
    $r = enviarCorreoPedidoAprobadoInterno($conexion, 90, function ($para, $nombre, $asunto, $html) use (&$enviado) {
        $enviado = compact('para', 'asunto', 'html');
        return ['success' => true];
    });
    revisar(!empty($r['success']) && $enviado['para'] === ADMIN_NOTIFICATION_EMAIL && $enviado['asunto'] === 'Pedido #90 aprobado: coordinar entrega', 'aviso interno al correo de la tienda con el número de pedido', $enviado['asunto'] ?? '');
    revisar(strpos($enviado['html'], 'Pérez &lt;b&gt;') !== false && strpos($enviado['html'], '$31.980') !== false, 'trae al cliente (escapado) y el total');
    revisar(strpos($enviado['html'], 'https://wa.me/56987654321?text=') !== false, 'y el botón de WhatsApp al celular del cliente');
    revisar(enviarCorreoPedidoAprobadoInterno($conexion, 999, function () { return ['success' => true]; })['success'] === false, 'un pedido inexistente no envía nada');

    $checkout = (string)file_get_contents(__DIR__ . '/../checkout.php');
    $posAviso = strpos($checkout, 'register_shutdown_function');
    revisar($posAviso !== false && strpos($checkout, 'litespeed_finish_request') !== false && $posAviso < strpos($checkout, 'enviarCorreoNuevoPedido($conexion, $pedidoId)'),
            'checkout: el aviso de pedido nuevo va después de responder (shutdown + litespeed/fastcgi_finish_request)');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
