<?php
header('Content-Type: application/json; charset=UTF-8');
include 'conexion.php';

try {
    $conn = conectarDB();

    // Verificar método y parámetros
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido', 405);
    }

    $userId = $_GET['user_id'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        throw new Exception('ID de usuario no válido', 400);
    }

    // Construir consulta
    $sql = "SELECT 
                p.ID, 
                p.Nombre, 
                p.Precio,
                p.FotoPrincipal,
                p.Status,
                p.Stock,
                p.Calificacion
            FROM Producto p
            WHERE p.ID_Usuario = ?";

    // Parámetros para bind
    $params = [$userId];
    $types = 'i'; // i = integer

    // Aplicar filtros
    if (isset($_GET['status']) && $_GET['status'] !== 'todos') {
        $sql .= " AND p.Status = ?";
        $params[] = $_GET['status'];
        $types .= 's'; // s = string
    }

    if (isset($_GET['categoria']) && $_GET['categoria'] !== 'todas') {
        $sql .= " AND p.ID_CategoriaPrincipal = ?";
        $params[] = $_GET['categoria'];
        $types .= 'i'; // i = integer
    }

    // Aplicar orden
    $orden = $_GET['orden'] ?? 'recientes';
    switch ($orden) {
        case 'antiguos':
            $sql .= " ORDER BY p.ID ASC";
            break;
        case 'precio_asc':
            $sql .= " ORDER BY p.Precio ASC";
            break;
        case 'precio_desc':
            $sql .= " ORDER BY p.Precio DESC";
            break;
        default:
            $sql .= " ORDER BY p.ID DESC";
    }

    // Preparar y ejecutar
    $stmt = $conn->prepare($sql);
    
    // Bind parameters dinámicamente
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $productos = $result->fetch_all(MYSQLI_ASSOC);

    // Procesar imágenes
    foreach ($productos as &$producto) {
        if ($producto['FotoPrincipal']) {
            $producto['FotoPrincipal'] = base64_encode($producto['FotoPrincipal']);
        }
    }

    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>