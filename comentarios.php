<?php
session_start();
require_once 'vendor/autoload.php'; // Para cargar la librería de NLP

// Configuración de la base de datos
$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Función para analizar sentimiento del comentario (implementación básica)
function analizarSentimiento($comentario)
{
    $comentario = strtolower($comentario);

    $frases_positivas = ['me encantó', 'muy bueno', 'lo recomiendo', 'vale la pena', 'excelente compra'];
    $frases_negativas = ['muy malo', 'no sirve', 'pérdida de dinero', 'no lo recomiendo', 'me arrepiento'];

    $palabras_positivas = ['bueno', 'excelente', 'genial', 'perfecto', 'satisfecho'];
    $palabras_negativas = ['malo', 'horrible', 'defectuoso', 'decepcionante', 'insatisfecho'];

    $score = 0;

    foreach ($frases_positivas as $frase) {
        if (strpos($comentario, $frase) !== false) $score += 2;
    }

    foreach ($frases_negativas as $frase) {
        if (strpos($comentario, $frase) !== false) $score -= 2;
    }

    $words = preg_split('/\s+/', $comentario);
    foreach ($words as $word) {
        if (in_array($word, $palabras_positivas)) $score += 1;
        if (in_array($word, $palabras_negativas)) $score -= 1;
    }

    if ($score > 1) {
        return ['sentimiento' => 'positivo', 'puntuacion' => $score];
    } elseif ($score < -1) {
        return ['sentimiento' => 'negativo', 'puntuacion' => $score];
    } else {
        return ['sentimiento' => 'neutral', 'puntuacion' => $score];
    }
}


