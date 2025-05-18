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

// Recoger datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$stock = intval($_POST['stock'] ?? 0);
$catsPost = $_POST['categorias'] ?? [];
$tipo = trim($_POST['metodo_venta'] ?? '');

// Validaciones 
if (empty($nombre) || empty($descripcion) || $precio <= 0 || $stock < 0 || empty($catsPost) || empty($tipo)) {
    echo json_encode(["success" => false, "error" => "Datos del producto inválidos"]);
    exit;
}

$categoriasValidas = [];
foreach ($catsPost as $catId) {
    $catId = intval($catId);
    if ($catId > 0) {
        $categoriasValidas[] = $catId;
    }
}

if (empty($categoriasValidas)) {
    echo json_encode(["success" => false, "error" => "Seleccione al menos una categoría válida"]);
    exit;
}

// Manejo de archivos
try {
    // Foto principal (obligatoria)
    if (empty($_FILES["fotoPrincipal"]["tmp_name"]) || $_FILES["fotoPrincipal"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("La imagen principal es requerida");
    }
    $fotoPrincipal = file_get_contents($_FILES["fotoPrincipal"]["tmp_name"]);

    // Fotos opcionales
    $fotoExtra1 = $_FILES["fotoExtra1"]["error"] === UPLOAD_ERR_OK ? 
        file_get_contents($_FILES["fotoExtra1"]["tmp_name"]) : null;
    
    $fotoExtra2 = $_FILES["fotoExtra2"]["error"] === UPLOAD_ERR_OK ? 
        file_get_contents($_FILES["fotoExtra2"]["tmp_name"]) : null;

    // Video opcional
    $video_path = null;
    if ($_FILES["video"]["error"] === UPLOAD_ERR_OK) {
        $video_dir = "uploads/videos/";
        if (!file_exists($video_dir)) {
            mkdir($video_dir, 0777, true);
        }
        $video_name = uniqid() . '_' . basename($_FILES["video"]["name"]);
        $video_path = $video_dir . $video_name;
        if (!move_uploaded_file($_FILES["video"]["tmp_name"], $video_path)) {
            throw new Exception("Error al guardar el video");
        }
    }

    // Insertar producto
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";

    $stmtProducto = $conn->prepare($sqlProducto);
    if (!$stmtProducto) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
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

    // Enviar datos BLOB
    $stmtProducto->send_long_data(2, $fotoPrincipal);
    if ($fotoExtra1) $stmtProducto->send_long_data(3, $fotoExtra1);
    if ($fotoExtra2) $stmtProducto->send_long_data(4, $fotoExtra2);

    if (!$stmtProducto->execute()) {
        throw new Exception("Error al guardar producto: " . $stmtProducto->error);
    }

    $productoId = $conn->insert_id;
    $stmtProducto->close();
    $sqlRelacion = "INSERT INTO Producto_Categoria (ID_Producto, ID_Categoria) VALUES (?, ?)";
    $stmtRelacion = $conn->prepare($sqlRelacion);
    
    foreach ($categoriasValidas as $catId) {
        $stmtRelacion->bind_param("ii", $productoId, $catId);
        $stmtRelacion->execute();
    }
    $stmtRelacion->close();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>