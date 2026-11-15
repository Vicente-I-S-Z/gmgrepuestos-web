<?php
/**
 * F2-8 (E6-H2, E6-H3, RF-051, RF-052): panel de productos con fotos subidas y edición rápida.
 * Casos del Plan de Pruebas v2: CP-40 (crear, editar y desactivar con auditoría), CP-41
 * (validaciones y SKU repetido sin dejar nada huérfano) y CP-46 (subidas inválidas; también
 * la foto de un modelo nuevo en el catálogo, que usa la misma función). VL-18: 2 MB exactos sí,
 * 2 MB + 1 byte no.
 * Base temporal desde BD_v2 y sitio con php -S (tests/chat_entorno.php); entra al panel con un
 * administrador de prueba y envía los formularios reales (con CSRF). Las fotos se guardan en
 * img/repuestos e img/modelos del proyecto: la prueba borra al final todo archivo nuevo.
 * Requisitos: MariaDB de XAMPP encendido, extensión GD, puertos 8092 y 8093 libres.
 * Uso: php tests/panel_productos_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$raiz = dirname(__DIR__);
$archivosAntes = array_merge(glob("$raiz/img/repuestos/*"), glob("$raiz/img/repuestos/min/*"), glob("$raiz/img/modelos/*"));
$e = entornoIniciar('productos');
$cookies = $e['tmp'] . '/cookies.txt';

// --- Imágenes y archivos de prueba ---
function imagenPrueba(string $ruta, string $tipo, int $ancho = 800, int $alto = 600): string {
    $img = imagecreatetruecolor($ancho, $alto);
    imagefill($img, 0, 0, imagecolorallocate($img, 211, 47, 47));
    $tipo === 'jpg' ? imagejpeg($img, $ruta) : ($tipo === 'png' ? imagepng($img, $ruta) : imagewebp($img, $ruta));
    imagedestroy($img);
    return $ruta;
}
$t = $e['tmp'];
$jpg = imagenPrueba("$t/foto.jpg", 'jpg');
$png = imagenPrueba("$t/foto.png", 'png');
$webp = imagenPrueba("$t/foto.webp", 'webp');
copy($jpg, "$t/dos_mb.jpg");
file_put_contents("$t/dos_mb.jpg", str_repeat("\0", 2 * 1024 * 1024 - filesize($jpg)), FILE_APPEND); // VL-18: justo 2 MB
copy("$t/dos_mb.jpg", "$t/dos_mb_mas_uno.jpg");
file_put_contents("$t/dos_mb_mas_uno.jpg", "\0", FILE_APPEND);
file_put_contents("$t/documento.pdf", "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF");
file_put_contents("$t/script.jpg", '<?php echo "hola"; ?>');

// --- Ayudas del panel ---
function panel(array $e, string $ruta, ?array $post = null, bool $multipart = false): string {
    global $cookies;
    $ch = curl_init($e['sitio'] . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $multipart ? $post : http_build_query($post)]);
    }
    $html = (string)curl_exec($ch);
    curl_close($ch);
    return $html;
}
function token(array $e, string $pagina = '/admin/productos.php'): string {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', panel($e, $pagina), $m);
    return $m[1] ?? '';
}
function aviso(string $html): string {
    return preg_match('/class="flash-msg[^"]*">([^<]*)</', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
}
/** Envía el formulario completo de producto. $fotos: [n => ruta local]; $quitar: [n, ...]. */
function guardar(array $e, array $datos, array $fotos = [], array $quitar = []): string {
    $post = $datos + ['csrf' => token($e), 'accion' => 'guardar', 'activo' => '1'];
    foreach ($fotos as $n => $ruta) {
        $post["foto_$n"] = new CURLFile($ruta, 'image/jpeg', basename($ruta));
    }
    foreach ($quitar as $n) {
        $post["quitar_foto_$n"] = '1';
    }
    if ($post['activo'] === null) {
        unset($post['activo']);
    }
    return aviso(panel($e, '/admin/productos.php', $post, true));
}
function rapido(array $e, int $id, string $precio, string $stock, bool $conCsrf = true): string {
    $post = ['accion' => 'rapido', 'id' => $id, 'precio' => $precio, 'stock' => $stock];
    if ($conCsrf) {
        $post['csrf'] = token($e);
    }
    return aviso(panel($e, '/admin/productos.php', $post));
}
function fila(array $e, string $sql): ?array {
    $r = $e['db']->query($sql)->fetch_assoc();
    return $r ?: null;
}
function fotos(array $e, int $id): array {
    return array_column($e['db']->query("SELECT orden, url FROM repuesto_imagenes WHERE id_repuesto = $id ORDER BY orden")->fetch_all(MYSQLI_ASSOC), 'url', 'orden');
}
function auditorias(array $e, int $id, string $accion): array {
    return $e['db']->query("SELECT valor_anterior, valor_nuevo, admin_nombre FROM auditoria WHERE entidad = 'repuesto' AND id_entidad = $id AND accion = '$accion' ORDER BY id_auditoria")->fetch_all(MYSQLI_ASSOC);
}
function archivosNuevos(): array {
    global $raiz, $archivosAntes;
    return array_values(array_diff(array_merge(glob("$raiz/img/repuestos/*"), glob("$raiz/img/repuestos/min/*"), glob("$raiz/img/modelos/*")), $archivosAntes));
}
function enTienda(array $e, int $id): bool {
    return strpos((string)@file_get_contents($e['sitio'] . '/index.php?q=PRUEBA-001'), 'data-producto-id="' . $id . '"') !== false;
}

