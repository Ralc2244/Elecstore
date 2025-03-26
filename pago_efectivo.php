<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener los productos del carrito
$query = "SELECT c.producto_id, p.nombre, p.ruta_imagen, p.precio, p.existencia, c.cantidad, (p.precio * c.cantidad) AS total_producto 
    FROM carrito c
    INNER JOIN productos p ON c.producto_id = p.id
    WHERE c.usuario_id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$productos = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['cantidad'] > $row['existencia']) {
        die("Error: No hay suficiente stock para el producto: " . $row['nombre']);
    }
    $productos[] = $row;
    $total += $row['total_producto'];
}
$stmt->close();

if (empty($productos)) {
    die("Error: No hay productos en el carrito.");
}

// Crear un ID de orden único
$orden_id = uniqid("ORD_");

// Guardar los productos en la tabla "compras"
foreach ($productos as $producto) {
    // Insertar en la tabla compras
    $insert_query = "INSERT INTO compras (usuario_id, fecha, producto_id, nombre_producto, imagen_producto, precio, cantidad, total, order_id)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
        
    $stmt_insert = $mysqli->prepare($insert_query);
    $stmt_insert->bind_param("iissdidi", $usuario_id, $producto['producto_id'], $producto['nombre'], $producto['ruta_imagen'], $producto['precio'], $producto['cantidad'], $producto['total_producto'], $orden_id);
    $stmt_insert->execute();
    $stmt_insert->close();

    // Actualizar el inventario de productos
    $update_query = "UPDATE productos SET existencia = existencia - ? WHERE id = ?";
    $stmt_update = $mysqli->prepare($update_query);
    $stmt_update->bind_param("ii", $producto['cantidad'], $producto['producto_id']);
    $stmt_update->execute();
    $stmt_update->close();
}

// Vaciar el carrito después del registro
$query = "DELETE FROM carrito WHERE usuario_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->close();

// Consultar el correo electrónico del cliente
$query = "SELECT email FROM usuarios WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id); // $usuario_id es el ID del cliente desde la sesión
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $email_cliente = $row['email'];
} else {
    die("Error: No se encontró el correo del cliente.");
}

$stmt->close();

// Generar QR y enviar email
$qr_command = escapeshellcmd("python3 qrCode.py $usuario_id $orden_id $email_cliente");
shell_exec($qr_command);

// Mensaje de confirmación
$_SESSION['mensaje_qr'] = "¡Tu compra con pago en efectivo ha sido registrada con éxito! Te hemos enviado un código QR por correo.";

header("Location: principal.php");
exit;
?>
