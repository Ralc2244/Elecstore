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

$query = "SELECT id, fecha, nombre_producto, precio, cantidad, total
    FROM compras
    WHERE usuario_id = ?
    ORDER BY fecha DESC";
    
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="historial.css">
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="principal.php">Elecstore</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="principal.php">Principal</a></li>
                    <li class="nav-item"><a class="nav-link" href="carrito.php">Mis Pedidos</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.php">Mis Compras</a></li>
                    <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi perfil</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<div class="container mt-5">
    <h2 class="mb-4">Mis Compras</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['fecha']); ?></td>
                    <td><?= htmlspecialchars($row['nombre_producto']); ?></td>
                    <td><?= $row['cantidad']; ?></td>
                    <td>$<?= number_format($row['precio'], 2); ?> MXN</td>
                    <td><strong>$<?= number_format($row['total'], 2); ?> MXN</strong></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>

<?php
$mysqli->close();
?>