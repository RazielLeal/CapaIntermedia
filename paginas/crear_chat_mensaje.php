<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Usuario no autenticado."]);    
    exit();
}

$id_usuario = intval($_SESSION['usuario_id']);
$vendedorId = intval($_SESSION['vendedor_id']);
$id_producto = intval($_POST['productId']);

// Verificar si ambos usuarios existen en la tabla usuario
$sql_verificar_usuarios = "SELECT COUNT(*) FROM usuario WHERE ID IN (?, ?)";
$stmt_verificar = $conn->prepare($sql_verificar_usuarios);
$stmt_verificar->bind_param("ii", $id_usuario, $vendedorId);
$stmt_verificar->execute();
$stmt_verificar->bind_result($usuarios_existentes);
$stmt_verificar->fetch();
$stmt_verificar->close();

if ($usuarios_existentes < 2) {
    echo json_encode([
        "success" => false, 
        "message" => "Uno o ambos usuarios no existen en la base de datos."
    ]);
    exit();
}

// Primero, verifica si ya existe un chat entre estos usuarios
$sql_existe = "SELECT id_chat FROM chat WHERE usuario1 = ? AND usuario2 = ? AND id_producto = ?";
$stmt_existe = $conn->prepare($sql_existe);
$stmt_existe->bind_param("iii", $id_usuario, $vendedorId, $id_producto);
$stmt_existe->execute();
$stmt_existe->store_result();

if ($stmt_existe->num_rows > 0) {
    // Si existe, devuelve el chat_id
    $stmt_existe->bind_result($chat_id);
    $stmt_existe->fetch();
    $stmt_existe->close();
    $response = [
        "success" => true, 
        "message" => "Chat ya existente.", 
        "chat_id" => $chat_id
    ];
} else {
    $stmt_existe->close();
    
    // Insertar el chat
    $sql_insertar = "INSERT INTO chat (usuario1, usuario2, id_producto) VALUES (?, ?, ?)";
    $stmt_insertar = $conn->prepare($sql_insertar);
    $stmt_insertar->bind_param("iii", $id_usuario, $vendedorId, $id_producto);
    $stmt_insertar->execute();
    
    if ($stmt_insertar->affected_rows > 0) {
        $chat_id = $conn->insert_id; // Obtener el ID insertado
        $response = [
            "success" => true, 
            "message" => "Chat registrado correctamente.", 
            "chat_id" => $chat_id
        ];
    } else {
        $response = [
            "success" => false, 
            "message" => "Error al registrar el chat."
        ];
    }
    
    $stmt_insertar->close();
}

$conn->close();
echo json_encode($response);
exit();
?>
