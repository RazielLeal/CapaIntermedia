<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require 'conexion.php';
$conn = conectarDB();

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST["correo"];
    $contrasena = $_POST["contrasena"];

    $sql = "SELECT ID, Nickname, Correo, DiaDeRegistro, Avatar, Rol FROM Usuario WHERE (Correo = ? OR Nickname = ?) AND Contrasena = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $correo, $correo, $contrasena);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $nickname, $correo, $diaderegistro, $avatar, $rol);
        $stmt->fetch();

        $_SESSION['usuario_id'] = $id;
        $_SESSION['username'] = $nickname;
        $_SESSION['role'] = $rol;

        $avatarBase64 = $avatar ? base64_encode($avatar) : null;

        echo json_encode([ 
            "success" => true, 
            "id" => $id,
            "username" => $nickname,
            "email" => $correo,
            "register_date" => $diaderegistro,
            "photo" => $avatarBase64,
            "role" => $rol,
            "token" => $id // En producción usar JWT
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Credenciales incorrectas"]);
    }

    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['user'])) {
    $nickname = $_GET['user'];

    $sql = "SELECT ID, Nickname, Correo, DiaDeRegistro, Avatar, Rol FROM Usuario WHERE Nickname = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nickname);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $nickname, $correo, $diaderegistro, $avatar, $rol);
        $stmt->fetch();

        $_SESSION['usuario_id'] = $id;
        $_SESSION['username'] = $nickname;
        $_SESSION['role'] = $rol;

        $avatarBase64 = $avatar ? base64_encode($avatar) : null;

        echo json_encode([ 
            "success" => true, 
            "id" => $id,
            "username" => $nickname,
            "email" => $correo,
            "register_date" => $diaderegistro,
            "photo" => $avatarBase64,
            "role" => $rol,
            "token" => $id
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Usuario no encontrado"]);
    }

    $stmt->close();
}

$conn->close();
?>