try {
    $hash = $e['db']->real_escape_string(password_hash('Clave-F28', PASSWORD_DEFAULT));
    $e['db']->query("INSERT INTO administradores (usuario, nombre, password_hash, activo) VALUES ('prueba_f28', 'Prueba F2-8', '$hash', 1)");
    panel($e, '/login.php', ['usuario' => 'prueba_f28', 'password' => 'Clave-F28']);
    revisar(strpos(panel($e, '/admin/productos.php'), '<h1>Productos</h1>') !== false, 'entra al panel de productos');
    $idCategoria = (int)fila($e, "SELECT id_categoria FROM categorias WHERE nombre = 'Frenos'")['id_categoria'];
    $idVehiculo = (int)fila($e, "SELECT v.id_vehiculo FROM vehiculos v JOIN modelos m ON m.id_modelo = v.id_modelo WHERE m.slug = 'swift' LIMIT 1")['id_vehiculo'];
    $base = ['id' => 0, 'nombre' => 'Repuesto de prueba F2-8', 'id_categoria' => $idCategoria, 'sku' => 'PRUEBA-001',
             'precio' => '10000', 'stock' => '3', 'vehiculos[0]' => $idVehiculo];

    echo "== CP-40 crear con fotos ==\n";
    $msg = guardar($e, $base, [1 => $jpg, 2 => $png]);
    $p = fila($e, "SELECT * FROM repuestos WHERE sku = 'PRUEBA-001'");
    $id = (int)($p['id_repuesto'] ?? 0);
    revisar($id > 0 && (float)$p['precio'] === 10000.0 && (int)$p['stock'] === 3, 'se crea el producto', $msg);
    $f = fotos($e, $id);
    revisar(count($f) === 2 && preg_match('#^img/repuestos/rep-[0-9a-f]{16}\.jpg$#', $f[1] ?? '') && preg_match('#^img/repuestos/rep-[0-9a-f]{16}\.png$#', $f[2] ?? ''),
            '2 fotos guardadas con nombre aleatorio y su extensión real', implode(', ', $f));
    $mini = $raiz . '/img/repuestos/min/' . basename($f[1] ?? 'x');
    revisar(is_file($raiz . '/' . ($f[1] ?? 'x')) && is_file($mini) && getimagesize($mini)[0] === 320, 'archivo guardado y miniatura de 320 px de ancho');
    $foto = @file_get_contents($e['sitio'] . '/' . ($f[1] ?? ''));
    revisar($foto !== false && strlen($foto) === filesize($jpg), 'la foto se ve desde la tienda');
    revisar(count(auditorias($e, $id, 'repuesto_creado')) === 1, 'el alta queda en auditoría');
    revisar(enTienda($e, $id) && strpos((string)file_get_contents($e['sitio'] . "/producto.php?id=$id"), '<h1 class="ficha-nombre">') !== false, 'aparece en la tienda y tiene ficha');
    revisar(strpos(panel($e, '/admin/productos.php?modelo=Swift'), 'min/' . basename($f[1] ?? 'x')) !== false, 'el listado del panel usa la miniatura');

    echo "\n== CP-40 editar: reemplazar y quitar fotos, precio y stock ==\n";
    $msg = guardar($e, ['id' => $id, 'precio' => '12000', 'stock' => '5'] + $base, [1 => $webp], [2]);
    $f2 = fotos($e, $id);
    revisar(count($f2) === 1 && preg_match('#\.webp$#', $f2[1] ?? ''), 'la principal se reemplaza por la WebP y la 2 se quita', $msg . ' / ' . implode(', ', $f2));
    revisar(!is_file("$raiz/{$f[1]}") && !is_file("$raiz/{$f[2]}") && !is_file($mini), 'los archivos reemplazados y quitados (y su miniatura) se borran del servidor');
    $precio = auditorias($e, $id, 'precio');
    $stock = auditorias($e, $id, 'stock');
    revisar(count($precio) === 1 && $precio[0]['valor_anterior'] === '$10.000' && $precio[0]['valor_nuevo'] === '$12.000' && $precio[0]['admin_nombre'] === 'Prueba F2-8'
            && count($stock) === 1 && $stock[0]['valor_anterior'] === '3' && $stock[0]['valor_nuevo'] === '5', 'auditoría con valor anterior, nuevo y administrador', json_encode($precio[0] ?? null));
    $fotoMuestra = 'img/repuestos/car-opd.webp';
    $e['db']->query("INSERT INTO repuesto_imagenes (id_repuesto, url, texto_alt, orden) VALUES ($id, '$fotoMuestra', '', 2)");
    guardar($e, ['id' => $id, 'precio' => '12000', 'stock' => '5'] + $base, [], [2]);
    revisar(is_file("$raiz/$fotoMuestra") && count(fotos($e, $id)) === 1, 'quitar una foto de muestra compartida no borra el archivo');

    echo "\n== Edición rápida desde el listado (RF-052) ==\n";
    $listado = panel($e, '/admin/productos.php?modelo=Swift');
    revisar(strpos($listado, 'form="rapido-' . $id . '"') !== false && strpos($listado, 'id="rapido-' . $id . '"') !== false, 'cada fila tiene precio y stock editables con su formulario');
    $msg = rapido($e, $id, '15000', '7');
    $p = fila($e, "SELECT precio, stock FROM repuestos WHERE id_repuesto = $id");
    revisar((float)$p['precio'] === 15000.0 && (int)$p['stock'] === 7 && strpos($msg, 'Guardado: PRUEBA-001') === 0, 'guarda precio y stock', $msg);
    $stocks = auditorias($e, $id, 'stock');
    revisar(count(auditorias($e, $id, 'precio')) === 2 && end($stocks)['valor_nuevo'] === '7', 'y lo audita');
    foreach ([['-1', '7'], ['15000', 'abc'], ['1.5', '7'], ['', '7'], ['15000', '9999999']] as [$pr, $st]) {
        $msg = rapido($e, $id, $pr, $st);
        $p = fila($e, "SELECT precio, stock FROM repuestos WHERE id_repuesto = $id");
        revisar((float)$p['precio'] === 15000.0 && (int)$p['stock'] === 7 && strpos($msg, 'deben ser números enteros') !== false, "precio \"$pr\" y stock \"$st\": se rechaza sin cambios");
    }
    $auditoriasAntes = (int)fila($e, "SELECT COUNT(*) c FROM auditoria")['c'];
    $msg = rapido($e, $id, '15000', '7');
    revisar(strpos($msg, 'No hubo cambios') === 0 && (int)fila($e, "SELECT COUNT(*) c FROM auditoria")['c'] === $auditoriasAntes, 'sin cambios: lo dice y no audita', $msg);
    $msg = rapido($e, $id, '1', '0', false);
    revisar((float)fila($e, "SELECT precio FROM repuestos WHERE id_repuesto = $id")['precio'] === 15000.0 && strpos($msg, 'seguridad del formulario') !== false, 'sin CSRF se rechaza');
    revisar(strpos(rapido($e, 999999, '1', '1'), 'ya no existe') !== false, 'un producto inexistente se rechaza');

    echo "\n== CP-41 validaciones y SKU repetido ==\n";
    $productos = (int)fila($e, "SELECT COUNT(*) c FROM repuestos")['c'];
    $msg = guardar($e, ['nombre' => ''] + $base + ['sku' => 'PRUEBA-002']);
    revisar(strpos($msg, 'obligatorios') !== false && (int)fila($e, "SELECT COUNT(*) c FROM repuestos")['c'] === $productos, 'sin nombre: mensaje y nada creado', $msg);
    $archivos = count(archivosNuevos());
    $msg = guardar($e, ['sku' => 'CAR-SWI3-OPD'] + $base, [1 => $jpg]);
    revisar(strpos($msg, 'ya lo usa otro producto') !== false && (int)fila($e, "SELECT COUNT(*) c FROM repuestos")['c'] === $productos && count(archivosNuevos()) === $archivos,
            'SKU repetido: mensaje claro, nada creado y ninguna foto suelta', $msg);
    $msg = guardar($e, ['sku' => 'PRUEBA-003'] + $base, [1 => $jpg, 2 => "$t/documento.pdf"]);
    revisar(strpos($msg, 'Foto 2:') === 0 && !fila($e, "SELECT 1 FROM repuestos WHERE sku = 'PRUEBA-003'") && count(archivosNuevos()) === $archivos,
            'la foto 2 no es imagen: no se guarda nada y la foto 1 ya subida se borra', $msg);

    echo "\n== CP-46 subidas inválidas y VL-18 ==\n";
    $casos = ['PDF' => ["$t/documento.pdf", 'no es una imagen válida'], '.php renombrado a .jpg' => ["$t/script.jpg", 'no es una imagen válida'],
              '2 MB + 1 byte' => ["$t/dos_mb_mas_uno.jpg", 'más de 2 MB']];
    foreach ($casos as $nombre => [$ruta, $texto]) {
        $msg = guardar($e, ['id' => $id, 'precio' => '15000', 'stock' => '7'] + $base, [1 => $ruta]);
        revisar(strpos($msg, $texto) !== false && count(fotos($e, $id)) === 1 && count(archivosNuevos()) === $archivos, "$nombre: rechazado y la foto anterior queda igual", $msg);
    }
    $msg = guardar($e, ['id' => $id, 'precio' => '15000', 'stock' => '7'] + $base, [3 => "$t/dos_mb.jpg"]);
    revisar(count(fotos($e, $id)) === 2 && strpos($msg, 'actualizado') !== false, '2 MB exactos: aceptado', $msg);

    echo "\n== CP-46 foto de un modelo nuevo (catálogo, misma función) ==\n";
    $idMarca = (int)fila($e, "SELECT id_marca FROM marcas WHERE activo = 1 LIMIT 1")['id_marca'];
    $idMotor = (int)fila($e, "SELECT id_motor FROM motores LIMIT 1")['id_motor'];
    $modelo = ['csrf' => token($e, '/admin/catalogo.php'), 'accion' => 'crear_modelo', 'id_marca' => $idMarca, 'nombre' => 'Modelo F28',
               'carroceria' => 'Hatchback', 'id_motor' => $idMotor, 'anio_desde' => '2020', 'imagen' => new CURLFile($jpg, 'image/jpeg', 'modelo.jpg')];
    $msg = aviso(panel($e, '/admin/catalogo.php', $modelo, true));
    $imagenModelo = (string)(fila($e, "SELECT imagen_url FROM modelos WHERE nombre = 'Modelo F28'")['imagen_url'] ?? '');
    revisar(preg_match('#^img/modelos/modelo-f28-[0-9a-f]{16}\.jpg$#', $imagenModelo) && is_file("$raiz/$imagenModelo"), 'el modelo se crea con su foto', $imagenModelo . ' / ' . $msg);
    $modelo['csrf'] = token($e, '/admin/catalogo.php');
    $modelo['nombre'] = 'Modelo F28 B';
    $modelo['imagen'] = new CURLFile("$t/script.jpg", 'image/jpeg', 'modelo.jpg');
    $msg = aviso(panel($e, '/admin/catalogo.php', $modelo, true));
    revisar(strpos($msg, 'no es una imagen válida') !== false && !fila($e, "SELECT 1 FROM modelos WHERE nombre = 'Modelo F28 B'"), 'un .php renombrado se rechaza también en el catálogo', $msg);

    echo "\n== CP-40 desactivar y eliminar ==\n";
    guardar($e, ['id' => $id, 'precio' => '15000', 'stock' => '7', 'activo' => null] + $base);
    revisar((int)fila($e, "SELECT activo FROM repuestos WHERE id_repuesto = $id")['activo'] === 0 && !enTienda($e, $id), 'desactivado: desaparece de la tienda');
    $ficha = @file_get_contents($e['sitio'] . "/producto.php?id=$id", false, stream_context_create(['http' => ['ignore_errors' => true]]));
    revisar(strpos($http_response_header[0] ?? '', '404') !== false, 'y su ficha responde 404');
    $fotosFinales = fotos($e, $id);
    $msg = aviso(panel($e, '/admin/productos.php', ['csrf' => token($e), 'accion' => 'eliminar', 'id' => $id]));
    $quedan = array_filter($fotosFinales, function ($u) use ($raiz) { return is_file("$raiz/$u"); });
    revisar(!fila($e, "SELECT 1 FROM repuestos WHERE id_repuesto = $id") && !$quedan, 'al eliminarlo se borran sus fotos subidas', $msg);

    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    foreach (archivosNuevos() as $sobrante) {
        @unlink($sobrante);
    }
    @rmdir("$raiz/img/repuestos/min"); // solo si quedó vacía (antes no existía)
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
