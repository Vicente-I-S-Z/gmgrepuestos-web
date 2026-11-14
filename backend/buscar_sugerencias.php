<?php
// Sugerencias de autocompletado para la barra de búsqueda del storefront.
// Devuelve hasta 6 productos que coincidan por nombre, SKU o código OEM.
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
require_once 'datos.php';

$q = trim($_GET['q'] ?? '');

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['sugerencias' => []]);
    exit;
}

$qEscapado = $conexion->real_escape_string($q);

$sql = "SELECT p.id, p.nombre, p.sku, p.precio, p.imagen_url, p.modelo, p.categoria
        FROM " . gmgSqlProductos() . " p
        WHERE p.activo = 1
          AND (p.nombre LIKE '%$qEscapado%' OR p.sku LIKE '%$qEscapado%' OR p.codigo_oem LIKE '%$qEscapado%')
        ORDER BY
            CASE WHEN p.sku LIKE '$qEscapado%' THEN 0 ELSE 1 END,
            p.nombre ASC
        LIMIT 6";

$resultado = $conexion->query($sql);
$sugerencias = [];

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $sugerencias[] = [
            'id' => (int)$fila['id'],
            'nombre' => $fila['nombre'],
            'sku' => $fila['sku'],
            'precio' => (float)$fila['precio'],
            'imagen' => $fila['imagen_url'] ?: 'img/placeholder-repuesto.svg',
            'modelo' => (string)$fila['modelo'],
            'categoria' => $fila['categoria'],
        ];
    }
}

echo json_encode(['sugerencias' => $sugerencias]);
