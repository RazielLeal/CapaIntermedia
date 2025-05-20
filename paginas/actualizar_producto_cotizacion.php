<?php
header('Content-Type: application/json');
include 'conexion.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_producto = $_POST['id_producto'] ?? null;
    $precio = $_POST['precio'] ?? null;
    $detalles = $_POST['detalles'] ?? null;

    if (!$id_producto || !is_numeric($id_producto)) {
        $response['message'] = 'ID de producto inválido.';
        echo json_encode($response);
        exit();
    }

    if (!is_numeric($precio) || $precio <= 0) {
        $response['message'] = 'Precio inválido. Debe ser un número positivo.';
        echo json_encode($response);
        exit();
    }

    if (empty($detalles)) {
        $response['message'] = 'Los detalles de la cotización no pueden estar vacíos.';
        echo json_encode($response);
        exit();
    }

    $conn = conectarDB();
    if (!$conn) {
        $response['message'] = 'Error de conexión a la base de datos.';
        echo json_encode($response);
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE Producto SET Precio = ?, Descripcion = ?, Status = 'Aceptado', tipo = 'Venta' WHERE ID = ?");
        
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        $stmt->bind_param("dsi", $precio, $detalles, $id_producto);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Producto actualizado exitosamente a tipo Venta.';
            } else {
                $response['message'] = 'No se encontró el producto o no hubo cambios para actualizar.';
            }
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    } finally {
        $conn->close();
    }

} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>