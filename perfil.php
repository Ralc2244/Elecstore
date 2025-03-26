<?php
require 'auth.php'; // Cargar middleware de autenticación

$usuario = verificarToken(); // Verifica si el token es válido

echo json_encode(["success" => "Acceso permitido", "usuario" => $usuario]);
?>
