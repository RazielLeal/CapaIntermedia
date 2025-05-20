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
    
    $productQuery = "SELECT p.ID, p.Nombre, p.Descripcion, p.FotoPrincipal, p.FotoExtra1, p.FotoExtra2, 
                     p.Video, p.Precio, p.Stock, p.Vendidos,
                     p.tipo, p.ID_Usuario, u.Nickname as Vendedor,
                     GROUP_CONCAT(c.Nombre SEPARATOR ',') AS Categorias
                     FROM Producto p
                     LEFT JOIN Producto_Categoria pc ON p.ID = pc.ID_Producto
                     LEFT JOIN Categoria c ON pc.ID_Categoria = c.ID
                     LEFT JOIN Usuario u ON p.ID_Usuario=u.ID           
                     WHERE p.ID = ?
                     GROUP BY p.ID";

    $stmt = $conn->prepare($productQuery);
    if ($stmt === false) {
        echo json_encode(["success" => false, "error" => "Error al preparar la consulta del producto: " . $conn->error]);
        exit();
    }
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $categoriasArray = !empty($row['Categorias']) ? explode(',', $row['Categorias']) : [];

        $product = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => number_format($row['Precio'], 2),
            'categorias' => $categoriasArray,
            'stock' => $row['Stock'],
            'vendidos' => $row['Vendidos'],
            'video' => $row['Video'],
            'imagenPrincipal' => $row['FotoPrincipal'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoPrincipal']) : null,
            'imagenExtra1' => $row['FotoExtra1'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra1']) : null,
            'imagenExtra2' => $row['FotoExtra2'] ? 'data:image/jpeg;base64,' . base64_encode($row['FotoExtra2']) : null,
            'tipo' => $row['tipo'],
            'vendedor' => $row['Vendedor'],
            'vendedor_id' => $row['ID_Usuario'],
            'calificacion_promedio' => 0, 
            'reseñas' => [] 
        ];
        
        $stmt->close();

        $reviewsQuery = "SELECT v.Calificacion, v.Comentario, u.Nickname AS AutorReseña
                         FROM Venta v
                         JOIN Usuario u ON v.ID_Usuario = u.ID
                         WHERE v.ID_Producto = ? AND v.Calificacion IS NOT NULL AND v.Comentario IS NOT NULL AND v.Comentario != ''";
        
        $stmtReviews = $conn->prepare($reviewsQuery);
        if ($stmtReviews === false) {
            error_log("Error al preparar la consulta de reseñas: " . $conn->error);
        } else {
            $stmtReviews->bind_param("i", $productId);
            $stmtReviews->execute();
            $resultReviews = $stmtReviews->get_result();
            
            $totalCalificaciones = 0;
            $conteoReseñas = 0;
            $reseñasArray = [];

            while ($reviewRow = $resultReviews->fetch_assoc()) {
                if ($reviewRow['Calificacion'] !== null) { 
                    $totalCalificaciones += $reviewRow['Calificacion'];
                    $conteoReseñas++;
                }
                if (!empty($reviewRow['Comentario'])) {
                    $reseñasArray[] = [
                        'calificacion' => $reviewRow['Calificacion'],
                        'comentario' => $reviewRow['Comentario'],
                        'autor' => $reviewRow['AutorReseña']
                    ];
                }
            }
            
            if ($conteoReseñas > 0) {
                $product['calificacion_promedio'] = round($totalCalificaciones / $conteoReseñas, 1); 
            }
            $product['reseñas'] = $reseñasArray;
            $stmtReviews->close();
        }
        $_SESSION['vendedor_id'] = $row['ID_Usuario'];
        
        echo json_encode([
            "success" => true,
            "product" => $product
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
    }
    
    $conn->close();
} else {
    echo json_encode(["success" => false, "error" => "ID de producto no proporcionado"]);
}
?>