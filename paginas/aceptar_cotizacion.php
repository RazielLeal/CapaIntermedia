<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once 'conexion.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chatId = $_POST['chat_id'] ?? null;
    $messageId = $_POST['message_id'] ?? null;
    $compradorId = $_SESSION['usuario_id'] ?? null;
    $productId = $_POST['product_id'] ?? null;
    $precio = $_POST['precio'] ?? null;
    $stock = $_POST['stock'] ?? null;

    if (!$compradorId) {
        $response['message'] = 'Usuario no autenticado.';
        echo json_encode($response);
        exit;
    }

    if (!$chatId || !$messageId || !$productId || !isset($precio) || !isset($stock)) {
        $response['message'] = 'Faltan datos requeridos para aceptar la cotización.';
        echo json_encode($response);
        exit;
    }

    try {
        $conn = conectarDB();
        $conn->begin_transaction(); 
        
        $sqlCheckAndUpdateMessage = "UPDATE Mensajes SET Estado = 'InactivaPositiva' WHERE ID = ? AND ID_Chat = ? AND Estado = 'Activa'";
        $stmtMessage = $conn->prepare($sqlCheckAndUpdateMessage);
        if (!$stmtMessage) {
            throw new Exception("Error al preparar la consulta de actualización de mensaje: " . $conn->error);
        }
        $stmtMessage->bind_param("ii", $messageId, $chatId);
        $stmtMessage->execute();

        if ($stmtMessage->affected_rows === 0) {
            
            throw new Exception("La cotización ya ha sido respondida o no está activa.");
        }
        $stmtMessage->close();

        $sqlInsertOrUpdateCotizacion = "INSERT INTO Cotizacion_Producto_Usuario (ID_Usuario, ID_Producto, Cantidad, PrecioAcordado) VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE Cantidad = VALUES(Cantidad), PrecioAcordado = VALUES(PrecioAcordado)";
        $stmtCotizacion = $conn->prepare($sqlInsertOrUpdateCotizacion);
        if (!$stmtCotizacion) {
            throw new Exception("Error al preparar la consulta de cotización: " . $conn->error);
        }
        $stmtCotizacion->bind_param("iiss", $compradorId, $productId, $stock, $precio);

        if (!$stmtCotizacion->execute()) {
            throw new Exception("Error al guardar la cotización: " . $stmtCotizacion->error);
        }
        $stmtCotizacion->close();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Cotización aceptada y registrada.';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    } finally {
        if (isset($conn)) $conn->close();
        echo json_encode($response);
    }
} else {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
}
?>