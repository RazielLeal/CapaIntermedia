<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$conn = conectarDB();

function obtenerUsuarioId() {
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    }
    
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        return intval($token);
    }
    
    return null;
}

$usuarioId = obtenerUsuarioId();

if (!$usuarioId) {
    echo json_encode(["success" => false, "error" => "Usuario no autenticado"]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$nuevoEstado = $data['nuevoEstado'];

if (!in_array($nuevoEstado, ['Pendiente', 'Comprado', 'Cancelado'])) {
    echo json_encode(["success" => false, "error" => "Estado inválido"]);
    exit();
}

try {
    $conn->begin_transaction();

    // 1. Actualizar estado del carrito
    $update = "UPDATE Carrito SET Status = ? WHERE ID_Usuario = ? AND Status = 'Pendiente'";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("si", $nuevoEstado, $usuarioId);
    $stmt->execute();

    // 2. Si es una compra, actualizar stock y vendidos
    if ($nuevoEstado === 'Comprado') {
        // Obtener items del carrito
        $query = "SELECT ID_Producto, Cantidad FROM Carrito WHERE ID_Usuario = ? AND Status = 'Comprado'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Actualizar producto
            $updateProducto = "UPDATE Producto SET Stock = Stock - ?, Vendidos = Vendidos + ? WHERE ID = ?";
            $stmtUpdate = $conn->prepare($updateProducto);
            $stmtUpdate->bind_param("iii", $row['Cantidad'], $row['Cantidad'], $row['ID_Producto']);
            $stmtUpdate->execute();
        }
    }

    $conn->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>