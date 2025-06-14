<?php
$host = 'sql308.infinityfree.com';
$dbname = 'if0_39096654_elecstore';
$username = 'if0_39096654';
$password = 'D6PMCsfj39K';

session_start(); // Iniciar sesión

try {
    // Conexión a la base de datos (corregido aquí)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['email']) || empty($_POST['contrasenia'])) {
            echo "<p style='color: red;'>Por favor, completa todos los campos.</p>";
        } else {
            $email = $_POST['email'];
            $contrasenia = $_POST['contrasenia'];

            // Buscar al usuario por correo
            $stmt = $pdo->prepare("SELECT id, nombre, email, contrasenia FROM usuarios WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($contrasenia, $usuario['contrasenia'])) {
                // Autenticación exitosa
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['email'] = $usuario['email'];
                header("Location: principal.php");
                exit;
            } else {
                echo "<p style='color: red;'>Correo o contraseña incorrectos.</p>";
            }
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error en la conexión: " . $e->getMessage() . "</p>";
}
