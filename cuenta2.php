<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'elecstore';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $contrasenia = $_POST['contrasenia'];

        // Expresión regular para validar los formatos de correo
        $patron = '/^(a\d{8}@ceti\.mx|a\d{8}@live\.ceti\.mx)$/';

        if (!preg_match($patron, $email)) {
            die("Error: El correo debe ser institucional o de los dominios permitidos.");
        }

        // Validar longitud de la contraseña
        if (strlen($contrasenia) < 8) {
            die("Error: La contraseña debe tener al menos 8 caracteres.");
        }

        // Encriptar la contraseña
        $hashed_password = password_hash($contrasenia, PASSWORD_BCRYPT);

        // Verificar si el correo ya está registrado
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => $email]);

        if ($stmt->rowCount() > 0) {
            die("Error: El correo ya está registrado. Intenta con otro.");
        } else {
            try {
                // Insertar usuario en la base de datos
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, contrasenia) VALUES (:nombre, :email, :contrasenia)");
                $stmt->execute(['nombre' => $nombre, 'email' => $email, 'contrasenia' => $hashed_password]);

                echo "Cuenta creada con éxito. Ahora puedes <a href='login.php'>iniciar sesión</a>.";
            } catch (PDOException $e) {
                echo "Error al insertar datos: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
}
?>

