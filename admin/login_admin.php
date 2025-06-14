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
        } else {
            $error = 'Credenciales incorrectas';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            width: 90%;
            max-width: 400px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        input {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 12px;
            background: rgb(12, 12, 12);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: background 0.3s;
        }

        button:hover {
            background: rgb(60, 62, 65);
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .login-container {
                width: 95%;
                padding: 20px 15px;
            }

            h1 {
                font-size: 1.3rem;
            }

            input,
            button {
                padding: 10px;
            }
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