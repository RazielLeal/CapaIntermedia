<?php
require_once 'conexion.php';

header('Content-Type: application/json');

// Obtener datos del formulario
$current_username = $_POST['current_username'] ?? '';
$nombre = $_POST['nombre'] ?? null;
$apellidoP = $_POST['apellidoP'] ?? null;
$apellidoM = $_POST['apellidoM'] ?? null;
$username = $_POST['username'] ?? null;
$genero = $_POST['genero'] ?? null;

// Procesar imagen de avatar si se subió
$avatar = null;
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $avatar = file_get_contents($_FILES['avatar']['tmp_name']);
}

$conn = conectarDB();

// Construir la consulta SQL dinámicamente
$sql = "UPDATE Usuario SET ";
$params = [];
$types = '';
$updates = [];

if (!empty($nombre)) {
    $updates[] = "Nombre = ?";
    $params[] = $nombre;
    $types .= 's';
}

if (!empty($apellidoP)) {
    $updates[] = "ApellidoPaterno = ?";
    $params[] = $apellidoP;
    $types .= 's';
}

if (!empty($apellidoM)) {
    $updates[] = "ApellidoMaterno = ?";
    $params[] = $apellidoM;
    $types .= 's';
}

if (!empty($username)) {
    // Verificar si el nuevo username ya existe
    $check_stmt = $conn->prepare("SELECT ID FROM Usuario WHERE Nickname = ? AND Nickname != ?");
    $check_stmt->bind_param("ss", $username, $current_username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso']);
        exit;
    }
    
    $updates[] = "Nickname = ?";
    $params[] = $username;
    $types .= 's';
}

if (!empty($genero)) {
    $updates[] = "Genero = ?";
    $params[] = $genero;
    $types .= 's';
}

if (!empty($avatar)) {
    $updates[] = "Avatar = ?";
    $params[] = $avatar;
    $types .= 's';
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron datos para actualizar']);
    exit;
}

$sql .= implode(", ", $updates) . " WHERE Nickname = ?";
$params[] = $current_username;
$types .= 's';

$stmt = $conn->prepare($sql);

// Vincular parámetros dinámicamente
$bind_names[] = $types;
for ($i = 0; $i < count($params); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $params[$i];
    $bind_names[] = &$$bind_name;
}

call_user_func_array([$stmt, 'bind_param'], $bind_names);

if ($stmt->execute()) {
    $response = ['success' => true];
    if (!empty($username)) {
        $response['new_username'] = $username;
    }
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>