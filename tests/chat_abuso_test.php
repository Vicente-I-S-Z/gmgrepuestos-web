<?php
/**
 * F1-8 (INC-02, E10-H5, E5-H13, RNF-036): fotos del chat (máximo 3, 4 MB, solo JPG, PNG o WebP
 * según su contenido), sin entradas antiguas (image, image_base64), mensajes de hasta 1.000
 * caracteres, campos de tipo texto y límite de 15 mensajes por minuto por IP.
 * De punta a punta con backend/chat.php y la API falsa (tests/fake_openai.php).
 * Requisitos: MariaDB de XAMPP encendido, PHP con GD, puertos 8092 y 8093 libres.
 * Uso: php tests/chat_abuso_test.php   (termina en "TODO OK")
 */
require __DIR__ . '/chat_entorno.php';

$e = entornoIniciar('abuso');
$enviados = 0; // todo envío cuenta para el límite por IP, también los rechazados
/** Envía al chat y devuelve [respuesta, peticiones nuevas que llegaron a la API]. */
function enviar(array $e, array $campos, bool $json = false): array {
    global $enviados;
    $enviados++;
    $antes = count(peticiones($e));
    $r = chat($e, $campos, $json);
    return [$r, array_slice(peticiones($e), $antes)];
}
/** Partes de imagen del último mensaje del usuario en una petición a la API. */
function imagenesEnviadas(array $peticion): array {
    $ultimo = end($peticion['input']);
    return is_array($ultimo['content'] ?? null)
        ? array_values(array_filter($ultimo['content'], fn($p) => ($p['type'] ?? '') === 'input_image'))
        : [];
}
const AVISO_FOTOS = 'Puedes enviar hasta 3 fotos JPG, PNG o WebP de 4 MB cada una.';

