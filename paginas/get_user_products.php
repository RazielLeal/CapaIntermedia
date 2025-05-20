<?php
include 'conexion.php';

header('Content-Type: application/json');

$response = ['success' => false, 'products' => [], 'error' => ''];

try {
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        throw new Exception('ID de usuario inválido');
    }

    $userId = intval($_GET['user_id']);
    $conn = conectarDB();

    $sql = "SELECT 
                p.ID, 
                p.Nombre, 
                p.Descripcion, 
                p.FotoPrincipal,
                p.Precio,
                p.Stock,
                p.Calificacion,
                p.Status,
                c.Nombre as Categoria
            FROM Producto p
            LEFT JOIN Categoria c ON p.ID_CategoriaPrincipal = c.ID
            WHERE p.ID_Usuario = ? 
            AND p.Status = 'Aceptado'
            ORDER BY p.ID DESC";

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('Error en la preparación: ' . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Error en la ejecución: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'ID' => $row['ID'],
            'Nombre' => $row['Nombre'],
            'Descripcion' => $row['Descripcion'],
            'FotoPrincipal' => $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null,
            'Precio' => (float)$row['Precio'],
            'Stock' => (int)$row['Stock'],
            'Calificacion' => (float)$row['Calificacion'],
            'Categoria' => $row['Categoria']
        ];
    }

    $response = [
        'success' => true,
        'products' => $products
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
    echo json_encode($response);
    exit;
}
?>