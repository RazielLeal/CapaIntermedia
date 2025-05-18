<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';


$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['productId']) || !isset($data['newStatus'])) {
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit();
}

$allowedStatuses = ['Aceptado', 'No aceptado', 'Rechazado'];
if (!in_array($data['newStatus'], $allowedStatuses)) {
    echo json_encode(["success" => false, "error" => "Estado no válido"]);
    exit();
}

$conn = conectarDB();

// Verificar que el producto existe y está en estado "No aceptado"
$checkQuery = "SELECT Status FROM Producto WHERE ID = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("i", $data['productId']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
    exit();
}

$product = $checkResult->fetch_assoc();
if ($product['Status'] != 'No aceptado') {
    echo json_encode(["success" => false, "error" => "Solo se pueden modificar productos no aceptados"]);
    exit();
}

// Actualizar el estado
$updateQuery = "UPDATE Producto SET Status = ? WHERE ID = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("si", $data['newStatus'], $data['productId']);
$success = $updateStmt->execute();

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$checkStmt->close();
$updateStmt->close();
$conn->close();
?>