try {
    // Fotos de prueba hechas con GD, más una "foto" que en realidad es código PHP.
    $d = $e['tmp'];
    $img = imagecreatetruecolor(24, 24);
    imagefill($img, 0, 0, imagecolorallocate($img, 211, 47, 47));
    imagejpeg($img, "$d/foto.jpg");
    imagepng($img, "$d/foto.png");
    imagewebp($img, "$d/foto.webp");
    imagegif($img, "$d/foto.gif");
    file_put_contents("$d/grande.jpg", file_get_contents("$d/foto.jpg") . str_repeat("\0", 4 * 1024 * 1024 + 1000));
    file_put_contents("$d/falsa.jpg", "<?php echo 'esto no es una foto'; ?>");
    $f = fn(string $archivo, string $tipo = 'image/jpeg') => new CURLFile("$d/$archivo", $tipo, $archivo);

    echo "== Fotos válidas llegan a la IA (E5-H13) ==\n";
    [$r, $p] = enviar($e, ['message' => '¿qué pieza es esta?', 'sesion_id' => 's1', 'images[0]' => $f('foto.jpg')]);
    $partes = $p ? imagenesEnviadas($p[0]) : [];
    revisar(($r['status'] ?? '') === 'success' && count($partes) === 1 && strpos($partes[0]['image_url'], 'data:image/jpeg;base64,') === 0,
            'una foto JPG llega a la IA como input_image');
    [$r, $p] = enviar($e, ['message' => 'tres fotos', 'sesion_id' => 's1',
                           'images[0]' => $f('foto.jpg'), 'images[1]' => $f('foto.png', 'image/png'), 'images[2]' => $f('foto.webp', 'image/webp')]);
    $tipos = array_map(fn($x) => substr($x['image_url'], 5, strpos($x['image_url'], ';') - 5), $p ? imagenesEnviadas($p[0]) : []);
    revisar($tipos === ['image/jpeg', 'image/png', 'image/webp'], 'JPG, PNG y WebP juntas, con su tipo real', implode(', ', $tipos));

    echo "\n== Fotos rechazadas: no llegan a la IA ==\n";
    $casos = [
        '4 fotos (el máximo es 3)'                  => ['images[0]' => $f('foto.jpg'), 'images[1]' => $f('foto.jpg'), 'images[2]' => $f('foto.jpg'), 'images[3]' => $f('foto.jpg')],
        'foto de más de 4 MB'                        => ['images[0]' => $f('grande.jpg')],
        'código PHP con nombre .jpg y tipo image/jpeg' => ['images[0]' => $f('falsa.jpg')],
        'GIF (no está permitido)'                    => ['images[0]' => $f('foto.gif', 'image/gif')],
        'una válida y una falsa juntas'              => ['images[0]' => $f('foto.jpg'), 'images[1]' => $f('falsa.jpg')],
    ];
    foreach ($casos as $nombre => $archivos) {
        [$r, $p] = enviar($e, ['message' => 'mira esta foto', 'sesion_id' => 's1'] + $archivos);
        revisar(($r['status'] ?? '') === 'error' && ($r['reply'] ?? '') === AVISO_FOTOS && $p === [], $nombre, $r['reply'] ?? 'sin respuesta');
    }

    echo "\n== Entradas antiguas y campos raros ==\n";
    [$r, $p] = enviar($e, ['message' => 'hola por json', 'sesion_id' => 's2', 'image_base64' => base64_encode(file_get_contents("$d/foto.jpg"))], true);
    revisar(($r['status'] ?? '') === 'success' && $p !== [] && imagenesEnviadas($p[0]) === [], 'image_base64 por JSON se ignora (llega solo el texto)');
    [$r, $p] = enviar($e, ['message' => 'campo antiguo', 'sesion_id' => 's2', 'image' => $f('foto.jpg')]);
    revisar(($r['status'] ?? '') === 'success' && $p !== [] && imagenesEnviadas($p[0]) === [], 'el campo antiguo "image" se ignora');
    [$r, $p] = enviar($e, ['message[]' => 'arreglo', 'sesion_id' => 's2']);
    revisar(($r['status'] ?? '') === 'error' && strpos($r['reply'] ?? '', 'escribe un mensaje') !== false && $p === [], 'message enviado como arreglo no rompe la página');
    [$r, $p] = enviar($e, ['message' => str_repeat('a', 1001), 'sesion_id' => 's2']);
    revisar(($r['status'] ?? '') === 'error' && strpos($r['reply'] ?? '', '1.000 caracteres') !== false && $p === [], 'un mensaje de más de 1.000 caracteres no llega a la IA');
    [$r, $p] = enviar($e, ['message' => str_repeat('ñ', 1000), 'sesion_id' => 's2']);
    revisar(($r['status'] ?? '') === 'success' && $p !== [], 'uno de 1.000 caracteres con tilde sí pasa');

    echo "\n== Límite de 15 por minuto por IP (aunque cambie la sesión) ==\n";
    $bloqueadoAntes = false;
    while ($enviados < 15) {
        [$r] = enviar($e, ['message' => 'hola', 'sesion_id' => 'sesion-' . $enviados]);
        $bloqueadoAntes = $bloqueadoAntes || strpos($r['reply'] ?? '', 'muy rápido') !== false;
    }
    revisar(!$bloqueadoAntes, 'los primeros 15 envíos pasan', "$enviados envíos");
    [$r, $p] = enviar($e, ['message' => 'hola otra vez', 'sesion_id' => 'sesion-nueva-' . uniqid()]);
    revisar(($r['status'] ?? '') === 'error' && strpos($r['reply'] ?? '', 'muy rápido') !== false && $p === [], 'el envío 16 se bloquea con sesión nueva y no llega a la IA');
    $claves = array_column($e['db']->query("SELECT clave FROM limites_solicitudes")->fetch_all(MYSQLI_ASSOC), 'clave');
    revisar(count($claves) === 1 && strpos($claves[0], 'chat:') === 0 && !preg_grep('/127\.0\.0\.1|::1/', $claves), 'el contador guarda el hash de la IP, nunca la IP');

    echo "\n== Registro ==\n";
    $errores = erroresPhp($e);
    revisar($errores === [], 'registro de PHP sin errores', $errores ? trim($errores[0]) : '');
} finally {
    entornoTerminar($e);
}
echo $fallas === 0 ? "\nTODO OK\n" : "\n$fallas FALLAS\n";
exit($fallas === 0 ? 0 : 1);
