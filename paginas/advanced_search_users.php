<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$conn = conectarDB();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'asc'; // Default: A-Z
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8; // Default a 8 por página

$offset = ($page - 1) * $limit;

try {
    // Construir la parte WHERE de la consulta para el conteo y los resultados
    $whereClause = " WHERE 1=1 ";
    $params = [];
    $types = "";

    // **NUEVO: Excluir usuarios con roles 'Admin' y 'SuperAdmin'**
    $whereClause .= " AND Rol NOT IN ('Admin', 'SuperAdmin') ";

    if (!empty($query)) {
        $searchQuery = "%" . $conn->real_escape_string($query) . "%";
        $whereClause .= " AND (Nombre LIKE ? OR ApellidoPaterno LIKE ? OR ApellidoMaterno LIKE ? OR Nickname LIKE ?)";
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $types .= "ssss";
    }

    if (!empty($roleFilter)) {
        $whereClause .= " AND Rol = ?";
        $params[] = $roleFilter;
        $types .= "s";
    }

    // Consulta para obtener el total de usuarios con los filtros aplicados
    $countSql = "SELECT COUNT(ID) AS total FROM Usuario " . $whereClause;
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalItems = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Consulta para obtener los usuarios con filtros, orden y paginación
    $sql = "
        SELECT
            ID,
            Nombre,
            ApellidoPaterno,
            ApellidoMaterno,
            Nickname,
            Avatar,
            Rol
        FROM
            Usuario
        " . $whereClause;

    switch ($sortBy) {
        case 'desc':
            $sql .= " ORDER BY Nickname DESC";
            break;
        case 'asc':
        default:
            $sql .= " ORDER BY Nickname ASC"; // Ordena por Nickname A-Z por defecto
            break;
    }

    $sql .= " LIMIT ?, ?"; // Añadir LIMIT al final

    $stmt = $conn->prepare($sql);

    // Añadir los parámetros de offset y limit a los parámetros existentes
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";

    // Asegúrate de que el número de tipos y parámetros coincida
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    } else {
        // Esto no debería ocurrir si siempre se añade la exclusión de roles
        // pero se mantiene como fallback de seguridad si la lógica anterior cambia.
        // La exclusión de roles agrega un WHERE clause con parámetros.
        $stmt->bind_param("ii", $offset, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'apellidoPaterno' => $row['ApellidoPaterno'],
            'apellidoMaterno' => $row['ApellidoMaterno'],
            'nickname' => $row['Nickname'],
            'rol' => $row['Rol'],
            'avatar' => $row['Avatar'] ? 'data:image/jpeg;base64,' . base64_encode($row['Avatar']) : 'avatar.png'
        ];
    }

    echo json_encode(["success" => true, "users" => $users, "total_items" => $totalItems]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>