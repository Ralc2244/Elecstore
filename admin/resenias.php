<?php
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

// Manejar acciones de ocultar/mostrar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion_producto'])) {
    $producto_id = intval($_POST['producto_id']);
    $accion = $_POST['accion_producto'];

    if ($accion == 'ocultar') {
        $stmt = $mysqli->prepare("UPDATE productos SET oculto = TRUE WHERE id = ?");
        $mensaje = "Producto ocultado del catálogo exitosamente";
    } elseif ($accion == 'mostrar') {
        $stmt = $mysqli->prepare("UPDATE productos SET oculto = FALSE WHERE id = ?");
        $mensaje = "Producto vuelto a mostrar en el catálogo exitosamente";
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $producto_id);
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = $mensaje;
        } else {
            $_SESSION['admin_error'] = "Error al actualizar el producto";
        }
        $stmt->close();
        header("Location: resenias.php");
        exit();
    }
}

// Obtener estadísticas de comentarios con información de visibilidad
$comentarios_stats = $mysqli->query("
    SELECT 
        p.id,
        p.nombre,
        p.oculto,
        COUNT(c.id) as total_comentarios,
        SUM(c.sentimiento = 'positivo') as positivos,
        SUM(c.sentimiento = 'negativo') as negativos,
        SUM(c.sentimiento = 'neutral') as neutrales
    FROM productos p
    LEFT JOIN comentarios c ON c.producto_id = p.id
    GROUP BY p.id
    ORDER BY total_comentarios DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Obtener los últimos 20 comentarios
$comentarios = $mysqli->query("
    SELECT c.*, p.nombre as producto_nombre, p.oculto, u.nombre as usuario_nombre
    FROM comentarios c
    JOIN productos p ON c.producto_id = p.id
    JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.fecha DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Identificar productos problemáticos (más del 60% de reseñas negativas y al menos 5 reseñas)
$productos_problematicos = array_filter($comentarios_stats, function ($prod) {
    $total = $prod['total_comentarios'];
    $negativos = $prod['negativos'];
    return $total > 5 && ($negativos / $total) > 0.6 && !$prod['oculto'];
});

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis de Reseñas - ELECSTORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        :root {
            --positive-color: #28a745;
            --neutral-color: #ffc107;
            --negative-color: #dc3545;
        }

        .sentiment-card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .sentiment-card:hover {
            transform: translateY(-5px);
        }

        .positive-bg {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--positive-color);
        }

        .neutral-bg {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--neutral-color);
        }

        .negative-bg {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--negative-color);
        }

        .product-card {
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .product-hidden {
            background-color: #f8f9fa;
            opacity: 0.9;
        }

        .progress-thin {
            height: 8px;
        }

        .comment-card {
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 15px;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .sentiment-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .highlight-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
        }

        .alert-products {
            border-left: 4px solid #dc3545;
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
                        <a class="nav-link active" href="resenias.php">
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

        <!-- Alerta para productos problemáticos -->
        <?php if (!empty($productos_problematicos)): ?>
            <div class="alert alert-warning alert-dismissible fade show alert-products" role="alert">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Productos con malas reseñas</h5>
                <p>Los siguientes productos tienen un alto porcentaje de reseñas negativas:</p>
                <ul class="mb-3">
                    <?php foreach ($productos_problematicos as $prod): ?>
                        <li>
                            <strong><?= htmlspecialchars($prod['nombre']) ?></strong> -
                            <?= round(($prod['negativos'] / $prod['total_comentarios']) * 100) ?>% negativas
                            (<?= $prod['negativos'] ?> de <?= $prod['total_comentarios'] ?> reseñas)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="mb-0"><i class="fas fa-lightbulb me-1"></i> Considera revisar estos productos y ocultarlos temporalmente del catálogo si es necesario.</p>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="mb-0">Análisis de Reseñas</h1>
                    <span class="badge bg-primary">
                        <i class="fas fa-comments me-1"></i>
                        <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) ?> comentarios totales
                    </span>
                </div>
                <p class="text-muted mb-0">Análisis de sentimiento de los productos más comentados</p>
            </div>
        </div>

        <!-- Resumen de sentimientos -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card h-100 sentiment-card positive-bg">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Comentarios Positivos</h6>
                                <h2 class="mb-0 text-success"><?= array_sum(array_column($comentarios_stats, 'positivos')) ?></h2>
                            </div>
                            <span class="display-4 text-success opacity-25">
                                <i class="fas fa-smile"></i>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-success" role="progressbar"
                                    style="width: <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                                        (array_sum(array_column($comentarios_stats, 'positivos')) / array_sum(array_column($comentarios_stats, 'total_comentarios'))) * 100 : 0 ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                    round(array_sum(array_column($comentarios_stats, 'positivos')) / array_sum(array_column($comentarios_stats, 'total_comentarios')) * 100, 1) : 0 ?>% del total
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card h-100 sentiment-card neutral-bg">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Comentarios Neutrales</h6>
                                <h2 class="mb-0 text-warning"><?= array_sum(array_column($comentarios_stats, 'neutrales')) ?></h2>
                            </div>
                            <span class="display-4 text-warning opacity-25">
                                <i class="fas fa-meh"></i>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                                        (array_sum(array_column($comentarios_stats, 'neutrales')) / array_sum(array_column($comentarios_stats, 'total_comentarios'))) * 100 : 0 ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                    round((array_sum(array_column($comentarios_stats, 'neutrales')) / array_sum(array_column($comentarios_stats, 'total_comentarios'))) * 100, 1) : 0 ?>% del total
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card h-100 sentiment-card negative-bg">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Comentarios Negativos</h6>
                                <h2 class="mb-0 text-danger"><?= array_sum(array_column($comentarios_stats, 'negativos')) ?></h2>
                            </div>
                            <span class="display-4 text-danger opacity-25">
                                <i class="fas fa-frown"></i>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-danger" role="progressbar"
                                    style="width: <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                                        (array_sum(array_column($comentarios_stats, 'negativos')) / array_sum(array_column($comentarios_stats, 'total_comentarios'))) * 100 : 0 ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= array_sum(array_column($comentarios_stats, 'total_comentarios')) > 0 ?
                                    round((array_sum(array_column($comentarios_stats, 'negativos')) / array_sum(array_column($comentarios_stats, 'total_comentarios'))) * 100, 1) : 0 ?>% del total
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico y análisis de sentimiento -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Distribución de Sentimientos</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="comentariosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i> Productos Destacados</h5>
                    </div>
                    <div class="card-body">
                        <div class="highlight-card p-3 mb-3 rounded">
                            <h6><i class="fas fa-crown text-warning me-2"></i> Mejor Evaluado</h6>
                            <?php
                            $mejor_evaluado = null;
                            $max_ratio = 0;
                            foreach ($comentarios_stats as $prod) {
                                if ($prod['total_comentarios'] > 0) {
                                    $ratio = $prod['positivos'] / $prod['total_comentarios'];
                                    if ($ratio > $max_ratio) {
                                        $max_ratio = $ratio;
                                        $mejor_evaluado = $prod;
                                    }
                                }
                            }
                            ?>
                            <p class="mb-1"><strong><?= $mejor_evaluado ? htmlspecialchars($mejor_evaluado['nombre']) : 'N/A' ?></strong></p>
                            <small class="text-muted">
                                <?= $mejor_evaluado ? round($max_ratio * 100, 1) : '0' ?>% positivos
                                (<?= $mejor_evaluado ? $mejor_evaluado['positivos'] : '0' ?> de <?= $mejor_evaluado ? $mejor_evaluado['total_comentarios'] : '0' ?>)
                            </small>
                        </div>

                        <div class="highlight-card p-3 rounded">
                            <h6><i class="fas fa-exclamation-triangle text-danger me-2"></i> Peor Evaluado</h6>
                            <?php
                            $peor_evaluado = null;
                            $max_neg_ratio = 0;
                            foreach ($comentarios_stats as $prod) {
                                if ($prod['total_comentarios'] > 5) { // Mínimo 5 comentarios para considerar
                                    $ratio = $prod['negativos'] / $prod['total_comentarios'];
                                    if ($ratio > $max_neg_ratio) {
                                        $max_neg_ratio = $ratio;
                                        $peor_evaluado = $prod;
                                    }
                                }
                            }
                            ?>
                            <p class="mb-1"><strong><?= $peor_evaluado ? htmlspecialchars($peor_evaluado['nombre']) : 'N/A' ?></strong></p>
                            <small class="text-muted">
                                <?= $peor_evaluado ? round($max_neg_ratio * 100, 1) : '0' ?>% negativos
                                (<?= $peor_evaluado ? $peor_evaluado['negativos'] : '0' ?> de <?= $peor_evaluado ? $peor_evaluado['total_comentarios'] : '0' ?>)
                            </small>
                            <?php if ($peor_evaluado && $max_neg_ratio > 0.6): ?>
                                <span class="badge bg-danger mt-2">
                                    <i class="fas fa-exclamation-circle me-1"></i> Necesita atención
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Análisis por producto -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box-open me-2"></i> Análisis por Producto</h5>
                <span class="badge bg-secondary">
                    <i class="fas fa-eye-slash me-1"></i> Ocultos: <?= count(array_filter($comentarios_stats, fn($p) => $p['oculto'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($comentarios_stats as $prod):
                        $total = $prod['total_comentarios'];
                        $positivos = $prod['positivos'];
                        $neutrales = $prod['neutrales'];
                        $negativos = $prod['negativos'];
                        $neg_ratio = $total > 0 ? $negativos / $total : 0;
                    ?>
                        <div class="col-md-6 mb-3">
                            <div class="product-card <?= $prod['oculto'] ? 'product-hidden' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-0">
                                        <?= htmlspecialchars($prod['nombre']) ?>
                                        <?php if ($prod['oculto']): ?>
                                            <span class="badge bg-secondary ms-2"><i class="fas fa-eye-slash"></i> Oculto</span>
                                        <?php endif; ?>
                                    </h5>
                                    <span class="badge bg-primary"><?= $total ?> comentarios</span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <span class="text-success me-2">
                                            <i class="fas fa-thumbs-up"></i> <?= $positivos ?>
                                        </span>
                                        <span class="text-warning me-2">
                                            <i class="fas fa-meh"></i> <?= $neutrales ?>
                                        </span>
                                        <span class="text-danger">
                                            <i class="fas fa-thumbs-down"></i> <?= $negativos ?>
                                        </span>
                                    </div>
                                    <?php if ($neg_ratio > 0.6 && $total > 5): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Problemas
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?= $total > 0 ? ($positivos / $total) * 100 : 0 ?>%"></div>
                                    <div class="progress-bar bg-warning" role="progressbar"
                                        style="width: <?= $total > 0 ? ($neutrales / $total) * 100 : 0 ?>%"></div>
                                    <div class="progress-bar bg-danger" role="progressbar"
                                        style="width: <?= $total > 0 ? ($negativos / $total) * 100 : 0 ?>%"></div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?= $total > 0 ? round(($positivos / $total) * 100, 1) : 0 ?>% positivos
                                    </small>

                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="producto_id" value="<?= $prod['id'] ?>">
                                        <?php if ($prod['oculto']): ?>
                                            <button type="submit" name="accion_producto" value="mostrar"
                                                class="btn btn-sm btn-success">
                                                <i class="fas fa-eye me-1"></i> Mostrar
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="accion_producto" value="ocultar"
                                                class="btn btn-sm btn-warning">
                                                <i class="fas fa-eye-slash me-1"></i> Ocultar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Comentarios recientes -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i> Comentarios Recientes</h5>
                <span class="badge bg-primary">
                    <?= count($comentarios) ?> comentarios
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($comentarios)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                        <h4>No hay comentarios registrados</h4>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($comentarios as $comentario):
                            $sentiment_class = '';
                            $sentiment_icon = '';
                            if ($comentario['sentimiento'] == 'positivo') {
                                $sentiment_class = 'border-success';
                                $sentiment_icon = 'fa-smile text-success';
                            } elseif ($comentario['sentimiento'] == 'negativo') {
                                $sentiment_class = 'border-danger';
                                $sentiment_icon = 'fa-frown text-danger';
                            } else {
                                $sentiment_class = 'border-warning';
                                $sentiment_icon = 'fa-meh text-warning';
                            }
                        ?>
                            <div class="list-group-item <?= $sentiment_class ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($comentario['producto_nombre']) ?>
                                            <?php if ($comentario['oculto']): ?>
                                                <span class="badge bg-secondary ms-2"><i class="fas fa-eye-slash"></i></span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">Por <?= htmlspecialchars($comentario['usuario_nombre']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas <?= $sentiment_icon ?> me-2"></i>
                                        <small><?= date('d/m/Y H:i', strtotime($comentario['fecha'])) ?></small>
                                    </div>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($comentario['comentario']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de donut para sentimientos
        const ctx = document.getElementById('comentariosChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Positivos', 'Neutrales', 'Negativos'],
                datasets: [{
                    data: [
                        <?= array_sum(array_column($comentarios_stats, 'positivos')) ?>,
                        <?= array_sum(array_column($comentarios_stats, 'neutrales')) ?>,
                        <?= array_sum(array_column($comentarios_stats, 'negativos')) ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    </script>
</body>

</html>
<?php $mysqli->close(); ?>