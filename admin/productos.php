<?php
require_once '../backend/auth.php';
requerirAdmin('../');
require_once '../backend/config.php';
require_once '../backend/modelos.php';
require_once '../backend/datos.php';
require_once '../backend/auditoria.php';

// Base de datos v2:
// - El modelo, año y motor ya no se escriben a mano: se marcan los
//   vehículos compatibles (tabla compatibilidades).
// - La categoría se elige de la tabla categorias.
// - Las fotos se guardan en repuesto_imagenes (hasta 3).

$mensaje = '';
$tipoMensaje = '';

gmgCargarModelos($conexion);
$categorias = gmgCategorias($conexion);
$vehiculosPorModelo = gmgVehiculosPorModelo($conexion);

// --- Procesar acciones (crear / actualizar / eliminar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        // Se anota cómo estaba el repuesto antes de borrarlo (registro de auditoría).
        $repuestoAntesDeBorrar = gmgAuditoriaLeerRepuesto($conexion, $id);
        // Las fotos, compatibilidades y avisos se borran en cascada; los
        // pedidos antiguos conservan el nombre, SKU y precio del repuesto.
        $stmt = $conexion->prepare("DELETE FROM repuestos WHERE id_repuesto = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            if ($repuestoAntesDeBorrar) {
                gmgAuditar($conexion, 'repuesto_eliminado', 'repuesto', $id,
                    'Repuesto ' . $repuestoAntesDeBorrar['sku'] . ' eliminado (precio ' . gmgAuditoriaPesos($repuestoAntesDeBorrar['precio']) . ', stock ' . (int)$repuestoAntesDeBorrar['stock'] . ')',
                    $repuestoAntesDeBorrar['sku'], null);
            }
            $mensaje = 'Producto eliminado correctamente.';
            $tipoMensaje = 'exito';
        } else {
            $mensaje = 'No se pudo eliminar el producto.';
            $tipoMensaje = 'error';
        }
        $stmt->close();
    } elseif ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $idCategoria = (int)($_POST['id_categoria'] ?? 0);
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $codigoOem = trim($_POST['codigo_oem'] ?? '');
        $codigoOem = $codigoOem !== '' ? $codigoOem : null;
        $marcaFabricante = trim($_POST['marca_fabricante'] ?? '');
        $marcaFabricante = $marcaFabricante !== '' ? $marcaFabricante : null;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descripcion = $descripcion !== '' ? $descripcion : null;
        $precio = max(0, (float)($_POST['precio'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $activo = isset($_POST['activo']) ? 1 : 0;
        $imagenes = [
            $_POST['imagen_url'] ?? '',
            $_POST['imagen_url_2'] ?? '',
            $_POST['imagen_url_3'] ?? '',
        ];
        $vehiculos = isset($_POST['vehiculos']) && is_array($_POST['vehiculos']) ? $_POST['vehiculos'] : [];

        if ($nombre === '' || $sku === '' || $idCategoria <= 0) {
            $mensaje = 'Nombre, categoría y SKU son obligatorios.';
            $tipoMensaje = 'error';
        } else {
            // Cómo estaba el repuesto antes de guardar (registro de auditoría de precio y stock).
            $repuestoAntes = $id > 0 ? gmgAuditoriaLeerRepuesto($conexion, $id) : null;
            $conexion->begin_transaction();
            if ($id > 0) {
                $stmt = $conexion->prepare("UPDATE repuestos SET id_categoria=?, nombre=?, descripcion=?, sku=?, codigo_oem=?, marca_fabricante=?, precio=?, stock=?, activo=? WHERE id_repuesto=?");
                $stmt->bind_param("isssssdiii", $idCategoria, $nombre, $descripcion, $sku, $codigoOem, $marcaFabricante, $precio, $stock, $activo, $id);
            } else {
                $stmt = $conexion->prepare("INSERT INTO repuestos (id_categoria, nombre, descripcion, sku, codigo_oem, marca_fabricante, precio, stock, activo) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("isssssdii", $idCategoria, $nombre, $descripcion, $sku, $codigoOem, $marcaFabricante, $precio, $stock, $activo);
            }

            $ok = $stmt->execute();
            if ($ok && $id <= 0) {
                $id = (int)$conexion->insert_id;
            }
            $stmt->close();

            if ($ok) {
                $ok = gmgGuardarImagenesRepuesto($conexion, $id, $imagenes, $nombre)
                   && gmgGuardarCompatibilidades($conexion, $id, $vehiculos);
            }

            if ($ok) {
                $conexion->commit();
                // Quién cambió el precio o el stock, y cuándo (historia E10-H7).
                gmgAuditarRepuesto($conexion, $id, $repuestoAntes, gmgAuditoriaLeerRepuesto($conexion, $id));
                $mensaje = ((int)($_POST['id'] ?? 0) > 0) ? 'Producto actualizado correctamente.' : 'Producto creado correctamente.';
                if (count($vehiculos) === 0) {
                    $mensaje .= ' Ojo: no marcaste vehículos compatibles, así que no aparecerá al filtrar por modelo.';
                }
                $tipoMensaje = 'exito';
            } else {
                $conexion->rollback();
                $mensaje = 'No se pudo guardar el producto (revisa que el SKU no esté repetido).';
                $tipoMensaje = 'error';
            }
        }
    }
}

// --- ¿Mostrar formulario de creación/edición? ---
$mostrarFormulario = isset($_GET['nuevo']) || isset($_GET['editar']);
$productoEditar = null;
$vehiculosMarcados = [];

if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = $conexion->prepare("SELECT * FROM " . gmgSqlProductos() . " p WHERE p.id = ?");
    $stmt->bind_param("i", $idEditar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $productoEditar = $res->fetch_assoc();
        $vehiculosMarcados = gmgVehiculosDeRepuesto($conexion, $idEditar);
    } else {
        $mostrarFormulario = false;
    }
    $stmt->close();
}

// --- Listado de productos, agrupado por modelo ---
// Un repuesto aparece en la pestaña de cada modelo con el que es compatible.
$productosPorModelo = [];
$r = $conexion->query("SELECT p.id, p.nombre, p.categoria, p.sku, p.precio, p.stock, p.stock_minimo, p.activo, p.imagen_url, p.modelo
                         FROM " . gmgSqlProductos() . " p ORDER BY p.nombre ASC");
if ($r) {
    while ($fila = $r->fetch_assoc()) {
        $modelosFila = $fila['modelo'] !== null && $fila['modelo'] !== '' ? explode(', ', $fila['modelo']) : ['Sin vehículo asignado'];
        foreach ($modelosFila as $modeloFila) {
            $productosPorModelo[$modeloFila][] = $fila;
        }
    }
}
$modelosOrden = gmgOrdenarModelos(array_keys($productosPorModelo));

$totalProductosListado = 0;
$r = $conexion->query("SELECT COUNT(*) AS c FROM repuestos");
if ($r) { $totalProductosListado = (int)$r->fetch_assoc()['c']; }

// --- Modelo seleccionado (solo se muestra un listado a la vez) ---
$modeloSeleccionado = $_GET['modelo'] ?? ($_POST['modelo_actual'] ?? ($modelosOrden[0] ?? ''));
$modeloSeleccionado = gmgModeloCorto($modeloSeleccionado);
if (!in_array($modeloSeleccionado, $modelosOrden, true)) {
    $modeloSeleccionado = $modelosOrden[0] ?? '';
}
$listaSeleccionada = $productosPorModelo[$modeloSeleccionado] ?? [];

$idCategoriaActual = (int)($productoEditar['id_categoria'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Panel administrador - GMG Repuestos</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">

    <div class="admin-topbar">
        <div class="container">
            <div class="admin-brand">GMG <span>Admin</span></div>
            <nav class="admin-nav">
<?php require_once '../backend/admin_ui.php'; gmgAdminEnlaces('productos'); ?>
                <span class="admin-user">Hola, <?php echo htmlspecialchars(nombreAdminActual()); ?></span>
                <a href="../logout.php" class="admin-logout">Cerrar sesión</a>
            </nav>
        </div>
    </div>

    <main class="admin-main">
        <div class="container">
            <div class="admin-heading">
                <h1>Productos</h1>
                <?php if (!$mostrarFormulario): ?>
                    <a href="productos.php?nuevo=1&modelo=<?php echo urlencode($modeloSeleccionado); ?>" class="btn-admin">+ Nuevo producto</a>
                <?php else: ?>
                    <a href="productos.php?modelo=<?php echo urlencode($modeloSeleccionado); ?>" class="btn-admin btn-secundario">← Volver al listado</a>
                <?php endif; ?>
            </div>

            <?php if ($mensaje): ?>
                <div class="flash-msg flash-<?php echo $tipoMensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if ($mostrarFormulario): ?>
                <form class="admin-form" method="POST" action="productos.php">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?php echo $productoEditar ? (int)$productoEditar['id'] : 0; ?>">
                    <input type="hidden" name="modelo_actual" value="<?php echo htmlspecialchars($modeloSeleccionado); ?>">
                    <div class="form-grid">
                        <div class="form-full">
                            <label for="nombre">Nombre del repuesto</label>
                            <input type="text" id="nombre" name="nombre" required maxlength="150" value="<?php echo htmlspecialchars($productoEditar['nombre'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="id_categoria">Categoría</label>
                            <select id="id_categoria" name="id_categoria" required>
                                <option value="">Elige una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo (int)$cat['id_categoria']; ?>" <?php echo (int)$cat['id_categoria'] === $idCategoriaActual ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="sku">SKU</label>
                            <input type="text" id="sku" name="sku" required maxlength="30" placeholder="Ej: FRE-SWI3-PDL" value="<?php echo htmlspecialchars($productoEditar['sku'] ?? ''); ?>">
                            <small class="form-ayuda">Formato CATEGORÍA-GRUPO-TIPO (ej: FRE-SWI3-PDL = pastillas delanteras Swift 3ª gen.).</small>
                        </div>
                        <div>
                            <label for="codigo_oem">Código OEM</label>
                            <input type="text" id="codigo_oem" name="codigo_oem" maxlength="40" value="<?php echo htmlspecialchars($productoEditar['codigo_oem'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="marca_fabricante">Marca del repuesto</label>
                            <input type="text" id="marca_fabricante" name="marca_fabricante" maxlength="60" placeholder="Ej: Suzuki Genuine, NGK, Bosch" value="<?php echo htmlspecialchars($productoEditar['marca_fabricante'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="precio">Precio (CLP)</label>
                            <input type="number" id="precio" name="precio" min="0" step="1" required value="<?php echo htmlspecialchars((string)(isset($productoEditar['precio']) ? (int)$productoEditar['precio'] : 0)); ?>">
                        </div>
                        <div>
                            <label for="stock">Stock</label>
                            <input type="number" id="stock" name="stock" min="0" step="1" required value="<?php echo htmlspecialchars((string)($productoEditar['stock'] ?? '0')); ?>">
                        </div>
                        <div class="form-full">
                            <label for="descripcion">Descripción (opcional)</label>
                            <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($productoEditar['descripcion'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-full">
                            <label class="check-inline">
                                <input type="checkbox" name="activo" value="1" <?php echo (!$productoEditar || (int)$productoEditar['activo'] === 1) ? 'checked' : ''; ?>>
                                Visible en la tienda
                            </label>
                        </div>

                        <div class="form-full">
                            <label>Vehículos compatibles</label>
                            <small class="form-ayuda">Marca cada modelo, motor y rango de años en que sirve este repuesto. Un mismo repuesto puede servir a varios modelos.</small>
                            <div class="compat-grid">
                                <?php foreach ($vehiculosPorModelo as $nombreModelo => $listaVehiculos): ?>
                                    <fieldset class="compat-modelo">
                                        <legend><?php echo htmlspecialchars($nombreModelo); ?></legend>
                                        <?php foreach ($listaVehiculos as $veh): ?>
                                            <label class="compat-item">
                                                <input type="checkbox" name="vehiculos[]" value="<?php echo (int)$veh['id_vehiculo']; ?>" <?php echo in_array((int)$veh['id_vehiculo'], $vehiculosMarcados, true) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($veh['descripcion']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-full">
                            <label for="imagen_url">URL de la imagen (foto principal)</label>
                            <input type="text" id="imagen_url" name="imagen_url" placeholder="img/placeholder-repuesto.svg" value="<?php echo htmlspecialchars($productoEditar['imagen_url'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="imagen_url_2">URL foto adicional 2 (opcional)</label>
                            <input type="text" id="imagen_url_2" name="imagen_url_2" placeholder="Para activar el carrusel del producto" value="<?php echo htmlspecialchars($productoEditar['imagen_url_2'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="imagen_url_3">URL foto adicional 3 (opcional)</label>
                            <input type="text" id="imagen_url_3" name="imagen_url_3" placeholder="Opcional" value="<?php echo htmlspecialchars($productoEditar['imagen_url_3'] ?? ''); ?>">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-admin">Guardar producto</button>
                            <a href="productos.php?modelo=<?php echo urlencode($modeloSeleccionado); ?>" class="btn-admin btn-secundario">Cancelar</a>
                        </div>
                    </div>
                </form>
            <?php else: ?>

                <?php if ($totalProductosListado === 0): ?>
                    <div class="admin-panel">
                        <div class="admin-table-wrap">
                            <p class="admin-empty">No hay productos cargados todavía.</p>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="model-tabs">
                        <?php foreach ($modelosOrden as $modeloNombre): ?>
                            <a href="productos.php?modelo=<?php echo urlencode($modeloNombre); ?>" class="model-tab<?php echo $modeloNombre === $modeloSeleccionado ? ' model-tab-activo' : ''; ?>">
                                <?php echo htmlspecialchars($modeloNombre); ?>
                                <span class="model-tab-count"><?php echo count($productosPorModelo[$modeloNombre]); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-heading admin-heading-modelo">
                        <h2><?php echo htmlspecialchars($modeloSeleccionado); ?> <span class="modelo-count">(<?php echo count($listaSeleccionada); ?>)</span></h2>
                    </div>

                    <div class="admin-panel">
                        <div class="admin-table-wrap">
                            <?php if (count($listaSeleccionada) === 0): ?>
                                <p class="admin-empty">Todavía no hay productos cargados para <?php echo htmlspecialchars($modeloSeleccionado); ?>.</p>
                            <?php else: ?>
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Foto</th>
                                            <th>Nombre</th>
                                            <th>Categoría</th>
                                            <th>SKU</th>
                                            <th>Precio</th>
                                            <th>Stock</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($listaSeleccionada as $p): ?>
                                        <tr>
                                            <?php $srcFoto = preg_match('#^(https?:)?//|^/#', $p['imagen_url']) ? $p['imagen_url'] : '../' . $p['imagen_url']; ?>
                                            <td><img class="thumb" src="<?php echo htmlspecialchars($srcFoto); ?>" alt="" onerror="this.onerror=null;this.src='../img/placeholder-repuesto.svg';"></td>
                                            <td>
                                                <?php echo htmlspecialchars($p['nombre']); ?>
                                                <?php if ((int)$p['activo'] !== 1): ?>
                                                    <span class="badge badge-error">Oculto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($p['sku']); ?></td>
                                            <td>$<?php echo number_format((float)$p['precio'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php echo (int)$p['stock']; ?>
                                                <?php if ((int)$p['stock'] < (int)$p['stock_minimo']): ?>
                                                    <span class="badge badge-stock-bajo">Bajo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="productos.php?editar=<?php echo (int)$p['id']; ?>&modelo=<?php echo urlencode($modeloSeleccionado); ?>" class="link-editar">Editar</a>
                                                    <form method="POST" action="productos.php" onsubmit="return confirm('¿Eliminar este producto? Se quitará de todos los modelos. Esta acción no se puede deshacer.');">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                        <input type="hidden" name="modelo_actual" value="<?php echo htmlspecialchars($modeloSeleccionado); ?>">
                                                        <button type="submit" class="link-eliminar">Eliminar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
