<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'elecstore';
$user = 'root';
$password = '';

session_start(); // Iniciar sesión para mantener al usuario logueado

try {
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si el formulario fue enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar si los campos están vacíos
        if (empty($_POST['email']) || empty($_POST['contrasenia'])) {
            echo "<p style='color: red;'>Por favor, completa todos los campos.</p>";
        } else {
            $email = $_POST['email'];
            $contrasenia = $_POST['contrasenia'];

            // Buscar al usuario por correo
            $stmt = $pdo->prepare("SELECT id, nombre, email, contrasenia FROM usuarios WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar contraseña
            if ($usuario && password_verify($contrasenia, $usuario['contrasenia'])) {
                // Contraseña correcta: guardar información en sesión
                $_SESSION['usuario_id'] = $usuario['id']; // Guardar ID del usuario en la sesión
                $_SESSION['nombre'] = $usuario['nombre']; // Guardar nombre en la sesión
                $_SESSION['email'] = $usuario['email']; // Guardar email en la sesión
                header("Location: principal.php"); // Redirigir al panel de usuario
                exit;
            } else {
                // Contraseña o correo incorrectos
                echo "<p style='color: red;'>Correo o contraseña incorrectos.</p>";
            }
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error en la conexión: " . $e->getMessage() . "</p>";
}
