<?php
require 'jwt.php';

header("Content-Type: application/json");

$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (!$token) {
    echo json_encode(["error" => "Token no proporcionado"]);
    exit;
}

$decoded = validarTokenJWT($token);

if (!$decoded) {
    echo json_encode(["error" => "Token inválido o expirado"]);
    exit;
}

echo json_encode(["mensaje" => "Acceso permitido", "usuario" => $decoded->correo]);
?>
