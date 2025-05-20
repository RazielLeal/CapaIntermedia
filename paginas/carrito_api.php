<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$conn = conectarDB();

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión a la base de datos: " . $conn->connect_error]);
    exit();
}

/** * @return int|null  */

function obtenerUsuarioId() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        if (is_numeric($token)) {
            return intval($token);
        }
    }
    
    if (isset($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    }
    
    return null;
}

$usuarioId = obtenerUsuarioId();

if (!$usuarioId) {
    http_response_code(401); 
    echo json_encode(["success" => false, "error" => "Usuario no autenticado", "code" => 401]);
    $conn->close();
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (isset($data['productId']) && isset($data['cantidad'])) {
            $productoId = intval($data['productId']);
            $cantidad = intval($data['cantidad']);
            
            if ($cantidad <= 0) {
                http_response_code(400); 
                echo json_encode(["success" => false, "error" => "La cantidad debe ser un número positivo."]);
                $conn->close();
                exit();
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare("SELECT Stock, Precio FROM Producto WHERE ID = ?");
            if (!$stmt) {
                throw new Exception("Error en preparación de consulta (Stock): " . $conn->error);
            }
            $stmt->bind_param("i", $productoId);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            
            if (!$producto) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Producto no encontrado."]);
                $conn->close();
                exit();
            }
            
            $precioUnitario = $producto['Precio'];
            $stockDisponible = $producto['Stock'];

            $stmt = $conn->prepare("SELECT ID, Cantidad FROM Carrito WHERE ID_Usuario = ? AND ID_Producto = ? AND Status = 'Pendiente'");
            if (!$stmt) {
                throw new Exception("Error en preparación de consulta (Carrito Existente): " . $conn->error);
            }
            $stmt->bind_param("ii", $usuarioId, $productoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingCartItem = $result->fetch_assoc();
            
            $total_en_carrito_actual = $existingCartItem ? $existingCartItem['Cantidad'] : 0;
            
            $nueva_cantidad_total = $total_en_carrito_actual + $cantidad; 

            if ($nueva_cantidad_total > $stockDisponible) {
                $conn->rollback();
                http_response_code(409); 
                echo json_encode([
                    "success" => false, 
                    "error" => "Stock insuficiente. Cantidad disponible en total (incluyendo lo que ya tienes): " . $stockDisponible . ". Puedes añadir hasta " . ($stockDisponible - $total_en_carrito_actual) . " más.",
                    "availableToAdd" => $stockDisponible - $total_en_carrito_actual,
                    "currentStock" => $stockDisponible,
                    "currentInCart" => $total_en_carrito_actual 
                ]);
                $conn->close();
                exit();
            }
            
            if ($existingCartItem) {
                $carritoId = $existingCartItem['ID'];
                $update = "UPDATE Carrito SET Cantidad = ?, Total = ? WHERE ID = ? AND ID_Usuario = ? AND Status = 'Pendiente'";
                $stmt = $conn->prepare($update);
                if (!$stmt) {
                    throw new Exception("Error en preparación de actualización (Carrito): " . $conn->error);
                }
                $totalProducto = $nueva_cantidad_total * $precioUnitario;
                $stmt->bind_param("idii", $nueva_cantidad_total, $totalProducto, $carritoId, $usuarioId);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO Carrito (ID_Usuario, ID_Producto, Cantidad, Total, Status) 
                                     VALUES (?, ?, ?, ?, 'Pendiente')";
                $stmt = $conn->prepare($insert);
                if (!$stmt) {
                    throw new Exception("Error en preparación de inserción (Carrito): " . $conn->error);
                }
                $totalProducto = $cantidad * $precioUnitario;
                $stmt->bind_param("iiid", $usuarioId, $productoId, $cantidad, $totalProducto);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Producto añadido/actualizado en el carrito correctamente."]);
        } 
        elseif (isset($data['action']) && $data['action'] === 'process_payment') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT c.ID, c.ID_Producto, c.Cantidad, c.Total, p.Stock, p.Precio 
                                         FROM Carrito c 
                                         JOIN Producto p ON c.ID_Producto = p.ID 
                                         WHERE c.ID_Usuario = ? AND c.Status = 'Pendiente' FOR UPDATE"); 
                if (!$stmt) {
                    throw new Exception("Error en preparación de consulta (Obtener carrito para pago): " . $conn->error);
                }
                $stmt->bind_param("i", $usuarioId);
                $stmt->execute();
                $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                if (empty($cartItems)) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => "El carrito está vacío o no hay ítems pendientes para procesar."]);
                    $conn->close();
                    exit();
                }

                foreach ($cartItems as $item) {
                    $carritoItemId = $item['ID'];
                    $productoId = $item['ID_Producto'];
                    $cantidadComprada = $item['Cantidad'];
                    $precioUnitario = $item['Precio'];
                    $stockActual = $item['Stock'];
                    $totalItem = $item['Total'];

                    if ($cantidadComprada > $stockActual) {
                        $conn->rollback();
                        http_response_code(409); 
                        echo json_encode(["success" => false, "error" => "Stock insuficiente para el producto ID " . $productoId . ". Disponible: " . $stockActual . ", Solicitado: " . $cantidadComprada]);
                        $conn->close();
                        exit();
                    }

                    $stmt = $conn->prepare("UPDATE Producto SET Stock = Stock - ?, Vendidos = Vendidos + ? WHERE ID = ?");
                    if (!$stmt) {
                        throw new Exception("Error en preparación de actualización (Stock Producto): " . $conn->error);
                    }
                    $stmt->bind_param("iii", $cantidadComprada, $cantidadComprada, $productoId);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("No se pudo actualizar el stock para el producto ID " . $productoId . ". Puede que ya no exista o el stock sea 0.");
                    }
                    $stmt->close(); 

                    $stmtCheckVenta = $conn->prepare("SELECT ID, Cantidad, PrecioTotal FROM Venta WHERE ID_Usuario = ? AND ID_Producto = ? AND DATE(Fecha) = CURDATE()");
                    if (!$stmtCheckVenta) {
                        throw new Exception("Error en preparación de consulta (Verificar Venta Existente): " . $conn->error);
                    }
                    $stmtCheckVenta->bind_param("ii", $usuarioId, $productoId);
                    $stmtCheckVenta->execute();
                    $existingVenta = $stmtCheckVenta->get_result()->fetch_assoc();
                    $stmtCheckVenta->close(); 

                    if ($existingVenta) {
                        $nuevaCantidadVenta = $existingVenta['Cantidad'] + $cantidadComprada;
                        $nuevoTotalVenta = $existingVenta['PrecioTotal'] + $totalItem;

                        $updateVenta = "UPDATE Venta SET Cantidad = ?, PrecioTotal = ? WHERE ID = ?";
                        $stmt = $conn->prepare($updateVenta);
                        if (!$stmt) {
                            throw new Exception("Error en preparación de actualización (Venta Consolidada): " . $conn->error);
                        }
                        $stmt->bind_param("idi", $nuevaCantidadVenta, $nuevoTotalVenta, $existingVenta['ID']);
                        $stmt->execute();
                        if ($stmt->affected_rows === 0) {
                            throw new Exception("No se pudo actualizar la venta consolidada para el producto ID " . $productoId);
                        }
                        $stmt->close(); 
                    } else {
                        $insertVenta = "INSERT INTO Venta (ID_Usuario, ID_Producto, Cantidad, PrecioTotal, Fecha) 
                                         VALUES (?, ?, ?, ?, NOW())";
                        $stmt = $conn->prepare($insertVenta);
                        if (!$stmt) {
                            throw new Exception("Error en preparación de inserción (Nueva Venta): " . $conn->error);
                        }
                        $stmt->bind_param("iiid", $usuarioId, $productoId, $cantidadComprada, $totalItem);
                        $stmt->execute();
                        if ($stmt->affected_rows === 0) {
                            throw new Exception("No se pudo registrar la venta para el producto ID " . $productoId);
                        }
                        $stmt->close(); 
                    }

                    $updateCartStatus = "UPDATE Carrito SET Status = 'Comprado' WHERE ID = ? AND ID_Usuario = ?";
                    $stmt = $conn->prepare($updateCartStatus);
                    if (!$stmt) {
                        throw new Exception("Error en preparación de actualización (Estado Carrito): " . $conn->error);
                    }
                    $stmt->bind_param("ii", $carritoItemId, $usuarioId);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("No se pudo actualizar el estado del carrito para el ítem ID " . $carritoItemId);
                    }
                    $stmt->close(); 
                }

                $conn->commit();
                echo json_encode(["success" => true, "message" => "Pago procesado y carrito vaciado/actualizado correctamente."]);

            } catch (Exception $e) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Error al procesar el pago: " . $e->getMessage()]);
            }
        } 
        else {
            http_response_code(400); 
            echo json_encode(["success" => false, "error" => "Acción POST no reconocida o datos incompletos."]);
            $conn->close();
            exit();
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') { 
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!is_array($data) || !isset($data['carritoId']) || !isset($data['cantidad'])) {
            http_response_code(400); 
            echo json_encode(["success" => false, "error" => "Datos de entrada inválidos. Se esperaban 'carritoId' y 'cantidad'."]);
            $conn->close();
            exit();
        }

        $carritoId = intval($data['carritoId']);
        $newQuantity = intval($data['cantidad']);
        
        if ($newQuantity < 1) {
            http_response_code(400); 
            echo json_encode(["success" => false, "error" => "La cantidad para actualizar debe ser al menos 1. Use DELETE para eliminar el producto."]);
            $conn->close();
            exit();
        }
        
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT c.ID_Producto, p.Stock, p.Precio FROM Carrito c JOIN Producto p ON c.ID_Producto = p.ID WHERE c.ID = ? AND c.ID_Usuario = ? AND c.Status = 'Pendiente'");
        if (!$stmt) {
            throw new Exception("Error en preparación de consulta (PUT Carrito): " . $conn->error);
        }
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if (!$item) {
            $conn->rollback();
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Ítem de carrito no encontrado o no pertenece al usuario o ya no está pendiente."]);
            $conn->close();
            exit();
        }

        $productoId = $item['ID_Producto'];
        $maxStock = $item['Stock'];
        $precioUnitario = $item['Precio'];
        
        if ($newQuantity > $maxStock) {
            $conn->rollback();
            http_response_code(409);
            echo json_encode([
                "success" => false, 
                "error" => "Stock insuficiente para la cantidad solicitada. Stock máximo para este producto: " . $maxStock,
                "available" => $maxStock
            ]);
            $conn->close();
            exit();
        }
        
        $totalItem = $newQuantity * $precioUnitario;
        $update = "UPDATE Carrito SET Cantidad = ?, Total = ? WHERE ID = ? AND ID_Usuario = ? AND Status = 'Pendiente'";
        $stmt = $conn->prepare($update);
        if (!$stmt) {
            throw new Exception("Error en preparación de actualización (PUT Cantidad): " . $conn->error);
        }
        $stmt->bind_param("idii", $newQuantity, $totalItem, $carritoId, $usuarioId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Cantidad actualizada.", "new_total_item" => $totalItem]);
        } else {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "No se pudo actualizar la cantidad. Puede que la cantidad ya sea la misma o el ítem no exista."]);
        }
        $stmt->close();

    } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') { 
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data) || !isset($data['carritoId'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Datos de entrada inválidos. Se esperaba 'carritoId'."]);
            $conn->close();
            exit();
        }

        $carritoId = intval($data['carritoId']);
        
        $delete = "DELETE FROM Carrito WHERE ID = ? AND ID_Usuario = ? AND Status = 'Pendiente'";
        $stmt = $conn->prepare($delete);
        if (!$stmt) {
            throw new Exception("Error en preparación de eliminación (DELETE Carrito): " . $conn->error);
        }
        $stmt->bind_param("ii", $carritoId, $usuarioId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Producto eliminado del carrito."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Ítem no encontrado en el carrito o no se pudo eliminar."]);
        }
        $stmt->close();
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT 
                      c.ID, 
                      c.Cantidad, 
                      c.Total, 
                      p.ID AS ProductoID, 
                      p.Nombre, 
                      p.Precio, 
                      p.FotoPrincipal, 
                      p.Stock, 
                      p.Vendidos
                    FROM Carrito c
                    JOIN Producto p ON c.ID_Producto = p.ID
                    WHERE c.ID_Usuario = ? AND c.Status = 'Pendiente'";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error en preparación de consulta (GET Carrito): " . $conn->error);
        }
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $carrito = [];
        while ($row = $result->fetch_assoc()) {
            $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
            $carrito[] = $row;
        }
        
        echo json_encode(["success" => true, "carrito" => $carrito]);
        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Método no permitido."]);
    }

} catch (Exception $e) {
    
    if ($conn->in_transaction) { 
        $conn->rollback();
    }
    http_response_code(500); 
    echo json_encode(["success" => false, "error" => "Error del servidor: " . $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>