<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header("Location: login.php");
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre']);

// Verificar si se recibió un ID de producto válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de producto no válido.");
}

$producto_id = (int)$_GET['id'];

// Consultar detalles del producto
$sql = "SELECT id, nombre, descripcion, precio, ruta_imagen FROM productos WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Producto no encontrado.");
}

$producto = $resultado->fetch_assoc();

$sql_productos = "SELECT DISTINCT productos.id, productos.nombre, productos.descripcion, productos.precio, productos.ruta_imagen, productos.existencia
    FROM productos 
    LEFT JOIN productotienecategoria 
    ON productos.id = productotienecategoria.producto_id
    WHERE 1=1";


$stmt->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="detalles.css">
</head>
<body>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="detalles.php">Elecstore</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="principal.php">Principal</a></li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="carrito.php" data-cantidad="<?php echo isset($_SESSION['carrito_cantidad']) ? $_SESSION['carrito_cantidad'] : 0; ?>">Mis Pedidos
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

    <main class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <img src="<?php echo htmlspecialchars($producto['ruta_imagen']); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
            </div>
            <div class="col-md-6">
                <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                <p class="text-muted">Precio: <strong>$<?php echo number_format($producto['precio'], 2); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                <div class="input-group mb-3">
    <input type="number" name="cantidad" class="form-control cantidad-input" min="1" value="1" required>
    <input type="hidden" class="producto-id" value="<?php echo $producto['id']; ?>">
    <button class="btn btn-primary agregar-carrito" data-id="<?php echo $producto['id']; ?>">Agregar</button>
</div>
                <a href="principal.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </main>
    <script>
document.querySelectorAll('.agregar-carrito').forEach(boton => {
    boton.addEventListener('click', function (e) {
        e.preventDefault();
        let productoId = this.getAttribute('data-id');

        fetch('agregar_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'producto_id=' + productoId + '&cantidad=1'
        })
        .then(() => {
            let badge = document.querySelector('.nav-link[href="carrito.php"] .badge');
            if (!badge) {
                let misPedidos = document.querySelector('.nav-link[href="carrito.php"]');
                let newBadge = document.createElement('span');
                newBadge.className = "badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle";
                newBadge.innerText = parseInt(misPedidos.getAttribute('data-cantidad')) + 1;
                misPedidos.appendChild(newBadge);
            } else {
                badge.innerText = parseInt(badge.innerText) + 1;
            }
        });
    });
});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $(".agregar-carrito").click(function() {
        var boton = $(this);
        var productoId = boton.data("id");

        $.post("agregar_carrito.php", { id: productoId }, function(respuesta) {
            var data = JSON.parse(respuesta);
            if (data.success) {
                // Actualizar la cantidad de stock en pantalla
                boton.closest(".card").find(".text-muted").text("Stock disponible: " + data.nueva_existencia);
            } else {
                alert("Error al agregar el producto.");
            }
        });
    });
});
</script>

</body>

</html>

