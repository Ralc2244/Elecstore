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

// Variables para búsqueda y filtrado
$producto = $_GET['producto'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Consulta de compras
$query_compras = "SELECT id, order_id, fecha, total, estado_pago
    FROM compras
    WHERE usuario_id = ?";

if ($producto) {
    // Ya no necesitamos buscar en detalles_compras, solo en compras
    $query_compras .= " AND nombre_producto LIKE ?";
}

if ($fecha_inicio && $fecha_fin) {
    $query_compras .= " AND fecha BETWEEN ? AND ?";
}

$stmt_compras = $mysqli->prepare($query_compras);
if ($producto && $fecha_inicio && $fecha_fin) {
    $stmt_compras->bind_param("ssss", $usuario_id, "%$producto%", $fecha_inicio, $fecha_fin);
} elseif ($producto) {
    $stmt_compras->bind_param("ss", $usuario_id, "%$producto%");
} elseif ($fecha_inicio && $fecha_fin) {
    $stmt_compras->bind_param("sss", $usuario_id, $fecha_inicio, $fecha_fin);
} else {
    $stmt_compras->bind_param("i", $usuario_id);
}

$stmt_compras->execute();
$result_compras = $stmt_compras->get_result();

// Obtener detalles de las compras (productos dentro de compras)
$compras = [];
while ($compra = $result_compras->fetch_assoc()) {
    $compra_id = $compra['id'];

    // Ahora directamente consultamos los productos que están guardados en la misma tabla de compras
    $query_productos = "SELECT nombre_producto, cantidad, precio, total 
                        FROM compras 
                        WHERE order_id = ?";
    $stmt_productos = $mysqli->prepare($query_productos);
    $stmt_productos->bind_param("s", $compra['order_id']);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();

    $productos = [];
    while ($producto = $result_productos->fetch_assoc()) {
        $productos[] = $producto;
    }

    $compra['productos'] = $productos;
    $compras[] = $compra;
}

$stmt_compras->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="principal.php">Elecstore</a>
            </div>
        </nav>
    </header>

    <div class="container mt-5">
        <h2 class="mb-4">Mis Compras</h2>

        <form method="GET" action="historial.php">
            <div class="mb-3">
                <input type="text" name="producto" class="form-control" placeholder="Buscar por producto" value="<?= htmlspecialchars($producto); ?>">
            </div>
            <div class="mb-3">
                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio); ?>">
            </div>
            <div class="mb-3">
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <table class="table table-striped mt-4">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Order ID</th>
                    <th>Total</th>
                    <th>Estado de Pago</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($compras as $compra): ?>
                    <tr>
                        <td><?= htmlspecialchars($compra['fecha']); ?></td>
                        <td><?= htmlspecialchars($compra['order_id']); ?></td>
                        <td>$<?= number_format($compra['total'], 2); ?> MXN</td>
                        <td><?= htmlspecialchars($compra['estado_pago']); ?></td>
                        <td>
                            <a href="recibos/recibo_<?= $compra['order_id']; ?>.pdf" target="_blank">Descargar Recibo</a>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="5">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unitario</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compra['productos'] as $producto): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($producto['nombre_producto']); ?></td>
                                            <td><?= $producto['cantidad']; ?></td>
                                            <td>$<?= number_format($producto['precio'], 2); ?> MXN</td>
                                            <td>$<?= number_format($producto['total'], 2); ?> MXN</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>