// Función para actualizar la clasificación del producto
function actualizarClasificacionProducto($mysqli, $producto_id)
{
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(sentimiento = 'positivo') AS positivos,
            SUM(sentimiento = 'negativo') AS negativos,
            SUM(sentimiento = 'neutral') AS neutrales,
            AVG(puntuacion) AS promedio
        FROM comentarios 
        WHERE producto_id = ?
    ");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $clasificacion = 'Neutral';
    $estado = 'Disponible';

    if ($stats['total'] > 0) {
        $ratio_positivos = $stats['positivos'] / $stats['total'];
        $ratio_negativos = $stats['negativos'] / $stats['total'];

        if ($ratio_positivos > 0.7) {
            $clasificacion = 'Recomendado';
        } elseif ($ratio_negativos > 0.5) {
            $clasificacion = 'No recomendado';
            if ($ratio_negativos > 0.6 && $stats['total'] > 5) {
                $estado = 'No recomendado';
                $mensaje = "El producto ID $producto_id ha recibido demasiados comentarios negativos (" . round($ratio_negativos * 100) . "%)";
                $stmt = $mysqli->prepare("INSERT INTO notificaciones (producto_id, tipo, mensaje) VALUES (?, 'alto_negativos', ?)");
                $stmt->bind_param("is", $producto_id, $mensaje);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $stmt = $mysqli->prepare("
        UPDATE productos 
        SET 
            clasificacion = ?, 
            estado = ?, 
            contador_positivos = ?, 
            contador_negativos = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssiii", $clasificacion, $estado, $stats['positivos'], $stats['negativos'], $producto_id);
    $stmt->execute();
    $stmt->close();
}


// Procesar el formulario de comentarios
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["producto_id"], $_POST["comentario"])) {
    $producto_id = (int)$_POST["producto_id"];
    $comentario = trim($_POST["comentario"]);

    if (!empty($comentario)) {
        // Analizar sentimiento del comentario
        $sentiment = analizarSentimiento($comentario);

        $stmt = $mysqli->prepare("INSERT INTO comentarios (usuario_id, producto_id, comentario, sentimiento, puntuacion) 
                                 VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissd", $usuario_id, $producto_id, $comentario, $sentiment['sentimiento'], $sentiment['puntuacion']);

        if ($stmt->execute()) {
            // Actualizar clasificación del producto
            actualizarClasificacionProducto($mysqli, $producto_id);

            $_SESSION['mensaje_exito'] = "¡Comentario enviado con éxito!";
            header("Location: comentarios.php");
            exit;
        } else {
            $mensaje_error = "Error al enviar el comentario: " . $mysqli->error;
        }
        $stmt->close();
    } else {
        $mensaje_error = "El comentario no puede estar vacío";
    }
}

// Obtener los productos comprados por el usuario que aún no ha comentado
$query = "SELECT p.id, p.nombre, p.precio, p.ruta_imagen, p.descripcion, p.clasificacion, p.estado
          FROM compras c
          INNER JOIN productos p ON c.producto_id = p.id
          LEFT JOIN comentarios com ON p.id = com.producto_id AND com.usuario_id = ?
          WHERE c.usuario_id = ? AND com.id IS NULL AND p.estado != 'Eliminado'
          GROUP BY p.id
          ORDER BY c.fecha DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comentarios | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            text-decoration: underline;
        }

        /* Estilos para las cards de productos */
        .product-card {
            transition: transform 0.3s ease;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .product-image-container {
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }

        .product-image-container img {
            max-height: 100%;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }

        .badge-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
            font-size: 0.8rem;
        }

        .empty-state {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
        }

        .btn-black-white {
            background-color: #000;
            color: white;
            border: 1px solid #000;
        }

        .btn-black-white:hover {
            background-color: white;
            color: #000;
        }

        .alert-auto-close {
            animation: fadeOut 5s forwards;
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                display: none;
            }
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
                                Mis Pedidos
                                <?php if (isset($_SESSION['carrito_cantidad']) && $_SESSION['carrito_cantidad'] > 0): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                                        <?= $_SESSION['carrito_cantidad'] ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="historial.php">Mis Compras</a></li>
                        <li class="nav-item"><a class="nav-link active" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi Perfil</a></li>
                    </ul>

                </div>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <h1 class="mb-4 text-center"><i class="fas fa-comments me-2"></i>Deja tu opinión</h1>

        <?php if ($result->num_rows > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm product-card">
                            <div class="product-image-container position-relative">
                                <img src="<?= htmlspecialchars($row['ruta_imagen']) ?>"
                                    class="card-img-top"
                                    alt="<?= htmlspecialchars($row['nombre']) ?>"
                                    loading="lazy">
                                <?php if ($row['estado'] == 'No recomendado'): ?>
                                    <span class="badge bg-danger badge-tag">No recomendado</span>
                                <?php elseif ($row['clasificacion'] == 'Recomendado'): ?>
                                    <span class="badge bg-success badge-tag">Recomendado</span>
                                <?php elseif ($row['estado'] == 'Disponible' && $row['clasificacion'] != 'Neutral'): ?>
                                    <span class="badge bg-primary badge-tag">Ya disponible</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h3 class="card-title h5"><?= htmlspecialchars($row['nombre']) ?></h3>
                                <p class="text-muted mb-2 flex-grow-1"><?= htmlspecialchars($row['descripcion']) ?></p>
                                <p class="price fw-bold">$<?= number_format($row['precio'], 2) ?> MXN</p>

                                <form method="post" class="mt-3">
                                    <input type="hidden" name="producto_id" value="<?= $row['id'] ?>">
                                    <div class="mb-3">
                                        <label for="comentario-<?= $row['id'] ?>" class="form-label">Tu opinión:</label>
                                        <textarea id="comentario-<?= $row['id'] ?>"
                                            name="comentario"
                                            class="form-control"
                                            rows="3"
                                            placeholder="¿Qué te pareció este producto?..."
                                            required></textarea>
                                        <small class="text-muted">Tu comentario será analizado automáticamente</small>
                                    </div>
                                    <button type="submit" class="btn btn-black-white w-100 mt-auto">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Comentario
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fas fa-comment-slash fa-4x text-muted mb-4"></i>
                    <h2 class="h4">No tienes productos pendientes por comentar</h2>
                    <p class="text-muted">Cuando compres productos nuevos, podrás dejar tus comentarios aquí.</p>
                    <a href="principal.php" class="btn btn-primary mt-3">Seguir comprando</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cerrar automáticamente las alertas después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar alertas con el atributo data-bs-dismiss
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert[data-bs-dismiss]');
                alerts.forEach(alert => {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) closeBtn.click();
                });
            }, 5000);

            // Marcar notificaciones como leídas al hacer clic
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-notification-id');
                    if (notificationId) {
                        fetch('marcar_notificacion.php?id=' + notificationId, {
                            method: 'POST'
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>

<?php
$mysqli->close();
?>