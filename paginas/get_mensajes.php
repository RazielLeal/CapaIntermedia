<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Usuario no autenticado.", "mensajes" => []]);
    exit();
}

$id_usuario = intval($_SESSION['usuario_id']);
$chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : null;
//ESTO ES PARA EL CHAT DEL VENDEDOR PARA LAS LISTAS
// Obtener mensajes sin leer
$sql = "SELECT chat.id_chat as CHAT, chat.usuario1 as CompradorID, usuario.Nombre as Comprador, mensajes.mensaje as mensaje, chat.id_producto
FROM chat
JOIN mensajes on chat.usuario1=mensajes.id_usuario
JOIN usuario on chat.usuario1=usuario.ID
WHERE chat.usuario2=?
GROUP BY mensajes.fecha DESC;";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

$mensajes = [];
while ($row = $result->fetch_assoc()) {
    $mensajes[] = [
        "mensaje" => $row["mensaje"],
        "id_chat" => $row["CHAT"],
        "id_producto" => $row["id_producto"],
    ];
}

$stmt->close();
echo json_encode(["success" => true, "mensajes" => $mensajes]);
exit();
?>