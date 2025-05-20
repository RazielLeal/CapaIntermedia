<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3306");
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
    $required_fields = ["nombre", "apellidoP", "email", "password", "username", "nacimiento", "genero", "rol"];
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
    $genero = $_POST["genero"];
    $rol = $_POST["rol"];
    
    // Lógica para el estatus de la cuenta
    $estatus = 'Publico'; // Valor por defecto

    if ($rol === 'Usuario') {
        // Si el rol es 'Usuario', se permite seleccionar el estatus
        // Asegúrate de que el campo 'estatus' se envíe desde el formulario HTML
        if (isset($_POST['estatus']) && ($_POST['estatus'] === 'Publico' || $_POST['estatus'] === 'Privado')) {
            $estatus = $_POST['estatus'];
        }
    } 
    // Si el rol es 'Vendedor' o 'Admin', el estatus siempre será 'Publico' por defecto.
    // La asignación inicial de $estatus = 'Publico' ya lo maneja.


    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "success" => false, 
            "error" => "Formato de correo inválido"
        ]);
        exit;
    }

    // Procesar imagen (opcional)
    $avatar = NULL;
    if (!empty($_FILES["foto"]["tmp_name"]) && $_FILES["foto"]["error"] == UPLOAD_ERR_OK) {
        $avatar = file_get_contents($_FILES["foto"]["tmp_name"]);
    }

    // Verificar si el usuario o correo ya existen
    $check = $conn->prepare("SELECT Nickname, Correo FROM Usuario WHERE Nickname = ? OR Correo = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            "success" => false, 
            "error" => "El nombre de usuario o correo ya están registrados"
        ]);
        $check->close();
        exit;
    }
    $check->close();

    // Insertar en la base de datos (contraseña en texto plano)
    $stmt = $conn->prepare("INSERT INTO Usuario (
        Correo, Contrasena, Nombre, ApellidoPaterno, ApellidoMaterno, 
        Nickname, Nacimiento, Rol, Avatar, Genero, Estatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"); // Agregado Estatus

    $stmt->bind_param(
        "sssssssssss", // Agregado un 's' para Estatus
        $email, $password, $nombre, $apellidoP, $apellidoM, 
        $username, $nacimiento, $rol, $avatar, $genero, $estatus // Agregado $estatus
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
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