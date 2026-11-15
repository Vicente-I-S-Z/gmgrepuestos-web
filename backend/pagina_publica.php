<?php
/**
 * Estructura común de las páginas públicas - GMG Repuestos
 *
 * Reúne en un solo lugar lo que se repite en todas las páginas que ve el
 * cliente: el encabezado, el pie de página (con los enlaces a las páginas
 * informativas y legales, historia E9-H1) y el chat/carrito/formulario de pago.
 *
 * Uso en una página:
 *   require_once 'backend/config.php';
 *   require_once 'backend/pagina_publica.php';
 *   gmgCabeceraPublica(['titulo' => 'Contacto', 'descripcion' => '...']);
 *   ... contenido de la página dentro de <main> ...
 *   gmgPiePublico($conexion);
 */

require_once __DIR__ . '/modelos.php';
require_once __DIR__ . '/conocimiento.php';

/** Escapa texto para mostrarlo en HTML. */
function gmgH($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Fecha en formato chileno dd-mm-aaaa (historia E10-H14). */
function gmgFechaChile($fecha = null) {
    $marca = $fecha === null ? time() : (is_numeric($fecha) ? (int)$fecha : strtotime($fecha));
    return $marca ? date('d-m-Y', $marca) : '';
}

/**
 * Modelos con repuestos para mostrar en la portada, el buscador, el pie de
 * página y el chat. Se arman desde la base de datos: un modelo nuevo aparece
 * solo, sin tocar código (historia E10-H12).
 *
 * Devuelve ['modelosInfo' => [...], 'modelosCortosTexto' => 'Alto, S-Presso y Swift'].
 */
function gmgDatosModelosPublicos($conexion) {
    $modelosInfo = [];
    $modelosCortosTexto = 'todos los modelos Suzuki';

    if ($conexion && !$conexion->connect_error) {
        foreach (gmgModelosConProductos($conexion) as $modeloCompleto) {
            $imagenModelo = gmgModeloImagen($modeloCompleto);
            $resumenModelo = gmgModeloResumen($modeloCompleto);
            $modelosInfo[] = [
                'completo' => $modeloCompleto,
                'corto' => gmgModeloCorto($modeloCompleto),
                'imagen' => $imagenModelo,
                'tiene_foto' => strpos($imagenModelo, 'placeholder-modelo') === false,
                'repuestos' => $resumenModelo['repuestos'],
                'anios' => $resumenModelo['anios'],
            ];
        }
        if (count($modelosInfo) > 0) {
            $modelosCortosTexto = gmgUnirConY(array_column($modelosInfo, 'corto'));
        }
    }
    return ['modelosInfo' => $modelosInfo, 'modelosCortosTexto' => $modelosCortosTexto];
}

/**
 * Imprime el inicio de la página: <head> y encabezado con el menú.
 *
 * Opciones:
 *   titulo       Título de la pestaña (se le agrega " - GMG Repuestos").
 *   titulo_completo  Título exacto, sin agregar nada (opcional).
 *   descripcion  Texto para buscadores (meta description).
 *   canonical    Dirección canónica absoluta (opcional, historia E8-H8).
 *   robots       Valor de la etiqueta robots, ej. "noindex, follow" (opcional).
 *   css          Hojas de estilo adicionales (arreglo de rutas).
 *   og_tipo      Tipo para redes sociales (por defecto "website"; la ficha usa "product").
 *   og_imagen    Dirección absoluta de la imagen para redes sociales (opcional).
 *   json_ld      Datos estructurados para buscadores (arreglo; se imprime como JSON-LD).
 */
function gmgCabeceraPublica(array $o = []) {
    $titulo = $o['titulo_completo'] ?? (($o['titulo'] ?? 'Inicio') . ' - GMG Repuestos');
    $descripcion = $o['descripcion'] ?? 'GMG Repuestos SpA: tienda en línea de repuestos para vehículos Suzuki en Chile.';
    $css = array_merge(['css/style.css', 'css/chat.css'], $o['css'] ?? []);
    ?>
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo gmgH($titulo); ?></title>
    <meta name="description" content="<?php echo gmgH($descripcion); ?>">
<?php if (!empty($o['canonical'])): ?>
    <link rel="canonical" href="<?php echo gmgH($o['canonical']); ?>">
<?php endif; ?>
<?php if (!empty($o['robots'])): ?>
    <meta name="robots" content="<?php echo gmgH($o['robots']); ?>">
<?php endif; ?>
    <meta property="og:type" content="<?php echo gmgH($o['og_tipo'] ?? 'website'); ?>">
    <meta property="og:locale" content="es_CL">
    <meta property="og:site_name" content="GMG Repuestos">
    <meta property="og:title" content="<?php echo gmgH($titulo); ?>">
    <meta property="og:description" content="<?php echo gmgH($descripcion); ?>">
<?php if (!empty($o['canonical'])): ?>
    <meta property="og:url" content="<?php echo gmgH($o['canonical']); ?>">
<?php endif; ?>
<?php if (!empty($o['og_imagen'])): ?>
    <meta property="og:image" content="<?php echo gmgH($o['og_imagen']); ?>">
<?php endif; ?>
<?php if (!empty($o['json_ld'])): ?>
    <script type="application/ld+json"><?php echo json_encode($o['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php endif; ?>
<?php foreach ($css as $hoja): ?>
    <link rel="stylesheet" href="<?php echo gmgH($hoja); ?>?v=<?php echo gmgVersionArchivo($hoja); ?>">
<?php endforeach; ?>
</head>
<body>

    <header>
        <div class="container nav-container">
            <a href="index.php" class="logo">GMG <span>Repuestos</span></a>
            <nav aria-label="Menú principal">
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="mi-pedido.php">Mi pedido</a></li>
                    <li>
                        <a href="#" id="cart-toggle" class="cart-toggle" aria-label="Abrir carrito">
                            🛒 <span id="cart-count" class="cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
<?php
}

/**
 * Imprime el final de la página: pie de página con enlaces informativos y
 * legales, asistente virtual, carrito y formulario de pago, y los scripts.
 *
 * $conexion        Conexión a la base de datos (para modelos y datos de contacto).
 * $o['scripts']    Scripts adicionales de la página (arreglo de rutas).
 * $o['modelos']    Resultado de gmgDatosModelosPublicos() si la página ya lo calculó.
 */
function gmgPiePublico($conexion, array $o = []) {
    $datos = $o['modelos'] ?? gmgDatosModelosPublicos($conexion);
    $modelosInfo = $datos['modelosInfo'];
    $modelosCortosTexto = $datos['modelosCortosTexto'];

    $contacto = gmgConocimientoTodo($conexion);
    $porConfirmar = 'Por confirmar';
    $paginas = [
        'quienes-somos.php'            => 'Quiénes somos',
        'contacto.php'                 => 'Contacto',
        'despacho.php'                 => 'Despacho',
        'cambios-y-devoluciones.php'   => 'Cambios y devoluciones',
        'terminos-y-condiciones.php'   => 'Términos y condiciones',
        'politica-de-privacidad.php'   => 'Política de privacidad',
    ];
    ?>
    <footer>
        <div class="container footer-grid">
            <div class="footer-col footer-brand">
                <div class="footer-logo">GMG <span>Repuestos</span></div>
                <p class="footer-desc">Especialistas en repuestos originales y compatibles para <?php echo gmgH($modelosCortosTexto); ?>. Piezas de calidad, con garantía, coordinando cada compra directo por WhatsApp.</p>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Modelos</h4>
                <ul class="footer-links">
                    <?php foreach ($modelosInfo as $mi): ?>
                        <li><a href="index.php?modelo=<?php echo urlencode($mi['corto']); ?>"><?php echo gmgH($mi['completo']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Información</h4>
                <ul class="footer-links">
                    <?php foreach ($paginas as $archivo => $nombre): ?>
                        <li><a href="<?php echo gmgH($archivo); ?>"><?php echo gmgH($nombre); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Contacto</h4>
                <ul class="footer-contact">
                    <li>📍 <?php echo gmgH($contacto['direccion'] !== '' ? $contacto['direccion'] : $porConfirmar); ?></li>
                    <li>📞 <?php echo gmgH($contacto['telefono'] !== '' ? $contacto['telefono'] : $porConfirmar); ?></li>
                    <li>✉️ <?php echo gmgH($contacto['email'] !== '' ? $contacto['email'] : $porConfirmar); ?></li>
                    <li>🕒 <?php echo gmgH($contacto['horario'] !== '' ? $contacto['horario'] : $porConfirmar); ?></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container footer-bottom-inner">
                <p>&copy; <?php echo date('Y'); ?> GMG Repuestos SpA. Especialistas en repuestos Suzuki en Chile.</p>
                <a href="login.php" class="footer-admin-link">Acceso administrador</a>
            </div>
        </div>
    </footer>

<?php
    require __DIR__ . '/parciales/widgets_tienda.php';

    $scripts = array_merge(['js/cart.js', 'js/chat.js'], $o['scripts'] ?? []);
    foreach ($scripts as $script) {
        echo '    <script src="' . gmgH($script) . '?v=' . gmgVersionArchivo($script) . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}
