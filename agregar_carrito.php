<?php
session_start(); // Inicia sesión

// Conexión a la base de datos
$mysqli = new mysqli("localhost", "root", "", "elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verifica si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
$cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;

// Validación de entradas
if ($producto_id <= 0 || $cantidad <= 0) {
    exit;
}

// Verificar la existencia del producto
$stmt = $mysqli->prepare("SELECT existencia FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$stmt->bind_result($existencia);
$stmt->fetch();
$stmt->close();

if ($cantidad > $existencia) {
    exit;
}

// Verificar si el producto ya está en el carrito
$stmt = $mysqli->prepare("SELECT cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?");
$stmt->bind_param("ii", $usuario_id, $producto_id);
$stmt->execute();
$stmt->bind_result($cantidad_actual);
$stmt->fetch();
$stmt->close();

if ($cantidad_actual) {
    // Si ya existe en el carrito, actualizar cantidad
    $nueva_cantidad = $cantidad_actual + $cantidad;
    $stmt = $mysqli->prepare("UPDATE carrito SET cantidad = ? WHERE usuario_id = ? AND producto_id = ?");
    $stmt->bind_param("iii", $nueva_cantidad, $usuario_id, $producto_id);
} else {
    // Insertar nuevo producto en el carrito
    $stmt = $mysqli->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $usuario_id, $producto_id, $cantidad);
}

// Ejecutar la consulta
if ($stmt->execute()) {
    // Restar cantidad del inventario
    $stmt = $mysqli->prepare("UPDATE productos SET existencia = existencia - ? WHERE id = ?");
    $stmt->bind_param("ii", $cantidad, $producto_id);
    $stmt->execute();

    // Actualizar la cantidad total de productos en la sesión
    $_SESSION['carrito_cantidad'] = isset($_SESSION['carrito_cantidad']) ? $_SESSION['carrito_cantidad'] + $cantidad : $cantidad;
}

// Cerrar conexiones
$stmt->close();
$mysqli->close();
?>
