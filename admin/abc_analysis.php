<?php
session_start();

// Configuración de la base de datos
$mysqli = new mysqli("localhost", "root", "", "elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: login_admin.php');
    exit();
}

// Obtener parámetros de fecha (opcional)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-3 months'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Verificar productos que necesitan reabastecimiento
$query_reabastecer = "SELECT p.id, p.nombre, p.existencia, 
                     SUM(c.cantidad) as cantidad_vendida,
                     SUM(c.total) as total_vendido
                     FROM productos p
                     LEFT JOIN compras c ON c.producto_id = p.id AND c.estado_pago = 'Pagado'
                     WHERE (p.existencia < 10 AND (SELECT SUM(c2.total) FROM compras c2 WHERE c2.producto_id = p.id AND c2.estado_pago = 'Pagado') > 1000)
                     OR (p.existencia < 5 AND (SELECT SUM(c2.total) FROM compras c2 WHERE c2.producto_id = p.id AND c2.estado_pago = 'Pagado') BETWEEN 500 AND 1000)
                     OR (p.existencia < 2)
                     GROUP BY p.id
                     HAVING cantidad_vendida > 0
                     ORDER BY total_vendido DESC";

if (!empty($productos_reabastecer)) {
    $_SESSION['admin_notification'] = [
        'type' => 'warning',
        'message' => 'Hay ' . count($productos_reabastecer) . ' productos que necesitan reabastecimiento',
        'link' => 'abc_analysis.php?categoria=A'
    ];
}

$result_reabastecer = $mysqli->query($query_reabastecer);
$productos_reabastecer = $result_reabastecer->fetch_all(MYSQLI_ASSOC);

if (!empty($productos_reabastecer)) {
    $_SESSION['admin_notification'] = [
        'type' => 'warning',
        'message' => 'Hay ' . count($productos_reabastecer) . ' productos que necesitan reabastecimiento',
        'link' => 'abc_analysis.php?categoria=A'
    ];
}

// Consulta para obtener rotación de productos
$query = "SELECT 
    p.id,
    p.nombre,
    p.precio,
    p.existencia,
    SUM(c.cantidad) as cantidad_vendida,
    SUM(c.total) as total_vendido
FROM productos p
JOIN compras c ON c.producto_id = p.id
WHERE c.estado_pago = 'Pagado'
AND DATE(c.fecha) BETWEEN ? AND ?
GROUP BY p.id
ORDER BY total_vendido DESC";

$stmt = $mysqli->prepare($query);  // Cambiado de $conn a $mysqli
if (!$stmt) {
    die("Error al preparar la consulta: " . $mysqli->error);
}

$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
if (!$stmt->execute()) {
    die("Error al ejecutar la consulta: " . $stmt->error);
}

$result = $stmt->get_result();
$productos = [];
$total_ventas = 0;

while ($row = $result->fetch_assoc()) {
    $productos[] = $row;
    $total_ventas += $row['total_vendido'];
}

// Clasificación ABC
$productos_clasificados = [];
$acumulado = 0;

foreach ($productos as $producto) {
    $porcentaje_ventas = ($producto['total_vendido'] / $total_ventas) * 100;
    $acumulado += $porcentaje_ventas;

    if ($acumulado <= 80) {
        $categoria = 'A';
    } elseif ($acumulado <= 95) {
        $categoria = 'B';
    } else {
        $categoria = 'C';
    }

    // Dentro del foreach que clasifica los productos, modificar el array $productos_clasificados:
    $productos_clasificados[] = [
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'precio' => $producto['precio'],
        'existencia' => $producto['existencia'],
        'cantidad_vendida' => $producto['cantidad_vendida'],
        'total_vendido' => $producto['total_vendido'],
        'porcentaje_ventas' => round($porcentaje_ventas, 2),
        'porcentaje_acumulado' => round($acumulado, 2),
        'categoria' => $categoria,
        'necesita_reabastecimiento' => ($categoria == 'A' && $producto['existencia'] < 10) ||
            ($categoria == 'B' && $producto['existencia'] < 5) ||
            ($categoria == 'C' && $producto['existencia'] < 2)
    ];
}

// Filtrar por categoría si se especifica
$categoria_filtro = $_GET['categoria'] ?? '';
if ($categoria_filtro && in_array($categoria_filtro, ['A', 'B', 'C'])) {
    $productos_clasificados = array_filter($productos_clasificados, function ($p) use ($categoria_filtro) {
        return $p['categoria'] == $categoria_filtro;
    });
}

// Calcular estadísticas por categoría
$stats = [
    'A' => ['count' => 0, 'total_ventas' => 0, 'porcentaje_ventas' => 0],
    'B' => ['count' => 0, 'total_ventas' => 0, 'porcentaje_ventas' => 0],
    'C' => ['count' => 0, 'total_ventas' => 0, 'porcentaje_ventas' => 0]
];

foreach ($productos_clasificados as $p) {
    $stats[$p['categoria']]['count']++;
    $stats[$p['categoria']]['total_ventas'] += $p['total_vendido'];
}

foreach ($stats as &$s) {
    if ($total_ventas > 0) {
        $s['porcentaje_ventas'] = round(($s['total_ventas'] / $total_ventas) * 100, 2);
    }
}

