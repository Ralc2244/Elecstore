<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (isset($_POST['producto_id']) && isset($_POST['cantidad'])) {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];

    // Actualizar el stock
    $mysqli->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $producto_id");

    // Obtener el nuevo stock
    $resultado = $mysqli->query("SELECT stock FROM productos WHERE id = $producto_id");
    $producto = $resultado->fetch_assoc();

    // Devolver el nuevo stock
    echo json_encode(['success' => true, 'nuevo_stock' => $producto['stock']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incorrectos']);
}

$mysqli->close();
?>
