<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

// Solo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Método no válido"]);
    exit;
}

// Verificamos ID de usuario según tu login.php
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "error" => "Usuario no autenticado"]);
    exit;
}

require 'conexion.php';
$conn = conectarDB();
$conn->set_charset("utf8");

// Recoger campos del formulario
$nombre      = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$precio      = floatval($_POST['precio'] ?? 0);
$stock       = intval($_POST['stock'] ?? 0);
$catsPost    = $_POST['categorias'] ?? [];

// Validaciones
if ($nombre === '' || $descripcion === '' || $precio <= 0 || $stock < 0 || !is_array($catsPost) || count($catsPost) === 0) {
    echo json_encode(["success" => false, "error" => "Todos los campos son requeridos y válidos."]);
    exit;
}

// Foto principal (obligatoria)
if (empty($_FILES["fotoPrincipal"]["tmp_name"]) || $_FILES["fotoPrincipal"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "error" => "La imagen principal es requerida"]);
    exit;
}
$fotoPrincipal = file_get_contents($_FILES["fotoPrincipal"]["tmp_name"]);

// Fotos extra (opcionales)
$fotoExtra1 = (isset($_FILES["fotoExtra1"]) && $_FILES["fotoExtra1"]["error"] === UPLOAD_ERR_OK)
    ? file_get_contents($_FILES["fotoExtra1"]["tmp_name"])
    : null;
$fotoExtra2 = (isset($_FILES["fotoExtra2"]) && $_FILES["fotoExtra2"]["error"] === UPLOAD_ERR_OK)
    ? file_get_contents($_FILES["fotoExtra2"]["tmp_name"])
    : null;

// Video (opcional)
$video_path = null;
if (isset($_FILES["video"]) && $_FILES["video"]["error"] === UPLOAD_ERR_OK) {
    $video_dir  = "uploads/videos/";
    if (!file_exists($video_dir)) mkdir($video_dir, 0777, true);
    $video_name = uniqid() . '_' . basename($_FILES["video"]["name"]);
    $video_path = $video_dir . $video_name;
    if (!move_uploaded_file($_FILES["video"]["tmp_name"], $video_path)) {
        echo json_encode(["success" => false, "error" => "No se pudo guardar el video"]);
        exit;
    }
}

// Usamos la primera categoría como principal
$categoriaPrincipal = intval($catsPost[0]);

// Insertar en Producto
$sql = "INSERT INTO Producto
    (Nombre, Descripcion, FotoPrincipal, FotoExtra1, FotoExtra2, Video, Precio, Stock, ID_CategoriaPrincipal, ID_Usuario)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Error al preparar consulta: ".$conn->error]);
    exit;
}

// Configuramos bind_param con placeholders 'b' para BLOBs
$null = null;
$stmt->bind_param(
    "ssbbbsdiii",
    $nombre,
    $descripcion,
    $null,           // FotoPrincipal (se envía con send_long_data)
    $null,           // FotoExtra1
    $null,           // FotoExtra2
    $video_path,     // Ruta al video o NULL
    $precio,
    $stock,
    $categoriaPrincipal,
    $_SESSION['usuario_id']  // Aquí está la clave corregida
);

// Enviamos los datos binarios
$stmt->send_long_data(2, $fotoPrincipal);
if ($fotoExtra1 !== null) $stmt->send_long_data(3, $fotoExtra1);
if ($fotoExtra2 !== null) $stmt->send_long_data(4, $fotoExtra2);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Error al publicar: ".$stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$productoId = $conn->insert_id;
$stmt->close();

// Insertar en Producto_Categoria (relación muchos a muchos)
$relSql = "INSERT INTO Producto_Categoria (ID_Producto, ID_Categoria) VALUES (?, ?)";
$relStmt = $conn->prepare($relSql);
foreach ($catsPost as $catId) {
    $c = intval($catId);
    $relStmt->bind_param("ii", $productoId, $c);
    $relStmt->execute();
}
$relStmt->close();

//INSERCION DE CATEGORIAS NUEVAS

$nombreCategoria = trim($_POST["nombreCategoria"] ?? '');
$descripcionCategoria = trim($_POST["descripcionCategoria"] ?? '');

// Verificar si la categoría ya existe
$check_sql = "SELECT ID FROM Categoria WHERE Nombre = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $nombreCategoria);
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

// Verificar si los valores enviados están vacíos
if (empty($nombreCategoria) || empty($descripcionCategoria)) {
    echo json_encode([
        "success" => false,
    ]);
    exit;
}

// Insertar nueva categoría
$insert_sql = "INSERT INTO Categoria (Nombre, Descripcion) VALUES (?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("ss", $nombreCategoria, $descripcionCategoria);

if ($insert_stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Error al crear categoría: " . $insert_stmt->error
    ]);
}

$insert_stmt->close();
    
echo json_encode(["success" => true]);
