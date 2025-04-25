<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';
$conn = conectarDB();

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;

    $productQuery = "SELECT ID, Nombre, Descripcion, FotoPrincipal, Precio, Vendidos, Stock 
                    FROM Producto 
                    WHERE Status = 'Aceptado'
                    ORDER BY Vendidos DESC
                    LIMIT ?";
    
    $stmtProducts = $conn->prepare($productQuery);
    $stmtProducts->bind_param("i", $limit);
    $stmtProducts->execute();
    $productsResult = $stmtProducts->get_result();
    
    $products = [];
    while ($row = $productsResult->fetch_assoc()) {
        $imagenBase64 = null;
        if (!empty($row['FotoPrincipal'])) {
            $imagenBase64 = base64_encode($row['FotoPrincipal']);
        }
        
        $products[] = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => number_format($row['Precio'], 2),
            'vendidos' => $row['Vendidos'],
            'stock' => (int)$row['Stock'],
            'imagen' => $imagenBase64 ? 'data:image/jpeg;base64,' . $imagenBase64 : null,
            'status' => 'Aceptado' // Añadido para referencia
        ];
    }
    $stmtProducts->close();

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);
}

$conn->close();
?>