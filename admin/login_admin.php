<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validar campos
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese email y contraseña';
    } else {
        // Buscar administrador en la base de datos
        $stmt = $conn->prepare("SELECT id, nombre, email, contrasenia FROM administradores WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar contraseña
            if (password_verify($password, $admin['contrasenia'])) {
                // Iniciar sesión
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['nombre'];
                $_SESSION['admin_email'] = $admin['email'];

                // Verificar que las variables de sesión se establecieron
                if (isset($_SESSION['admin_id'])) {
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Error al iniciar sesión';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
        }

        .login-container {
            width: 300px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background: rgb(12, 12, 12);
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: rgb(60, 62, 65);
        }

        .error {
            color: red;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>Acceso Administrador</h1>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="post" action="login_admin.php">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>

</html>