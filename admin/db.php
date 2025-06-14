<?php
$host = 'sql308.infinityfree.com';
$dbname = 'if0_39096654_elecstore';
$username = 'if0_39096654';
$password = 'D6PMCsfj39K';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
