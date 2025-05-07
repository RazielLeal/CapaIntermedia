<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3306"); // Ajusta según tu frontend
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli("localhost", "root", "", "PWInter");

if ($conn->connect_error) {
    die(json_encode([
        "success" => false, 
        "error" => "Error de conexión: " . $conn->connect_error
    ]));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar campos obligatorios
    $required_fields = ["nombre", "apellidoP", "email", "password", "username", "nacimiento", "rol"];
    foreach ($required_fields as $field) {

        
        if (empty($_POST[$field])) {
            echo json_encode([
                "success" => false, 
                "error" => "El campo " . ucfirst($field) . " es requerido"
            ]);
            exit;
        }
    }

    // Obtener datos del formulario
    $nombre = trim($_POST["nombre"]);
    $apellidoP = trim($_POST["apellidoP"]);
    $apellidoM = trim($_POST["apellidoM"] ?? "");
    $email = trim($_POST["email"]);
    $password = $_POST["password"]; // Contraseña en texto plano (sin hash)
    $username = trim($_POST["username"]);
    $nacimiento = $_POST["nacimiento"];
    $rol = $_POST["rol"];

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "success" => false, 
            "error" => "Formato de correo inválido"
        ]);
        exit;
    }

    // Verificar si el usuario o correo ya existen
    $check = $conn->prepare("SELECT Nickname, Correo FROM Usuario WHERE Nickname = ? OR Correo = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Este administrador ya está registrado');</script>";
        echo json_encode([
            "success" => false, 
            "error" => "Este administrador ya está registrado"
        ]);
        $check->close();
        header("Location: mainSA.html");
        exit;
    }
    $check->close();

    // Insertar en la base de datos (contraseña en texto plano)
    $stmt = $conn->prepare("INSERT INTO Usuario (
        Correo, Contrasena, Nombre, ApellidoPaterno, ApellidoMaterno, 
        Nickname, Nacimiento, Rol
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssssss", 
        $email, $password, $nombre, $apellidoP, $apellidoM, 
        $username, $nacimiento, $rol    
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
        header("Location: mainSA.html");
    } else {
        echo json_encode([
            "success" => false, 
            "error" => "Error al registrar: " . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        "success" => false, 
        "error" => "Método de solicitud no válido"
    ]);
}
?>
