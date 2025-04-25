<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json; charset=UTF-8');

include 'conexion.php';
$conn = conectarDB();
$conn->set_charset('utf8');

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

// Parámetros
$userId   = isset($_GET['user_id'])   ? intval($_GET['user_id'])   : null;
$pagina   = isset($_GET['pagina'])    ? max(1, intval($_GET['pagina'])) : 1;
$porPagina= isset($_GET['por_pagina'])? min(max(1, intval($_GET['por_pagina'])), 20) : 8;
$categoriaFilter = isset($_GET['categoria']) ? intval($_GET['categoria']) : null;
$orden    = isset($_GET['orden'])     ? trim($_GET['orden'])       : 'recientes';

if (!$userId || $userId <= 0) {
    echo json_encode(["success" => false, "error" => "ID de usuario no válido"]);
    exit;
}

// 1) Total de productos (con filtro opcional)
$sqlTotal = "SELECT COUNT(DISTINCT p.ID) as total
    FROM Producto p
    " . ($categoriaFilter
        ? "INNER JOIN Producto_Categoria pf ON p.ID = pf.ID_Producto AND pf.ID_Categoria = ?"
        : ""
    ) . "
    WHERE p.ID_Usuario = ?";
$stmtT = $conn->prepare($sqlTotal);
if ($categoriaFilter) {
    $stmtT->bind_param("ii", $categoriaFilter, $userId);
} else {
    $stmtT->bind_param("i", $userId);
}
$stmtT->execute();
$total = $stmtT->get_result()->fetch_assoc()['total'];
$stmtT->close();

// 2) Datos paginados con GROUP_CONCAT de categorías
$orderBy = match($orden) {
    'antiguos'    => "p.FechaPublicacion ASC",
    'precio_asc'  => "p.Precio ASC",
    'precio_desc' => "p.Precio DESC",
    default       => "p.FechaPublicacion DESC",
};

$sql = "SELECT
        p.ID,
        p.Nombre,
        p.Descripcion,
        TO_BASE64(p.FotoPrincipal) AS imagen,       -- ← CAMBIO: devolvemos base64
        p.Precio,
        DATE_FORMAT(p.FechaPublicacion, '%Y-%m-%d') AS FechaPublicacion,
        GROUP_CONCAT(c.Nombre SEPARATOR ',') AS categorias  -- ← CAMBIO: todas las categorías
    FROM Producto p
    LEFT JOIN Producto_Categoria pc ON p.ID = pc.ID_Producto
    LEFT JOIN Categoria c ON pc.ID_Categoria = c.ID
    " . ($categoriaFilter
        ? "INNER JOIN Producto_Categoria pf ON p.ID = pf.ID_Producto AND pf.ID_Categoria = ?"
        : ""
    ) . "
    WHERE p.ID_Usuario = ?
    GROUP BY p.ID
    ORDER BY $orderBy
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($categoriaFilter) {
    // params: filter-cat, userId, porPagina, offset
    $offset = ($pagina - 1) * $porPagina;
    $stmt->bind_param("iiii", $categoriaFilter, $userId, $porPagina, $offset);
} else {
    // params: userId, porPagina, offset
    $offset = ($pagina - 1) * $porPagina;
    $stmt->bind_param("iii", $userId, $porPagina, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

$productos = [];
while ($row = $result->fetch_assoc()) {
    // Construimos la URL de la imagen
    $row['imagen'] = $row['imagen']
        ? "data:image/jpeg;base64,{$row['imagen']}"
        : null;
    $productos[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success"       => true,
    "productos"     => $productos,
    "total"         => intval($total),
    "pagina"        => $pagina,
    "total_paginas" => ceil($total / $porPagina),
    "por_pagina"    => $porPagina
]);
