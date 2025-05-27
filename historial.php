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
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Consulta para obtener las compras del usuario
$query_compras = "SELECT 
    id, 
    order_id, 
    fecha, 
    total, 
    estado_pago
FROM compras
WHERE usuario_id = ?";

if ($fecha_inicio && $fecha_fin) {
    $query_compras .= " AND fecha BETWEEN ? AND ?";
}

$query_compras .= " GROUP BY order_id ORDER BY fecha DESC";

$stmt_compras = $mysqli->prepare($query_compras);

if ($fecha_inicio && $fecha_fin) {
    $stmt_compras->bind_param("iss", $usuario_id, $fecha_inicio, $fecha_fin);
} else {
    $stmt_compras->bind_param("i", $usuario_id);
}

$stmt_compras->execute();
$result_compras = $stmt_compras->get_result();

$compras = [];
while ($compra = $result_compras->fetch_assoc()) {
    // Definir rutas absolutas para verificar la existencia del archivo
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/elecstore/';

    // Para pagos online
    if (strpos($compra['order_id'], 'PP_') === 0) {
        $ruta_absoluta = $base_path . 'recibos/recibo_' . $compra['order_id'] . '.pdf';
        $ruta_relativa = 'recibos/recibo_' . $compra['order_id'] . '.pdf';
    }
    // Para pagos en efectivo
    else {
        $ruta_absoluta = $base_path . 'admin/recibos/recibo_' . $compra['order_id'] . '.pdf';
        $ruta_relativa = 'admin/recibos/recibo_' . $compra['order_id'] . '.pdf';
    }

    // Verificar existencia del archivo
    $compra['recibo_existe'] = file_exists($ruta_absoluta);
    $compra['ruta_recibo'] = $ruta_relativa;

    // Obtener productos de la compra
    $query_productos = "SELECT nombre_producto, cantidad, precio, total 
                       FROM compras 
                       WHERE order_id = ?";
    $stmt_productos = $mysqli->prepare($query_productos);
    $stmt_productos->bind_param("s", $compra['order_id']);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();

    $compra['productos'] = $result_productos->fetch_all(MYSQLI_ASSOC);
    $compras[] = $compra;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .navbar.bg-black {
            background-color: #000 !important;
        }

        .navbar-dark .navbar-brand,
        .navbar-dark .nav-link {
            color: white !important;
        }

        .navbar-dark .nav-link.active {
            font-weight: bold;
        }

        .page-title {
            color: #333;
            font-weight: 600;
        }

        .btn-black-white {
            background-color: #000;
            color: white;
            border: 1px solid #000;
        }

        .btn-black-white:hover {
            background-color: white;
            color: #000;
        }

        .accordion-item {
            border-radius: 8px;
            overflow: hidden;
        }

        .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #000;
        }

        .empty-state {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 40px 20px;
        }

        .badge-pendiente {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-pagado {
            background-color: #28a745;
            color: white;
        }

        .badge-cancelado {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <header class="mb-4">
        <nav class="navbar navbar-expand-lg navbar-dark bg-black">
            <div class="container">
                <a class="navbar-brand" href="principal.php">ELECSTORE</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="principal.php">Principal</a></li>
                        <li class="nav-item"><a class="nav-link" href="para_ti.php">Para ti</a></li>
                        <li class="nav-item position-relative">
                            <a class="nav-link" href="carrito.php">
                                Mis Pedidos
                                <?php if (isset($_SESSION['carrito_cantidad']) && $_SESSION['carrito_cantidad'] > 0): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                                        <?= $_SESSION['carrito_cantidad'] ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link active" href="historial.php">Mis Compras</a></li>
                        <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi Perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Historial de Compras</h1>
            <a href="principal.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Seguir comprando
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h2 class="h5 mb-0">Filtrar por fecha</h2>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label for="fecha_inicio" class="form-label">Fecha de inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="fecha_fin" class="form-label">Fecha de fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-black-white w-100">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($compras)): ?>
            <div class="empty-state text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                <h2 class="h4">No hay compras registradas</h2>
                <p class="text-muted">Cuando realices compras, aparecerán en este historial.</p>
                <a href="principal.php" class="btn btn-primary mt-3">Ver productos</a>
            </div>
        <?php else: ?>
            <div class="accordion" id="comprasAccordion">
                <?php foreach ($compras as $compra): ?>
                    <div class="accordion-item mb-3 border-0 shadow-sm">
                        <h2 class="accordion-header" id="heading<?= $compra['id'] ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse<?= $compra['id'] ?>" aria-expanded="false"
                                aria-controls="collapse<?= $compra['id'] ?>">
                                <div class="d-flex justify-content-between w-100">
                                    <span class="me-3">
                                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                        <?= date('d/m/Y', strtotime($compra['fecha'])) ?>
                                    </span>
                                    <span class="me-3">
                                        <i class="fas fa-hashtag me-2 text-primary"></i>
                                        <?= htmlspecialchars($compra['order_id']) ?>
                                    </span>
                                    <span class="me-3">
                                        <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                                        $<?= number_format($compra['total'], 2) ?> MXN
                                    </span>
                                    <span class="badge <?=
                                                        $compra['estado_pago'] == 'Pagado' ? 'badge-pagado' : ($compra['estado_pago'] == 'Pendiente' ? 'badge-pendiente' : 'badge-cancelado')
                                                        ?>">
                                        <?= htmlspecialchars($compra['estado_pago']) ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $compra['id'] ?>" class="accordion-collapse collapse"
                            aria-labelledby="heading<?= $compra['id'] ?>" data-bs-parent="#comprasAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-center">Cantidad</th>
                                                <th class="text-end">Precio Unitario</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($compra['productos'] as $producto): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($producto['nombre_producto']) ?></td>
                                                    <td class="text-center"><?= $producto['cantidad'] ?></td>
                                                    <td class="text-end">$<?= number_format($producto['precio'], 2) ?> MXN</td>
                                                    <td class="text-end">$<?= number_format($producto['total'], 2) ?> MXN</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                                <td class="text-end fw-bold">$<?= number_format($compra['total'], 2) ?> MXN</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="p-3 text-end">
                                    <?php if ($compra['estado_pago'] == 'Pagado'): ?>
                                        <?php if ($compra['recibo_existe']): ?>
                                            <a href="<?= $compra['ruta_recibo'] ?>"
                                                class="btn btn-outline-primary"
                                                download="recibo_<?= htmlspecialchars($compra['order_id']) ?>.pdf">
                                                <i class="fas fa-file-pdf me-2"></i>Descargar Recibo
                                            </a>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <?php if (strpos($compra['order_id'], 'EFEC_') === 0): ?>
                                                    Recibo no encontrado. Contacta al administrador.
                                                    <?php error_log("Recibo no encontrado para orden EFEC: " . $compra['order_id'] . " en ruta: " . $compra['ruta_recibo']); ?>
                                                <?php else: ?>
                                                    Recibo no disponible
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($compra['estado_pago'] == 'Pendiente'): ?>
                                        <span class="text-danger">
                                            <i class="fas fa-clock me-2"></i>
                                            <?= strpos($compra['order_id'], 'EFEC_') === 0 ? 'Recibo disponible al completar el pago' : 'Recibo no disponible' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger">
                                            <i class="fas fa-times-circle me-2"></i>Recibo no generado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>