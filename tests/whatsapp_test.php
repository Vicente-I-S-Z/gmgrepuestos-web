<?php
/**
 * F3-6 (E5-H7, E7-H4, E3-H11, RF-042, RF-065, RF-029): WhatsApp desde la tienda, el chat y el carrito.
 * En Edge sin ventana con la tienda real (php -S, puerto 8097, base gmg_local, WHATSAPP_NUMBER de prueba).
 * Requisitos: Edge o Chrome, MariaDB con gmg_local.
 * Uso: php tests/whatsapp_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$id = (int)$db->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 AND stock > 3 ORDER BY id_repuesto LIMIT 1")->fetch_row()[0];
$sku = (string)$db->query("SELECT sku FROM repuestos WHERE id_repuesto = $id")->fetch_row()[0];
$s = sitioIniciar(8097);
try {
    $r = navegadorResultado($s, "/tests/whatsapp_prueba.html?id=$id", 12000);
    revisar($r !== null, 'la página de prueba entregó resultado');
    if ($r) {
        revisar($r['numero'] === 'https://wa.me/56911112222', 'usa el número configurado', $r['numero']);
        revisar($r['flotanteModelo'] === 'Hola, busco un repuesto para mi Suzuki swift' || stripos($r['flotanteModelo'], 'suzuki swift') !== false, 'botón flotante en ?modelo=swift: menciona el modelo', $r['flotanteModelo']);
        revisar(strpos($r['flotanteFicha'], $sku) !== false && strpos($r['flotanteFicha'], $r['nombre']) !== false, 'botón flotante en la ficha: nombre y SKU', $r['flotanteFicha']);
        revisar(strpos($r['resumenChat'], 'Mi consulta: Necesito pastillas de freno para Swift 2019') !== false
                && strpos($r['resumenChat'], 'Pastillas delanteras Swift 3ª gen. (SKU FRE-SWI3-PDL)') !== false, 'chat: resumen con la consulta (modelo y año) y los repuestos mostrados', str_replace("\n", ' | ', $r['resumenChat']));
        revisar($r['carritoVacioOculto'] === true && $r['carritoVisible'] === true, '"Finalizar por WhatsApp" se oculta con el carrito vacío');
        $total = '$' . number_format($r['precio'] * 2, 0, ',', '.');
        revisar(strpos($r['pedidoCarrito'], "- 2 x {$r['nombre']}: $total") !== false && strpos($r['pedidoCarrito'], "Total: $total CLP") !== false, 'carrito: productos, cantidades y total', str_replace("\n", ' | ', $r['pedidoCarrito']));
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
