<?php

// Si el usuario ya ha iniciado sesión, redirigirlo a la página principal
if (isset($_SESSION['usuario_id'])) {
    header("Location: principal.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="inicio-sesion">
        <h2>Iniciar Sesión</h2>
        <form action="login2.php" method="POST">
            <div class="input-group">
                <label for="email">Correo Institucional:</label>
                <input type="email" name="email" id="email" placeholder="a12345678@ceti.mx" required>
            </div>
            <div class="input-group">
                <label for="contrasenia">Contraseña:</label>
                <input type="password" name="contrasenia" id="contrasenia" placeholder="8 caracteres" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
        <p class="no-cuenta">¿No tienes cuenta? <a href="cuenta.php"><strong>Regístrate aquí</strong></a></p>
    </div>
</body>
</html>