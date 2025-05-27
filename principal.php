<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header("Location: login.php");
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre']);

// Obtener categorías
$categorias = [];
$resultado_categorias = $mysqli->query("SELECT id, nombre FROM categorias");

if ($resultado_categorias) {
    $categorias = $resultado_categorias->fetch_all(MYSQLI_ASSOC);
}

// Filtrar productos por categoría o búsqueda
$categoria_seleccionada = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : "";

if (!empty($busqueda)) {
    $busqueda_limpia = $mysqli->real_escape_string($busqueda);
    $usuario_id = $_SESSION['usuario_id'];

    // Buscar un solo producto coincidente
    $query_producto = "SELECT id FROM productos 
                       WHERE (nombre LIKE '%$busqueda_limpia%' OR descripcion LIKE '%$busqueda_limpia%')
                       AND oculto = FALSE 
                       LIMIT 1";
    $resultado = $mysqli->query($query_producto);

    $producto_id = null;
    if ($resultado && $row = $resultado->fetch_assoc()) {
        $producto_id = $row['id'];
    }

    // Insertar solo una vez la búsqueda (aunque no haya productos encontrados)
    $stmt = $mysqli->prepare("INSERT INTO busquedas_usuarios (usuario_id, producto_id, termino_busqueda) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $usuario_id, $producto_id, $busqueda_limpia);
    $stmt->execute();
    $stmt->close();
}

// Consulta de productos - MODIFICADA PARA FILTRAR PRODUCTOS OCULTOS
$sql_productos = "SELECT DISTINCT productos.id, productos.nombre, productos.descripcion, productos.precio, 
productos.existencia, productos.ruta_imagen
    FROM productos 
    LEFT JOIN productotienecategoria 
    ON productos.id = productotienecategoria.producto_id
    WHERE productos.oculto = FALSE";  // Solo productos no ocultos

if ($categoria_seleccionada) {
    $sql_productos .= " AND productotienecategoria.categoria_id = $categoria_seleccionada";
}

if (!empty($busqueda)) {
    $sql_productos .= " AND (productos.nombre LIKE '%$busqueda%' OR productos.descripcion LIKE '%$busqueda%')";
}

$resultado_productos = $mysqli->query($sql_productos);

if ($resultado_productos) {
    $productos = $resultado_productos->fetch_all(MYSQLI_ASSOC);
} else {
    die("Error en la consulta de productos: " . $mysqli->error);
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/principal.css">
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

        .btn-outline-light:hover {
            color: #000 !important;
            background-color: #fff;
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
                        <li class="nav-item"><a class="nav-link active" href="principal.php">Principal</a></li>
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
                    <form class="d-flex ms-3" method="GET" action="principal.php">
                        <div class="input-group">
                            <input class="form-control" type="search" name="q" placeholder="Buscar productos..."
                                value="<?= htmlspecialchars($busqueda) ?>">
                            <button class="btn btn-outline-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </nav>
    </header>

    <?php if (isset($_SESSION['mensaje_qr'])): ?>
        <div class="alert alert-info alert-dismissible fade show text-center">
            <?= htmlspecialchars($_SESSION['mensaje_qr']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensaje_qr']); ?>
    <?php endif; ?>

    <main class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h4 class="mb-0"><i class="fas fa-list me-2"></i>Categorías</h4>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <a href="principal.php" class="text-decoration-none d-block py-2">
                                    <i class="fas fa-boxes me-2"></i>Todas las categorías
                                </a>
                            </li>
                            <?php foreach ($categorias as $categoria): ?>
                                <li class="list-group-item">
                                    <a href="principal.php?categoria_id=<?= $categoria['id'] ?>"
                                        class="text-decoration-none d-block py-2">
                                        <i class="fas fa-folder me-2"></i><?= htmlspecialchars($categoria['nombre']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php if (!empty($busqueda)): ?>
                    <div class="alert alert-info mb-4">
                        <h4 class="alert-heading">Resultados de búsqueda</h4>
                        <p>Mostrando productos para: <strong>"<?= htmlspecialchars($busqueda) ?>"</strong></p>
                        <a href="principal.php" class="btn btn-sm btn-outline-secondary">Ver todos los productos</a>
                    </div>
                <?php endif; ?>

                <?php if (empty($productos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                        <h3>No se encontraron productos</h3>
                        <p class="text-muted">Intenta con otra búsqueda o categoría</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                        <?php foreach ($productos as $producto): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <div class="product-image-container">
                                        <img src="<?= htmlspecialchars($producto['ruta_imagen']) ?>"
                                            class="card-img-top"
                                            alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                            loading="lazy">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                        <p class="card-text text-truncate"><?= htmlspecialchars($producto['descripcion']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="price fw-bold">$<?= number_format($producto['precio'], 2) ?></span>
                                            <span class="badge bg-<?= ($producto['existencia'] > 0) ? 'success' : 'danger' ?>">
                                                <?= ($producto['existencia'] > 0) ? 'Disponible' : 'Agotado' ?>
                                            </span>
                                        </div>
                                        <a href="detalles.php?id=<?= $producto['id'] ?>" class="btn btn-black-white w-100">
                                            <i class="fas fa-eye me-2"></i>Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Eliminar mensajes de alerta después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>

</html>