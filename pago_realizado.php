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

// Verificar si se ha recibido el ID de la orden en el caso de PayPal
if (isset($_GET['orderID'])) {
    $order_id = $_GET['orderID'];

    // Actualizar estado de la orden a "Pagado"
    $update_query = "UPDATE compras SET estado_pago = 'Pagado' WHERE order_id = ?";
    $stmt_update = $mysqli->prepare($update_query);
    $stmt_update->bind_param("s", $order_id);
    $stmt_update->execute();
    $stmt_update->close();

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
    $qr_command = escapeshellcmd("python3 C:\\xampp\\htdocs\\elecstore\\qrCode.py $usuario_id $order_id $email_cliente");
    shell_exec($qr_command);

    $_SESSION['mensaje'] = "¡Tu pago con PayPal ha sido procesado exitosamente! Te hemos enviado un código QR por correo.";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Realizado</title>
</head>
<body>
    <h1>Confirmación de Compra</h1>
    <p><?php echo $_SESSION['mensaje']; ?></p>
    <a href="principal.php">Volver al catálogo</a>
</body>
</html>
