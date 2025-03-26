<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verifica si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Cuando se agrega un producto al carrito
if (isset($_POST['agregar_carrito'])) {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];

    // Reducir el stock
    $mysqli->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $producto_id");

    // Otros procesos para agregar el producto al carrito...
}


$usuario_id = $_SESSION['usuario_id'];

// Obtener productos del carrito
$query = "
    SELECT 
        c.id AS carrito_id,
        p.id AS producto_id,
        p.nombre, 
        p.precio, 
        c.cantidad 
    FROM 
        carrito c
    INNER JOIN 
        productos p 
    ON 
        c.producto_id = p.id
    WHERE 
        c.usuario_id = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$carrito = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular el total
$total = 0;
foreach ($carrito as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

// Guardar cantidad total en sesión para mostrar en "Mis pedidos"
$_SESSION['carrito_cantidad'] = array_sum(array_column($carrito, 'cantidad'));

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="carrito.css">
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
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="carrito.php" data-cantidad="<?php echo isset($_SESSION['carrito_cantidad']) ? $_SESSION['carrito_cantidad'] : 0; ?>">
                                Mis Pedidos
                                <?php if (isset($_SESSION['carrito_cantidad']) && $_SESSION['carrito_cantidad'] > 0): ?>
                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle">
                                <?php echo $_SESSION['carrito_cantidad']; ?>
                            </span>
                            <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="historial.php">Mis Compras</a></li>
                        <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

<table border="1">
    <thead>
        <tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Subtotal</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($carrito)): ?>
            <?php foreach ($carrito as $item): ?>
                <tr data-id="<?= $item['carrito_id'] ?>">
                    <td><?= htmlspecialchars($item['nombre']); ?></td>
                    <td>$<?= number_format($item['precio'], 2); ?></td>
                    <td class="cantidad"><?= $item['cantidad']; ?></td>
                    <td class="subtotal">$<?= number_format($item['precio'] * $item['cantidad'], 2); ?></td>
                    <td>
                        <button class="eliminar-btn" data-id="<?= $item['carrito_id']; ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                <td id="total"><strong>$<?= number_format($total, 2); ?></strong></td>
                <td></td>
            </tr>
        <?php else: ?>
            <tr>
                <td colspan="5">Tu carrito está vacío.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="payment-container">
    <form action="pago_paypal.php" method="POST">
        <input type="hidden" name="total" value="<?= number_format($total, 2, '.', ''); ?>">
        <button type="submit">Pagar con PayPal</button>
    </form>
    <form action="pago_efectivo.php" method="POST">
        <button type="submit">Pagar en efectivo</button>
    </form>
</div>

<script>
document.querySelectorAll('.eliminar-btn').forEach(boton => {
    boton.addEventListener('click', function() {
        let carritoId = this.getAttribute('data-id');
        let fila = this.closest('tr');

        fetch('eliminar_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'carrito_id=' + carritoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fila.remove();
                document.getElementById('total').innerText = "$" + data.nuevo_total;
                let badge = document.querySelector('.badge');
                if (badge) {
                    badge.innerText = data.nueva_cantidad;
                }
            }
        });
    });
});
</script>
</body>
</html>
