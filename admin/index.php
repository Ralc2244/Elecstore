<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();


// Verificar si el administrador está logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit();
}

// Configuración de la base de datos
$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Cerrar sesión
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: login_admin.php');
    exit();
}

// Procesar búsqueda
$search_type = $_GET['search_type'] ?? 'order_id';
$search_value = $_GET['search_value'] ?? '';

// Procesar marcado como completado
$marcar_completado = $_POST['marcar_completado'] ?? '';

if ($marcar_completado) {
    $mysqli->begin_transaction();

    try {
        // 1. Obtener información del pedido y usuario
        $query_pedido = "SELECT c.*, u.nombre, u.email 
                        FROM compras c
                        JOIN usuarios u ON c.usuario_id = u.id
                        WHERE c.order_id = ?
                        LIMIT 1";
        $stmt = $mysqli->prepare($query_pedido);
        $stmt->bind_param("s", $marcar_completado);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            throw new Exception("No se encontró el pedido especificado");
        }

        // 2. Marcar como completado (escaneado y pagado si era pendiente)
        $stmt = $mysqli->prepare("UPDATE compras SET escaneado = TRUE, estado_pago = 'Pagado', fecha_escaneo = NOW() WHERE order_id = ?");
        $stmt->bind_param("s", $marcar_completado);
        $stmt->execute();
        $stmt->close();

        // 3. Generar recibo solo para pagos en efectivo
        if (strpos($marcar_completado, 'EFEC_') === 0) {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../lib/fpdf.php';

            // Obtener productos del pedido
            $query_productos = "SELECT nombre_producto, cantidad, precio, total 
                              FROM compras 
                              WHERE order_id = ?";
            $stmt = $mysqli->prepare($query_productos);
            $stmt->bind_param("s", $marcar_completado);
            $stmt->execute();
            $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Calcular total
            $total = array_reduce($productos, function ($carry, $item) {
                return $carry + $item['total'];
            }, 0);

            // Crear directorio de recibos si no existe
            $recibos_dir = __DIR__ . '/recibos';
            if (!file_exists($recibos_dir)) {
                mkdir($recibos_dir, 0755, true);
            }

            // Generar PDF
            $pdf = new FPDF();
            $pdf->AddPage();

            // Encabezado
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'RECIBO DE COMPRA', 0, 1, 'C');
            $pdf->Ln(10);

            // Datos del cliente
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Cliente: ' . $pedido['nombre'], 0, 1);
            $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1);
            $pdf->Cell(0, 10, 'Folio: ' . $marcar_completado, 0, 1);
            $pdf->Ln(10);

            // Tabla de productos
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(100, 10, 'Producto', 1, 0, 'C');
            $pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C');
            $pdf->Cell(30, 10, 'P. Unitario', 1, 0, 'C');
            $pdf->Cell(30, 10, 'Total', 1, 1, 'C');

            $pdf->SetFont('Arial', '', 10);
            foreach ($productos as $producto) {
                $pdf->Cell(100, 10, $producto['nombre_producto'], 1);
                $pdf->Cell(30, 10, $producto['cantidad'], 1, 0, 'C');
                $pdf->Cell(30, 10, '$' . number_format($producto['precio'], 2), 1, 0, 'R');
                $pdf->Cell(30, 10, '$' . number_format($producto['total'], 2), 1, 1, 'R');
            }

            // Total
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(160, 10, 'Total:', 1, 0, 'R');
            $pdf->Cell(30, 10, '$' . number_format($total, 2), 1, 1, 'R');

            // Guardar PDF
            $pdf_path = $recibos_dir . '/recibo_' . $marcar_completado . '.pdf';
            $pdf->Output('F', $pdf_path);

            // 4. Enviar email con el recibo
            $mail = new PHPMailer(true);
            try {
                // Configuración SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'elecstoreceti@gmail.com';
                $mail->Password = 'dipx bojn iywk flff';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Remitente y destinatario
                $mail->setFrom('elecstoreceti@gmail.com', 'Elecstore');
                $mail->addAddress($pedido['email'], $pedido['nombre']);

                // Contenido del correo
                $mail->isHTML(true);
                $mail->Subject = 'Recibo de compra #' . $marcar_completado;
                $mail->Body = "
                    <h2 style='color: #0066cc;'>¡Gracias por tu compra!</h2>
                    <p>Hola {$pedido['nombre']},</p>
                    <p>Adjuntamos el recibo de tu compra con folio <strong>{$marcar_completado}</strong>.</p>
                    
                    <h3>Detalles de la compra:</h3>
                    <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
                    <p><strong>Total:</strong> $" . number_format($total, 2) . " MXN</p>
                    <p><strong>Método de pago:</strong> Efectivo</p>
                    
                    <p>Guarda este correo como comprobante de tu compra.</p>
                    <p>Gracias por tu preferencia,</p>
                    <p><strong>Equipo ELECSTORE</strong></p>
                ";

                $mail->AltBody = "Recibo de compra\n\nFolio: {$marcar_completado}\nFecha: " . date('d/m/Y H:i:s') .
                    "\nTotal: $" . number_format($total, 2) . " MXN\n\nGracias por tu compra.";

                // Adjuntar recibo
                $mail->addAttachment($pdf_path, 'recibo_' . $marcar_completado . '.pdf');

                $mail->send();
            } catch (Exception $e) {
                error_log("Error al enviar email con recibo: " . $e->getMessage());
            }
        }

        $mysqli->commit();

        $_SESSION['admin_message'] = "Pedido $marcar_completado marcado como completado" .
            (strpos($marcar_completado, 'EFEC_') === 0 ? " y recibo enviado" : "");
        header("Location: index.php?search_type=" . urlencode($search_type) . "&search_value=" . urlencode($search_value));
        exit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['admin_error'] = "Error al procesar el pedido: " . $e->getMessage();
        header("Location: index.php?search_type=" . urlencode($search_type) . "&search_value=" . urlencode($search_value));
        exit();
    }
}

