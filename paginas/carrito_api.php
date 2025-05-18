<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Añadido PUT
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$conn = conectarDB();

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}



function obtenerUsuarioId() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        // Asumiendo que el token es el ID de usuario (si tu autenticación lo maneja así)
        // Si tu autenticación es JWT, necesitarías decodificarlo y obtener el ID del payload.
        return intval($token); 
    }
    
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
        // Log de usuario_id para depuración
        echo "<script>console.log('usuario_id: " . $_SESSION['usuario_id'] . "');</script>";
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
        
        $conn->begin_transaction();

        // Obtener el stock disponible del producto
        $stmt = $conn->prepare("SELECT Stock, Precio FROM Producto WHERE ID = ?");
        $stmt->bind_param("i", $productoId);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
        
        if (!$producto) {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
            exit();
        }
        
        $precioUnitario = $producto['Precio'];

        // Obtener la cantidad actual en el carrito (solo si el status es 'Pendiente')
        $stmt = $conn->prepare("SELECT ID, Cantidad FROM Carrito WHERE ID_Usuario = ? AND ID_Producto = ? AND Status = 'Pendiente'");
        $stmt->bind_param("ii", $usuarioId, $productoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingCartItem = $result->fetch_assoc();
        
        $total_en_carrito_actual = $existingCartItem ? $existingCartItem['Cantidad'] : 0;
        
        // La cantidad que se intenta agregar (solo para POST, para PUT es la cantidad final)
        $cantidad_a_agregar = $cantidad; 
        $nueva_cantidad_total = $total_en_carrito_actual + $cantidad_a_agregar;

        if ($nueva_cantidad_total > $producto['Stock']) {
            $conn->rollback();
            echo json_encode([
                "success" => false, 
                "error" => "Stock insuficiente. Disponible en total: " . ($producto['Stock'] - $total_en_carrito_actual), // Cuánto más se puede agregar
                "available" => $producto['Stock'] - $total_en_carrito_actual // Cantidad máxima que se puede agregar
            ]);
            exit();
        }
        
        if ($existingCartItem) {
            // Actualizar cantidad existente
            $carritoId = $existingCartItem['ID'];
            $update = "UPDATE Carrito SET Cantidad = ?, Total = ? * ? WHERE ID = ? AND ID_Usuario = ?";
            $stmt = $conn->prepare($update);
            $totalProducto = $nueva_cantidad_total * $precioUnitario;
            $stmt->bind_param("idiii", $nueva_cantidad_total, $precioUnitario, $nueva_cantidad_total, $carritoId, $usuarioId);
            $stmt->execute();
        } else {
            // Insertar nuevo registro
            $insert = "INSERT INTO Carrito (ID_Usuario, ID_Producto, Cantidad, Total, Status) 
                       VALUES (?, ?, ?, ? * ?, 'Pendiente')";
            $stmt = $conn->prepare($insert);
            $totalProducto = $cantidad * $precioUnitario;
            $stmt->bind_param("iiidi", $usuarioId, $productoId, $cantidad, $precioUnitario, $cantidad);
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(["success" => true]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') { // NUEVO MÉTODO: PUT para actualizar cantidad
        $data = json_decode(file_get_contents('php://input'), true);
        
        $carritoId = intval($data['carritoId']); // ID de la fila en la tabla Carrito
        $newQuantity = intval($data['cantidad']);
        
        if ($newQuantity < 1) {
            // Si la cantidad es 0 o menos, se asume que se quiere eliminar el ítem.
            // Se puede manejar aquí o dejar que el frontend llame a DELETE.
            // Por ahora, la lógica del frontend ya llama a DELETE si newQuantity < 1.
            echo json_encode(["success" => false, "error" => "La cantidad debe ser al menos 1. Use DELETE para eliminar el producto."]);
            exit();
        }
        
        $conn->begin_transaction();

        // Obtener información del item del carrito y del producto
        $stmt = $conn->prepare("SELECT c.ID_Producto, p.Stock, p.Precio FROM Carrito c JOIN Producto p ON c.ID_Producto = p.ID WHERE c.ID = ? AND c.ID_Usuario = ? AND c.Status = 'Pendiente'");
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();

        if (!$item) {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => "Ítem de carrito no encontrado o no pertenece al usuario."]);
            exit();
        }

        $productoId = $item['ID_Producto'];
        $maxStock = $item['Stock'];
        $precioUnitario = $item['Precio'];
        
        // Verificar stock
        if ($newQuantity > $maxStock) {
            $conn->rollback();
            echo json_encode([
                "success" => false, 
                "error" => "Stock insuficiente para la cantidad solicitada. Stock máximo: " . $maxStock,
                "available" => $maxStock // Cantidad máxima que se puede establecer
            ]);
            exit();
        }
        
        // Actualizar cantidad y total
        $totalItem = $newQuantity * $precioUnitario;
        $update = "UPDATE Carrito SET Cantidad = ?, Total = ? WHERE ID = ? AND ID_Usuario = ? AND Status = 'Pendiente'";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("idii", $newQuantity, $totalItem, $carritoId, $usuarioId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode(["success" => true, "new_total_item" => $totalItem]);
        } else {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => "No se pudo actualizar la cantidad. Puede que la cantidad ya sea la misma o el ítem no exista."]);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $carritoId = intval($data['carritoId']);
        
        // Eliminamos el ítem directamente (ya no necesitamos liberar stock explícitamente aquí,
        // ya que la validación de stock ocurre al agregar o actualizar y se asume que el stock
        // solo se reserva al marcar como 'Comprado')
        $delete = "DELETE FROM Carrito WHERE ID = ? AND ID_Usuario = ?";
        $stmt = $conn->prepare($delete);
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Ítem no encontrado o no se pudo eliminar."]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT c.ID, c.Cantidad, c.Total, p.ID as ProductoID, p.Nombre, p.Precio, 
                             p.FotoPrincipal, p.Stock, p.Vendidos
                      FROM Carrito c
                      JOIN Producto p ON c.ID_Producto = p.ID
                      WHERE c.ID_Usuario = ? AND c.Status = 'Pendiente'"; // Solo ítems pendientes
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $carrito = [];
        while ($row = $result->fetch_assoc()) {
            $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
            // El stock disponible se calcula en base al stock total del producto, no se resta lo que ya está en el carrito
            // porque se asume que 'Stock' ya representa la cantidad actual real disponible para nuevas adiciones.
            // Para la compra, el 'Stock' del producto es el límite.
            $carrito[] = $row;
        }
        
        echo json_encode(["success" => true, "carrito" => $carrito]);
    }
} catch (Exception $e) {
    $conn->rollback(); // Asegurar rollback en caso de error
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>