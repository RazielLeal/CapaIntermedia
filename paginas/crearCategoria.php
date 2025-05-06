<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Método no válido"]);
    exit;
}

require 'conexion.php';
$conn = conectarDB();
$conn->set_charset("utf8");

// Validar y sanitizar datos
$nombre = trim(filter_input(INPUT_POST, 'nombreCategoria', FILTER_SANITIZE_STRING));
$descripcion = trim(filter_input(INPUT_POST, 'descripcionCategoria', FILTER_SANITIZE_STRING));

if (empty($nombre) || empty($descripcion)) {
    echo json_encode(["success" => false, "error" => "Nombre y descripción son requeridos"]);
    exit;
}

try {
    // Verificar existencia previa
    $sqlCheck = "SELECT ID FROM Categoria WHERE Nombre = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("s", $nombre);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("Ya existe una categoría con ese nombre");
    }

    // Insertar nueva categoría
    $sqlInsert = "INSERT INTO Categoria (Nombre, Descripcion) VALUES (?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("ss", $nombre, $descripcion);
    
    if (!$stmtInsert->execute()) {
        throw new Exception("Error al crear categoría: " . $stmtInsert->error);
    }

    $nuevaCatId = $conn->insert_id;
    
    echo json_encode([
        "success" => true,
        "id" => $nuevaCatId,
        "nombre" => $nombre
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    if (isset($stmtCheck)) $stmtCheck->close();
    if (isset($stmtInsert)) $stmtInsert->close();
    $conn->close();
}
?>