<?php
session_start(); 
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$response = ['success' => false, 'error' => ''];

try {
    $conn = conectarDB();
    
    if (!isset($_GET['id'])) {
        $response['error'] = 'ID de lista no proporcionado.';
        echo json_encode($response);
        exit;
    }
    
    $listaId = intval($_GET['id']);
    $requestedUserId = isset($_GET['userId']) ? intval($_GET['userId']) : null; 
    $loggedInUserId = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : null; 

    $sqlLista = "SELECT ID, Nombre, Descripcion, Status, ID_Usuario FROM Lista WHERE ID = ?";
    $stmt = $conn->prepare($sqlLista);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta de la lista: " . $conn->error);
    }
    $stmt->bind_param("i", $listaId);
    $stmt->execute();
    $resultLista = $stmt->get_result();
    
    if ($resultLista->num_rows === 0) {
        $response['error'] = 'Lista no encontrada.';
        echo json_encode($response);
        exit;
    }
    
    $lista = $resultLista->fetch_assoc();
    $ownerId = $lista['ID_Usuario'];
    $listaStatus = $lista['Status'];

    $canAccess = false;

    if ($listaStatus === 'Publica') {
        if ($requestedUserId === null || $requestedUserId === $ownerId) {
            $canAccess = true;
        }
    } elseif ($listaStatus === 'Privada') {
        if ($loggedInUserId !== null && $loggedInUserId === $ownerId) {
            $canAccess = true;
        }
    }

    if (!$canAccess) {
        $response['error'] = 'Acceso denegado a esta lista. Es privada o no pertenece al usuario especificado.';
        echo json_encode($response);
        exit;
    }

    $response['lista'] = $lista; 

    $sqlProductos = "SELECT p.ID, p.Nombre, p.Descripcion, p.Precio, 
                             p.FotoPrincipal, p.Stock, p.Vendidos
                     FROM Lista_Producto lp 
                     JOIN Producto p ON lp.ID_Producto = p.ID
                     WHERE lp.ID_Lista = ?
                     ORDER BY lp.Orden ASC";
    
    $stmtProductos = $conn->prepare($sqlProductos);
    if (!$stmtProductos) {
        throw new Exception("Error al preparar la consulta de productos: " . $conn->error);
    }
    $stmtProductos->bind_param("i", $listaId);
    $stmtProductos->execute();
    $resultProductos = $stmtProductos->get_result();
    
    $productos = [];
    while ($row = $resultProductos->fetch_assoc()) {
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