// Cerrar la conexión al final del script
$stmt->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis ABC de Productos | Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-category {
            border-left: 5px solid;
        }

        .card-category.A {
            border-left-color: #dc3545;
        }

        .card-category.B {
            border-left-color: #ffc107;
        }

        .card-category.C {
            border-left-color: #28a745;
        }

        .badge-category {
            font-size: 0.9em;
            padding: 0.35em 0.65em;
        }

        .badge-category.A {
            background-color: #dc3545;
        }

        .badge-category.B {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-category.C {
            background-color: #28a745;
        }

        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }

        .needs-restock {
            background-color: #fff3cd;
        }
    </style>
</head>

<body>
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
        <h1 class="mb-4">Análisis ABC de Productos</h1>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="categoria" class="form-label">Categoría</label>
                        <select name="categoria" class="form-select">
                            <option value="">Todas</option>
                            <option value="A" <?= $categoria_filtro == 'A' ? 'selected' : '' ?>>A (Alta rotación)</option>
                            <option value="B" <?= $categoria_filtro == 'B' ? 'selected' : '' ?>>B (Media rotación)</option>
                            <option value="C" <?= $categoria_filtro == 'C' ? 'selected' : '' ?>>C (Baja rotación)</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-category A">
                    <div class="card-body">
                        <h5 class="card-title">Productos Categoría A</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0"><?= $stats['A']['count'] ?></h2>
                            <span class="badge badge-category A">Alta Rotación</span>
                        </div>
                        <p class="mb-0 mt-2">
                            <i class="fas fa-chart-line me-2"></i>
                            <?= $stats['A']['porcentaje_ventas'] ?>% de las ventas
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-category B">
                    <div class="card-body">
                        <h5 class="card-title">Productos Categoría B</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0"><?= $stats['B']['count'] ?></h2>
                            <span class="badge badge-category B">Media Rotación</span>
                        </div>
                        <p class="mb-0 mt-2">
                            <i class="fas fa-chart-line me-2"></i>
                            <?= $stats['B']['porcentaje_ventas'] ?>% de las ventas
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-category C">
                    <div class="card-body">
                        <h5 class="card-title">Productos Categoría C</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0"><?= $stats['C']['count'] ?></h2>
                            <span class="badge badge-category C">Baja Rotación</span>
                        </div>
                        <p class="mb-0 mt-2">
                            <i class="fas fa-chart-line me-2"></i>
                            <?= $stats['C']['porcentaje_ventas'] ?>% de las ventas
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico ABC -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribución ABC de Ventas</h5>
            </div>
            <div class="card-body">
                <div style="width: 87%; margin: 0 auto;"> <!-- Ajusta el ancho aquí -->
                    <canvas id="abcChart" height="250"></canvas> <!-- Ajusta la altura aquí -->
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Productos</h5>
                <span class="badge bg-primary">
                    <?= count($productos_clasificados) ?> productos encontrados
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Existencia</th>
                                <th class="text-end">Ventas (uds)</th>
                                <th class="text-end">Ventas ($)</th>
                                <th class="text-end">% Ventas</th>
                                <th class="text-end">% Acum.</th>
                                <th>Categoría</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_clasificados as $producto): ?>
                                <tr class="<?= $producto['necesita_reabastecimiento'] ? 'needs-restock' : '' ?>">
                                    <td><?= $producto['id'] ?></td>
                                    <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                    <td class="text-end">$<?= number_format($producto['precio'], 2) ?></td>
                                    <td class="text-end"><?= $producto['existencia'] ?></td>
                                    <td class="text-end"><?= $producto['cantidad_vendida'] ?></td>
                                    <td class="text-end">$<?= number_format($producto['total_vendido'], 2) ?></td>
                                    <td class="text-end"><?= $producto['porcentaje_ventas'] ?>%</td>
                                    <td class="text-end"><?= $producto['porcentaje_acumulado'] ?>%</td>
                                    <td>
                                        <span class="badge badge-category <?= $producto['categoria'] ?>">
                                            <?= $producto['categoria'] ?>
                                        </span>
                                        <?php if ($producto['necesita_reabastecimiento']): ?>
                                            <span class="badge bg-danger ms-1">
                                                <i class="fas fa-exclamation-circle"></i> Reabastecer
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Gráfico ABC
        const ctx = document.getElementById('abcChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Categoría A (Alta rotación)', 'Categoría B (Media rotación)', 'Categoría C (Baja rotación)'],
                datasets: [{
                    data: [<?= $stats['A']['porcentaje_ventas'] ?>, <?= $stats['B']['porcentaje_ventas'] ?>, <?= $stats['C']['porcentaje_ventas'] ?>],
                    backgroundColor: [
                        '#dc3545',
                        '#ffc107',
                        '#28a745'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Permite controlar manualmente la relación de aspecto
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 30, // Hace más pequeños los cuadros de la leyenda
                            font: {
                                size: 20 // Tamaño de fuente más pequeño
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw}% de las ventas`;
                            }
                        },
                        bodyFont: {
                            size: 10 // Tamaño de fuente más pequeño en tooltips
                        }
                    }
                },
                cutout: '65%' // Hace el agujero del donut más grande, haciendo el gráfico más delgado
            }
        });
    </script>
</body>

</html>