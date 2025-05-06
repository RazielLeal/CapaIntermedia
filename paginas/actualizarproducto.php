<?php
// actualizarproducto.php
include 'conexion.php'; // Asegúrate de que este archivo está en la ruta correcta
$conn = conectarDB(); // Llamamos a la función para obtener la conexión

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Definimos la URL de retorno usando HTTP_REFERER. Si no existe, se utiliza 'perfilvendedor.html' como valor por defecto.
    $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'perfilvendedor.html';
    
    // Obtenemos el ID del producto de forma segura
    $id_producto = isset($_POST["id_producto"]) ? intval($_POST["id_producto"]) : 0;

    // Procesamos la acción según el botón presionado
    if (isset($_POST["btnagregar"])) {
        // Lógica para agregar stock
        $cantidad_stock = isset($_POST["cantidadstock"]) ? intval($_POST["cantidadstock"]) : 0;
        
        if ($cantidad_stock > 0) {
            $sql = "UPDATE producto SET Stock = Stock + ? WHERE ID = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $cantidad_stock, $id_producto);
                if ($stmt->execute()) {
                    // Redirigir a la página anterior con un mensaje de éxito
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
        // Lógica para marcar el producto como eliminado
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
    
    // Se omite el cierre de la conexión, como solicitaste.
}
?>