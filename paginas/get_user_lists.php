<?php
include 'conexion.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    if (!isset($_GET['username'])) {
        throw new Exception('Usuario no especificado');
    }
    
    if (!isset($_GET['productId'])) {
        throw new Exception('Producto no especificado');
    }
    
    $conn = conectarDB();
    
    // Obtener ID del usuario
    $stmt = $conn->prepare("SELECT ID FROM Usuario WHERE Nickname = ?");
    $stmt->bind_param("s", $_GET['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Usuario no encontrado');
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['ID'];
    $productId = $_GET['productId'];
    
    // Obtener todas las listas del usuario
    $stmt = $conn->prepare("SELECT ID, Nombre, Status FROM Lista WHERE ID_Usuario = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lists = [];
    while ($row = $result->fetch_assoc()) {
        $lists[] = $row;
    }
    
    // Verificar si el producto está en cada lista
    foreach ($lists as &$list) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Lista_Producto WHERE ID_Lista = ? AND ID_Producto = ?");
        $stmt->bind_param("ii", $list['ID'], $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        $list['enLista'] = ($count > 0);
    }
    
    $response['success'] = true;
    $response['lists'] = $lists;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
    echo json_encode($response);
}
?>