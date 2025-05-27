<?php
session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_GET['orden_id'])) {
    header("Location: principal.php");
    exit;
}

$orden_id = $_GET['orden_id'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Confirmada | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h2 class="mb-0"><i class="fas fa-check-circle me-2"></i>Reserva Confirmada</h2>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['mensaje_qr'])): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading"><?= $_SESSION['mensaje_qr'] ?></h4>
                        <p>ID de Reserva: <strong><?= htmlspecialchars($orden_id) ?></strong></p>
                        <hr>
                        <p class="mb-0">
                            Hemos enviado un código QR a tu correo electrónico. Preséntalo en nuestra tienda física
                            junto con este número de reserva para completar tu pago en efectivo.
                        </p>
                    </div>
                    <?php unset($_SESSION['mensaje_qr']); ?>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Instrucciones para completar tu compra</h5>
                            </div>
                            <div class="card-body">
                                <ol class="list-group list-group-numbered">
                                    <li class="list-group-item">Revisa tu correo electrónico para encontrar el código QR</li>
                                    <li class="list-group-item">Acude a nuestra tienda física dentro de las próximas 96 horas</li>
                                    <li class="list-group-item">Presenta el código QR o este número de reserva</li>
                                    <li class="list-group-item">Realiza el pago en efectivo</li>
                                    <li class="list-group-item">Recibe tus productos y tu recibo oficial</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Detalles de tu reserva</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Número de Reserva:</strong> <?= htmlspecialchars($orden_id) ?></p>
                                <p><strong>Estado:</strong> <span class="badge bg-warning">Pendiente de pago</span></p>
                                <p><strong>Válido hasta:</strong> <?= date('d/m/Y H:i', strtotime('+4 days')) ?></p>
                                <p class="text-muted mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Si no realizas el pago en el plazo indicado, tu reserva será cancelada automáticamente.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                    <a href="principal.php" class="btn btn-primary me-md-2">
                        <i class="fas fa-home me-2"></i>Volver a la tienda
                    </a>
                    <a href="historial.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>Ver mis reservas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>