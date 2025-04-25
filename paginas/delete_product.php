<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['productId'])) {
    echo json_encode(["success" => false, "error" => "ID de producto no proporcionado"]);
    exit();
}

$conn = conectarDB();

$query = "DELETE FROM Producto WHERE ID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $data['productId']);
$success = $stmt->execute();

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>