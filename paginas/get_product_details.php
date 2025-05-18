<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

session_start();

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
                    p.Video, p.Precio, p.Stock, p.Vendidos,
                    p.tipo,  p.ID_Usuario, u.Nombre as Vendedor,
                    GROUP_CONCAT(c.Nombre SEPARATOR ', ') AS Categorias
                    FROM Producto p
                    LEFT JOIN Producto_Categoria pc ON p.ID = pc.ID_Producto
                    LEFT JOIN Categoria c ON pc.ID_Categoria = c.ID
                    LEFT JOIN usuario u ON p.ID_Usuario=u.ID                   
                    WHERE p.ID = ?
                    GROUP BY p.ID";
    
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $categoriasArray = !empty($row['Categorias']) ? explode(', ', $row['Categorias']) : [];

        $product = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => number_format($row['Precio'], 2),
            'categoria' => $categoriasArray,
            'stock' => $row['Stock'],
            'vendidos' => $row['Vendidos'],
            'video' => $row['Video'],
            'imagenPrincipal' => $row['FotoPrincipal'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoPrincipal']) : null,
            'imagenExtra1' => $row['FotoExtra1'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra1']) : null,
            'imagenExtra2' => $row['FotoExtra2'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra2']) : null,
            'tipo' => $row['tipo'],
            'vendedor' => $row['Vendedor'],
            'vendedor_id' => $row['ID_Usuario'],
            // Guardar el ID del vendedor en la sesión
        ];
        $_SESSION['vendedor_id'] = $row['ID_Usuario'];
        
        
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

?>