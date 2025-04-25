<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "PWInter";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión: " . $conn->connect_error
    ]));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener y limpiar datos
    $nombre = trim($_POST["nombre"] ?? '');
    $descripcion = trim($_POST["descripcion"] ?? '');

    // Validaciones
    $errors = [];
    
    if (empty($nombre)) {
        $errors[] = "El nombre de la categoría es requerido";
    } elseif (strlen($nombre) > 100) {
        $errors[] = "El nombre no debe exceder 100 caracteres";
    }
    
    if (empty($descripcion)) {
        $errors[] = "La descripción es requerida";
    }

    if (!empty($errors)) {
        echo json_encode([
            "success" => false,
            "error" => implode(". ", $errors)
        ]);
        exit;
    }

    // Verificar si la categoría ya existe
    $check_sql = "SELECT ID FROM Categoria WHERE Nombre = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nombre);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error" => "Ya existe una categoría con ese nombre"
        ]);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Insertar nueva categoría
    $insert_sql = "INSERT INTO Categoria (Nombre, Descripcion) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ss", $nombre, $descripcion);

    if ($insert_stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Error al crear categoría: " . $insert_stmt->error
        ]);
    }

    $insert_stmt->close();
    $conn->close();
} else {
    echo json_encode([
        "success" => false,
        "error" => "Método no permitido"
    ]);
}
?>