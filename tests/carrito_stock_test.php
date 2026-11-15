<?php
/**
 * F2-2 (E3-H2, RF-020): el carrito no deja superar el stock, desde la tienda y desde el chat.
 * Abre tests/carrito_stock_prueba.html en Edge o Chrome sin ventana con la tienda real
 * (php -S, puerto 8087, base gmg_local con stock de prueba, sin leer el .env).
 * Requisitos: Edge o Chrome, MariaDB con gmg_local (ELE-M16A con productos de 2 y de 8 unidades).
 * Uso: php tests/carrito_stock_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/navegador.php';

if (!navegadorRuta()) {
    exit("No se encontró Edge ni Chrome.\n");
}
$s = sitioIniciar(8087);
try {
    $r = navegadorResultado($s, '/tests/carrito_stock_prueba.html');
    if ($r === null) {
        exit("La página de prueba no entregó resultado.\n");
    }
    echo "== Tienda ==\n";
    revisar($r['hayProductoCon2'] === true, 'hay un producto con 2 unidades en la búsqueda');
    revisar($r['tiendaCantidad'] === 2, '3 clics en "Añadir al carrito" dejan 2 (el stock)', (string)$r['tiendaCantidad']);
    revisar(strpos($r['tiendaAviso'], 'Solo quedan 2 unidades') === 0, 'se avisa por qué no se agregó la tercera', $r['tiendaAviso']);

    echo "\n== Chat ==\n";
    revisar($r['chatNueve'] === 0 && strpos($r['chatAviso'], 'Solo quedan 8 unidades') === 0, 'pedir 9 de un producto con 8 no agrega nada y avisa', $r['chatAviso']);
    revisar($r['chatOcho'] === 8, 'pedir 8 sí se agrega');
    revisar($r['chatUnoMas'] === 8, 'una unidad más ya no cabe');

    echo "\n== Sin dato de stock ==\n";
    revisar($r['sinDato'] === 50, 'un producto guardado antes del cambio se agrega sin tope (el servidor revisa al pagar)');
    revisar($r['stocksGuardados'] === [2, 8, null], 'el stock se guarda con cada ítem del carrito', json_encode($r['stocksGuardados']));

    $errores = sitioErrores($s);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    sitioTerminar($s);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
