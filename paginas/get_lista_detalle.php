<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$response = ['success' => false, 'error' => ''];

try {
    $conn = conectarDB();
    
    if (!isset($_GET['id'])) {
        $response['error'] = 'ID de lista no proporcionado';
        echo json_encode($response);
        exit;
    }
    
    $listaId = $conn->real_escape_string($_GET['id']);
    
    // Obtener información básica de la lista
    $sqlLista = "SELECT ID, Nombre, Descripcion, Status FROM Lista WHERE ID = ?";
    $stmt = $conn->prepare($sqlLista);
    $stmt->bind_param("i", $listaId);
    $stmt->execute();
    $resultLista = $stmt->get_result();
    
    if ($resultLista->num_rows === 0) {
        $response['error'] = 'Lista no encontrada';
        echo json_encode($response);
        exit;
    }
    
    $lista = $resultLista->fetch_assoc();
    $response['lista'] = $lista;
    
    // Obtener productos de la lista
    $sqlProductos = "SELECT p.ID, p.Nombre, p.Descripcion, p.Precio, 
                    p.FotoPrincipal, p.Stock, p.Vendidos
                    FROM Lista_Producto lp
                    JOIN Producto p ON lp.ID_Producto = p.ID
                    WHERE lp.ID_Lista = ?
                    ORDER BY lp.Orden ASC";
    
    $stmt = $conn->prepare($sqlProductos);
    $stmt->bind_param("i", $listaId);
    $stmt->execute();
    $resultProductos = $stmt->get_result();
    
    $productos = [];
    while ($row = $resultProductos->fetch_assoc()) {
        // Convertir BLOB a base64 si existe
        if ($row['FotoPrincipal']) {
            $row['FotoPrincipal'] = base64_encode($row['FotoPrincipal']);
        } else {
            $row['FotoPrincipal'] = null;
        }
        $productos[] = $row;
    }
    
    $response['productos'] = $productos;
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = 'Error del servidor: ' . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
    echo json_encode($response);
}
?>