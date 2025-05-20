<?php
include 'conexion.php'; 
$conn = conectarDB();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'perfilvendedor.html';
    $id_producto = isset($_POST["id_producto"]) ? intval($_POST["id_producto"]) : 0;

    if (isset($_POST["btnagregar"])) {
        $cantidad_stock = isset($_POST["cantidadstock"]) ? intval($_POST["cantidadstock"]) : 0;
        
        if ($cantidad_stock > 0) {
            $sql = "UPDATE producto SET Stock = Stock + ? WHERE ID = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $cantidad_stock, $id_producto);
                if ($stmt->execute()) {
                    header("Location: " . $redirectUrl . "?success=stockUpdated");
                    exit();
                } else {
                    header("Location: " . $redirectUrl . "?error=stockUpdateError");
                    exit();
                }
                $stmt->close();
            } else {
                header("Location: " . $redirectUrl . "?error=prepareError");
                exit();
            }
        } else {
            header("Location: " . $redirectUrl . "?error=invalidQuantity");
            exit();
        }
    } elseif (isset($_POST["btneliminar"])) {
        $sql = "UPDATE producto SET Status = 'Eliminado' WHERE ID = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id_producto);
            if ($stmt->execute()) {
                header("Location: " . $redirectUrl . "?success=productDeleted");
                exit();
            } else {
                header("Location: " . $redirectUrl . "?error=productDeletionError");
                exit();
            }
            $stmt->close();
        } else {
            header("Location: " . $redirectUrl . "?error=prepareError");
            exit();
        }
    } else {
        header("Location: " . $redirectUrl . "?error=noneActionSpecified");
        exit();
    }
    
}
?>