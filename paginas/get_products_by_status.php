<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$status = isset($_GET['status']) ? $_GET['status'] : '';

// Validar los estados permitidos incluyendo "No aceptado"
$allowedStatuses = ['Aceptado', 'No aceptado', 'Rechazado'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(["success" => false, "error" => "Estado no válido"]);
    exit();
}

$conn = conectarDB();

// Consulta que incluye información del vendedor
$query = "SELECT p.*, u.Nickname as Vendedor 
          FROM Producto p
          LEFT JOIN Usuario u ON p.ID_Usuario = u.ID
          WHERE p.Status = ? 
          ORDER BY p.ID DESC";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $status);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // Procesar imágenes
    $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
    $row['FotoExtra1'] = $row['FotoExtra1'] ? base64_encode($row['FotoExtra1']) : null;
    $row['FotoExtra2'] = $row['FotoExtra2'] ? base64_encode($row['FotoExtra2']) : null;
    
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "products" => $products
]);

$stmt->close();
$conn->close();
?>