<?php
function conectarDB() {
    $host = "localhost";
    $usuario = "root"; 
    $password = ""; 
    $baseDatos = "PWInter";

    $conn = new mysqli($host, $usuario, $password, $baseDatos);

    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    return $conn;
}


?>
