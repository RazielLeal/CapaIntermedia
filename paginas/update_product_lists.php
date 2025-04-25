<?php
include 'conexion.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['username']) || !isset($data['productId']) || !isset($data['changes'])) {
        throw new Exception('Datos inválidos');
    }
    
    $conn = conectarDB();
    
    // Obtener ID del usuario
    $stmt = $conn->prepare("SELECT ID FROM Usuario WHERE Nickname = ?");
    $stmt->bind_param("s", $data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Usuario no encontrado');
    }
    
    $userId = $result->fetch_assoc()['ID'];
    $productId = $data['productId'];
    
    // Procesar cada cambio
    foreach ($data['changes'] as $change) {
        $listId = $change['listId'];
        $checked = $change['checked'];
        
        // Verificar que la lista pertenece al usuario
        $stmt = $conn->prepare("SELECT ID FROM Lista WHERE ID = ? AND ID_Usuario = ?");
        $stmt->bind_param("ii", $listId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            continue; // La lista no pertenece al usuario
        }
        
        if ($checked) {
            // Agregar producto a la lista si no está ya
            $stmt = $conn->prepare("INSERT IGNORE INTO Lista_Producto (ID_Lista, ID_Producto) VALUES (?, ?)");
            $stmt->bind_param("ii", $listId, $productId);
            $stmt->execute();
        } else {
            // Eliminar producto de la lista
            $stmt = $conn->prepare("DELETE FROM Lista_Producto WHERE ID_Lista = ? AND ID_Producto = ?");
            $stmt->bind_param("ii", $listId, $productId);
            $stmt->execute();
        }
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
    echo json_encode($response);
}
?>