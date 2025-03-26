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

// Procesar el formulario de comentarios
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["producto_id"], $_POST["comentario"])) {
    $producto_id = $_POST["producto_id"];
    $comentario = trim($_POST["comentario"]);

    if (!empty($comentario)) {
        $stmt = $mysqli->prepare("INSERT INTO comentarios (usuario_id, producto_id, comentario) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $usuario_id, $producto_id, $comentario);

        if ($stmt->execute()) {
            $mensaje_exito = "✅ ¡Comentario enviado con éxito!";
        } else {
            $mensaje_error = "❌ Error al enviar el comentario.";
        }
        $stmt->close();
    }
}

// Obtener los productos comprados por el usuario
$query = "SELECT p.id, p.nombre, p.precio, p.ruta_imagen
          FROM compras c
          INNER JOIN productos p ON c.producto_id = p.id
          LEFT JOIN comentarios com ON p.id = com.producto_id AND com.usuario_id = ?
          WHERE c.usuario_id = ? AND com.id IS NULL
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
    <title>Comentarios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="comentarios.css">
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
    <h2 class="mb-4">Mis Comentarios</h2>

    <?php if (isset($mensaje_exito)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
    <?php elseif (isset($mensaje_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
    <?php endif; ?>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card">
                    <img src="imagenes/<?= htmlspecialchars($row['ruta_imagen']); ?>" class="card-img-top" alt="<?= htmlspecialchars($row['nombre']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['nombre']); ?></h5>
                        <p class="card-text">Precio: $<?= number_format($row['precio'], 2); ?> MXN</p>
                        
                        <!-- Formulario de Comentarios -->
                        <form method="post">
                            <input type="hidden" name="producto_id" value="<?= $row['id']; ?>">
                            <div class="mb-3">
                                <textarea name="comentario" class="form-control" placeholder="¿Qué opinas de este producto?..." required></textarea>
                            </div>
                            <button type="submit" class="btn-agregar">Enviar Comentario</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>

<?php
$mysqli->close();
?>
