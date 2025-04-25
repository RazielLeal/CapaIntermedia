<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'conexion.php';

// Obtener el contenido JSON del cuerpo de la solicitud
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $data) {
    $userId = isset($data['user_id']) ? intval($data['user_id']) : null;
    $nombre = isset($data['nombre']) ? trim($data['nombre']) : null;
    $descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : null;
    $status = isset($data['status']) ? trim($data['status']) : null;

    // Validar datos
    if (empty($nombre)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "El nombre de la lista es requerido"]);
        exit();
    }

    if (!in_array($status, ['Publica', 'Privada'])) {
        $status = 'Publica';
    }

    $conn = conectarDB();

    // Insertar la nueva lista
    $sql = "INSERT INTO Lista (Nombre, Descripcion, Status, ID_Usuario) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error en la preparación de la consulta: " . $conn->error]);
        exit();
    }

    $stmt->bind_param("sssi", $nombre, $descripcion, $status, $userId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Lista creada exitosamente"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error al crear la lista: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido o datos inválidos"]);
}
?>