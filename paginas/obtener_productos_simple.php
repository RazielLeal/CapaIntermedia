<?php
header('Content-Type: application/json; charset=UTF-8');
include 'conexion.php';

try {
    $conn = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('ID de usuario no válido');
    }

    $stmt = $conn->prepare("
        SELECT 
            ID, 
            Nombre, 
            CAST(Precio AS DECIMAL(10,2)) AS Precio,
            FotoPrincipal
        FROM Producto 
        WHERE ID_Usuario = ?
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

    // Forzar precios como tipo numérico
    foreach ($products as &$p) {
        $p['Precio'] = (float)$p['Precio'];
        $p['Calificacion'] = (int)$p['Calificacion'];
        if ($p['FotoPrincipal']) {
            $p['FotoPrincipal'] = base64_encode($p['FotoPrincipal']);
        }
    }
    unset($p);

    echo json_encode([
        'success' => true,
        'productos' => $products
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>