<?php
session_start();
header('Content-Type: application/json'); // Añade esta línea

$mysqli = new mysqli("localhost", "root", "", "elecstore");
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
$cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;

if ($producto_id <= 0 || $cantidad <= 0) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

// Verificar existencia del producto
$stmt = $mysqli->prepare("SELECT existencia FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$stmt->bind_result($existencia);
$stmt->fetch();
$stmt->close();

if ($cantidad > $existencia) {
    echo json_encode(['success' => false, 'error' => 'No hay suficiente stock']);
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
    $nueva_cantidad = $cantidad_actual + $cantidad;
    $stmt = $mysqli->prepare("UPDATE carrito SET cantidad = ? WHERE usuario_id = ? AND producto_id = ?");
    $stmt->bind_param("iii", $nueva_cantidad, $usuario_id, $producto_id);
} else {
    $stmt = $mysqli->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $usuario_id, $producto_id, $cantidad);
}

if ($stmt->execute()) {
    // Restar del inventario
    $stmt = $mysqli->prepare("UPDATE productos SET existencia = existencia - ? WHERE id = ?");
    $stmt->bind_param("ii", $cantidad, $producto_id);
    $stmt->execute();

    // Actualizar sesión
    $_SESSION['carrito_cantidad'] = isset($_SESSION['carrito_cantidad']) ? $_SESSION['carrito_cantidad'] + $cantidad : $cantidad;

    echo json_encode([
        'success' => true,
        'nueva_cantidad' => $_SESSION['carrito_cantidad']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar carrito']);
}

$stmt->close();
$mysqli->close();
