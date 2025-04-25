<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8080');
header('Access-Control-Allow-Methods: GET');

$conn = new mysqli("localhost", "root", "", "PWInter");

if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Establecer charset utf8
$conn->set_charset("utf8");

try {
    // Consulta para obtener productos aleatorios con status 'Aceptado'
    $sql = "SELECT 
            p.ID, 
            p.Nombre, 
            p.Descripcion, 
            p.FotoPrincipal,
            p.Precio,
            p.Calificacion,
            u.Nombre AS Vendedor
        FROM Producto p
        JOIN Usuario u ON p.ID_Usuario = u.ID
        WHERE p.Status = 'Aceptado'
        ORDER BY RAND() 
        LIMIT 8";

    
    $result = $conn->query($sql);

    $products = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Procesar imagen BLOB
            $imagen = 'img/placeholder.jpg';
            if (!empty($row['FotoPrincipal'])) {
                $imagen = 'data:image/jpeg;base64,' . base64_encode($row['FotoPrincipal']);
            }
            
            // Preparar datos del producto
            $product = [
                'ID' => $row['ID'],
                'Nombre' => htmlspecialchars($row['Nombre'], ENT_QUOTES, 'UTF-8'),
                'Descripcion' => htmlspecialchars($row['Descripcion'] ?? '', ENT_QUOTES, 'UTF-8'),
                'Foto' => $imagen,
                'Precio' => number_format($row['Precio'], 2),
                'Calificacion' => number_format($row['Calificacion'], 1),
                'Vendedor' => htmlspecialchars($row['Vendedor'], ENT_QUOTES, 'UTF-8')
            ];
            
            $products[] = $product;
        }
    }

    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener productos: ' . $e->getMessage()]);
}

$conn->close();
?>