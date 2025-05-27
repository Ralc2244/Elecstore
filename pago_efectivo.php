<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Configuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Directorios
$recibos_dir = __DIR__ . '/recibos';
$qr_dir = __DIR__ . '/qrs';

// Crear directorios si no existen
foreach ([$recibos_dir, $qr_dir] as $dir) {
    if (!file_exists($dir) && !mkdir($dir, 0755, true)) {
        die("No se pudo crear el directorio: $dir");
    }
}

// Obtener información del usuario
$usuario_id = $_SESSION['usuario_id'];
$query_usuario = "SELECT nombre, email FROM usuarios WHERE id = ?";
$stmt_usuario = $mysqli->prepare($query_usuario);
$stmt_usuario->bind_param("i", $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->get_result()->fetch_assoc();
$stmt_usuario->close();

// Verificar carrito
$query_carrito = "SELECT c.id, p.id AS producto_id, p.nombre, p.precio, c.cantidad, 
                 p.ruta_imagen, p.existencia FROM carrito c
                 JOIN productos p ON c.producto_id = p.id
                 WHERE c.usuario_id = ?";
$stmt_carrito = $mysqli->prepare($query_carrito);
$stmt_carrito->bind_param("i", $usuario_id);

if (!$stmt_carrito->execute()) {
    die("Error al obtener carrito: " . $stmt_carrito->error);
}

$productos = $stmt_carrito->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_carrito->close();

if (empty($productos)) {
    $_SESSION['error_pago'] = "Error: Carrito vacío";
    header("Location: carrito.php");
    exit;
}

// Verificar stock y calcular total
$total = 0;
foreach ($productos as $producto) {
    if ($producto['cantidad'] > $producto['existencia']) {
        $_SESSION['error_pago'] = "No hay suficiente stock para: " . $producto['nombre'];
        header("Location: carrito.php");
        exit;
    }
    $total += $producto['precio'] * $producto['cantidad'];
}

// Generar ID único para la orden
$orden_id = "EFEC_" . date('Ymd_His') . "_" . uniqid();

// Iniciar transacción
$mysqli->begin_transaction();

try {
    // 1. Insertar compras con estado "Pendiente" y escaneado = 0
    foreach ($productos as $producto) {
        $total_producto = $producto['precio'] * $producto['cantidad'];
        $insert_query = "INSERT INTO compras (
            usuario_id, producto_id, nombre_producto, imagen_producto,
            precio, cantidad, total, order_id, estado_pago, fecha, escaneado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW(), 0)";

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

        // Reservar stock (disminuir existencia)
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

    // 3. Generar QR Code
    $qr_script_path = 'C:\\xampp\\htdocs\\elecstore\\qrCode.py';
    $command = escapeshellcmd("python $qr_script_path $usuario_id $orden_id {$usuario['email']}");
    $qr_path = shell_exec($command);
    $qr_path = trim($qr_path);

    if (!file_exists($qr_path)) {
        throw new Exception("No se pudo generar el código QR en: $qr_path");
    }

    // 4. Generar PDF de reserva
    require 'vendor/autoload.php';
    require __DIR__ . '/lib/fpdf.php';

    $pdf = new FPDF();
    $pdf->AddPage();

    // Encabezado
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(200, 10, 'Reserva de Productos', 0, 1, 'C');
    $pdf->Ln(10);

    // Datos del cliente
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 10, 'Cliente: ' . $usuario['nombre']);
    $pdf->Ln(8);
    $pdf->Cell(100, 10, 'Fecha: ' . date('d/m/Y H:i:s'));
    $pdf->Ln(8);
    $pdf->Cell(100, 10, 'ID de Reserva: ' . $orden_id);
    $pdf->Ln(15);

    // Tabla de productos
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(80, 10, 'Producto', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Precio Unitario', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Total', 1, 1, 'C');
    $pdf->SetFont('Arial', '', 12);

    foreach ($productos as $producto) {
        $pdf->Cell(80, 10, substr($producto['nombre'], 0, 40), 1, 0, 'L');
        $pdf->Cell(30, 10, $producto['cantidad'], 1, 0, 'C');
        $pdf->Cell(40, 10, '$' . number_format($producto['precio'], 2), 1, 0, 'C');
        $pdf->Cell(40, 10, '$' . number_format($producto['precio'] * $producto['cantidad'], 2), 1, 1, 'C');
    }

    // Total y QR
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 10, 'Total a pagar en tienda:', 0, 0);
    $pdf->Cell(40, 10, '$' . number_format($total, 2), 1, 1, 'C');
    $pdf->Ln(10);
    $pdf->Cell(100, 10, 'Escanea este código QR en tienda:', 0, 1);
    $pdf->Image($qr_path, 60, $pdf->GetY(), 80);
    $pdf->Ln(90);

    // Instrucciones
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->MultiCell(0, 10, 'INSTRUCCIONES:', 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 7, '1. Presenta el código QR en tienda para completar tu compra.');
    $pdf->MultiCell(0, 7, '2. La reserva es válida por 96 horas (4 días) a partir de la fecha y hora mostrada.');
    $pdf->MultiCell(0, 7, '3. Al completar el pago en tienda, recibirás tu recibo fiscal por correo electrónico.');

    // Guardar PDF
    $pdf_filename = 'reserva_' . $orden_id . '.pdf';
    $pdf_path = $recibos_dir . '/' . $pdf_filename;
    $pdf->Output('F', $pdf_path);

    // 5. Enviar email de reserva
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elecstoreceti@gmail.com';
        $mail->Password = 'dipx bojn iywk flff';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('elecstoreceti@gmail.com', 'Elecstore');
        $mail->addAddress($usuario['email'], $usuario['nombre']);
        $mail->addReplyTo('no-reply@elecstore.com', 'No Responder');

        $mail->isHTML(true);
        $mail->Subject = 'Reserva registrada #' . $orden_id;
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0066cc;'>Reserva registrada</h2>
                <p>Hola {$usuario['nombre']},</p>
                <p>Tu reserva ha sido registrada con éxito. Presenta el código QR adjunto en tienda para completar tu compra.</p>
                
                <h3 style='color: #0066cc;'>Detalles:</h3>
                <p><strong>ID de Reserva:</strong> {$orden_id}</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Total a pagar:</strong> $" . number_format($total, 2) . " MXN</p>
                <p><strong>Estado:</strong> Pendiente de pago en tienda</p>
                
                <p>La reserva es válida por 96 horas (4 días).</p>
                <p>Gracias por tu preferencia,</p>
                <p><strong>Equipo ELECSTORE</strong></p>
            </div>
        ";

        $mail->AltBody = "Reserva registrada\n\nID: {$orden_id}\nFecha: " . date('d/m/Y H:i:s') .
            "\nTotal: $" . number_format($total, 2) . " MXN\nEstado: Pendiente de pago\n\n" .
            "Presenta el código QR en tienda para completar tu compra.";

        $mail->addAttachment($qr_path, 'codigo_qr_' . $orden_id . '.png');
        $mail->addAttachment($pdf_path, 'reserva_' . $orden_id . '.pdf');

        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $e->getMessage());
    }

    // Confirmar transacción
    $mysqli->commit();

    // Actualizar sesión
    $_SESSION['carrito_cantidad'] = 0;
    $_SESSION['mensaje_exito'] = "¡Reserva creada con éxito! Tu ID de reserva es: $orden_id";

    // Redirigir a página de confirmación
    header("Location: confirmacion_reserva.php?orden_id=" . urlencode($orden_id));
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Error en pago_efectivo.php: " . $e->getMessage());
    $_SESSION['error_pago'] = "Error al procesar la reserva. Por favor intenta nuevamente.";
    header("Location: carrito.php");
    exit;
}

$mysqli->close();
