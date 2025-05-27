<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'elecstore');
require 'vendor/autoload.php'; // Para PHPMailer y FPDF

// Conexión a la base de datos
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Obtener datos del usuario
$usuario_id = $_SESSION['usuario_id'];
$query_usuario = "SELECT nombre, email FROM usuarios WHERE id = ?";
$stmt_usuario = $mysqli->prepare($query_usuario);
$stmt_usuario->bind_param("i", $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->get_result()->fetch_assoc();
$stmt_usuario->close();

// Verificar si se recibió el orderID de PayPal
if (!isset($_GET['orderID'])) {
    die("Error: No se recibió el ID de la orden de PayPal");
}

// Obtener productos del carrito
$query_carrito = "SELECT c.id, p.id AS producto_id, p.nombre, p.precio, c.cantidad, 
                 p.ruta_imagen, p.existencia FROM carrito c
                 JOIN productos p ON c.producto_id = p.id
                 WHERE c.usuario_id = ?";
$stmt_carrito = $mysqli->prepare($query_carrito);
$stmt_carrito->bind_param("i", $usuario_id);
$stmt_carrito->execute();
$productos = $stmt_carrito->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_carrito->close();

if (empty($productos)) {
    die("Error: Carrito vacío");
}

// Calcular total y verificar stock
$total = 0;
foreach ($productos as $producto) {
    if ($producto['cantidad'] > $producto['existencia']) {
        die("No hay suficiente stock para: " . $producto['nombre']);
    }
    $total += $producto['precio'] * $producto['cantidad'];
}

// Generar ID único para nuestra orden
$orden_id = "PP_" . date('Ymd_His') . "_" . uniqid();

// Iniciar transacción
$mysqli->begin_transaction();

try {
    // 1. Registrar la compra en la base de datos
    foreach ($productos as $producto) {
        $total_producto = $producto['precio'] * $producto['cantidad'];
        $insert_query = "INSERT INTO compras (
            usuario_id, producto_id, nombre_producto, imagen_producto,
            precio, cantidad, total, order_id, estado_pago, fecha, escaneado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pagado', NOW(), 0)";

        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param(
            "iissdiis",
            $usuario_id,
            $producto['producto_id'],
            $producto['nombre'],
            $producto['ruta_imagen'],
            $producto['precio'],
            $producto['cantidad'],
            $total_producto,
            $orden_id
        );
        $stmt->execute();
        $stmt->close();

        // Actualizar existencias
        $update_query = "UPDATE productos SET existencia = existencia - ? WHERE id = ?";
        $stmt = $mysqli->prepare($update_query);
        $stmt->bind_param("ii", $producto['cantidad'], $producto['producto_id']);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Vaciar carrito
    $delete_query = "DELETE FROM carrito WHERE usuario_id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();

    // 3. Generar QR Code usando tu script Python
    $qr_script_path = 'C:\\xampp\\htdocs\\elecstore\\qrCode.py';
    $command = escapeshellcmd("python $qr_script_path $usuario_id $orden_id {$usuario['email']}");
    $qr_path = shell_exec($command);
    $qr_path = trim($qr_path); // Eliminar espacios/newlines

    if (!file_exists($qr_path)) {
        throw new Exception("No se pudo generar el código QR en: $qr_path");
    }

    // 4. Generar Recibo PDF
    $recibos_dir = __DIR__ . '/recibos';
    if (!file_exists($recibos_dir)) {
        mkdir($recibos_dir, 0755, true);
    }

    $pdf = new FPDF();
    $pdf->AddPage();

    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'RECIBO DE PAGO', 0, 1, 'C');
    $pdf->Ln(10);

    // Datos del cliente
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Cliente: ' . $usuario['nombre'], 0, 1);
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1);
    $pdf->Cell(0, 10, 'Orden #: ' . $orden_id, 0, 1);
    $pdf->Ln(10);

    // Tabla de productos
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(100, 10, 'Producto', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(30, 10, 'P. Unitario', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Total', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    foreach ($productos as $producto) {
        $pdf->Cell(100, 10, substr($producto['nombre'], 0, 40), 1);
        $pdf->Cell(30, 10, $producto['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 10, '$' . number_format($producto['precio'], 2), 1, 0, 'R');
        $pdf->Cell(30, 10, '$' . number_format($producto['precio'] * $producto['cantidad'], 2), 1, 1, 'R');
    }

    // Total
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(160, 10, 'Total:', 1, 0, 'R');
    $pdf->Cell(30, 10, '$' . number_format($total, 2), 1, 1, 'R');

    // Código QR
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Codigo QR para referencia:', 0, 1);
    $pdf->Image($qr_path, 60, $pdf->GetY(), 80);

    // Guardar PDF
    $pdf_path = $recibos_dir . '/recibo_' . $orden_id . '.pdf';
    $pdf->Output('F', $pdf_path);

    // 5. Enviar correo electrónico con recibo y QR
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elecstoreceti@gmail.com';
        $mail->Password = 'dipx bojn iywk flff';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Remitente y destinatario
        $mail->setFrom('elecstoreceti@gmail.com', 'Elecstore');
        $mail->addAddress($usuario['email'], $usuario['nombre']);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Recibo de compra #' . $orden_id;
        $mail->Body = "
            <h2 style='color: #0066cc;'>¡Gracias por tu compra!</h2>
            <p>Hola {$usuario['nombre']},</p>
            <p>Tu pago ha sido procesado exitosamente. Adjuntamos tu recibo y código QR para referencia.</p>
            
            <h3>Detalles de la compra:</h3>
            <p><strong>Orden #:</strong> {$orden_id}</p>
            <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
            <p><strong>Total:</strong> $" . number_format($total, 2) . " MXN</p>
            <p><strong>Estado:</strong> Pagado (pendiente de escaneo)</p>
            
            <p>Guarda este correo como comprobante de tu compra.</p>
            <p>Gracias por tu preferencia,</p>
            <p><strong>Equipo ELECSTORE</strong></p>
        ";

        $mail->AltBody = "Recibo de compra\n\nOrden: {$orden_id}\nFecha: " . date('d/m/Y H:i:s') .
            "\nTotal: $" . number_format($total, 2) . " MXN\nEstado: Pagado (pendiente de escaneo)\n\nGracias por tu compra.";

        // Adjuntar archivos
        $mail->addAttachment($pdf_path, 'Recibo_' . $orden_id . '.pdf');
        $mail->addAttachment($qr_path, 'CodigoQR_' . $orden_id . '.png');

        // Enviar correo
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $e->getMessage());
    }

    // Confirmar transacción
    $mysqli->commit();

    // Actualizar sesión
    $_SESSION['carrito_cantidad'] = 0;

    // Redirigir a página de confirmación
    header("Location: confirmacion_pago.php?order_id=" . urlencode($orden_id));
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    die("Error al procesar el pago: " . $e->getMessage());
}

$mysqli->close();
