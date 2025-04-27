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
$query = "SELECT c.producto_id, p.nombre, p.precio, p.existencia, c.cantidad, (p.precio * c.cantidad) AS total_producto 
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

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago con PayPal</title>
    <script src="https://www.paypal.com/sdk/js?client-id=Abr-T4VBBcG9sSfmJjJNtrYoKdTdl8IruLl3HT4zj1jN83i-Ie3f-XX5AjldUs5favXsRiFSngrYUNk6&currency=MXN"></script>
</head>

<body>
    <h2>Total a pagar: $<?= number_format($total, 2); ?> MXN</h2>
    <div id="paypal-button-container"></div>

    <script>
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?= number_format($total, 2, '.', ''); ?>'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    alert('Pago completado por ' + details.payer.name.given_name);
                    window.location.href = "pago_realizado.php?orderID=" + data.orderID;
                });
            }
        }).render('#paypal-button-container');
    </script>
</body>

</html>