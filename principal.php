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
    // Sanitize the search term before using it in the query
    $busqueda = $mysqli->real_escape_string($busqueda);  // Evitar inyecciones SQL

    // Guardar la búsqueda en la base de datos
    $usuario_id = $_SESSION['usuario_id'];  // Obtener el ID del usuario desde la sesión
    $sql_insert_busqueda = "INSERT INTO busquedas (usuario_id, termino_busqueda) VALUES (?, ?)";
    $stmt = $mysqli->prepare($sql_insert_busqueda);
    $stmt->bind_param("is", $usuario_id, $busqueda);  // Enlazar parámetros
    $stmt->execute();
    $stmt->close();
}

// Consulta de productos con búsqueda flexible
$sql_productos = "SELECT DISTINCT productos.id, productos.nombre, productos.descripcion, productos.precio, 
productos.existencia, productos.ruta_imagen
    FROM productos 
    LEFT JOIN productotienecategoria 
    ON productos.id = productotienecategoria.producto_id
    WHERE 1=1";

// Filtrar por categoría si se seleccionó alguna
if ($categoria_seleccionada) {
    $sql_productos .= " AND productotienecategoria.categoria_id = $categoria_seleccionada";
}

// Filtrar por término de búsqueda
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
    <title>Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="principal2.css">
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
                    <form class="d-flex" method="GET" action="principal.php">
                        <input class="form-control me-2" type="search" name="q" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-outline-light" type="submit">Buscar</button>
                    </form>
                </div>
            </div>
        </nav>
    </header>

    <?php
    if (isset($_SESSION['mensaje_qr'])) {
        echo "<script>alert('" . $_SESSION['mensaje_qr'] . "');</script>";
        unset($_SESSION['mensaje_qr']);
    }
    ?>

    <main class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <h4>Categorías</h4>
                <ul class="categorias-container">
                    <li class="list-group-item"><a href="principal.php" class="text-decoration-none">Todas</a></li>
                    <?php foreach ($categorias as $categoria): ?>
                        <li class="list-group-item">
                            <a href="principal.php?categoria_id=<?php echo $categoria['id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-9">
                <?php if (!empty($busqueda)): ?>
                    <h4>Resultados de búsqueda: "<?php echo htmlspecialchars($busqueda); ?>"</h4>
                <?php endif; ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                    <?php if (empty($productos)): ?>
                        <p class="text-muted">No se encontraron productos.</p>
                    <?php else: ?>
                        <?php foreach ($productos as $producto): ?>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <img src="<?php echo htmlspecialchars($producto['ruta_imagen']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                        <p class="card-text">Precio: $<?php echo number_format($producto['precio'], 2); ?></p>
                                        <p class="card-text">Stock disponible: <span id="existencia-<?php echo $producto['id']; ?>"><?php echo $producto['existencia']; ?></span></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group">
                                                <a href="detalles.php?id=<?php echo $producto['id']; ?>" class="btn btn-ver-detalles">Ver Detalles</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>

</html>