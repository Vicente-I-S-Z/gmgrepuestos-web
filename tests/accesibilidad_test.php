<?php
/**
 * F4-8 (E9-H5, RNF-026): accesibilidad básica de la tienda.
 * En Edge sin ventana (con movimiento reducido forzado) sobre la tienda real (php -S, puerto 8103, gmg_local).
 * Requisitos: Edge o Chrome, MariaDB con gmg_local.
 * Uso: php tests/accesibilidad_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$id = (int)$db->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 AND stock = 0 LIMIT 1")->fetch_row()[0];
$s = sitioIniciar(8103);
try {
    $r = navegadorResultado($s, "/tests/accesibilidad_prueba.html?id=$id", 12000, ['--force-prefers-reduced-motion']);
    revisar($r !== null, 'la página de prueba entregó resultado');
    if ($r) {
        revisar($r['toggleEsBoton'] && $r['ariaAbierto'] === 'true' && $r['ariaCerrado'] === 'false', 'el chat se abre con un <button> que avisa si está abierto (aria-expanded)');
        revisar($r['camposSinNombre'] === [], 'todos los campos y botones tienen nombre accesible', implode(', ', array_slice($r['camposSinNombre'], 0, 5)));
        revisar($r['fotosSinAlt'] === 0, 'todas las fotos de productos tienen alt', (string)$r['fotosSinAlt']);
        $bajos = array_filter($r['contrastes'], function ($c) { return $c < 4.5; });
        revisar(count($r['contrastes']) >= 8 && !$bajos, 'contraste de 4,5:1 o más en ' . count($r['contrastes']) . ' textos clave', json_encode($bajos ?: $r['contrastes'], JSON_UNESCAPED_UNICODE));
        revisar($r['focoVisible'], 'hay estilo de foco visible (:focus-visible)');
        revisar($r['movimientoReducido'] && in_array($r['transicionWhatsapp'], ['0s', ''], true), 'con movimiento reducido se apagan las animaciones', $r['transicionWhatsapp']);
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
