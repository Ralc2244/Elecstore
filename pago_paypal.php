<?php
session_start();

// Configuración de la base de datos (todo en un archivo)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'elecstore');
define('PAYPAL_CLIENT_ID', 'Abr-T4VBBcG9sSfmJjJNtrYoKdTdl8IruLl3HT4zj1jN83i-Ie3f-XX5AjldUs5favXsRiFSngrYUNk6');

// Conexión a la base de datos
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("<div class='alert alert-danger'>Error de conexión: " . $mysqli->connect_error . "</div>");
}

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener productos del carrito
$query = "SELECT c.producto_id, p.nombre, p.precio, p.existencia, c.cantidad, 
                 (p.precio * c.cantidad) AS total_producto 
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
        echo "<div class='alert alert-warning'>No hay suficiente stock para: " . htmlspecialchars($row['nombre']) . "</div>";
        exit;
    }
    $productos[] = $row;
    $total += $row['total_producto'];
}
$stmt->close();

if (empty($productos)) {
    echo "<div class='alert alert-info'>No hay productos en el carrito.</div>";
    exit;
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago con PayPal - Tienda Electrónica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=MXN"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 20px;
        }

        .payment-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .product-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .total-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
        }

        #paypal-button-container {
            margin-top: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: #0d6efd;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="payment-container">
            <div class="header">
                <h2><i class="fas fa-credit-card me-2"></i>Proceso de Pago</h2>
                <p class="text-muted">Complete su compra de manera segura con PayPal</p>
            </div>

            <div class="summary-card">
                <h4><i class="fas fa-shopping-cart me-2"></i>Resumen de tu pedido</h4>

                <?php foreach ($productos as $producto): ?>
                    <div class="product-item row">
                        <div class="col-md-6">
                            <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                            <div class="text-muted small">Cantidad: <?= $producto['cantidad'] ?></div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div>$<?= number_format($producto['precio'], 2) ?> c/u</div>
                            <div class="fw-bold">$<?= number_format($producto['total_producto'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="row mt-3 pt-2 border-top">
                    <div class="col-md-6">
                        <h5>Total a pagar:</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="total-display">$<?= number_format($total, 2) ?> MXN</div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <div id="paypal-button-container"></div>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-lock me-1"></i> Pago seguro procesado por PayPal
                </small>
                <a href="carrito.php" class="btn btn-outline-secondary mt-3">
                    <i class="fas fa-arrow-left me-1"></i> Volver al carrito
                </a>
            </div>
        </div>
    </div>

    <script>
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'paypal'
            },
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?= number_format($total, 2, '.', '') ?>'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    window.location.href = "pago_realizado.php?orderID=" + data.orderID;
                });
            },
            onError: function(err) {
                console.error('Error en el pago:', err);
                alert('Ocurrió un error al procesar tu pago. Por favor, intenta nuevamente.');
            }
        }).render('#paypal-button-container');
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>