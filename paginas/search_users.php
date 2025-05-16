<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Content-Type: application/json");

require_once 'conexion.php';

$conn = conectarDB();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(["success" => false, "error" => "Query too short"]);
    exit();
}

try {
    $searchQuery = "%" . $conn->real_escape_string($query) . "%";
    
    $stmt = $conn->prepare("
        SELECT 
            ID, 
            Nombre, 
            ApellidoPaterno, 
            Nickname, 
            Avatar 
        FROM 
            Usuario
        WHERE 
            Nombre LIKE ? OR ApellidoPaterno LIKE ? OR ApellidoMaterno LIKE ? OR Nickname LIKE ?
        LIMIT 5
    ");
    
    $stmt->bind_param("ssss", $searchQuery, $searchQuery, $searchQuery, $searchQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['ID'],
            'nombre' => $row['Nombre'],
            'apellidoPaterno' => $row['ApellidoPaterno'],
            'nickname' => $row['Nickname'],
            'avatar' => $row['Avatar'] ? 'data:image/jpeg;base64,' . base64_encode($row['Avatar']) : 'avatar.png'
        ];
    }
    
    echo json_encode(["success" => true, "users" => $users]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>