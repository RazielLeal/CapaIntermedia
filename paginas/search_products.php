<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$conn = conectarDB();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(["success" => false, "error" => "Query too short"]);
    exit();
}

try {
    $searchQuery = "%" . $conn->real_escape_string($query) . "%";
    
    $stmt = $conn->prepare("
        SELECT 
            p.ID, 
            p.Nombre as nombre, 
            p.Precio as precio, 
            p.Stock as stock,
            p.FotoPrincipal as imagen,
            p.Vendidos as vendidos
        FROM 
            Producto p
        WHERE 
            (p.Nombre LIKE ? OR p.Descripcion LIKE ?)
            AND p.Status = 'Aceptado'
        ORDER BY 
            p.Vendidos DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("ss", $searchQuery, $searchQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['ID'],
            'nombre' => $row['nombre'],
            'precio' => $row['precio'],
            'stock' => $row['stock'],
            'imagen' => $row['imagen'] ? 'data:image/jpeg;base64,' . base64_encode($row['imagen']) : 'avatar.png',
            'vendidos' => $row['vendidos'],
            'status' => 'Aceptado' // Añadido para referencia
        ];
    }
    
    echo json_encode(["success" => true, "products" => $products]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>