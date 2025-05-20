<?php
include 'conexion.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    if (!isset($_GET['vendedorId']) || empty($_GET['vendedorId'])) {
        throw new Exception('ID de vendedor no proporcionado en la URL.');
    }

    $vendedorId = intval($_GET['vendedorId']);

    if ($vendedorId <= 0) {
        throw new Exception('ID de vendedor inválido.');
    }

    $conn = conectarDB();

    $sql = "SELECT ID, Nombre, Descripcion, FotoPrincipal, Precio, Stock FROM Producto WHERE ID_Usuario = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Error al preparar la consulta SQL: ' . $conn->error . ' (SQL: ' . $sql . ')');
    }

    $stmt->bind_param("i", $vendedorId);
    $stmt->execute();
    $result = $stmt->get_result();

    $productos = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['FotoPrincipal']) {
            $row['FotoPrincipal'] = base64_encode($row['FotoPrincipal']);
        }
        $productos[] = $row;
    }

    $response['success'] = true;
    $response['productos'] = $productos;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    echo json_encode($response);
}
?>