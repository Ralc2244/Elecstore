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
$sql = "SELECT id, nombre, descripcion, precio, ruta_imagen, existencia FROM productos WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Producto no encontrado.");
}

$producto = $resultado->fetch_assoc();

// Consultar descuentos aplicables al producto
$fecha_actual = date('Y-m-d');
$sql_descuentos = "SELECT descripcion, porcentaje_descuento, fecha_inicio, fecha_fin 
                   FROM descuentos 
                   WHERE producto_id = ? AND fecha_inicio <= ? AND fecha_fin >= ?";
$stmt_descuentos = $mysqli->prepare($sql_descuentos);
$stmt_descuentos->bind_param("iss", $producto_id, $fecha_actual, $fecha_actual);
$stmt_descuentos->execute();
$resultado_descuentos = $stmt_descuentos->get_result();

// Guardar los descuentos aplicables
$descuentos = [];
while ($descuento = $resultado_descuentos->fetch_assoc()) {
    $descuentos[] = $descuento;
}

$stmt->close();
$stmt_descuentos->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($producto['nombre']) ?> | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/detalles.css">
    <style>
        /* Estilos específicos para el navbar negro */
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

        .btn-black-white {
            background-color: black;
            color: white;
            border: 1px solid black;
        }

        .btn-black-white:hover {
            background-color: white;
            color: black;
            border: 1px solid black;
        }
    </style>
</head>

<body>
    <!-- Navbar negro -->
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
                                <i class="fas fa-shopping-cart me-1"></i>Mis Pedidos
                                <?php if (isset($_SESSION['carrito_cantidad']) && $_SESSION['carrito_cantidad'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle">
                                        <?= $_SESSION['carrito_cantidad'] ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="historial.php">Mis Compras</a></li>
                        <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi Perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <div class="row">
            <div class="col-md-6">
                <div class="product-image-container mb-4">
                    <img src="<?= htmlspecialchars($producto['ruta_imagen']) ?>"
                        class="img-fluid rounded"
                        alt="<?= htmlspecialchars($producto['nombre']) ?>"
                        loading="lazy">
                </div>
            </div>
            <div class="col-md-6">
                <div class="product-details">
                    <h1 class="mb-3"><?= htmlspecialchars($producto['nombre']) ?></h1>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="price mb-0">$<?= number_format($producto['precio'], 2) ?></h3>
                        <span class="badge bg-<?= ($producto['existencia'] > 0) ? 'success' : 'danger' ?>">
                            <?= ($producto['existencia'] > 0) ? 'Disponible' : 'Agotado' ?>
                        </span>
                    </div>

                    <div class="description mb-4">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Descripción</h5>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>
                    </div>

                    <?php if (count($descuentos) > 0): ?>
                        <div class="discounts mb-4">
                            <h5 class="mb-3"><i class="fas fa-tag me-2"></i>Descuentos disponibles</h5>
                            <div class="list-group">
                                <?php foreach ($descuentos as $descuento): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($descuento['descripcion']) ?></strong>
                                            <span class="badge bg-success"><?= $descuento['porcentaje_descuento'] ?>% OFF</span>
                                        </div>
                                        <small class="text-muted">
                                            Válido del <?= date('d/m/Y', strtotime($descuento['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime($descuento['fecha_fin'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="actions mt-4">
                        <form id="add-to-cart-form" class="mb-3">
                            <div class="input-group">
                                <input type="number" name="cantidad" class="form-control" min="1" max="<?= $producto['existencia'] ?>" value="1" required>
                                <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                                <button class="btn btn-black-white agregar-carrito" type="button"
                                    data-id="<?= $producto['id'] ?>"
                                    <?= ($producto['existencia'] <= 0) ? 'disabled' : '' ?>>
                                    <i class="fas fa-cart-plus me-2"></i>Agregar al carrito
                                </button>
                            </div>
                        </form>
                        <a href="principal.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver a la tienda
                        </a>
                        <a href="para_ti.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Más para ti
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.agregar-carrito').forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                let productoId = this.getAttribute('data-id');
                let cantidad = this.closest('.input-group').querySelector('input[name="cantidad"]').value;

                fetch('agregar_carrito.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'producto_id=' + productoId + '&cantidad=' + cantidad
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar badge del carrito
                            let badge = document.querySelector('.nav-link[href="carrito.php"] .badge');
                            if (!badge) {
                                let misPedidos = document.querySelector('.nav-link[href="carrito.php"]');
                                let newBadge = document.createElement('span');
                                newBadge.className = "badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle";
                                newBadge.innerText = data.nueva_cantidad;
                                misPedidos.appendChild(newBadge);
                            } else {
                                badge.innerText = data.nueva_cantidad;
                            }

                            // Mostrar mensaje de éxito
                            alert('Producto agregado al carrito correctamente');
                        } else {
                            alert('Error: ' + (data.message || 'No se pudo agregar el producto al carrito'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Ocurrió un error al agregar el producto al carrito');
                    });
            });
        });
    </script>
</body>

</html>