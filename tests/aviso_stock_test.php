<?php
/**
 * F3-11 (E2-H9, RF-016): aviso de reposición en la tienda para repuestos sin stock.
 *   - tarjetas del listado y ficha muestran el formulario solo cuando el stock es 0;
 *   - en Edge: contacto inválido avisa sin enviar, un correo válido queda registrado;
 *   - el servidor acepta correo o celular chileno (normalizado) y rechaza lo demás.
 * Sitio con php -S (puerto 8098) sobre gmg_local; borra los avisos que crea.
 * Requisitos: Edge o Chrome, MariaDB con gmg_local (con repuestos sin stock).
 * Uso: php tests/aviso_stock_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$db->set_charset('utf8mb4');
$sinStock = (int)$db->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 AND stock = 0 ORDER BY id_repuesto LIMIT 1")->fetch_row()[0];
$conStock = (int)$db->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 AND stock > 0 ORDER BY id_repuesto LIMIT 1")->fetch_row()[0];
$maxAviso = (int)$db->query("SELECT COALESCE(MAX(id_aviso), 0) FROM avisos_stock")->fetch_row()[0];
$db->query("DELETE FROM limites_solicitudes WHERE clave LIKE 'aviso_stock:%'");
$s = sitioIniciar(8098);
function avisar(array $s, int $id, string $contacto): array {
    $ch = curl_init($s['url'] . '/backend/registrar_aviso_stock.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_POSTFIELDS => json_encode(['producto_id' => $id, 'contacto' => $contacto])]);
    $r = json_decode((string)curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}
try {
    echo "== Dónde aparece ==\n";
    $html = (string)file_get_contents($s['url'] . '/index.php?categoria=Frenos');
    preg_match_all('~<div class="producto-card" data-producto-id="(\d+)">(.*?)<div class="producto-card"~s', $html . '<div class="producto-card"', $tarjetas, PREG_SET_ORDER);
    $malas = 0;
    foreach ($tarjetas as [, $id, $cuerpo]) {
        $stock = (int)$db->query("SELECT stock FROM repuestos WHERE id_repuesto = " . (int)$id)->fetch_row()[0];
        $tiene = strpos($cuerpo, 'class="aviso-stock"') !== false;
        $malas += ($tiene !== ($stock === 0)) ? 1 : 0;
    }
    revisar(count($tarjetas) > 0 && $malas === 0, 'en el listado, el aviso aparece solo en las tarjetas sin stock', count($tarjetas) . " tarjetas, $malas mal");
    revisar(strpos((string)file_get_contents($s['url'] . "/producto.php?id=$sinStock"), 'class="aviso-stock"') !== false
            && strpos((string)file_get_contents($s['url'] . "/producto.php?id=$conStock"), 'class="aviso-stock"') === false, 'en la ficha, solo si no hay stock');

    echo "\n== En el navegador ==\n";
    $r = navegadorResultado($s, "/tests/aviso_stock_prueba.html?id=$sinStock", 12000);
    revisar($r && $r['hayFormulario'], 'la ficha sin stock tiene el formulario');
    if ($r && $r['hayFormulario']) {
        revisar($r['invalidoEsError'] && strpos($r['invalido'], 'correo o un celular') !== false, '"hola": avisa el error sin enviar', $r['invalido']);
        revisar($r['validoEsOk'] && strpos($r['valido'], '¡Listo!') === 0 && $r['inputBloqueado'], 'un correo válido: confirmación y campo bloqueado', $r['valido']);
        $guardado = $db->query("SELECT contacto FROM avisos_stock WHERE id_aviso > $maxAviso AND id_repuesto = $sinStock")->fetch_row()[0] ?? '';
        revisar($guardado === 'cliente.prueba.f311@example.com', 'queda en avisos_stock (correo en minúsculas)', $guardado);
    }

    echo "\n== Servidor ==\n";
    $r = avisar($s, $sinStock, '9 8765 4321');
    $guardado = $db->query("SELECT contacto FROM avisos_stock WHERE id_aviso > $maxAviso ORDER BY id_aviso DESC LIMIT 1")->fetch_row()[0] ?? '';
    revisar(($r['status'] ?? '') === 'success' && $guardado === '+56 9 8765 4321', 'celular: se guarda normalizado', $guardado);
    $r = avisar($s, $sinStock, 'mi vecino');
    revisar(($r['status'] ?? '') === 'error' && strpos($r['mensaje'] ?? '', 'correo o un celular') !== false, 'texto que no es correo ni celular: rechazado');
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    $db->query("DELETE FROM avisos_stock WHERE id_aviso > $maxAviso");
    $db->query("DELETE FROM limites_solicitudes WHERE clave LIKE 'aviso_stock:%'");
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
