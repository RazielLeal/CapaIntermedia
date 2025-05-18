<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Usuario no autenticado.", "chats" => []]);
    exit();
}

$id_usuario = intval($_SESSION['usuario_id']);

// Consultar los chats pendientes
$sql = "SELECT chat.id_chat, usuario.Nombre AS nombre_usuario, 
               (SELECT mensaje FROM mensajes WHERE id_chat = chat.id_chat ORDER BY fecha DESC LIMIT 1) AS ultimo_mensaje
        FROM chat
        INNER JOIN usuario ON (usuario.ID = chat.usuario1 OR usuario.ID = chat.usuario2)
        WHERE (chat.usuario1 = ? OR chat.usuario2 = ?) 
        GROUP BY chat.id_chat";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_usuario, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

$chats = [];
while ($row = $result->fetch_assoc()) {
    $chats[] = [
        "id_chat" => $row["id_chat"],
        "nombre_usuario" => $row["nombre_usuario"],
        "ultimo_mensaje" => $row["ultimo_mensaje"] ?: "No hay mensajes aún"
    ];
}

$stmt->close();
$conn->close();

// 🚨 Asegurar que solo se envíe JSON y no texto adicional
echo json_encode(["success" => true, "chats" => $chats]);
exit();