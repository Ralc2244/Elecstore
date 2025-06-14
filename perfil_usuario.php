<?php
session_start();
$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = htmlspecialchars($_SESSION['nombre']);
$email_usuario = $_SESSION['email'];

// Obtener los datos del usuario desde la base de datos
$query = "SELECT nombre, email FROM usuarios WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($nombre, $email);
$stmt->fetch();

// Procesar eliminación de cuenta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['eliminar'])) {
        // Confirmar eliminación
        echo "<script>
                if (confirm('¿Estás seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer.')) {
                    window.location.href = 'eliminar_cuenta.php';
                }
              </script>";
    }

    // Procesar cierre de sesión
    if (isset($_POST['cerrar'])) {
        session_destroy();
        header("Location: login.php");
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
    <title>Mi Perfil | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .profile-card {
            max-width: 600px;
            margin: 0 auto;
        }

        .btn-cerrar {
            background-color: #343a40;
            color: white;
            border: none;
        }

        .btn-cerrar:hover {
            background-color: #23272b;
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
                        <li class="nav-item"><a class="nav-link active" href="perfil_usuario.php">Mi Perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <div class="profile-card card shadow-sm">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0"><i class="fas fa-user me-2"></i>Información del Perfil</h3>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5 class="mb-1"><i class="fas fa-user me-2"></i>Nombre de Usuario</h5>
                    <p class="text-muted"><?= htmlspecialchars($nombre) ?></p>
                </div>

                <div class="mb-4">
                    <h5 class="mb-1"><i class="fas fa-envelope me-2"></i>Correo Electrónico</h5>
                    <p class="text-muted"><?= htmlspecialchars($email) ?></p>
                </div>

                <div class="text-center mt-4">
                    <form method="POST">
                        <button type="submit" name="cerrar" class="btn btn-cerrar">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </div>

                <div class="text-center mt-4">
                    <form method="POST">
                        <button type="submit" name="eliminar" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Eliminar Cuenta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>