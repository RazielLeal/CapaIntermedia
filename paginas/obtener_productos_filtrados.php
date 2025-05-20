<?php
header('Content-Type: application/json; charset=UTF-8');
include 'conexion.php';

try {
    $conn = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido', 405);
    }

    $userId = $_GET['user_id'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        throw new Exception('ID de usuario no válido', 400);
    }

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

    $params = [$userId];
    $types = 'i'; 

    if (isset($_GET['status']) && $_GET['status'] !== 'todos') {
        $sql .= " AND p.Status = ?";
        $params[] = $_GET['status'];
        $types .= 's'; 
    }

    if (isset($_GET['categoria']) && $_GET['categoria'] !== 'todas') {
        $sql .= " AND p.ID_CategoriaPrincipal = ?";
        $params[] = $_GET['categoria'];
        $types .= 'i'; 
    }

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

    $stmt = $conn->prepare($sql);
    
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $productos = $result->fetch_all(MYSQLI_ASSOC);

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