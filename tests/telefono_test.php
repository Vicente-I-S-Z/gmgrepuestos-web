<?php
/**
 * F2-7 (E3-H3, RF-021): teléfono chileno en el formulario de pago.
 *   1. gmgTelefonoChile() acepta celulares (+56 9 y 8 dígitos, en varias escrituras) y los deja
 *      como "+56 9 1234 5678"; rechaza fijos, largos incorrectos y texto.
 *   2. validarTelefono() de js/cart.js da el mismo resultado en los mismos casos (con Node).
 *   3. checkout.php rechaza un teléfono inválido sin crear pedido ni cliente, y guarda el válido
 *      normalizado. Base temporal desde BD_v2 (tests/chat_entorno.php) y proxy inexistente para
 *      no llegar a Mercado Pago (igual que checkout_stock_test).
 * Requisitos: MariaDB de XAMPP encendido, Node en el PATH, puertos 8092 y 8093 libres.
 * Uso: php tests/telefono_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';
require_once __DIR__ . '/../backend/datos.php';

$casos = [
    '+56 9 1234 5678'    => '+56 9 1234 5678',
    '+56912345678'       => '+56 9 1234 5678',
    '56912345678'        => '+56 9 1234 5678',
    '912345678'          => '+56 9 1234 5678',
    '9 8765 4321'        => '+56 9 8765 4321',
    '(+56) 9-1234-5678'  => '+56 9 1234 5678',
    '+56 9.1234.5678'    => '+56 9 1234 5678',
    ''                   => '',
    'hola'               => '',
    '12345678'           => '',
    '+56 2 2345 6789'    => '',
    '+56 9 1234 567'     => '',
    '+56 9 1234 56789'   => '',
    '812345678'          => '',
    '+57 9 1234 5678'    => '',
    '0912345678'         => '',
    '+56 +56 912345678'  => '',
];

echo "== Regla en el servidor (gmgTelefonoChile) ==\n";
$malas = [];
foreach ($casos as $entrada => $esperado) {
    $obtenido = gmgTelefonoChile($entrada);
    if ($obtenido !== $esperado) {
        $malas[] = "\"$entrada\" dio \"$obtenido\"";
    }
}
revisar($malas === [], count($casos) . ' casos: celulares aceptados y normalizados, el resto rechazado', implode('; ', $malas));

echo "\n== Misma regla en el navegador (validarTelefono de js/cart.js) ==\n";
$js = file_get_contents(__DIR__ . '/../js/cart.js');
if (!preg_match('/function validarTelefono\(tel\) \{.*?\n    \}/s', $js, $m)) {
    revisar(false, 'se encontró validarTelefono en js/cart.js');
} else {
    $script = $m[0] . "\nconst casos = " . json_encode(array_keys(array_map('strval', $casos)), JSON_UNESCAPED_UNICODE)
            . ";\nprocess.stdout.write(JSON.stringify(casos.map(c => validarTelefono(String(c)))));";
    $archivo = sys_get_temp_dir() . '/gmg_telefono_' . uniqid() . '.js';
    file_put_contents($archivo, $script);
    $salida = json_decode((string)shell_exec('node ' . escapeshellarg($archivo)), true);
    unlink($archivo);
    $esperadoJs = array_values(array_map(function ($v) { return $v !== ''; }, $casos));
    revisar($salida === $esperadoJs, 'el navegador acepta y rechaza exactamente los mismos ' . count($casos) . ' casos', json_encode($salida));
}

echo "\n== checkout.php ==\n";
$e = entornoIniciar('telefono', [], ['https_proxy' => 'http://127.0.0.1:9', 'HTTPS_PROXY' => 'http://127.0.0.1:9']);
function pagar(array $e, int $idRepuesto, $telefono, string $rut): array {
    $cuerpo = json_encode(['items' => [['id' => $idRepuesto, 'quantity' => 1]], 'acepta_terminos' => true,
                           'cliente' => ['nombre' => 'Prueba', 'apellido' => 'Telefono', 'rut' => $rut, 'telefono' => $telefono, 'email' => 'prueba@example.com']]);
    $ch = curl_init($e['sitio'] . '/checkout.php');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => $cuerpo]);
    $r = json_decode((string)curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}
try {
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 ORDER BY id_repuesto LIMIT 1")->fetch_row()[0];
    $e['db']->query("UPDATE repuestos SET stock = 10 WHERE id_repuesto = $id");
    $contar = function ($tabla) use ($e) { return (int)$e['db']->query("SELECT COUNT(*) FROM $tabla")->fetch_row()[0]; };

    foreach (['hola', '12345678', '+56 2 2345 6789'] as $malo) {
        [$pedidos, $clientes] = [$contar('pedidos'), $contar('clientes')];
        $r = pagar($e, $id, $malo, '12.345.678-5');
        revisar(($r['success'] ?? true) === false && strpos($r['message'] ?? '', 'El teléfono no es válido') === 0
                && $contar('pedidos') === $pedidos && $contar('clientes') === $clientes,
                "\"$malo\": se rechaza sin crear pedido ni cliente", $r['message'] ?? '');
    }
    $r = pagar($e, $id, '9 8765 4321', '10.000.013-K');
    $guardado = $e['db']->query("SELECT telefono FROM clientes WHERE rut = '10000013-K'")->fetch_row()[0] ?? '';
    revisar($guardado === '+56 9 8765 4321', 'un celular válido se guarda normalizado', "$guardado / " . ($r['message'] ?? ''));
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
