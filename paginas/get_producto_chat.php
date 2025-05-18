<?php
header("Content-Type: application/json");
session_start();
require 'conexion.php';
$conn = conectarDB();

// Verificar que el parámetro `id_chat` se ha recibido correctamente
if (!isset($_GET['id_chat'])) {
    echo json_encode(["success" => false, "error" => "ID de chat no proporcionado."]);
    exit();
}

$id_chat = intval($_GET['id_chat']);

// Obtener el ID del producto asociado al chat
$sqlGetProducto = "SELECT id_producto FROM chat WHERE id_chat = ?";
$stmtGetProducto = $conn->prepare($sqlGetProducto);
$stmtGetProducto->bind_param("i", $id_chat);
$stmtGetProducto->execute();
$stmtGetProducto->bind_result($id_producto);
$stmtGetProducto->fetch();
$stmtGetProducto->close();

if (!$id_producto) {
    echo json_encode(["success" => false, "error" => "No se encontró un producto asociado a este chat."]);
    exit();
}

// Obtener detalles del producto
$sqlGetDetalles = "SELECT p.ID, p.Nombre, p.Descripcion, p.Precio, p.Stock, p.Vendidos, u.Nombre as Vendedor
                   FROM Producto p
                   LEFT JOIN usuario u ON p.ID_Usuario = u.ID
                   WHERE p.ID = ?";
$stmtGetDetalles = $conn->prepare($sqlGetDetalles);
$stmtGetDetalles->bind_param("i", $id_producto);
$stmtGetDetalles->execute();
$result = $stmtGetDetalles->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    $producto = [
        "id" => $row["ID"],
        "nombre" => $row["Nombre"],
        "descripcion" => $row["Descripcion"],
        "precio" => number_format($row["Precio"], 2),
        "stock" => $row["Stock"],
        "vendidos" => $row["Vendidos"],
        "vendedor" => $row["Vendedor"]
    ];

    echo json_encode(["success" => true, "producto" => $producto]);
} else {
    echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
}

$stmtGetDetalles->close();
$conn->close();
exit();