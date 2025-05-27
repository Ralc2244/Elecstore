<?php

// Conectar a la base de datos
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

function actualizarPopularesSemestre()
{
    global $mysqli;

    $semestre = (date('n') <= 6) ? 1 : 2;
    $anio = date('Y');

    // Eliminar registros antiguos del mismo semestre
    $mysqli->query("DELETE FROM productos_populares_semestre WHERE semestre = $semestre AND anio = $anio");

    // Insertar nuevos datos
    $query = "INSERT INTO productos_populares_semestre 
              (semestre, anio, producto_id, cantidad_vendida, total_vendido, posicion)
              SELECT 
                  $semestre as semestre,
                  $anio as anio,
                  p.id as producto_id,
                  SUM(c.cantidad) as cantidad_vendida,
                  SUM(c.total) as total_vendido,
                  @posicion := @posicion + 1 as posicion
              FROM productos p
              JOIN compras c ON c.producto_id = p.id,
              (SELECT @posicion := 0) r
              WHERE c.estado_pago = 'Pagado'
              AND (
                  (MONTH(c.fecha) <= 6 AND $semestre = 1 AND YEAR(c.fecha) = $anio) OR
                  (MONTH(c.fecha) > 6 AND $semestre = 2 AND YEAR(c.fecha) = $anio)
              )
              GROUP BY p.id
              ORDER BY total_vendido DESC
              LIMIT 50";

    $mysqli->query($query);
}

// Ejecutar la función
actualizarPopularesSemestre();
echo "Actualización de productos populares completada - " . date('Y-m-d H:i:s');
