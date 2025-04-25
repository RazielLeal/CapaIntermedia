<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "PWInter";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    
    // Consulta modificada para obtener categorías
    $productQuery = "SELECT p.ID, p.Nombre, p.Descripcion, p.FotoPrincipal, p.FotoExtra1, p.FotoExtra2, 
                    p.Video, c.Nombre AS Categoria, p.Precio, p.Stock, p.Vendidos 
                    FROM Producto p
                    LEFT JOIN Producto_Categoria pc ON p.ID = pc.ID_Producto
                    LEFT JOIN Categoria c ON pc.ID_Categoria = c.ID
                    WHERE p.ID = ?";
    
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $product = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => number_format($row['Precio'], 2),
            'categoria' => $row['Categoria'] ?? 'Sin categoría',
            'stock' => $row['Stock'],
            'vendidos' => $row['Vendidos'],
            'video' => $row['Video'],
            'imagenPrincipal' => $row['FotoPrincipal'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoPrincipal']) : null,
            'imagenExtra1' => $row['FotoExtra1'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra1']) : null,
            'imagenExtra2' => $row['FotoExtra2'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra2']) : null
        ];
        
        echo json_encode([
            "success" => true,
            "product" => $product
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
    }
    
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "ID de producto no proporcionado"]);
}

$conn->close();
?>