// Consulta para obtener pedidos con paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$query = "SELECT 
    c.order_id,
    c.fecha,
    c.total,
    c.estado_pago,
    c.escaneado,
    c.fecha_escaneo,
    GROUP_CONCAT(p.nombre SEPARATOR ', ') AS productos,
    u.nombre AS estudiante,
    u.id AS usuario_id
FROM compras c
JOIN productos p ON c.producto_id = p.id
JOIN usuarios u ON c.usuario_id = u.id";

$where = [];
$params = [];
$types = "";

if ($search_value) {
    switch ($search_type) {
        case 'order_id':
            $where[] = "c.order_id = ?";
            $params[] = $search_value;
            $types .= "s";
            break;
        case 'usuario_id':
            $where[] = "u.id = ?";
            $params[] = $search_value;
            $types .= "s";
            break;
        case 'estudiante':
            $where[] = "u.nombre LIKE ?";
            $params[] = "%" . $search_value . "%";
            $types .= "s";
            break;
        case 'fecha':
            $where[] = "DATE(c.fecha) = ?";
            $params[] = $search_value;
            $types .= "s";
            break;
        case 'producto':
            $where[] = "p.nombre LIKE ?";
            $params[] = "%" . $search_value . "%";
            $types .= "s";
            break;
    }
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " GROUP BY c.order_id ORDER BY c.fecha DESC LIMIT $per_page OFFSET $offset";

$stmt = $mysqli->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$pedidos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Obtener total de pedidos para paginación
$count_query = "SELECT COUNT(DISTINCT c.order_id) as total 
               FROM compras c
               JOIN productos p ON c.producto_id = p.id
               JOIN usuarios u ON c.usuario_id = u.id";

if (!empty($where)) {
    $count_query .= " WHERE " . implode(" AND ", $where);
}

$count_stmt = $mysqli->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$total_pedidos = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_pedidos / $per_page);

