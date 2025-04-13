<?php
require_once 'conexion.php';

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Nombre de usuario no proporcionado']);
    exit;
}

$conn = conectarDB();

$stmt = $conn->prepare("SELECT Nombre, ApellidoPaterno, ApellidoMaterno, Nickname, Genero FROM Usuario WHERE Nickname = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'nombre' => $usuario['Nombre'],
        'apellidoPaterno' => $usuario['ApellidoPaterno'],
        'apellidoMaterno' => $usuario['ApellidoMaterno'],
        'nickname' => $usuario['Nickname'],
        'genero' => $usuario['Genero']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
}

$stmt->close();
$conn->close();
?>