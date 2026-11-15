<?php
/**
 * F2-2 (E3-H6, INC-10, RF-024, CP-15): checkout.php revisa el stock dentro de la transacción,
 * con bloqueo (FOR UPDATE), antes de crear el pedido.
 * Base temporal desde BD_v2 y sitio con php -S (tests/chat_entorno.php). El sitio corre con un
 * proxy inexistente (https_proxy=127.0.0.1:9): la llamada a Mercado Pago falla al instante sin
 * salir del computador, así se prueba el registro del pedido sin tocar ese código.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/checkout_stock_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('checkout', [], ['https_proxy' => 'http://127.0.0.1:9', 'HTTPS_PROXY' => 'http://127.0.0.1:9']);
$cliente = ['nombre' => 'Prueba', 'apellido' => 'Stock', 'rut' => '12.345.678-5', 'telefono' => '+56912345678', 'email' => 'prueba@example.com'];

function cuerpoCheckout(array $items): string {
    global $cliente;
    return json_encode(['items' => $items, 'cliente' => $cliente, 'acepta_terminos' => true]);
}
function checkout(array $e, array $items): array {
    $ch = curl_init($e['sitio'] . '/checkout.php');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => cuerpoCheckout($items)]);
    $r = json_decode((string)curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}
function contar(array $e, string $tabla): int {
    return (int)$e['db']->query("SELECT COUNT(*) FROM $tabla")->fetch_row()[0];
}

try {
    // Tres repuestos de prueba: A con 5 unidades, B sin stock, C con 1.
    $ids = array_column($e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 ORDER BY id_repuesto LIMIT 3")->fetch_all(), 0);
    [$a, $b, $c] = array_map('intval', $ids);
    $e['db']->query("UPDATE repuestos SET stock = CASE id_repuesto WHEN $a THEN 5 WHEN $b THEN 0 WHEN $c THEN 1 END WHERE id_repuesto IN ($a, $b, $c)");
    $nombre = fn(int $id) => $e['db']->query("SELECT nombre FROM repuestos WHERE id_repuesto = $id")->fetch_row()[0];

    echo "== Con stock suficiente se registra el pedido ==\n";
    $antes = contar($e, 'pedidos');
    $r = checkout($e, [['id' => $a, 'quantity' => 2]]);
    $pedido = $e['db']->query("SELECT p.estado, d.id_repuesto, d.cantidad FROM pedidos p JOIN pedido_detalle d ON d.id_pedido = p.id_pedido ORDER BY p.id_pedido DESC LIMIT 1")->fetch_assoc();
    revisar(contar($e, 'pedidos') === $antes + 1 && (int)$pedido['id_repuesto'] === $a && (int)$pedido['cantidad'] === 2,
            '2 de 5 unidades: el pedido queda registrado con su detalle');
    revisar(strpos($r['message'] ?? '', 'Mercado Pago') !== false && $pedido['estado'] === 'error',
            'después sigue a Mercado Pago (aquí sin conexión a propósito: el pedido queda en "error")');

    echo "\n== Sin stock suficiente no se crea nada (CP-15) ==\n";
    $casos = [
        '6 de 5 unidades'                         => [[['id' => $a, 'quantity' => 6]], ["{$nombre($a)} (quedan 5)"], [$a]],
        'producto sin stock'                      => [[['id' => $b, 'quantity' => 1]], ["{$nombre($b)} (sin stock)"], [$b]],
        '3 + 3 del mismo en líneas separadas'     => [[['id' => $a, 'quantity' => 3], ['id' => $a, 'quantity' => 3]], ['(quedan 5)'], [$a]],
        'uno con stock y otro sin: se nombra solo el que falta' => [[['id' => $a, 'quantity' => 1], ['id' => $b, 'quantity' => 1]], ["{$nombre($b)} (sin stock)"], [$b]],
    ];
    foreach ($casos as $nombreCaso => [$items, $textos, $idsFaltantes]) {
        $pedidosAntes = contar($e, 'pedidos');
        $clientesAntes = contar($e, 'clientes');
        $r = checkout($e, $items);
        $mensaje = $r['message'] ?? '';
        $ok = ($r['success'] ?? true) === false
            && strpos($mensaje, 'No hay stock suficiente') === 0
            && !array_filter($textos, fn($t) => strpos($mensaje, $t) === false)
            && array_map('intval', array_column($r['sin_stock'] ?? [], 'id')) === $idsFaltantes
            && contar($e, 'pedidos') === $pedidosAntes && contar($e, 'clientes') === $clientesAntes;
        revisar($ok, $nombreCaso, $mensaje);
    }
    revisar(strpos(checkout($e, [['id' => $a, 'quantity' => 1], ['id' => $b, 'quantity' => 1]])['message'] ?? '', $nombre($a)) === false,
            'el producto que sí tiene stock no aparece en el aviso');

    echo "\n== Bloqueo: el checkout espera a otra transacción y lee el stock nuevo (FOR UPDATE) ==\n";
    $otra = new mysqli('localhost', 'root', '', $e['base']);
    $otra->begin_transaction();
    $otra->query("SELECT stock FROM repuestos WHERE id_repuesto = $c FOR UPDATE"); // como si otra compra estuviera en curso
    $multi = curl_multi_init();
    $ch = curl_init($e['sitio'] . '/checkout.php');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => cuerpoCheckout([['id' => $c, 'quantity' => 1]])]);
    curl_multi_add_handle($multi, $ch);
    $inicio = microtime(true);
    $activos = 0;
    do {
        curl_multi_exec($multi, $activos);
        curl_multi_select($multi, 0.1);
    } while ($activos && microtime(true) - $inicio < 2);
    revisar($activos > 0, 'mientras la otra transacción tiene la fila, el checkout espera', round(microtime(true) - $inicio, 1) . ' s');
    $otra->query("UPDATE repuestos SET stock = 0 WHERE id_repuesto = $c"); // la otra compra se llevó la última unidad
    $otra->commit();
    do {
        curl_multi_exec($multi, $activos);
        curl_multi_select($multi, 0.1);
    } while ($activos);
    $r = json_decode((string)curl_multi_getcontent($ch), true) ?: [];
    curl_multi_remove_handle($multi, $ch);
    curl_multi_close($multi);
    revisar(strpos($r['message'] ?? '', '(sin stock)') !== false, 'al liberarse, ve el stock ya actualizado y no vende la última unidad dos veces', $r['message'] ?? '');

    echo "\n== Registro ==\n";
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
