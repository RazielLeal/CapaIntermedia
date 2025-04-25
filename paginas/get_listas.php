<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);

    $conn = conectarDB();
    
    // Consulta principal para obtener las listas del usuario
    $sql = "SELECT ID, Nombre, Descripcion, Status FROM Lista WHERE ID_Usuario = ? ORDER BY ID DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error en la preparación de la consulta"]);
        exit();
    }

    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error al ejecutar la consulta"]);
        exit();
    }

    $result = $stmt->get_result();
    $listas = [];

    while ($row = $result->fetch_assoc()) {
        // Obtener la imagen del primer producto de la lista (si existe)
        $sqlProducto = "SELECT p.FotoPrincipal 
                       FROM Lista_Producto lp
                       JOIN Producto p ON lp.ID_Producto = p.ID
                       WHERE lp.ID_Lista = ?
                       ORDER BY lp.FechaAgregado ASC
                       LIMIT 1";
        
        $stmtProducto = $conn->prepare($sqlProducto);
        
        if ($stmtProducto) {
            $stmtProducto->bind_param("i", $row['ID']);
            $stmtProducto->execute();
            $resultProducto = $stmtProducto->get_result();
            $producto = $resultProducto->fetch_assoc();
            
            $primerProductoImagen = null;
            if ($producto && isset($producto['FotoPrincipal'])) {
                $primerProductoImagen = 'data:image/jpeg;base64,' . base64_encode($producto['FotoPrincipal']);
            }
            $stmtProducto->close();
        }

        // Obtener el conteo de productos en la lista
        $sqlCount = "SELECT COUNT(*) as cantidad FROM Lista_Producto WHERE ID_Lista = ?";
        $stmtCount = $conn->prepare($sqlCount);
        
        $cantidadProductos = 0;
        if ($stmtCount) {
            $stmtCount->bind_param("i", $row['ID']);
            $stmtCount->execute();
            $resultCount = $stmtCount->get_result();
            $countData = $resultCount->fetch_assoc();
            $cantidadProductos = $countData['cantidad'];
            $stmtCount->close();
        }

        // Agregar datos adicionales a la lista
        $row['primerProductoImagen'] = $primerProductoImagen;
        $row['cantidadProductos'] = $cantidadProductos;
        
        $listas[] = $row;
    }

    echo json_encode([
        "success" => true,
        "listas" => $listas
    ]);

    $stmt->close();
    $conn->close();
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de usuario no proporcionado"]);
}
?>