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

    if (!$compradorId) {
        $response['message'] = 'Usuario no autenticado.';
        echo json_encode($response);
        exit;
    }

    if (!$chatId || !$messageId) {
        $response['message'] = 'Faltan datos requeridos para rechazar la cotización.';
        echo json_encode($response);
        exit;
    }

    try {
        $conn = conectarDB();

        $sql = "UPDATE Mensajes SET Estado = 'InactivaNegativa' WHERE ID = ? AND ID_Chat = ? AND Estado = 'Activa'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        $stmt->bind_param("ii", $messageId, $chatId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Cotización rechazada.';
        } else {
            $response['message'] = 'La cotización ya ha sido respondida o no está activa para rechazar.';
        }
        $stmt->close();

    } catch (Exception $e) {
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