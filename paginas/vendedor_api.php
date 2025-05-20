<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: GET, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php'; 

$conn = conectarDB();

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión a la base de datos: " . $conn->connect_error]);
    exit();
}

/** * @return int|null */
function obtenerUsuarioId() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        if (is_numeric($token)) {
            return intval($token);
        }
    }
    if (isset($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    }
    return null;
}

$usuarioId = obtenerUsuarioId();
if (!$usuarioId) {
    http_response_code(401); 
    echo json_encode(["success" => false, "error" => "Usuario no autenticado", "code" => 401]);
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';

    try {
        switch ($action) {
            case 'getCategories':
                $stmt = $conn->prepare("SELECT ID, Nombre FROM Categoria ORDER BY Nombre");
                if (!$stmt) {
                    throw new Exception("Error en preparación de consulta (Categorías): " . $conn->error);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $categories = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode(["success" => true, "categories" => $categories]);
                $stmt->close();
                break;

            case 'getGroupedSales':
                $startDate = $_GET['startDate'] ?? '';
                $endDate = $_GET['endDate'] ?? '';
                $categoryId = $_GET['categoryId'] ?? '';

                $query = "SELECT
                            DATE_FORMAT(v.Fecha, '%Y-%m') AS MesAnio,
                            c.Nombre AS CategoriaNombre,
                            COUNT(v.ID) AS NumeroVentasUnicas, 
                            SUM(v.Cantidad) AS CantidadTotalVendida,
                            SUM(v.PrecioTotal) AS IngresoTotal
                          FROM Venta v
                          JOIN Producto p ON v.ID_Producto = p.ID
                          JOIN Categoria c ON p.ID_CategoriaPrincipal = c.ID
                          WHERE p.ID_Usuario = ?"; 
                
                $params = "i";
                $values = [$usuarioId];

                if (!empty($startDate)) {
                    $query .= " AND v.Fecha >= ?";
                    $params .= "s";
                    $values[] = $startDate . " 00:00:00";
                }
                if (!empty($endDate)) {
                    $query .= " AND v.Fecha <= ?";
                    $params .= "s";
                    $values[] = $endDate . " 23:59:59";
                }
                if (!empty($categoryId)) {
                    $query .= " AND c.ID = ?";
                    $params .= "i";
                    $values[] = $categoryId;
                }

                $query .= " GROUP BY MesAnio, CategoriaNombre ORDER BY MesAnio DESC, CategoriaNombre ASC";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en preparación de consulta (Ventas Agrupadas): " . $conn->error);
                }
                $stmt->bind_param($params, ...$values);
                $stmt->execute();
                $result = $stmt->get_result();
                $groupedSales = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode(["success" => true, "groupedSales" => $groupedSales]);
                $stmt->close();
                break;

            case 'getDetailedSalesProducts':
                $startDate = $_GET['startDate'] ?? '';
                $endDate = $_GET['endDate'] ?? '';
                $categoryId = $_GET['categoryId'] ?? '';
                $page = intval($_GET['page'] ?? 1);
                $limit = intval($_GET['limit'] ?? 8);
                $offset = ($page - 1) * $limit;

                $countQuery = "SELECT COUNT(DISTINCT p.ID) 
                               FROM Venta v
                               JOIN Producto p ON v.ID_Producto = p.ID
                               LEFT JOIN Categoria c ON p.ID_CategoriaPrincipal = c.ID
                               WHERE p.ID_Usuario = ?";
                
                $productQuery = "SELECT
                                    p.ID AS ProductoID,
                                    p.Nombre,
                                    p.FotoPrincipal,
                                    p.Precio,
                                    p.Stock,
                                    p.Vendidos,
                                    c.Nombre AS CategoriaNombre
                                 FROM Venta v
                                 JOIN Producto p ON v.ID_Producto = p.ID
                                 LEFT JOIN Categoria c ON p.ID_CategoriaPrincipal = c.ID
                                 WHERE p.ID_Usuario = ?";

                $params = "i";
                $values = [$usuarioId];

                if (!empty($startDate)) {
                    $countQuery .= " AND v.Fecha >= ?";
                    $productQuery .= " AND v.Fecha >= ?";
                    $params .= "s";
                    $values[] = $startDate . " 00:00:00";
                }
                if (!empty($endDate)) {
                    $countQuery .= " AND v.Fecha <= ?";
                    $productQuery .= " AND v.Fecha <= ?";
                    $params .= "s";
                    $values[] = $endDate . " 23:59:59";
                }
                if (!empty($categoryId)) {
                    $countQuery .= " AND c.ID = ?";
                    $productQuery .= " AND c.ID = ?";
                    $params .= "i";
                    $values[] = $categoryId;
                }

                $stmtCount = $conn->prepare($countQuery);
                if (!$stmtCount) {
                    throw new Exception("Error en preparación de consulta (Contar Productos Vendidos): " . $conn->error);
                }
                $stmtCount->bind_param($params, ...$values);
                $stmtCount->execute();
                $totalProducts = $stmtCount->get_result()->fetch_row()[0];
                $stmtCount->close();

                $totalPages = ceil($totalProducts / $limit);

                $productQuery .= " GROUP BY p.ID ORDER BY p.Nombre ASC LIMIT ? OFFSET ?";
                $params .= "ii";
                $values[] = $limit;
                $values[] = $offset;

                $stmtProducts = $conn->prepare($productQuery);
                if (!$stmtProducts) {
                    throw new Exception("Error en preparación de consulta (Productos Vendidos Detallados): " . $conn->error);
                }
                $stmtProducts->bind_param($params, ...$values);
                $stmtProducts->execute();
                $resultProducts = $stmtProducts->get_result();
                
                $detailedProducts = [];
                while ($row = $resultProducts->fetch_assoc()) {
                    $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
                    $detailedProducts[] = $row;
                }
                echo json_encode(["success" => true, "products" => $detailedProducts, "currentPage" => $page, "totalPages" => $totalPages]);
                $stmtProducts->close();
                break;

            case 'getProductSaleDetails':
                $productId = intval($_GET['productId'] ?? 0);
                if ($productId === 0) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => "ID de producto inválido."]);
                    break;
                }

                $query = "SELECT
                            v.Fecha,
                            v.Cantidad,
                            v.PrecioTotal,
                            v.Calificacion,
                            v.Comentario
                          FROM Venta v
                          WHERE v.ID_Producto = ? AND v.ID_Usuario = ?
                          ORDER BY v.Fecha DESC"; 
                $query = "SELECT
                            v.Fecha,
                            v.Cantidad,
                            v.PrecioTotal,
                            v.Calificacion,
                            v.Comentario
                          FROM Venta v
                          JOIN Producto p ON v.ID_Producto = p.ID
                          WHERE v.ID_Producto = ? AND p.ID_Usuario = ? 
                          ORDER BY v.Fecha DESC";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en preparación de consulta (Detalles Venta Producto): " . $conn->error);
                }
                $stmt->bind_param("ii", $productId, $usuarioId);
                $stmt->execute();
                $result = $stmt->get_result();
                $salesDetails = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode(["success" => true, "salesDetails" => $salesDetails]);
                $stmt->close();
                break;

            case 'getCurrentProducts':
                $categoryId = $_GET['categoryId'] ?? '';

                $query = "SELECT
                            p.ID,
                            p.Nombre,
                            p.FotoPrincipal,
                            p.Precio,
                            p.Stock,
                            c.Nombre AS CategoriaNombre
                          FROM Producto p
                          LEFT JOIN Categoria c ON p.ID_CategoriaPrincipal = c.ID
                          WHERE p.ID_Usuario = ?"; 
                
                $params = "i";
                $values = [$usuarioId];

                if (!empty($categoryId)) {
                    $query .= " AND c.ID = ?";
                    $params .= "i";
                    $values[] = $categoryId;
                }

                $query .= " ORDER BY p.Nombre ASC";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en preparación de consulta (Productos Actuales): " . $conn->error);
                }
                $stmt->bind_param($params, ...$values);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $currentProducts = [];
                while ($row = $result->fetch_assoc()) {
                    $row['FotoPrincipal'] = $row['FotoPrincipal'] ? base64_encode($row['FotoPrincipal']) : null;
                    $currentProducts[] = $row;
                }
                echo json_encode(["success" => true, "products" => $currentProducts]);
                $stmt->close();
                break;

            default:
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Acción no reconocida."]);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error del servidor: " . $e->getMessage()]);
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    http_response_code(405); 
    echo json_encode(["success" => false, "error" => "Método no permitido."]);
}
?>