<?php
session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_GET['order_id'])) {
    header("Location: principal.php");
    exit;
}

$orden_id = $_GET['order_id'];

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'elecstore');

// Conexión a la base de datos
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Obtener detalles de la compra
$query = "SELECT c.*, p.nombre AS producto_nombre, p.precio 
          FROM compras c
          JOIN productos p ON c.producto_id = p.id
          WHERE c.order_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $orden_id);
$stmt->execute();
$compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($compras)) {
    die("No se encontró la orden especificada");
}

// Calcular total
$total = 0;
foreach ($compras as $compra) {
    $total += $compra['total'];
}

// Obtener fecha de compra (usamos la primera compra como referencia)
$fecha_compra = $compras[0]['fecha'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra Confirmada | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .producto-img {
            max-width: 80px;
            max-height: 80px;
        }

        .badge-pagado {
            background-color: #28a745;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h2 class="mb-0"><i class="fas fa-check-circle me-2"></i>Compra Confirmada</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h4 class="alert-heading">¡Pago realizado con éxito!</h4>
                    <p>Número de orden: <strong><?= htmlspecialchars($orden_id) ?></strong></p>
                    <hr>
                    <p class="mb-0">
                        Hemos enviado un recibo detallado y un código QR a tu correo electrónico.
                        Puedes presentar este código QR si necesitas asistencia con tu compra.
                    </p>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Detalles de tu compra</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Precio</th>
                                                <th>Cantidad</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($compras as $compra): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?= htmlspecialchars($compra['imagen_producto']) ?>"
                                                            alt="<?= htmlspecialchars($compra['producto_nombre']) ?>"
                                                            class="producto-img me-2">
                                                        <?= htmlspecialchars($compra['producto_nombre']) ?>
                                                    </td>
                                                    <td>$<?= number_format($compra['precio'], 2) ?></td>
                                                    <td><?= $compra['cantidad'] ?></td>
                                                    <td>$<?= number_format($compra['total'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">Total:</th>
                                                <th>$<?= number_format($total, 2) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Resumen de la orden</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Número de orden:</strong> <?= htmlspecialchars($orden_id) ?></p>
                                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($fecha_compra)) ?></p>
                                <p><strong>Estado:</strong> <span class="badge badge-pagado">Pagado</span></p>
                                <p><strong>Método de pago:</strong> PayPal</p>

                                <div class="d-grid">
                                    <a href="recibos/recibo_<?= $orden_id ?>.pdf"
                                        class="btn btn-outline-primary"
                                        download="Recibo_<?= $orden_id ?>.pdf">
                                        <i class="fas fa-download me-2"></i>Descargar recibo
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                    <a href="principal.php" class="btn btn-primary me-md-2">
                        <i class="fas fa-home me-2"></i>Volver a la tienda
                    </a>
                    <a href="historial_compras.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>Ver mi historial de compras
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $mysqli->close(); ?>