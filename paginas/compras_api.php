<?php
header('Content-Type: application/json');
require_once 'conexion.php';

$response = ['success' => false, 'error' => ''];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'get_purchases' && isset($_GET['username'])) {
        $username = $_GET['username'];

        try {
            $conn = conectarDB(); 
            $stmt_user = $conn->prepare("SELECT ID FROM Usuario WHERE Nickname = ?");
            $stmt_user->bind_param('s', $username);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            $user = $result_user->fetch_assoc();
            $stmt_user->close();

            if ($user) {
                $user_id = $user['ID'];

                $stmt_purchases = $conn->prepare("
                SELECT
                    v.ID AS purchase_id,
                    v.ID_Producto AS product_id,
                    p.Nombre AS product_name,
                    v.Cantidad AS quantity,
                    v.PrecioTotal AS total_price,
                    v.Fecha AS purchase_date,
                    v.Calificacion AS existing_rating,
                    v.Comentario AS existing_comment, 
                    GROUP_CONCAT(c.Nombre SEPARATOR ', ') AS categories
                FROM Venta v
                JOIN Producto p ON v.ID_Producto = p.ID
                LEFT JOIN Producto_Categoria pc ON p.ID = pc.ID_Producto
                LEFT JOIN Categoria c ON pc.ID_Categoria = c.ID
                WHERE v.ID_Usuario = ?
                GROUP BY v.ID
                ORDER BY v.Fecha DESC
                ");
                $stmt_purchases->bind_param('i', $user_id);
                $stmt_purchases->execute();
                $result_purchases = $stmt_purchases->get_result();
                $purchases = $result_purchases->fetch_all(MYSQLI_ASSOC);
                $stmt_purchases->close();

                $response['success'] = true;
                $response['purchases'] = $purchases;
            } else {
                $response['error'] = 'Usuario no encontrado.';
            }
        } catch (Exception $e) {
            $response['error'] = 'Error de base de datos al obtener compras: ' . $e->getMessage();
        } finally {
            if (isset($conn) && $conn) {
                $conn->close();
            }
        }
    } else {
        $response['error'] = 'Acción GET no válida o parámetros faltantes.';
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action']) && $input['action'] === 'add_review') {
        $purchaseId = $input['purchase_id'] ?? null;
        $productId = $input['product_id'] ?? null;
        $rating = $input['rating'] ?? null;
        $comment = $input['comment'] ?? '';

        $headers = getallheaders();
        $username = null;
        if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $username = $matches[1];
        }

        if (!$username) {
            $response['error'] = 'No autorizado: usuario no identificado.';
            echo json_encode($response);
            exit();
        }

        if ($purchaseId && $rating !== null && $comment !== null) {
            try {
                $conn = conectarDB();

                $stmt_update_venta = $conn->prepare("
                    UPDATE Venta
                    SET Calificacion = ?, Comentario = ?
                    WHERE ID = ?
                "); 
                $stmt_update_venta->bind_param('dsi', $rating, $comment, $purchaseId);

                if ($stmt_update_venta->execute()) {
                    if ($stmt_update_venta->affected_rows > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Reseña ' . ($rating === 0 ? 'eliminada' : 'actualizada') . ' correctamente.';
                    } else {
                        $response['error'] = 'No se encontró la compra para actualizar la reseña o no hubo cambios.';
                    }
                } else {
                    $response['error'] = 'Error al actualizar la reseña en la tabla Venta: ' . $stmt_update_venta->error;
                }
                $stmt_update_venta->close();

            } catch (Exception $e) {
                $response['error'] = 'Error de base de datos al añadir/editar reseña: ' . $e->getMessage();
            } finally {
                if (isset($conn) && $conn) {
                    $conn->close();
                }
            }
        } else {
            $response['error'] = 'Datos de reseña incompletos.';
        }
    } else {
        $response['error'] = 'Acción POST no válida.';
    }
} else {
    $response['error'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>