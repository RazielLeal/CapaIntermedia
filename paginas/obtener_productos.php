<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

include 'conexion.php';

$conn = conectarDB();

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Obtener parámetros
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $porPagina = isset($_GET['por_pagina']) ? min(max(1, intval($_GET['por_pagina'])), 20) : 8;
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
    $orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'recientes';
    
    // Validar usuario
    if (!$userId || $userId <= 0) {
        echo json_encode(["success" => false, "error" => "ID de usuario no válido"]);
        exit();
    }
    
    // Construir consulta base
    $sqlBase = "FROM Producto WHERE ID_Usuario = ?";
    $params = [$userId];
    $types = "i";
    
    // Aplicar filtro de categoría si existe
    if (!empty($categoria)) {
        $sqlBase .= " AND Categoria = ?";
        $params[] = $categoria;
        $types .= "s";
    }
    
    // Construir ORDER BY según el criterio de ordenación
    $orderBy = "ORDER BY ";
    switch ($orden) {
        case 'antiguos':
            $orderBy .= "FechaPublicacion ASC";
            break;
        case 'precio_asc':
            $orderBy .= "Precio ASC";
            break;
        case 'precio_desc':
            $orderBy .= "Precio DESC";
            break;
        case 'recientes':
        default:
            $orderBy .= "FechaPublicacion DESC";
            break;
    }
    
    // Consulta para obtener el total de productos
    $sqlTotal = "SELECT COUNT(*) as total " . $sqlBase;
    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $total = $resultTotal->fetch_assoc()['total'];
    $stmtTotal->close();
    
    // Consulta para obtener los productos paginados
    $sql = "SELECT ID, Nombre, Descripcion, Foto, Precio, Categoria, 
                   DATE_FORMAT(FechaPublicacion, '%Y-%m-%d') as FechaPublicacion 
            " . $sqlBase . " " . $orderBy . " LIMIT ? OFFSET ?";
    
    $offset = ($pagina - 1) * $porPagina;
    $params[] = $porPagina;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        "success" => true,
        "productos" => $productos,
        "total" => $total,
        "pagina" => $pagina,
        "total_paginas" => ceil($total / $porPagina),
        "por_pagina" => $porPagina
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
}
?>