// Obtener estadísticas de pedidos
$total = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras")->fetch_assoc()['total'];
$pendientes = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras WHERE estado_pago = 'Pendiente'")->fetch_assoc()['total'];
$completados = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras WHERE estado_pago = 'Pagado'")->fetch_assoc()['total'];
$total_escaneados = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras WHERE escaneado = TRUE")->fetch_assoc()['total'];
$pendientes_escaneo = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras WHERE estado_pago = 'Pagado' AND escaneado = FALSE")->fetch_assoc()['total'];
$pendientes_pago_efectivo = $mysqli->query("SELECT COUNT(DISTINCT order_id) as total FROM compras WHERE estado_pago = 'Pendiente' AND escaneado = FALSE")->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - ELECSTORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .search-type-selector {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .search-input {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-left: 0;
        }

        .search-input-date {
            max-width: 200px;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="admin-container">
            <a class="navbar-brand" href="index.php">ELECSTORE - ADMIN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAdmin">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="abc_analysis.php">
                            <i class="fas fa-chart-bar me-1"></i> Análisis ABC
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="resenias.php">
                            <i class="fas fa-comment me-1"></i> Reseñas
                        </a>
                    </li>
                </ul>
                <form method="POST" class="logout-form ms-auto">
                    <button type="submit" name="logout" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="admin-container py-4">
        <!-- Mensajes de alerta -->
        <?php if (isset($_SESSION['admin_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['admin_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['admin_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['admin_error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-4">Panel de Gestión de Pedidos</h1>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="search-box">
                            <div class="input-group">
                                <select name="search_type" class="form-select search-type-selector">
                                    <option value="order_id" <?= $search_type === 'order_id' ? 'selected' : '' ?>>ID Pedido</option>
                                    <option value="usuario_id" <?= $search_type === 'usuario_id' ? 'selected' : '' ?>>ID Usuario</option>
                                    <option value="estudiante" <?= $search_type === 'estudiante' ? 'selected' : '' ?>>Nombre Estudiante</option>
                                    <option value="fecha" <?= $search_type === 'fecha' ? 'selected' : '' ?>>Fecha (YYYY-MM-DD)</option>
                                    <option value="producto" <?= $search_type === 'producto' ? 'selected' : '' ?>>Producto</option>
                                </select>

                                <?php if ($search_type === 'fecha'): ?>
                                    <input type="date" name="search_value" class="form-control form-control-lg search-input search-input-date"
                                        value="<?= htmlspecialchars($search_value) ?>" autofocus>
                                <?php else: ?>
                                    <input type="text" name="search_value" class="form-control form-control-lg search-input"
                                        placeholder="Buscar..." value="<?= htmlspecialchars($search_value) ?>" autofocus>
                                <?php endif; ?>

                                <button class="btn btn-dark" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if ($search_value): ?>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Pedidos</h5>
                        <h2 class="mb-0"><?= $total ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Pendientes Pago</h5>
                        <h2 class="mb-0"><?= $pendientes ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Pagados</h5>
                        <h2 class="mb-0"><?= $completados ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Completados</h5>
                        <h2 class="mb-0"><?= $total_escaneados ?></h2>
                        <small><?= $pendientes_escaneo + $pendientes_pago_efectivo ?> pendientes</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de pedidos -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i> Pedidos Recientes
                            <?php if ($search_value): ?>
                                <small class="float-end">Mostrando <?= count($pedidos) ?> de <?= $total_pedidos ?> resultados</small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedidos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                                <h4>No se encontraron pedidos</h4>
                                <p class="text-muted"><?= $search_value ? "No hay resultados para tu búsqueda" : "No hay pedidos registrados" ?></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table orders-table">
                                    <thead>
                                        <tr>
                                            <th>ID Pedido</th>
                                            <th>Estudiante (ID)</th>
                                            <th>Fecha</th>
                                            <th>Productos</th>
                                            <th>Total</th>
                                            <th>Estado Pago</th>
                                            <th>Completado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <td data-label="ID Pedido">
                                                    <strong><?= htmlspecialchars($pedido['order_id']) ?></strong>
                                                    <?php if (strpos($pedido['order_id'], 'PP_') === 0): ?>
                                                        <br><small class="text-primary">PayPal</small>
                                                    <?php else: ?>
                                                        <br><small class="text-secondary">Efectivo</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Estudiante">
                                                    <?= htmlspecialchars($pedido['estudiante']) ?>
                                                    <br><small class="text-muted">ID: <?= $pedido['usuario_id'] ?></small>
                                                </td>
                                                <td data-label="Fecha"><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></td>
                                                <td data-label="Productos"><?= htmlspecialchars($pedido['productos']) ?></td>
                                                <td data-label="Total">$<?= number_format($pedido['total'], 2) ?></td>
                                                <td data-label="Estado Pago">
                                                    <span class="badge rounded-pill <?= $pedido['estado_pago'] == 'Pagado' ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= htmlspecialchars($pedido['estado_pago']) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Completado">
                                                    <?php if ($pedido['escaneado']): ?>
                                                        <span class="badge rounded-pill bg-info">
                                                            <i class="fas fa-check-circle"></i> Sí<br>
                                                            <small><?= date('H:i', strtotime($pedido['fecha_escaneo'])) ?></small>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill bg-secondary">
                                                            <i class="fas fa-hourglass-half"></i> No
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Acciones">
                                                    <?php if (!$pedido['escaneado']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="marcar_completado" value="<?= $pedido['order_id'] ?>">
                                                            <input type="hidden" name="search_type" value="<?= htmlspecialchars($search_type) ?>">
                                                            <input type="hidden" name="search_value" value="<?= htmlspecialchars($search_value) ?>">
                                                            <button type="submit" class="btn btn-sm btn-completar">
                                                                <i class="fas fa-check-circle me-1"></i>
                                                                <?= strpos($pedido['order_id'], 'EFEC_') === 0 ? 'Pago Completado' : 'Marcar Escaneado' ?>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-success"><i class="fas fa-check-circle"></i> Finalizado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <?php if (!$search_value && $total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="index.php?page=<?= $page - 1 ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="index.php?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="index.php?page=<?= $page + 1 ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus en el campo de búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search_value"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Cambiar el tipo de input cuando se cambia el tipo de búsqueda
            const searchType = document.querySelector('select[name="search_type"]');
            const searchValue = document.querySelector('input[name="search_value"]');

            if (searchType && searchValue) {
                searchType.addEventListener('change', function() {
                    if (this.value === 'fecha') {
                        searchValue.type = 'date';
                        searchValue.className = 'form-control form-control-lg search-input search-input-date';
                    } else {
                        searchValue.type = 'text';
                        searchValue.className = 'form-control form-control-lg search-input';
                    }
                });
            }
        });
    </script>
</body>

</html>
<?php $mysqli->close(); ?>