<?php
require_once 'backend/config.php';
require_once 'backend/modelos.php';
require_once 'backend/datos.php';
require_once 'backend/pagina_publica.php';
require_once 'backend/chat_busqueda.php'; // chatDisponibilidad(): misma etiqueta de stock que el asistente

$productos = [];
$mostrarSelector = true; // Por defecto mostramos las tarjetas de autos
$categoriasDisponibles = [];

// Modelos que realmente tienen productos cargados (dinámico: se arma
// solo, sin tocar código, apenas el admin carga el primer producto de un
// modelo nuevo). $modelosInfo trae, para cada uno, su nombre completo,
// su nombre corto (el que se usa en la URL y el buscador) y su imagen.
$modelosInfo = [];
$modelosCortosTexto = 'todos los modelos Suzuki';

if (isset($conexion) && !$conexion->connect_error) {
    $datosModelos = gmgDatosModelosPublicos($conexion);
    $modelosInfo = $datosModelos['modelosInfo'];
    $modelosCortosTexto = $datosModelos['modelosCortosTexto'];

    // Categorías con al menos un repuesto activo, para el filtro del buscador (siempre visible)
    $resCategorias = $conexion->query("SELECT c.nombre FROM categorias c
                                        WHERE EXISTS (SELECT 1 FROM repuestos r WHERE r.id_categoria = c.id_categoria AND r.activo = 1)
                                        ORDER BY c.orden, c.nombre");
    if ($resCategorias) {
        while ($filaCategoria = $resCategorias->fetch_assoc()) {
            $categoriasDisponibles[] = $filaCategoria['nombre'];
        }
    }

    // Si hay una búsqueda, un modelo o una categoría seleccionada, ocultamos el selector y buscamos repuestos
    if (!empty($_GET['modelo']) || !empty($_GET['q']) || !empty($_GET['categoria'])) {
        $mostrarSelector = false;

        // Base v2: los repuestos se filtran por modelo a través de la tabla
        // compatibilidades (un repuesto puede servir a varios modelos).
        $sql = "SELECT * FROM " . gmgSqlProductos() . " p";
        $where = ["p.activo = 1"];

        if (!empty($_GET['modelo'])) {
            $where[] = gmgCondicionModelo($conexion, $_GET['modelo'], 'p.id');
        }
        if (!empty($_GET['categoria'])) {
            $where[] = "p.categoria = '" . $conexion->real_escape_string($_GET['categoria']) . "'";
        }
        if (!empty($_GET['q'])) {
            // Busca por nombre O por código SKU / OEM exacto o parcial (ej: FRE-SWI3-PDL)
            $qEscapado = $conexion->real_escape_string($_GET['q']);
            $where[] = "(p.nombre LIKE '%$qEscapado%' OR p.sku LIKE '%$qEscapado%' OR p.codigo_oem LIKE '%$qEscapado%')";
        }

        $sql .= " WHERE " . implode(" AND ", $where) . " ORDER BY p.categoria, p.nombre";

        $resultado = $conexion->query($sql);
        if ($resultado && $resultado->num_rows > 0) {
            while($fila = $resultado->fetch_assoc()) {
                $productos[] = $fila;
            }
        }
    }
}
// --- Datos para buscadores (E8-H8): título, descripción y etiqueta canonical ---
// Las páginas con filtros (modelo, categoría, búsqueda) apuntan con "canonical"
// a su versión principal para que no cuenten como contenido duplicado.
$modeloCanonico = !empty($_GET['modelo']) ? gmgModeloCorto((string)$_GET['modelo']) : '';
$baseSitio = gmgUrlBase();
$urlCanonica = $modeloCanonico !== ''
    ? $baseSitio . '/index.php?modelo=' . urlencode($modeloCanonico)
    : $baseSitio . '/';
$tituloPagina = 'GMG Repuestos SpA - Especialistas en Suzuki';
$descripcionPagina = 'Repuestos originales y compatibles para ' . $modelosCortosTexto . '. Compra en línea con Mercado Pago y coordina la entrega por WhatsApp.';
if ($modeloCanonico !== '') {
    $tituloPagina = 'Repuestos para Suzuki ' . $modeloCanonico . ' - GMG Repuestos';
    $descripcionPagina = 'Repuestos para Suzuki ' . $modeloCanonico . ': filtros, frenos, motor, suspensión y más. Revisa la compatibilidad por año y compra en línea.';
}
$esBusquedaFiltrada = !empty($_GET['q']) || !empty($_GET['categoria']);

gmgCabeceraPublica([
    'titulo_completo' => $tituloPagina,
    'descripcion'     => $descripcionPagina,
    'canonical'       => $urlCanonica,
    'robots'          => $esBusquedaFiltrada ? 'noindex, follow' : null,
    'css'             => ['css/model-carousel.css'],
]);
?>

    <div class="critical-rule">
        ⚠️ REGLA CRÍTICA: Por favor confirma la compatibilidad de tu repuesto con nosotros vía WhatsApp antes de comprar para evitar errores.
    </div>

    <!-- Barra de búsqueda siempre visible -->
    <section class="search-section">
        <div class="container">
            <form action="index.php" method="GET" class="search-form" id="searchForm" autocomplete="off">
                <div class="search-input-wrap">
                    <input type="text" name="q" id="searchInput" placeholder="Buscar por nombre o código SKU (ej: FRE-SWI3-PDL)" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <div id="searchSugerencias" class="search-sugerencias"></div>
                </div>
                <select name="modelo">
                    <option value="">Todos los modelos</option>
                    <?php foreach ($modelosInfo as $mi): ?>
                        <option value="<?php echo htmlspecialchars($mi['corto']); ?>" <?php echo (isset($_GET['modelo']) && $_GET['modelo'] == $mi['corto']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mi['completo']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="categoria">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categoriasDisponibles as $categoriaOpcion): ?>
                        <option value="<?php echo htmlspecialchars($categoriaOpcion); ?>" <?php echo (isset($_GET['categoria']) && $_GET['categoria'] === $categoriaOpcion) ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoriaOpcion); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search">Buscar</button>
            </form>
        </div>
    </section>

    <main class="container">

        <?php if ($mostrarSelector): ?>
            <!-- ESTADO 1: SELECTOR DE MODELOS -->
            <section class="hero-carousel" id="heroCarousel">
                <div class="hero-track">
                    <div class="hero-slide is-active">
                        <div class="hero-band-inner">
                            <h1 class="hero-title">Repuestos <span>Suzuki</span> al instante</h1>
                            <p class="hero-subtitle">Piezas originales y compatibles para <?php echo htmlspecialchars($modelosCortosTexto); ?>. Encuentra lo que buscas y coordina tu compra directo por WhatsApp.</p>
                        </div>
                    </div>
                    <div class="hero-slide">
                        <div class="hero-band-inner">
                            <h1 class="hero-title">Compra <span>rápido</span>, sin vueltas</h1>
                            <p class="hero-subtitle">Usa "Comprar ahora" en cualquier repuesto y paga directo con Mercado Pago en pocos clics, sin pasos de más.</p>
                        </div>
                    </div>
                    <div class="hero-slide">
                        <div class="hero-band-inner">
                            <h1 class="hero-title">Busca por <span>SKU</span> exacto</h1>
                            <p class="hero-subtitle">¿Ya tienes el código de tu repuesto? Escríbelo en el buscador y aparecerán sugerencias al instante.</p>
                        </div>
                    </div>
                </div>
                <div class="hero-dots">
                    <button type="button" class="hero-dot is-active" data-hero-index="0" aria-label="Slide 1"></button>
                    <button type="button" class="hero-dot" data-hero-index="1" aria-label="Slide 2"></button>
                    <button type="button" class="hero-dot" data-hero-index="2" aria-label="Slide 3"></button>
                </div>
            </section>

            <section class="gmg-chat-promo">
                <div class="gmg-chat-promo-icon">🤖</div>
                <div class="gmg-chat-promo-texto">
                    <h3>¿No encuentras tu repuesto?</h3>
                    <p>Pregúntale a nuestro Asistente Virtual con IA: identifica piezas por foto, busca en el catálogo en tiempo real y te ayuda a comprar sin salir del chat.</p>
                </div>
                <button type="button" id="gmgHomeChatCTA" class="gmg-chat-promo-btn">Hablar con el Asistente</button>
            </section>

            <h2 class="section-title">Selecciona tu modelo Suzuki</h2>

            <?php if (count($modelosInfo) > 0): ?>
                <!-- Carrusel de modelos: avanza solo con las flechas, los puntos,
                     el teclado o deslizando con el dedo (nunca automático). -->
                <div class="model-carousel" id="modelCarousel" role="region" aria-roledescription="carrusel" aria-label="Modelos Suzuki">
                    <button type="button" class="mc-nav mc-nav--prev" aria-label="Ver modelos anteriores" hidden>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <div class="mc-track" tabindex="0">
                        <?php foreach ($modelosInfo as $mi): ?>
                            <a href="index.php?modelo=<?php echo urlencode($mi['corto']); ?>"
                               class="mc-card"
                               aria-label="Ver repuestos para <?php echo htmlspecialchars($mi['completo']); ?>">
                                <span class="mc-card__media<?php echo $mi['tiene_foto'] ? '' : ' mc-card__media--sin-foto'; ?>">
                                    <span class="mc-card__placeholder" aria-hidden="true">
                                        <svg viewBox="0 0 120 52"><path d="M6 36v-6c0-4 3-7 9-8l18-2 12-10c2-2 5-3 9-3h24c5 0 8 1 11 4l11 10 10 2c5 1 8 4 8 9v4z"/><circle cx="31" cy="38" r="9"/><circle cx="31" cy="38" r="4"/><circle cx="91" cy="38" r="9"/><circle cx="91" cy="38" r="4"/></svg>
                                        <span>Foto próximamente</span>
                                    </span>
                                    <?php if ($mi['tiene_foto']): ?>
                                        <img class="mc-card__img" src="<?php echo htmlspecialchars($mi['imagen']); ?>" alt="<?php echo htmlspecialchars($mi['completo']); ?>" loading="lazy" draggable="false" onerror="this.parentNode.classList.add('mc-card__media--sin-foto');this.remove();">
                                    <?php endif; ?>
                                </span>
                                <span class="mc-card__body">
                                    <span class="mc-card__marca">Suzuki</span>
                                    <span class="mc-card__nombre"><?php echo htmlspecialchars($mi['corto']); ?></span>
                                    <span class="mc-card__meta">
                                        <?php echo (int)$mi['repuestos']; ?> repuestos<?php if ($mi['anios'] !== ''): ?> · <?php echo htmlspecialchars($mi['anios']); ?><?php endif; ?>
                                    </span>
                                    <span class="mc-card__cta">Ver repuestos <span aria-hidden="true">→</span></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mc-nav mc-nav--next" aria-label="Ver más modelos" hidden>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                    <div class="mc-dots" aria-label="Páginas del carrusel"></div>
                </div>
            <?php else: ?>
                <p style="color:#888; text-align:center; margin: 20px 0;">Todavía no hay productos cargados. Vuelve pronto.</p>
            <?php endif; ?>

        <?php else: ?>
            <!-- ESTADO 2: CATÁLOGO DE REPUESTOS -->
            <a href="index.php" class="btn-back">← Volver a todos los modelos</a>

            <?php
                $tituloCatálogo = "Repuestos Disponibles";
                if (!empty($_GET['modelo'])) {
                    $tituloCatálogo = "Repuestos para Suzuki " . htmlspecialchars($_GET['modelo']);
                }
                if (!empty($_GET['q'])) {
                    $tituloCatálogo .= " - Búsqueda: '" . htmlspecialchars($_GET['q']) . "'";
                }
            ?>
            <h2><?php echo $tituloCatálogo; ?></h2>

            <?php if (count($productos) > 0): ?>
                <div class="productos-grid">
                    <?php foreach($productos as $producto): ?>
                    <?php
                        $imagenes = [$producto['imagen_url']];
                        if (!empty($producto['imagen_url_2'])) { $imagenes[] = $producto['imagen_url_2']; }
                        if (!empty($producto['imagen_url_3'])) { $imagenes[] = $producto['imagen_url_3']; }
                        $numImagenes = count($imagenes);
                        // Disponibilidad (RF-013, E2-H6): "Disponible", "Últimas unidades" (3 o menos) o "Sin stock".
                        $disponibilidad = chatDisponibilidad((int)$producto['stock']);
                        $agotado = $disponibilidad === 'Sin stock';
                        $claseDisponibilidad = $agotado ? 'agotado' : ($disponibilidad === 'Últimas unidades' ? 'pocas' : 'ok');
                    ?>
                    <div class="producto-card" data-producto-id="<?php echo (int)$producto['id']; ?>">
                        <div class="producto-img-wrap">
                            <?php foreach ($imagenes as $i => $urlImagen): ?>
                                <img src="<?php echo htmlspecialchars($urlImagen); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="producto-img<?php echo $i === 0 ? ' producto-img-activa' : ''; ?>" data-index="<?php echo $i; ?>" loading="lazy" onerror="this.onerror=null;this.src='img/placeholder-repuesto.svg';">
                            <?php endforeach; ?>
                            <?php if ($numImagenes > 1): ?>
                                <button type="button" class="producto-img-nav producto-img-prev" aria-label="Foto anterior">‹</button>
                                <button type="button" class="producto-img-nav producto-img-next" aria-label="Foto siguiente">›</button>
                                <div class="producto-img-dots">
                                    <?php for ($i = 0; $i < $numImagenes; $i++): ?>
                                        <span class="producto-img-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="producto-info">
                            <div class="producto-categoria"><?php echo htmlspecialchars($producto['categoria']); ?></div>
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                            <div class="producto-detalles">
                                <p><strong>Modelo:</strong> <?php echo htmlspecialchars($producto['modelo'] ?? ''); ?></p>
                                <p><strong>Año:</strong> <?php echo htmlspecialchars($producto['ano'] ?? ''); ?></p>
                                <p><strong>SKU:</strong> <?php echo htmlspecialchars($producto['sku']); ?></p>
                                <?php if (!empty($producto['compatible_con'])): ?>
                                    <?php $vehiculosCompatibles = explode('; ', $producto['compatible_con']); ?>
                                    <details class="producto-compat">
                                        <summary>Vehículos compatibles (<?php echo count($vehiculosCompatibles); ?>)</summary>
                                        <ul>
                                            <?php foreach ($vehiculosCompatibles as $vehiculoCompatible): ?>
                                                <li><?php echo htmlspecialchars($vehiculoCompatible); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div class="producto-precio">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?> CLP</div>
                            <span class="gmg-disponibilidad gmg-disponibilidad--<?php echo $claseDisponibilidad; ?>"><?php echo gmgH($disponibilidad); ?></span>

                            <div class="producto-acciones">
                                <?php if ($agotado): ?>
                                <button type="button" class="btn-add-cart" disabled>Sin stock</button>
                                <?php else: ?>
                                <button class="btn-add-cart"
                                        data-id="<?php echo htmlspecialchars($producto['id']); ?>"
                                        data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        data-precio="<?php echo htmlspecialchars($producto['precio']); ?>"
                                        data-imagen="<?php echo htmlspecialchars($producto['imagen_url']); ?>"
                                        data-categoria="<?php echo htmlspecialchars($producto['categoria']); ?>">
                                    🛒 Añadir al carrito
                                </button>
                                <button class="btn-comprar-ahora"
                                        data-id="<?php echo htmlspecialchars($producto['id']); ?>"
                                        data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        data-precio="<?php echo htmlspecialchars($producto['precio']); ?>"
                                        data-imagen="<?php echo htmlspecialchars($producto['imagen_url']); ?>"
                                        data-categoria="<?php echo htmlspecialchars($producto['categoria']); ?>">
                                    ⚡ Comprar ahora
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sin-resultados" role="status">
                    <p>No encontramos repuestos para tu búsqueda<?php echo !empty($_GET['modelo']) ? ' en el modelo ' . htmlspecialchars($_GET['modelo']) : ''; ?>.</p>
                    <p>Puedes consultarle a nuestro Asistente Virtual: te ayuda a encontrar la pieza correcta, incluso con una foto.</p>
                    <button type="button" class="gmg-chat-promo-btn" data-abrir-chat>Consultar al Asistente</button>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

<?php
// Pie de página (con enlaces informativos y legales), asistente virtual,
// carrito y formulario de pago: se comparten con todas las páginas públicas.
gmgPiePublico($conexion, [
    'modelos' => ['modelosInfo' => $modelosInfo, 'modelosCortosTexto' => $modelosCortosTexto],
    'scripts' => ['js/tienda.js', 'js/model-carousel.js'],
]);
