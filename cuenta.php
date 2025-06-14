<?php

$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Crear Cuenta</title>
    <link rel="stylesheet" href="cuenta.css" />
    <script>
        function validarFormulario() {
            let email = document.getElementById("email").value;
            let password = document.getElementById("contrasenia").value;
            let emailRegex = /^a\d{8}@ceti\.mx$/; // Formato "a########@ceti.mx"
            let errorEmail = document.getElementById("error-email");
            let errorPassword = document.getElementById("error-password");

            errorEmail.textContent = "";
            errorPassword.textContent = "";

            if (!emailRegex.test(email)) {
                errorEmail.textContent = "El correo debe tener el formato a########@ceti.mx (Ejemplo: a22300612@ceti.mx).";
                return false;
            }

            if (password.length < 8) {
                errorPassword.textContent = "La contraseña debe tener al menos 8 caracteres.";
                return false;
            }

            return true;
        }
    </script>
</head>

<body>
    <div class="register-container">
        <h2>ElecStore</h2>
        <form action="cuenta2.php" method="POST" onsubmit="return validarFormulario();">
            <div class="input-group">
                <label for="nombre">Nombre de Usuario:</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ingresa tu nombre" required />
            </div>
            <div class="input-group">
                <label for="email">Correo Electrónico:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Ejemplo: a12345678@ceti.mx"
                    required />
                <span id="error-email" style="color: red;"></span>
            </div>
            <div class="input-group">
                <label for="contrasenia">Contraseña:</label>
                <input
                    type="password"
                    id="contrasenia"
                    name="contrasenia"
                    placeholder="Mínimo 8 caracteres"
                    required
                    minlength="8" />
                <span id="error-password" style="color: red;"></span>
            </div>
            <button type="submit">Crear Cuenta</button>
        </form>
        <p class="login-link">
            ¿Ya tienes una cuenta?
            <a href="login.php"><strong>Iniciar sesión</strong></a>
        </p>
    </div>
</body>

</html>