<?php
session_start();
$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['login_redirect'] = 'para_ti.php';
    header('Location: login.php?redirect=para_ti');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Función para obtener recomendaciones
function obtenerRecomendaciones($usuario_id, $limit = 12)
{
    global $mysqli;

    // 1. Obtener términos de búsqueda recientes del usuario
    $query_busquedas = "SELECT DISTINCT termino_busqueda 
                       FROM busquedas_usuarios 
                       WHERE usuario_id = ? 
                       AND termino_busqueda IS NOT NULL
                       ORDER BY fecha DESC 
                       LIMIT 5";
    $stmt = $mysqli->prepare($query_busquedas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $terminos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. Obtener productos más comprados en el semestre actual
    $semestre_actual = (date('n') <= 6) ? 1 : 2;
    $anio_actual = date('Y');

    $query_compras = "SELECT p.id, p.nombre, p.precio, p.descripcion, p.ruta_imagen,
                     SUM(c.cantidad) as total_vendido
                     FROM productos p
                     JOIN compras c ON c.producto_id = p.id
                     WHERE c.estado_pago = 'Pagado'
                     AND p.oculto = FALSE
                     AND (
                         (MONTH(c.fecha) <= 6 AND ? = 1 AND YEAR(c.fecha) = ?) OR
                         (MONTH(c.fecha) > 6 AND ? = 2 AND YEAR(c.fecha) = ?)
                     )
                     GROUP BY p.id
                     ORDER BY total_vendido DESC
                     LIMIT ?";

    $stmt = $mysqli->prepare($query_compras);
    $stmt->bind_param("iiiii", $semestre_actual, $anio_actual, $semestre_actual, $anio_actual, $limit);
    $stmt->execute();
    $productos_populares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Si hay términos de búsqueda, obtener productos relacionados
    $recomendaciones = [];
    if (!empty($terminos)) {
        $terminos_array = array_column($terminos, 'termino_busqueda');

        $query_recomendaciones = "SELECT DISTINCT p.* 
                                FROM productos p
                                WHERE ";

        $conditions = [];
        foreach ($terminos_array as $termino) {
            $conditions[] = "p.nombre LIKE ?";
        }

        $query_recomendaciones .= implode(' OR ', $conditions) . " ORDER BY RAND() LIMIT ?";

        $stmt = $mysqli->prepare($query_recomendaciones);

        // Bind parameters
        $types = str_repeat('s', count($terminos_array)) . 'i';

        // Preparamos los términos para LIKE
        $like_terms = array_map(function ($term) {
            return "%$term%";
        }, $terminos_array);

        $params = array_merge($like_terms, [$limit]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $recomendaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Combinar resultados (70% recomendaciones personalizadas, 30% populares)
    $mezcla_recomendaciones = array_merge(
        array_slice($recomendaciones, 0, ceil($limit * 0.7)),
        array_slice($productos_populares, 0, floor($limit * 0.3))
    );

    // Si no hay suficientes, completar con populares
    if (count($mezcla_recomendaciones) < $limit) {
        $mezcla_recomendaciones = array_merge(
            $mezcla_recomendaciones,
            array_slice($productos_populares, count($mezcla_recomendaciones), $limit - count($mezcla_recomendaciones))
        );
    }

    return !empty($mezcla_recomendaciones) ? $mezcla_recomendaciones : $productos_populares;
}

// Obtener categorías populares
$semestre_actual = (date('n') <= 6) ? 1 : 2;
$anio_actual = date('Y');

$query_categorias = "SELECT cat.id, cat.nombre, COUNT(*) as total
                   FROM categorias cat
                   JOIN productotienecategoria pc ON cat.id = pc.categoria_id
                   JOIN productos p ON p.id = pc.producto_id
                   JOIN compras c ON c.producto_id = p.id
                   WHERE c.estado_pago = 'Pagado'
                   AND p.oculto = FALSE
                   AND (
                       (MONTH(c.fecha) <= 6 AND ? = 1 AND YEAR(c.fecha) = ?) OR
                       (MONTH(c.fecha) > 6 AND ? = 2 AND YEAR(c.fecha) = ?)
                   )
                   GROUP BY cat.id
                   ORDER BY total DESC
                   LIMIT 5";

$stmt = $mysqli->prepare($query_categorias);
$stmt->bind_param("iiii", $semestre_actual, $anio_actual, $semestre_actual, $anio_actual);
$stmt->execute();
$categorias_populares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener todas las categorías para el menú
$query_todas_categorias = "SELECT id, nombre FROM categorias";
$todas_categorias = $mysqli->query($query_todas_categorias)->fetch_all(MYSQLI_ASSOC);

// Filtrar por categoría si se seleccionó una
$categoria_seleccionada = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$recomendaciones = obtenerRecomendaciones($usuario_id, 12);

// Si hay una categoría seleccionada, filtrar las recomendaciones
if ($categoria_seleccionada) {
    $query_filtrada = "SELECT p.* FROM productos p
                      JOIN productotienecategoria pc ON p.id = pc.producto_id
                      WHERE pc.categoria_id = ? AND p.id IN (" . implode(',', array_column($recomendaciones, 'id')) . ")";

    $stmt = $mysqli->prepare($query_filtrada);
    $stmt->bind_param("i", $categoria_seleccionada);
    $stmt->execute();
    $recomendaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Para Ti | ElecStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
        }

        .navbar-black {
            background-color: #000 !important;
        }

        .hero-section {
            background-color: #000;
            color: #fff;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .card {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
        }

        .product-img {
            height: 200px;
            object-fit: contain;
            background: #fff;
            padding: 15px;
        }

        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background-color: #000;
        }

        .badge-interest {
            background-color: #e9ecef;
            color: #495057;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .badge-top {
            background-color: #ffc107;
            color: #000;
        }

        .badge-new {
            background-color: #dc3545;
            color: #fff;
        }

        .categoria-activa {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Navbar negro -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-black">
        <div class="container">
            <a class="navbar-brand" href="principal.php">ELECSTORE</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="principal.php">Principal</a></li>
                    <li class="nav-item"><a class="nav-link active" href="para_ti.php">Para ti</a></li>
                    <li class="nav-item"><a class="nav-link" href="carrito.php">Mis Pedidos</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.php">Mis Compras</a></li>
                    <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi Perfil</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">Recomendaciones para ti</h1>
            <p class="lead">Productos seleccionados según tus intereses</p>
            <?php if ($categoria_seleccionada): ?>
                <?php
                $nombre_categoria = '';
                foreach ($todas_categorias as $cat) {
                    if ($cat['id'] == $categoria_seleccionada) {
                        $nombre_categoria = $cat['nombre'];
                        break;
                    }
                }
                ?>
                <div class="mt-3">
                    <span class="badge bg-dark">Filtrado por: <?= htmlspecialchars($nombre_categoria) ?></span>
                    <a href="para_ti.php" class="btn btn-sm btn-outline-light ms-2">Quitar filtro</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        Tus intereses
                    </div>
                    <div class="card-body">
                        <?php
                        $query_intereses = "SELECT DISTINCT termino_busqueda 
                                          FROM busquedas_usuarios 
                                          WHERE usuario_id = ? 
                                          AND termino_busqueda IS NOT NULL
                                          ORDER BY fecha DESC 
                                          LIMIT 8";
                        $stmt = $mysqli->prepare($query_intereses);
                        $stmt->bind_param("i", $usuario_id);
                        $stmt->execute();
                        $intereses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                        if (!empty($intereses)): ?>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($intereses as $interes): ?>
                                    <span class="badge badge-interest rounded-pill mb-2">
                                        <?= htmlspecialchars($interes['termino_busqueda']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Realiza búsquedas para ver tus intereses aquí.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-list me-2"></i>Todas las categorías
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item <?= !$categoria_seleccionada ? 'categoria-activa' : '' ?>">
                                <a href="para_ti.php" class="text-decoration-none d-block py-2">
                                    <i class="fas fa-boxes me-2"></i>Todas las categorías
                                </a>
                            </li>
                            <?php foreach ($todas_categorias as $categoria): ?>
                                <li class="list-group-item <?= $categoria_seleccionada == $categoria['id'] ? 'categoria-activa' : '' ?>">
                                    <a href="para_ti.php?categoria_id=<?= $categoria['id'] ?>"
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
                <h2 class="section-title">
                    <?= $categoria_seleccionada ? 'Productos recomendados en esta categoría' : 'Basado en tus intereses' ?>
                </h2>

                <?php if (!empty($recomendaciones)): ?>
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php foreach ($recomendaciones as $producto): ?>
                            <div class="col">
                                <div class="card h-100">
                                    <?php if (strtotime($producto['fecha_creacion'] ?? '') > strtotime('-1 month')): ?>
                                        <span class="badge badge-new position-absolute top-0 end-0 m-2">Nuevo</span>
                                    <?php endif; ?>
                                    <img src="<?= htmlspecialchars($producto['ruta_imagen']) ?>"
                                        class="card-img-top product-img"
                                        alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                        onerror="this.src='img/productos/default.jpg'">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                        <p class="card-text text-muted"><?= substr(htmlspecialchars($producto['descripcion'] ?? ''), 0, 60) ?>...</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="h5">$<?= number_format($producto['precio'], 2) ?></span>
                                            <a href="detalles.php?id=<?= $producto['id'] ?>" class="btn btn-dark btn-sm">Ver detalles</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-dark">
                        <h4 class="alert-heading">
                            <?= $categoria_seleccionada ? 'No hay recomendaciones en esta categoría' : '¡Aún no tenemos recomendaciones para ti!' ?>
                        </h4>
                        <p>
                            <?= $categoria_seleccionada ?
                                'No encontramos productos recomendados en esta categoría.' :
                                'Comienza a buscar productos para que podamos aprender tus preferencias.' ?>
                        </p>
                        <hr>
                        <a href="<?= $categoria_seleccionada ? 'para_ti.php' : 'principal.php' ?>"
                            class="btn btn-outline-dark">
                            <?= $categoria_seleccionada ? 'Ver todas las categorías' : 'Explorar productos' ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (!$categoria_seleccionada): ?>
                    <div class="mt-5">
                        <h2 class="section-title">Los más populares este semestre</h2>
                        <?php
                        $query_populares = "SELECT p.* 
                                          FROM productos p
                                          JOIN (
                                              SELECT producto_id, SUM(cantidad) as total_vendido
                                              FROM compras
                                              WHERE estado_pago = 'Pagado'
                                              AND (
                                                  (MONTH(fecha) <= 6 AND ? = 1 AND YEAR(fecha) = ?) OR
                                                  (MONTH(fecha) > 6 AND ? = 2 AND YEAR(fecha) = ?)
                                              )
                                              GROUP BY producto_id
                                              ORDER BY total_vendido DESC
                                              LIMIT 6
                                          ) ps ON p.id = ps.producto_id
                                          WHERE p.oculto = FALSE";
                        $stmt = $mysqli->prepare($query_populares);
                        $stmt->bind_param("iiii", $semestre_actual, $anio_actual, $semestre_actual, $anio_actual);
                        $stmt->execute();
                        $populares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                        if (!empty($populares)): ?>
                            <div class="row row-cols-1 row-cols-md-3 g-4">
                                <?php foreach ($populares as $index => $producto): ?>
                                    <div class="col">
                                        <div class="card h-100">
                                            <span class="badge badge-top position-absolute top-0 start-0 m-2">
                                                Top #<?= $index + 1 ?>
                                            </span>
                                            <img src="<?= htmlspecialchars($producto['ruta_imagen']) ?>"
                                                class="card-img-top product-img"
                                                alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                                onerror="this.src='img/productos/default.jpg'">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="h5">$<?= number_format($producto['precio'], 2) ?></span>
                                                    <a href="detalles.php?id=<?= $producto['id'] ?>" class="btn btn-dark btn-sm">Ver detalles</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No hay datos disponibles de productos populares este semestre.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejar imágenes rotas
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img').forEach(img => {
                img.onerror = function() {
                    if (!this.src.includes('default.jpg')) {
                        this.src = 'img/productos/default.jpg';
                    }
                };
            });
        });
    </script>
</body>

</html>