<?php
header('Content-Type: application/json; charset=UTF-8');
require 'conexion.php';

$conn = conectarDB();
$conn->set_charset('utf8');

$sql = "SELECT ID, Nombre FROM Categoria ORDER BY Nombre";
$result = $conn->query($sql);

$cats = [];
while ($row = $result->fetch_assoc()) {
    $cats[] = $row;
}

echo json_encode($cats);
$conn->close();
