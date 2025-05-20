<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Método no válido"]);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "error" => "Usuario no autenticado"]);
    exit;
}

require 'conexion.php'; 
$conn = conectarDB();
$conn->set_charset("utf8");

$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$stock = intval($_POST['stock'] ?? 0);  
$catsPost = $_POST['categorias'] ?? [];
$tipo = trim($_POST['metodo_venta'] ?? '');

$errors = []; 

if (empty($nombre)) {
    $errors[] = "El nombre del producto es requerido.";
}
if (empty($descripcion)) {
    $errors[] = "La descripción del producto es requerida.";
}
if ($stock < 0) { 
    $errors[] = "El stock no puede ser un número negativo.";
}

if ($tipo === 'Venta') {
    if (!is_numeric($precio) || $precio <= 0) {
        $errors[] = "Para productos de venta, el precio debe ser un número positivo.";
    }
} elseif ($tipo === 'Cotizacion') {
    $precio = 0;
} else {
    $errors[] = "Tipo de método de venta inválido.";
}


$categoriasValidas = [];
foreach ($catsPost as $catId) {
    $catId = intval($catId);
    if ($catId > 0) {
        $categoriasValidas[] = $catId;
    }
}

if (empty($categoriasValidas)) {
    $errors[] = "Debe seleccionar al menos una categoría válida.";
}

if (!empty($errors)) {
    echo json_encode(["success" => false, "error" => implode(" ", $errors)]);
    exit;
}

try {
    if (empty($_FILES["fotoPrincipal"]["tmp_name"]) || $_FILES["fotoPrincipal"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("La imagen principal es requerida.");
    }
    $fotoPrincipal = file_get_contents($_FILES["fotoPrincipal"]["tmp_name"]);

    $fotoExtra1 = null;
    if (isset($_FILES["fotoExtra1"]) && $_FILES["fotoExtra1"]["error"] === UPLOAD_ERR_OK) {
        $fotoExtra1 = file_get_contents($_FILES["fotoExtra1"]["tmp_name"]);
    }
    
    $fotoExtra2 = null;
    if (isset($_FILES["fotoExtra2"]) && $_FILES["fotoExtra2"]["error"] === UPLOAD_ERR_OK) {
        $fotoExtra2 = file_get_contents($_FILES["fotoExtra2"]["tmp_name"]);
    }

    $video_path = null;
    if (isset($_FILES["video"]) && $_FILES["video"]["error"] === UPLOAD_ERR_OK) {
        $video_dir = "uploads/videos/";
        if (!file_exists($video_dir)) {
            mkdir($video_dir, 0777, true);
        }
        $video_name = uniqid() . '_' . basename($_FILES["video"]["name"]); 
        $video_path = $video_dir . $video_name;
        if (!move_uploaded_file($_FILES["video"]["tmp_name"], $video_path)) {
            throw new Exception("Error al guardar el video en el servidor.");
        }
    }

    $sqlProducto = "INSERT INTO Producto (
        Nombre,
        Descripcion,
        FotoPrincipal,
        FotoExtra1,
        FotoExtra2,
        Video,
        Precio,
        Stock,
        ID_CategoriaPrincipal,
        ID_Usuario,
        tipo
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtProducto = $conn->prepare($sqlProducto);
    if (!$stmtProducto) {
        throw new Exception("Error al preparar la consulta de producto: " . $conn->error);
    }

    $categoriaPrincipal = $categoriasValidas[0];
    $null = null;

    $stmtProducto->bind_param(
        "ssbbbsdiiis",
        $nombre,
        $descripcion,
        $null,
        $null, 
        $null,
        $video_path,
        $precio,
        $stock,
        $categoriaPrincipal,
        $_SESSION['usuario_id'],
        $tipo
    );

    $stmtProducto->send_long_data(2, $fotoPrincipal);
    if ($fotoExtra1) $stmtProducto->send_long_data(3, $fotoExtra1);
    if ($fotoExtra2) $stmtProducto->send_long_data(4, $fotoExtra2);

    if (!$stmtProducto->execute()) {
        throw new Exception("Error al insertar el producto: " . $stmtProducto->error);
    }

    $productoId = $conn->insert_id;
    $stmtProducto->close();

    $sqlRelacion = "INSERT INTO Producto_Categoria (ID_Producto, ID_Categoria) VALUES (?, ?)";
    $stmtRelacion = $conn->prepare($sqlRelacion);
    if (!$stmtRelacion) {
        throw new Exception("Error al preparar la consulta de relación de categoría: " . $conn->error);
    }
    
    foreach ($categoriasValidas as $catId) {
        $stmtRelacion->bind_param("ii", $productoId, $catId);
        if (!$stmtRelacion->execute()) {
            error_log("Error al insertar relación Producto_Categoria para Producto ID $productoId y Categoria ID $catId: " . $stmtRelacion->error);
        }
    }
    $stmtRelacion->close();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>