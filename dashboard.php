<?php
require 'jwt.php';
header("Content-Type: application/json");

// Obtener el token enviado desde el cliente
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

// Validar token
$decoded = validarTokenJWT($token);
if (!$decoded) {
    echo json_encode(["error" => "Acceso no autorizado"]);
    http_response_code(401);
    exit;
}

// Usuario autenticado
echo json_encode(["mensaje" => "Bienvenido", "usuario" => $decoded->correo]);
?>
