<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "elecstore");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener productos del carrito
$query = "SELECT c.producto_id, p.nombre, p.ruta_imagen, p.precio, p.existencia, c.cantidad, (p.precio * c.cantidad) AS total_producto 
    FROM carrito c
    INNER JOIN productos p ON c.producto_id = p.id
    WHERE c.usuario_id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$productos = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['cantidad'] > $row['existencia']) {
        die("Error: No hay suficiente stock para el producto: " . $row['nombre']);
    }
    $productos[] = $row;
    $total += $row['total_producto'];
}
$stmt->close();

if (empty($productos)) {
    die("Error: No hay productos en el carrito.");
}

$orden_id = uniqid("ORD_");

$insert_query = "INSERT INTO compras (usuario_id, fecha, producto_id, nombre_producto, imagen_producto, precio, cantidad, total, order_id, estado_pago) 
                 VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
$stmt_insert = $mysqli->prepare($insert_query);

$mysqli->begin_transaction();

try {
    foreach ($productos as $producto) {
        $stmt_insert->bind_param(
            "iissdidi",
            $usuario_id,
            $producto['producto_id'],
            $producto['nombre'],
            $producto['ruta_imagen'],
            $producto['precio'],
            $producto['cantidad'],
            $producto['total_producto'],
            $orden_id
        );
        $stmt_insert->execute();
    }

    $delete_query = "DELETE FROM carrito WHERE usuario_id = ?";
    $stmt_delete = $mysqli->prepare($delete_query);
    $stmt_delete->bind_param("i", $usuario_id);
    $stmt_delete->execute();

    $mysqli->commit();

    // Obtener correo del cliente
    $stmt = $mysqli->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $email_cliente = $result->fetch_assoc()['email'];
    $stmt->close();

    // Crear PDF del recibo
    require('lib/fpdf.php');
    define('FPDF_FONTPATH', 'lib/font');
    date_default_timezone_set('America/Mexico_City');
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(200, 10, 'Comprobante de Pago', 0, 1, 'C');
    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 10, 'Fecha: ' . date('Y-m-d H:i:s'));
    $pdf->Ln(10);
    $pdf->Cell(100, 10, 'ID de Orden: ' . $orden_id);
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 10, 'Producto', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Precio Unitario', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Total', 1, 1, 'C');
    $pdf->SetFont('Arial', '', 12);

    foreach ($productos as $producto) {
        $pdf->Cell(50, 10, $producto['nombre'], 1, 0, 'L');
        $pdf->Cell(30, 10, $producto['cantidad'], 1, 0, 'C');
        $pdf->Cell(40, 10, '$' . number_format($producto['precio'], 2), 1, 0, 'C');
        $pdf->Cell(40, 10, '$' . number_format($producto['total_producto'], 2), 1, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 10, 'Total a pagar', 0, 0);
    $pdf->Cell(40, 10, '$' . number_format($total, 2), 1, 1, 'C');

    $pdf_filename = __DIR__ . "/recibos/recibo_" . $orden_id . ".pdf";
    $pdf->Output('F', $pdf_filename);

    // Ejecutar script de QR
    $cmd = "C:\\Python313\\python.exe C:\\xampp\\htdocs\\elecstore\\qrCode.py $usuario_id $orden_id $email_cliente";
    $descriptorspec = array(
        0 => array("pipe", "r"),  // STDIN
        1 => array("pipe", "w"),  // STDOUT
        2 => array("pipe", "w")   // STDERR
    );

    $process = proc_open($cmd, $descriptorspec, $pipes);

    if (is_resource($process)) {
        fclose($pipes[0]); // No escribimos en stdin

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !$stdout) {
            throw new Exception("Error al generar el QR. Comando: $cmd\nError: $stderr\nSalida: $stdout");
        }

        $qr_path = trim($stdout);

        if (!file_exists($qr_path)) {
            throw new Exception("El archivo QR no fue encontrado. Ruta esperada: $qr_path");
        }
    } else {
        throw new Exception("No se pudo iniciar el proceso de generación del código QR.");
    }

    // Enviar correo con QR + PDF
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/autoload.php';

    // Validar correo con expresión regular
    $patron = '/^(a\d{8}@ceti\.mx|a\d{8}@live\.ceti\.mx)$/';

    if (!preg_match($patron, $email_cliente)) {
        throw new Exception("Correo inválido: $email_cliente");
    }

    // Si el correo es válido, continuamos con el proceso de envío de correo
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configuración de PHPMailer
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'a21100265@ceti.mx';
    $mail->Password = 'tkze srxw uzno zzek';
    $mail->SMTPSecure = 'ssl';  // O usa 'ssl' si es necesario
    $mail->Port = 465;

    $mail->setFrom('a21100265@ceti.mx', 'Elecstore');
    $mail->addAddress($email_cliente);  // El correo validado del cliente
    $mail->Subject = "Código QR y recibo de tu compra";
    $mail->Body = "Gracias por tu compra. Adjuntamos tu código QR y el recibo en PDF.";

    // Adjuntar los archivos (QR y PDF)
    $mail->addAttachment(trim($qr_path));  // Ruta del archivo QR
    $mail->addAttachment($pdf_filename);   // Ruta del archivo PDF

    // Enviar el correo
    $mail->send();

    $_SESSION['mensaje_qr'] = "¡Tu compra ha sido registrada! Te enviamos el QR y el recibo por correo.";
    header("Location: principal.php");
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    die("Error al procesar compra: " . $e->getMessage());
}

$mysqli->close();
