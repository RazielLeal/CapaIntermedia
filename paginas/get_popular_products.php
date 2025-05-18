<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$conn = conectarDB();

// Obtener parámetros de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8; // Default a 8 por página

$offset = ($page - 1) * $limit;

try {
    // Consulta para obtener el total de productos (para la paginación)
    $countSql = "SELECT COUNT(ID) AS total FROM Producto WHERE Status = 'Aceptado'";
    $countResult = $conn->query($countSql);
    $totalItems = $countResult->fetch_assoc()['total'];

    // Consulta para obtener los productos populares con paginación
    $sql = "
        SELECT
            ID,
            Nombre as nombre,
            Precio as precio,
            Stock as stock,
            FotoPrincipal as imagen,
            Vendidos as vendidos
        FROM
            Producto
        WHERE
            Status = 'Aceptado'
        ORDER BY
            Vendidos DESC
        LIMIT ?, ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['ID'],
            'nombre' => $row['nombre'],
            'precio' => $row['precio'],
            'stock' => $row['stock'],
            'imagen' => $row['imagen'] ? 'data:image/jpeg;base64,' . base64_encode($row['imagen']) : 'avatar2.png',
            'vendidos' => $row['vendidos']
        ];
    }

    echo json_encode(["success" => true, "products" => $products, "total_items" => $totalItems]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>