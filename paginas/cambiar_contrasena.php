<?php
require_once 'conexion.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$contrasena_actual = $data['contrasena_actual'] ?? '';
$nueva_contrasena = $data['nueva_contrasena'] ?? '';

if (empty($username) || empty($contrasena_actual) || empty($nueva_contrasena)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$conn = conectarDB();

// Verificar contraseña actual
$stmt = $conn->prepare("SELECT Contrasena FROM Usuario WHERE Nickname = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

$usuario = $result->fetch_assoc();

if (!password_verify($contrasena_actual, $usuario['Contrasena'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
    exit;
}

// Actualizar contraseña
$hashed_password = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE Usuario SET Contrasena = ? WHERE Nickname = ?");
$update_stmt->bind_param("ss", $hashed_password, $username);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>