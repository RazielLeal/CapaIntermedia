<?php
header("Content-Type: application/json");
$conn = new mysqli("localhost", "root", "", "PWInter");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión"]);
    exit();
}

$conn->set_charset("utf8");

$sql = "SELECT ID, Nombre FROM Categoria ORDER BY Nombre ASC";
$result = $conn->query($sql);

$categorias = [];
while ($row = $result->fetch_assoc()) {
    $categorias[] = $row;
}

echo json_encode(["success" => true, "categorias" => $categorias]);

$conn->close();
?>
