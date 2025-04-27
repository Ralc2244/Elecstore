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

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = htmlspecialchars($_SESSION['nombre']);
$email_usuario = $_SESSION['email']; // Asegúrate de tener el correo almacenado en la sesión

// Obtener los datos del usuario desde la base de datos
$query = "SELECT nombre, email FROM usuarios WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($nombre, $email);
$stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['eliminar'])) {
        // Eliminar la cuenta del usuario
        $delete_query = "DELETE FROM usuarios WHERE id = ?";
        $delete_stmt = $mysqli->prepare($delete_query);
        $delete_stmt->bind_param("i", $usuario_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Cerrar sesión y redirigir
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="perfil_usuario.css">
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="principal.php">Elecstore</a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" href="principal.php">Principal</a></li>
                        <li class="nav-item"><a class="nav-link" href="carrito.php">Mis Pedidos</a></li>
                        <li class="nav-item"><a class="nav-link" href="historial.php">Mis compras</a></li>
                        <li class="nav-item"><a class="nav-link" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link" href="perfil_usuario.php">Mi perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container mt-4">
        <h3>Mi Perfil</h3>
        <p><strong>Nombre de Usuario:</strong> <?php echo $nombre; ?></p>
        <p><strong>Correo Electrónico:</strong> <?php echo $email; ?></p>

        <form method="POST" action="">
            <button type="submit" name="eliminar" class="btn btn-danger">Eliminar Cuenta</button>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>