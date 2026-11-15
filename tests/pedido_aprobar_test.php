<?php
/**
 * F2-3 (E4-H3, RF-033): al aprobar un pedido en admin/pedidos.php se descuenta el stock una sola
 * vez, con bloqueo (FOR UPDATE), sin dejarlo negativo y con auditoría.
 * Base temporal desde BD_v2 y sitio con php -S (tests/chat_entorno.php); entra al panel con un
 * administrador de prueba y envía el formulario real (con CSRF). No usa ni lee el .env.
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/pedido_aprobar_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('aprobar');
$cookies = $e['tmp'] . '/cookies.txt';

function panel(array $e, string $ruta, ?array $post = null): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $html = (string)curl_exec($ch);
    curl_close($ch);
    return $html;
}
function token(array $e): string {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', panel($e, '/admin/pedidos.php'), $m);
    return $m[1] ?? '';
}
function datosAprobar(array $e, int $pedido, string $estado = 'aprobado'): array {
    return ['csrf' => token($e), 'accion' => 'actualizar_estado', 'id' => $pedido, 'estado' => $estado];
}
function cambiarEstado(array $e, int $pedido, string $estado = 'aprobado'): string {
    $html = panel($e, '/admin/pedidos.php', datosAprobar($e, $pedido, $estado));
    return preg_match('/class="flash-msg[^"]*">([^<]*)</', $html, $m) ? html_entity_decode($m[1]) : '';
}
function valor(array $e, string $sql) {
    $fila = $e['db']->query($sql)->fetch_row();
    return $fila ? $fila[0] : null;
}
function stock(array $e, int $id): int {
    return (int)valor($e, "SELECT stock FROM repuestos WHERE id_repuesto = $id");
}
function estado(array $e, int $pedido): string {
    return (string)valor($e, "SELECT estado FROM pedidos WHERE id_pedido = $pedido");
}
/** Crea un pedido pendiente con [id_repuesto => cantidad]. */
function pedido(array $e, array $lineas, string $estado = 'pendiente'): int {
    $e['db']->query("INSERT INTO pedidos (id_cliente, estado, total) VALUES (1, '$estado', 1000)");
    $id = $e['db']->insert_id;
    foreach ($lineas as $rep => $cant) {
        $e['db']->query("INSERT INTO pedido_detalle (id_pedido, id_repuesto, sku, nombre_repuesto, cantidad, precio_unitario)
                         SELECT $id, id_repuesto, sku, nombre, $cant, precio FROM repuestos WHERE id_repuesto = $rep");
    }
    return $id;
}
function auditorias(array $e, string $accion): int {
    return (int)valor($e, "SELECT COUNT(*) FROM auditoria WHERE accion = '$accion'");
}

try {
    $e['db']->query("INSERT INTO clientes (id_cliente, rut, nombre, apellido, email, telefono) VALUES (1, '12345678-5', 'Prueba', 'Stock', 'prueba@example.com', '+56912345678')");
    $hash = $e['db']->real_escape_string(password_hash('Clave-F23', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f23', 'Prueba F2-3', '$hash', 1)");
    panel($e, '/login.php', ['usuario' => 'prueba_f23', 'password' => 'Clave-F23']);
    revisar(strpos(panel($e, '/admin/pedidos.php'), '<h1>Pedidos</h1>') !== false, 'entra al panel de pedidos con el administrador de prueba');

    $ids = array_map('intval', array_column($e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 ORDER BY id_repuesto LIMIT 4")->fetch_all(), 0));
    [$a, $b, $c, $d] = $ids;
    $e['db']->query("UPDATE repuestos SET stock = CASE id_repuesto WHEN $a THEN 5 WHEN $b THEN 1 WHEN $c THEN 2 WHEN $d THEN 4 END WHERE id_repuesto IN ($a, $b, $c, $d)");

    echo "== Aprobar descuenta el stock y lo audita ==\n";
    $p1 = pedido($e, [$a => 2, $d => 1]);
    $msg = cambiarEstado($e, $p1);
    revisar(estado($e, $p1) === 'aprobado' && stock($e, $a) === 3 && stock($e, $d) === 3, 'pedido de 2 + 1 unidades: stock 5 a 3 y 4 a 3', $msg);
    revisar(strpos($msg, 'Se descontó el stock') !== false, 'el panel avisa que se descontó el stock');
    revisar(valor($e, "SELECT stock_descontado_en IS NOT NULL FROM pedidos WHERE id_pedido = $p1") == 1, 'el pedido queda marcado como descontado');
    revisar(auditorias($e, 'stock') === 2 && auditorias($e, 'estado_pedido') === 1, 'auditoría: 2 cambios de stock y 1 de estado');
    revisar((int)valor($e, "SELECT COUNT(*) FROM auditoria WHERE accion = 'stock' AND detalle LIKE '%pedido #$p1 aprobado%' AND valor_anterior = '5' AND valor_nuevo = '3'") === 1,
            'la línea de auditoría dice el pedido y el antes y después');

    echo "\n== Una sola vez (idempotente) ==\n";
    cambiarEstado($e, $p1, 'pendiente');
    $msg = cambiarEstado($e, $p1);
    revisar(estado($e, $p1) === 'aprobado' && stock($e, $a) === 3 && stock($e, $d) === 3, 'pasar a pendiente y aprobar de nuevo no descuenta otra vez', $msg);
    revisar(auditorias($e, 'stock') === 2, 'sin auditorías de stock nuevas');
    $e['db']->query("INSERT INTO pedidos (id_cliente, estado, total) VALUES (1, 'aprobado', 1000)");
    $viejo = $e['db']->insert_id;
    $e['db']->multi_query(file_get_contents($e['raiz'] . '/BD_v2/07_stock_descontado.sql'));
    while ($e['db']->more_results() && $e['db']->next_result()) {
    }
    revisar(valor($e, "SELECT stock_descontado_en IS NOT NULL FROM pedidos WHERE id_pedido = $viejo") == 1,
            'el script 07 marca como descontados los pedidos que ya estaban aprobados (y se puede repetir)');

    echo "\n== Dos pedidos por la última unidad ==\n";
    $p2 = pedido($e, [$b => 1]);
    $p3 = pedido($e, [$b => 1]);
    cambiarEstado($e, $p2);
    $msg = cambiarEstado($e, $p3);
    revisar(estado($e, $p2) === 'aprobado' && stock($e, $b) === 0, 'el primero se aprueba y se lleva la última unidad');
    revisar(estado($e, $p3) === 'pendiente' && stock($e, $b) === 0 && strpos($msg, 'No se aprobó el pedido #' . $p3) === 0 && strpos($msg, 'quedan 0') !== false,
            'el segundo no se aprueba, el stock no queda negativo y el panel dice qué falta', $msg);

    echo "\n== Todo o nada ==\n";
    $p4 = pedido($e, [$a => 1, $c => 3]);
    $msg = cambiarEstado($e, $p4);
    revisar(estado($e, $p4) === 'pendiente' && stock($e, $a) === 3 && stock($e, $c) === 2 && strpos($msg, 'pide 3, quedan 2') !== false,
            'si falta stock de un producto, no se descuenta ninguno', $msg);
    revisar(strpos($msg, valor($e, "SELECT nombre FROM repuestos WHERE id_repuesto = $a")) === false, 'el aviso nombra solo el producto que falta');
    $p5 = pedido($e, [$a => 1, $c => 1]);
    $e['db']->query("UPDATE pedido_detalle SET id_repuesto = NULL WHERE id_pedido = $p5 AND id_repuesto = $c"); // repuesto eliminado
    $msg = cambiarEstado($e, $p5);
    revisar(estado($e, $p5) === 'aprobado' && stock($e, $a) === 2 && stock($e, $c) === 2, 'una línea de un repuesto eliminado no impide aprobar ni descuenta', $msg);
    cambiarEstado($e, $p4, 'rechazado');
    revisar(estado($e, $p4) === 'rechazado' && stock($e, $a) === 2, 'rechazar un pedido no toca el stock');

    echo "\n== Bloqueo: aprobar espera a otra transacción y lee el stock nuevo (FOR UPDATE) ==\n";
    $p6 = pedido($e, [$d => 3]);
    $datos = datosAprobar($e, $p6);
    $otra = new mysqli('localhost', 'root', '', $e['base']);
    $otra->begin_transaction();
    $otra->query("SELECT stock FROM repuestos WHERE id_repuesto = $d FOR UPDATE"); // como si otro pedido se estuviera aprobando
    $multi = curl_multi_init();
    $ch = curl_init($e['sitio'] . '/admin/pedidos.php');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_COOKIEFILE => $cookies, CURLOPT_POSTFIELDS => http_build_query($datos)]);
    curl_multi_add_handle($multi, $ch);
    $inicio = microtime(true);
    $activos = 0;
    do {
        curl_multi_exec($multi, $activos);
        curl_multi_select($multi, 0.1);
    } while ($activos && microtime(true) - $inicio < 2);
    revisar($activos > 0, 'mientras la otra transacción tiene la fila, la aprobación espera', round(microtime(true) - $inicio, 1) . ' s');
    $otra->query("UPDATE repuestos SET stock = 1 WHERE id_repuesto = $d"); // el otro pedido se llevó 2 unidades
    $otra->commit();
    do {
        curl_multi_exec($multi, $activos);
        curl_multi_select($multi, 0.1);
    } while ($activos);
    curl_multi_remove_handle($multi, $ch);
    curl_multi_close($multi);
    revisar(estado($e, $p6) === 'pendiente' && stock($e, $d) === 1, 'al liberarse, ve el stock nuevo (1) y no aprueba un pedido de 3');

    echo "\n== Registro ==\n";
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
