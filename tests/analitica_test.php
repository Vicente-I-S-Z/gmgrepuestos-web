<?php
/**
 * F4-3 (E8-H6, RF-071): Google Analytics 4. Sin GA4_ID no se carga nada; con GA4_ID se miden
 * view_item, add_to_cart, chat_abierto, contacto_whatsapp y begin_checkout (revisados en dataLayer).
 * Edge resuelve googletagmanager.com a la propia máquina: la prueba no envía nada a Google.
 * Sitio con php -S (puerto 8107) sobre gmg_local. Requisitos: Edge o Chrome, MariaDB con gmg_local.
 * Uso: php tests/analitica_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

$db = new mysqli('localhost', 'root', '', 'gmg_local');
$id = (int)$db->query("SELECT id_repuesto FROM repuestos WHERE activo = 1 AND stock > 3 LIMIT 1")->fetch_row()[0];
$s = sitioIniciar(8107);
try {
    $html = (string)file_get_contents($s['url'] . "/producto.php?id=$id");
    revisar(strpos($html, 'googletagmanager') === false && strpos(file_get_contents($s['url'] . '/politica-de-privacidad.php'), 'Google Analytics') === false, 'sin GA4_ID no se carga Analytics ni se menciona en la política');
    file_put_contents($s['tmp'] . '/config.txt', "GA4_ID=G-PRUEBA123\n", FILE_APPEND);
    $html = (string)file_get_contents($s['url'] . "/producto.php?id=$id");
    revisar(strpos($html, 'googletagmanager.com/gtag/js?id=G-PRUEBA123') !== false && strpos(file_get_contents($s['url'] . '/politica-de-privacidad.php'), 'Google Analytics') !== false, 'con GA4_ID se carga gtag y la política lo explica');
    $cab = implode("\n", get_headers($s['url'] . '/index.php'));
    revisar(strpos($cab, 'https://www.googletagmanager.com') !== false && strpos($cab, 'google-analytics.com') !== false, 'la CSP permite Analytics');
    $r = navegadorResultado($s, "/tests/analitica_prueba.html?id=$id", 12000, ['--host-resolver-rules=MAP *.googletagmanager.com 127.0.0.1,MAP *.google-analytics.com 127.0.0.1']);
    revisar($r !== null && $r['hayGtag'], 'la página tiene gtag');
    if ($r) {
        $faltan = array_diff(['view_item', 'add_to_cart', 'chat_abierto', 'contacto_whatsapp', 'begin_checkout'], $r['eventos']);
        revisar($faltan === [], 'se miden los 5 eventos', 'faltan: ' . implode(', ', $faltan) . ' / hay: ' . implode(', ', $r['eventos']));
        revisar(($r['vistaItem']['item_id'] ?? '') === (string)$id && ($r['vistaItem']['price'] ?? 0) > 0, 'view_item lleva el id y el precio del repuesto');
    }
    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
