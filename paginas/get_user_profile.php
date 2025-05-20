<?php
include 'conexion.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID de usuario no proporcionado en la URL.');
    }

    $user_id = intval($_GET['id']); 

    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido o no encontrado.');
    }

    $conn = conectarDB(); 
    
    $sql = "SELECT ID, Nickname, Correo, DiaDeRegistro, Avatar, Rol, Estatus FROM Usuario WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('Error al preparar la consulta SQL: ' . $conn->error . ' (SQL: ' . $sql . ')');
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();

        $response = [
            'success' => true,
            'nickname' => $user_data['Nickname'],
            'photo' => $user_data['Avatar'] ? base64_encode($user_data['Avatar']) : null,
            'is_private' => ($user_data['Estatus'] === 'Privado') 
        ];

        if (!$response['is_private']) {
            $response['email'] = $user_data['Correo']; 
            $response['register_date'] = $user_data['DiaDeRegistro']; 
            $response['rol'] = $user_data['Rol'];
        }
        $response['user_id'] = $user_data['ID']; 

    } else {
        throw new Exception('Usuario con ID "' . $user_id . '" no encontrado en la base de datos.');
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    echo json_encode($response);
}
?>