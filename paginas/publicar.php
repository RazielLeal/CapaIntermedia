<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Establecer la codificación de caracteres
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

$conn = new mysqli("localhost", "root", "", "PWInter");

// Verificar conexión y establecer charset
if ($conn->connect_error) {
    die(json_encode([
        "success" => false, 
        "error" => "Error de conexión: " . $conn->connect_error
    ]));
}
$conn->set_charset("utf8");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            "success" => false, 
            "error" => "Usuario no autenticado"
        ]);
        exit;
    }

    // Obtener datos del formulario (manera segura para multipart/form-data)
    $nombre = isset($_POST['nombre']) ? trim($conn->real_escape_string($_POST['nombre'])) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($conn->real_escape_string($_POST['descripcion'])) : '';
    $categoria = isset($_POST['categoria']) ? trim($conn->real_escape_string($_POST['categoria'])) : '';
    $precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;

    // Validación exhaustiva
    if (empty($nombre) || empty($descripcion) || empty($categoria) || $precio <= 0 || $stock < 0) {
        echo json_encode([
            "success" => false, 
            "error" => "Todos los campos son requeridos y deben ser válidos"
        ]);
        exit;
    }

    // Validar imagen principal
    if (empty($_FILES["fotoPrincipal"]["tmp_name"]) || $_FILES["fotoPrincipal"]["error"] != UPLOAD_ERR_OK) {
        echo json_encode([
            "success" => false, 
            "error" => "La imagen principal del producto es requerida"
        ]);
        exit;
    }

    // Procesar imágenes
    $fotoPrincipal = file_get_contents($_FILES["fotoPrincipal"]["tmp_name"]);
    $fotoExtra1 = (!empty($_FILES["fotoExtra1"]["tmp_name"]) && $_FILES["fotoExtra1"]["error"] == UPLOAD_ERR_OK) 
        ? file_get_contents($_FILES["fotoExtra1"]["tmp_name"]) 
        : NULL;
    $fotoExtra2 = (!empty($_FILES["fotoExtra2"]["tmp_name"]) && $_FILES["fotoExtra2"]["error"] == UPLOAD_ERR_OK) 
        ? file_get_contents($_FILES["fotoExtra2"]["tmp_name"]) 
        : NULL;

    // Procesar video (opcional)
    $video_path = NULL;
    if (!empty($_FILES["video"]["tmp_name"]) && $_FILES["video"]["error"] == UPLOAD_ERR_OK) {
        $video_dir = "uploads/videos/";
        if (!file_exists($video_dir)) {
            mkdir($video_dir, 0777, true);
        }
        $video_name = uniqid() . '_' . basename($_FILES["video"]["name"]);
        $video_path = $video_dir . $video_name;
        move_uploaded_file($_FILES["video"]["tmp_name"], $video_path);
    }

    // DEBUG: Mostrar datos antes de insertar
    error_log("Datos a insertar:");
    error_log("Categoría: " . $categoria);
    error_log("Tipo de categoría: " . gettype($categoria));
    error_log("Longitud categoría: " . strlen($categoria));

    // Consulta SQL alternativa para verificar el problema
    $sql = "INSERT INTO Producto (Nombre, Descripcion, FotoPrincipal, Categoria, Precio, Stock, ID_Usuario) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode([
            "success" => false, 
            "error" => "Error al preparar la consulta: " . $conn->error
        ]);
        exit;
    }

    // Enlazar parámetros de forma simplificada
    $null = NULL;
    $stmt->bind_param("ssbsdii", 
        $nombre, 
        $descripcion, 
        $null,
        $categoria,
        $precio, 
        $stock, 
        $_SESSION['user_id']
    );

    // Enviar BLOB
    $stmt->send_long_data(2, $fotoPrincipal);

    // Ejecutar consulta
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode([
            "success" => false, 
            "error" => "Error al publicar: " . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        "success" => false, 
        "error" => "Método de solicitud no válido"
    ]);
}
?>