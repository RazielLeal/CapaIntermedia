<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$conn = conectarDB();

function obtenerUsuarioId() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        return intval($token);
    }
    
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    }
    
    return null;
}

$usuarioId = obtenerUsuarioId();

if (!$usuarioId) {
    echo json_encode(["success" => false, "error" => "Usuario no autenticado", "code" => 401]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $productoId = intval($data['productoId']);
        $cantidad = intval($data['cantidad']);
        
        // Obtener el stock disponible del producto
        $stmt = $conn->prepare("SELECT Stock FROM Producto WHERE ID = ?");
        $stmt->bind_param("i", $productoId);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
        
        if (!$producto) {
            echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
            exit();
        }
        
        // Obtener la cantidad actual en el carrito
        $stmt = $conn->prepare("SELECT SUM(Cantidad) as total_en_carrito FROM Carrito 
                              WHERE ID_Usuario = ? AND ID_Producto = ? AND Status = 'Pendiente'");
        $stmt->bind_param("ii", $usuarioId, $productoId);
        $stmt->execute();
        $carrito = $stmt->get_result()->fetch_assoc();
        
        $total_en_carrito = $carrito['total_en_carrito'] ?? 0;
        $stock_disponible = $producto['Stock'] - $total_en_carrito;
        
        if ($cantidad > $stock_disponible) {
            echo json_encode([
                "success" => false, 
                "error" => "Stock insuficiente", 
                "available" => $stock_disponible
            ]);
            exit();
        }
        
        $conn->begin_transaction();
        
        // Verificar si el producto ya está en el carrito
        $stmt = $conn->prepare("SELECT * FROM Carrito WHERE ID_Usuario = ? AND ID_Producto = ? AND Status = 'Pendiente'");
        $stmt->bind_param("ii", $usuarioId, $productoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Actualizar cantidad existente
            $row = $result->fetch_assoc();
            $nuevaCantidad = $row['Cantidad'] + $cantidad;
            
            $update = "UPDATE Carrito SET Cantidad = ?, Total = (SELECT Precio FROM Producto WHERE ID = ?) * ? WHERE ID = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("iiii", $nuevaCantidad, $productoId, $nuevaCantidad, $row['ID']);
            $stmt->execute();
        } else {
            // Insertar nuevo registro
            $insert = "INSERT INTO Carrito (ID_Usuario, ID_Producto, Cantidad, Total, Status) 
                      VALUES (?, ?, ?, (SELECT Precio FROM Producto WHERE ID = ?) * ?, 'Pendiente')";
            $stmt = $conn->prepare($insert);
            $stmt->bind_param("iiiii", $usuarioId, $productoId, $cantidad, $productoId, $cantidad);
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(["success" => true]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $carritoId = intval($data['carritoId']);
        
        // Primero obtenemos la cantidad para liberar el stock
        $stmt = $conn->prepare("SELECT ID_Producto, Cantidad FROM Carrito WHERE ID = ? AND ID_Usuario = ?");
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(["success" => false, "error" => "Ítem no encontrado"]);
            exit();
        }
        
        $item = $result->fetch_assoc();
        $productoId = $item['ID_Producto'];
        $cantidad = $item['Cantidad'];
        
        // Eliminamos el ítem
        $delete = "DELETE FROM Carrito WHERE ID = ? AND ID_Usuario = ?";
        $stmt = $conn->prepare($delete);
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        
        echo json_encode(["success" => true]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT c.ID, c.Cantidad, c.Total, p.ID as ProductoID, p.Nombre, p.Precio, 
                         p.FotoPrincipal, p.Stock, p.Vendidos
                  FROM Carrito c
                  JOIN Producto p ON c.ID_Producto = p.ID
                  WHERE c.ID_Usuario = ? AND c.Status = 'Pendiente'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $carrito = [];
        while ($row = $result->fetch_assoc()) {
            $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
            
            // Calcular stock disponible considerando lo que ya está en el carrito
            $row['StockDisponible'] = $row['Stock'] - $row['Cantidad'];
            
            $carrito[] = $row;
        }
        
        echo json_encode(["success" => true, "carrito" => $carrito]);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>