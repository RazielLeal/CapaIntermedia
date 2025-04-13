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

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    session_start();
    $userId = isset($_GET['userId']) ? intval($_GET['userId']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 4;

    if ($userId <= 0) {
        echo json_encode(["success" => false, "error" => "ID de usuario inválido"]);
        exit();
    }

    // Consulta para contar productos
    $countQuery = "SELECT COUNT(*) as total FROM Producto WHERE ID_Usuario = ?";
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->bind_param("i", $userId);
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result();
    $total = $totalResult->fetch_assoc()['total'];
    $stmtCount->close();

    // Consulta para obtener productos (ahora usando FotoPrincipal en lugar de Foto)
    $offset = ($page - 1) * $perPage;
    $productQuery = "SELECT ID, Nombre, Descripcion, FotoPrincipal, Precio, Categoria 
                    FROM Producto 
                    WHERE ID_Usuario = ?
                    ORDER BY ID DESC
                    LIMIT ? OFFSET ?";
    
    $stmtProducts = $conn->prepare($productQuery);
    $stmtProducts->bind_param("iii", $userId, $perPage, $offset);
    $stmtProducts->execute();
    $productsResult = $stmtProducts->get_result();
    
    $products = [];
    while ($row = $productsResult->fetch_assoc()) {
        // Convertir BLOB a base64 directamente
        $imagenBase64 = null;
        if (!empty($row['FotoPrincipal'])) {
            $imagenBase64 = 'data:image/jpeg;base64,' . base64_encode($row['FotoPrincipal']);
        }
        
        $products[] = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'descripcion' => $row['Descripcion'],
            'precio' => number_format($row['Precio'], 2),
            'categoria' => $row['Categoria'],
            'imagen' => $imagenBase64
        ];
    }
    $stmtProducts->close();

    echo json_encode([
        "success" => true,
        "products" => $products,
        "total" => $total,
        "page" => $page,
        "perPage" => $perPage
    ]);
}

$conn->close();
?>