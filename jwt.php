<?php

$mysqli = new mysqli("localhost", "root", "", "elecstore");
if ($mysqli->connect_error) {
    die("Fallo en la conexión: " . $mysqli->connect_error);
}

require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = "eddfeddf";

function generarTokenJWT($correo) {
    global $key;
    $payload = [
        "iss" => "localhost",
        "correo" => $correo,
        "exp" => time() + (60 * 60) // Expira en 1 hora
    ];
    return JWT::encode($payload, $key, 'HS256');
}

function validarTokenJWT($token) {
    global $key;
    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}
?>
