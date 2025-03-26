<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die(json_encode(["success" => false, "error" => "Error de conexión"]));
}

// Verifica si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "error" => "No autenticado"]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$carrito_id = isset($_POST['carrito_id']) ? (int)$_POST['carrito_id'] : 0;

if ($carrito_id <= 0) {
    echo json_encode(["success" => false, "error" => "ID inválido"]);
    exit;
}

// Obtener la cantidad del producto en el carrito
$stmt = $mysqli->prepare("SELECT producto_id, cantidad FROM carrito WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $carrito_id, $usuario_id);
$stmt->execute();
$stmt->bind_result($producto_id, $cantidad);
$stmt->fetch();
$stmt->close();

if (!$producto_id) {
    echo json_encode(["success" => false, "error" => "Producto no encontrado"]);
    exit;
}

// Eliminar producto del carrito
$stmt = $mysqli->prepare("DELETE FROM carrito WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $carrito_id, $usuario_id);
$stmt->execute();
$stmt->close();

// Restaurar la cantidad eliminada al stock de productos
$stmt = $mysqli->prepare("UPDATE productos SET existencia = existencia + ? WHERE id = ?");
$stmt->bind_param("ii", $cantidad, $producto_id);
$stmt->execute();
$stmt->close();

// Recalcular el total
$query = "SELECT SUM(p.precio * c.cantidad) AS total FROM carrito c INNER JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($nuevo_total);
$stmt->fetch();
$stmt->close();

// Calcular nueva cantidad en el carrito
$query = "SELECT SUM(cantidad) FROM carrito WHERE usuario_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($nueva_cantidad);
$stmt->fetch();
$stmt->close();

$_SESSION['carrito_cantidad'] = $nueva_cantidad ?: 0;

echo json_encode(["success" => true, "nuevo_total" => number_format($nuevo_total, 2), "nueva_cantidad" => $nueva_cantidad ?: 0]);
$mysqli->close();
?>
