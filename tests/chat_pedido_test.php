<?php
/**
 * F3-12 (E5-H12, RF-047): estado del pedido en el chat con la herramienta consultar_pedido.
 * Con la API falsa (tests/fake_openai.php, sin gastar tokens) y una base temporal desde BD_v2:
 *   - con el número y el correo o el RUT correctos, la IA recibe estado, pago, productos y total;
 *   - con datos que no coinciden (o un pedido que no existe) recibe siempre el mismo aviso;
 *   - el resultado no se convierte en tarjeta de producto; más de 5 consultas en 10 min se frenan.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/chat_pedido_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('chatpedido');
try {
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos LIMIT 1")->fetch_row()[0];
    $e['db']->query("INSERT INTO clientes (id_cliente, rut, nombre, apellido, email, telefono) VALUES (1, '12345678-5', 'Ana', 'Pérez', 'ana@example.com', '+56 9 1111 2222')");
    $e['db']->query("INSERT INTO pedidos (id_pedido, id_cliente, estado, pago_estado, total) VALUES (125, 1, 'aprobado', 'recibido', 31980)");
    $e['db']->query("INSERT INTO pedido_detalle (id_pedido, id_repuesto, sku, nombre_repuesto, cantidad, precio_unitario) SELECT 125, id_repuesto, sku, nombre, 2, 15990 FROM repuestos WHERE id_repuesto = $id");
    $preguntar = function (string $texto) use ($e) {
        $r = chat($e, ['message' => $texto, 'sesion_id' => 'sesion-' . uniqid()]);
        $json = json_decode(preg_replace('/^Resultado del pedido: /', '', (string)($r['reply'] ?? '')), true);
        $json = is_array($json) && isset($json[0]) ? $json[0] : $json; // los datos llegan como lista de un elemento
        return [$r, is_array($json) ? $json : []];
    };
    [$r, $datos] = $preguntar('consulta pedido 125 con 12.345.678-5');
    revisar(($datos['estado'] ?? '') === 'aprobado' && ($datos['pago'] ?? '') === 'recibido' && ($datos['total_clp'] ?? 0) === 31980 && count($datos['productos'] ?? []) === 1,
            'con número y RUT (con puntos): estado, pago, total y productos', json_encode($datos, JSON_UNESCAPED_UNICODE));
    revisar(($r['products'] ?? []) === [], 'el pedido no aparece como tarjeta de producto');
    [, $datos] = $preguntar('consulta pedido 125 con ANA@example.com');
    revisar(($datos['pedido'] ?? 0) === 125, 'también con el correo (sin importar mayúsculas)');
    [, $malRut] = $preguntar('consulta pedido 125 con 11.111.111-1');
    [, $noExiste] = $preguntar('consulta pedido 999 con 12.345.678-5');
    revisar(isset($malRut['aviso']) && $malRut === $noExiste && !isset($malRut['estado']), 'RUT que no coincide y pedido inexistente: el mismo aviso, sin datos');
    $preguntar('consulta pedido 125 con 12.345.678-5'); // quinta
    [, $sexta] = $preguntar('consulta pedido 125 con 12.345.678-5');
    revisar(strpos($sexta['aviso'] ?? '', 'muchas consultas') !== false, 'la sexta consulta en 10 minutos se frena', json_encode($sexta, JSON_UNESCAPED_UNICODE));
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
