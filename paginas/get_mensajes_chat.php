<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

if (!isset($_SESSION['usuario_id']) || !isset($_GET['id_chat'])) {
    echo json_encode(["success" => false, "message" => "Acceso denegado.", "mensajes" => []]);
    exit();
}

//TODO ESTO PARA EL CHAT DEL COMPRADOR
$id_chat = intval($_GET['id_chat']);

$sql = "SELECT mensajes.mensaje, mensajes.id_usuario  FROM mensajes WHERE mensajes.id_chat = ? ORDER BY fecha ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_chat);
$stmt->execute();
$result = $stmt->get_result();

$mensajes = [];
while ($row = $result->fetch_assoc()) {
    $mensajes[] = [
        "mensaje" => $row["mensaje"],
        "es_usuario" => ($row["id_usuario"] == $_SESSION['usuario_id'])
    ];
}

$stmt->close();
$conn->close();

echo json_encode(["success" => true, "mensajes" => $mensajes]);
?>
