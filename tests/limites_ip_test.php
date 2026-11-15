<?php
/**
 * F3-3 (E10-H3, RNF-035): límites por IP en los endpoints públicos que no tenían.
 *   sugerencias 60/min, código de descuento 10/min, aviso de stock 5 cada 10 min, checkout 10 cada 10 min.
 * También: checkout y aviso de stock con campos enviados como arreglo responden un mensaje (antes, error 500).
 * Base temporal desde BD_v2 (tests/chat_entorno.php), sin llegar a Mercado Pago (proxy inexistente).
 * Requisitos: MariaDB de XAMPP encendido, puertos 8092 y 8093 libres.
 * Uso: php tests/limites_ip_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('limites', [], ['https_proxy' => 'http://127.0.0.1:9', 'HTTPS_PROXY' => 'http://127.0.0.1:9']);
function llamar(array $e, string $ruta, ?array $json = null): array {
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    if ($json !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($json)]);
    }
    $cuerpo = (string)curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['codigo' => $codigo, 'json' => json_decode($cuerpo, true) ?: []];
}
try {
    $id = (int)$e['db']->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 LIMIT 1")->fetch_row()[0];
    $e['db']->query("INSERT INTO codigos_descuento (codigo, tipo, valor, activo) VALUES ('PRUEBA10', 'porcentaje', 10, 1)");
    $cliente = ['nombre' => 'A', 'apellido' => 'B', 'rut' => '12.345.678-5', 'telefono' => '+56912345678', 'email' => 'a@example.com'];

    echo "== Campos como arreglo ==\n";
    $r = llamar($e, '/checkout.php', ['items' => [['id' => $id, 'quantity' => 1]], 'acepta_terminos' => true, 'cliente' => ['nombre' => ['x'], 'rut' => ['y']] + $cliente]);
    revisar($r['codigo'] === 200 && ($r['json']['success'] ?? true) === false && strpos($r['json']['message'] ?? '', 'Faltan datos') === 0, 'checkout con nombre y RUT como arreglo: mensaje, no error 500', (string)$r['codigo']);
    $r = llamar($e, '/backend/registrar_aviso_stock.php', ['producto_id' => $id, 'contacto' => ['x']]);
    revisar($r['codigo'] === 200 && ($r['json']['status'] ?? '') === 'error', 'aviso de stock con contacto como arreglo: mensaje, no error 500', (string)$r['codigo']);
    $r = llamar($e, '/backend/validar_codigo.php', ['codigo' => ['PRUEBA10'], 'subtotal' => 1000]);
    revisar($r['codigo'] === 200 && ($r['json']['valido'] ?? true) === false, 'código como arreglo: mensaje, no error 500', (string)$r['codigo']);
    $e['db']->query("DELETE FROM limites_solicitudes");

    $casos = [
        'sugerencias'     => [60, fn() => llamar($e, '/backend/buscar_sugerencias.php?q=filtro'), fn($j) => count($j['sugerencias'] ?? []) > 0],
        'código de descuento' => [10, fn() => llamar($e, '/backend/validar_codigo.php', ['codigo' => 'PRUEBA10', 'subtotal' => 50000]), fn($j) => ($j['valido'] ?? false) === true],
        'aviso de stock'  => [5, fn() => llamar($e, '/backend/registrar_aviso_stock.php', ['producto_id' => $id, 'contacto' => 'a@example.com']), fn($j) => ($j['status'] ?? '') === 'success'],
        'checkout'        => [10, fn() => llamar($e, '/checkout.php', ['items' => [['id' => $id, 'quantity' => 1]], 'acepta_terminos' => true, 'cliente' => $cliente]),
                              fn($j) => strpos($j['message'] ?? '', 'Mercado Pago') !== false],
    ];
    foreach ($casos as $nombre => [$maximo, $pedir, $normal]) {
        echo "\n== $nombre ($maximo) ==\n";
        $buenas = 0;
        for ($i = 1; $i <= $maximo; $i++) {
            $buenas += $normal(($pedir())['json']) ? 1 : 0;
        }
        revisar($buenas === $maximo, "las primeras $maximo responden normal", "$buenas de $maximo");
        $r = $pedir();
        revisar($r['codigo'] === 200 && !$normal($r['json']), 'la siguiente se bloquea', json_encode($r['json'], JSON_UNESCAPED_UNICODE));
    }
    $avisos = (int)$e['db']->query("SELECT COUNT(*) FROM avisos_stock")->fetch_row()[0];
    revisar($avisos === 5, 'solo quedaron 5 avisos de stock registrados', (string)$avisos);
    $claves = $e['db']->query("SELECT clave FROM limites_solicitudes")->fetch_all();
    revisar(!array_filter($claves, fn($c) => strpos($c[0], '127.0.0.1') !== false), 'la IP se guarda como hash, nunca en claro');
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
