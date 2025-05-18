<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || !isset($_POST['mensaje']) || !isset($_POST['id_chat'])) {
    echo json_encode(["success" => false, "message" => "Datos insuficientes."]);
    exit();
}

$id_usuario = intval($_SESSION['usuario_id']);
$id_chat = intval($_POST['id_chat']);
$mensaje = trim($_POST['mensaje']);

if ($mensaje === "") {
    echo json_encode(["success" => false, "message" => "El mensaje no puede estar vacío."]);
    exit();
}

// Insertar mensaje en la base de datos
$sql_insertar = "INSERT INTO mensajes (id_chat, id_usuario, mensaje, fecha) VALUES (?, ?, ?, NOW())";
$stmt_insertar = $conn->prepare($sql_insertar);
$stmt_insertar->bind_param("iis", $id_chat, $id_usuario, $mensaje);

if ($stmt_insertar->execute()) {
    echo json_encode(["success" => true, "message" => "Mensaje enviado correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al enviar el mensaje."]);
}

$stmt_insertar->close();
$conn->close();
exit();
?>
