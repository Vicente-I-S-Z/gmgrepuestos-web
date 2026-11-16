<?php
/**
 * Fotos desde la cámara del celular en el chat (pedido del 15-11):
 *   - "📸 Tomar foto" abre un campo con capture="environment" (cámara trasera); el clip abre la galería;
 *   - una foto grande (más de 4 MB, como las de un celular) se reduce en el navegador a JPEG de máximo
 *     1600 px antes de enviarse; una chica pasa sin cambios; máximo 3 fotos por mensaje.
 * En Edge sin ventana con la tienda real (php -S, puerto 8112, gmg_local). El envío se intercepta: no llama a la IA.
 * Requisitos: Edge o Chrome, MariaDB con gmg_local.
 * Uso: php tests/chat_camara_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$s = sitioIniciar(8112);
try {
    $r = navegadorResultado($s, '/tests/chat_camara_prueba.html', 20000);
    revisar($r !== null, 'la página de prueba entregó resultado');
    if ($r) {
        revisar($r['capture'] === 'environment' && $r['galeriaSinCapture'], 'campo de cámara con capture="environment"; el de galería sin capture');
        revisar($r['chipAbreCamara'], '"📸 Tomar foto" abre la cámara');
        $grande = $r['enviadas'][0] ?? [];
        revisar($r['pesoGrandeMB'] > 4 && ($grande['tipo'] ?? '') === 'image/jpeg' && max($grande['ancho'] ?? 0, $grande['alto'] ?? 0) === 1600 && ($grande['kb'] ?? 9999) < 1024,
                "una foto de {$r['pesoGrandeMB']} MB se envía como JPEG de 1600 px y menos de 1 MB", json_encode($grande));
        $chica = $r['enviadas'][1] ?? [];
        revisar(($chica['ancho'] ?? 0) === 400 && ($chica['nombre'] ?? '') === 'chica.jpg', 'una foto chica se envía tal cual', json_encode($chica));
        revisar($r['miniaturas'] === 3 && count($r['enviadas']) === 3 && strpos($r['aviso'], 'máximo de 3') !== false, 'máximo 3 fotos por mensaje, con aviso', $r['aviso']);
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
