<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

require 'vendor/autoload.php'; // PHPMailer y FPDF

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$query_usuario = "SELECT nombre, email FROM usuarios WHERE id = ?";
$stmt_usuario = $mysqli->prepare($query_usuario);
$stmt_usuario->bind_param("i", $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->get_result()->fetch_assoc();
$stmt_usuario->close();

if (!isset($_GET['orderID'])) {
    die("Error: No se recibió el ID de la orden de PayPal");
}

$query_carrito = "SELECT c.id, p.id AS producto_id, p.nombre, p.precio, c.cantidad, p.ruta_imagen, p.existencia FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?";
$stmt_carrito = $mysqli->prepare($query_carrito);
$stmt_carrito->bind_param("i", $usuario_id);
$stmt_carrito->execute();
$productos = $stmt_carrito->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_carrito->close();

if (empty($productos)) {
    die("Error: Carrito vacío");
}

$total = 0;
foreach ($productos as $producto) {
    if ($producto['cantidad'] > $producto['existencia']) {
        die("No hay suficiente stock para: " . $producto['nombre']);
    }
    $total += $producto['precio'] * $producto['cantidad'];
}

$orden_id = "PP_" . date('Ymd_His') . "_" . uniqid();

require_once __DIR__ . '/phpqrcode-2010100721_1.1.4/phpqrcode/qrlib.php';

function generarCodigoQR($usuario_id, $orden_id, $email_cliente, $ruta_guardado)
{
    date_default_timezone_set('America/Mexico_City');

    $fecha_actual = date("Y-m-d H:i:s");
    $data = "ELECSTORE - Reserva\n";
    $data .= "------------------------\n";
    $data .= "Usuario ID: $usuario_id\n";
    $data .= "Orden ID: $orden_id\n";
    $data .= "Email: $email_cliente\n";
    $data .= "Fecha: $fecha_actual\n";
    $data .= "------------------------\n";
    $data .= "Presentar este código en tienda";

    QRcode::png($data, $ruta_guardado, 'H', 10, 4);

    return $ruta_guardado;
}

$mysqli->begin_transaction();

try {
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
    }

    $delete_query = "DELETE FROM carrito WHERE usuario_id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();

    $recibos_dir = __DIR__ . '/recibos';
    $qr_dir = __DIR__ . '/qrs';

    foreach ([$recibos_dir, $qr_dir] as $dir) {
        if (!file_exists($dir)) mkdir($dir, 0755, true);
    }

    $qr_filename = $qr_dir . "/qr_{$orden_id}.png";
    generarCodigoQR($usuario_id, $orden_id, $usuario['email'], $qr_filename);

    // Generar PDF recibo
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'RECIBO DE PAGO', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Cliente: ' . $usuario['nombre'], 0, 1);
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1);
    $pdf->Cell(0, 10, 'Orden #: ' . $orden_id, 0, 1);
    $pdf->Ln(10);

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

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(160, 10, 'Total:', 1, 0, 'R');
    $pdf->Cell(30, 10, '$' . number_format($total, 2), 1, 1, 'R');

    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Código QR para referencia:', 0, 1);
    $pdf->Image($qr_filename, 60, $pdf->GetY(), 80);

    $pdf_path = $recibos_dir . '/recibo_' . $orden_id . '.pdf';
    $pdf->Output('F', $pdf_path);

    // Enviar correo con PHPMailer
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

        $mail->addAttachment($pdf_path, 'Recibo_' . $orden_id . '.pdf');
        $mail->addAttachment($qr_filename, 'CodigoQR_' . $orden_id . '.png');

        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $e->getMessage());
    }

    $mysqli->commit();

    $_SESSION['carrito_cantidad'] = 0;

    header("Location: confirmacion_pago.php?order_id=" . urlencode($orden_id));
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    die("Error al procesar el pago: " . $e->getMessage());
}

$mysqli->close();
