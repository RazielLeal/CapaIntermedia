<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$conn = conectarDB();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'calificacion_desc'; // Default: Mejor Calificados
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8; // Default a 8 por página

$offset = ($page - 1) * $limit;

try {
    // Construir la parte WHERE de la consulta para el conteo y los resultados
    $whereClause = " WHERE p.Status = 'Aceptado' ";
    $params = [];
    $types = "";

    if (!empty($query)) {
        $searchQuery = "%" . $conn->real_escape_string($query) . "%";
        $whereClause .= " AND (p.Nombre LIKE ? OR p.Descripcion LIKE ?)";
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $types .= "ss";
    }

    // Consulta para obtener el total de productos con los filtros aplicados
    $countSql = "SELECT COUNT(p.ID) AS total FROM Producto p " . $whereClause;
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalItems = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Consulta para obtener los productos con filtros, orden y paginación
    $sql = "
        SELECT
            p.ID,
            p.Nombre as nombre,
            p.Descripcion,
            p.Precio as precio,
            p.Stock as stock,
            p.FotoPrincipal as imagen,
            p.Vendidos as vendidos,
            p.Calificacion as calificacion
        FROM
            Producto p
        " . $whereClause;

    switch ($sortBy) {
        case 'vendidos_asc':
            $sql .= " ORDER BY p.Vendidos ASC";
            break;
        case 'vendidos_desc':
            $sql .= " ORDER BY p.Vendidos DESC";
            break;
        case 'precio_asc':
            $sql .= " ORDER BY p.Precio ASC";
            break;
        case 'precio_desc':
            $sql .= " ORDER BY p.Precio DESC";
            break;
        case 'calificacion_desc':
        default: // Mejor Calificados por defecto
            $sql .= " ORDER BY p.Calificacion DESC";
            break;
    }

    $sql .= " LIMIT ?, ?"; // Añadir LIMIT al final

    $stmt = $conn->prepare($sql);

    // Añadir los parámetros de offset y limit a los parámetros existentes
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['ID'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => $row['precio'],
            'stock' => $row['stock'],
            'imagen' => $row['imagen'] ? 'data:image/jpeg;base64,' . base64_encode($row['imagen']) : 'avatar2.png',
            'vendidos' => $row['vendidos'],
            'calificacion' => $row['calificacion'] // Asegúrate de que esta columna exista en tu BD
        ];
    }

    echo json_encode(["success" => true, "products" => $products, "total_items" => $totalItems]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>