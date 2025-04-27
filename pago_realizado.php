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

if (isset($_GET['orderID'])) {
    $order_id = $_GET['orderID'];

    $query = "
        SELECT 
            c.id AS carrito_id,
            p.id AS producto_id,
            p.nombre, 
            p.precio, 
            c.cantidad, 
            p.ruta_imagen
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
        $productos[] = $row;
        $total += $row['precio'] * $row['cantidad'];
    }

    foreach ($productos as $producto) {
        $insert_producto_query = "INSERT INTO compras (usuario_id, producto_id, nombre_producto, imagen_producto, precio, cantidad, total, order_id, estado_pago)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pagado')";
        $stmt_producto = $mysqli->prepare($insert_producto_query);
        $total_producto = $producto['precio'] * $producto['cantidad'];

        $stmt_producto->bind_param(
            "iissdiis",
            $usuario_id,
            $producto['producto_id'],
            $producto['nombre'],
            $producto['ruta_imagen'],
            $producto['precio'],
            $producto['cantidad'],
            $total_producto,
            $order_id
        );
        $stmt_producto->execute();
        $stmt_producto->close();
    }

    $delete_query = "DELETE FROM carrito WHERE usuario_id = ?";
    $stmt_delete = $mysqli->prepare($delete_query);
    $stmt_delete->bind_param("i", $usuario_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // Crear el PDF del recibo
    define('FPDF_FONTPATH', 'lib/font');
    require('lib/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();

    date_default_timezone_set('America/Mexico_City');
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(200, 10, 'Comprobante de Pago', 0, 1, 'C');
    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 10, 'Fecha: ' . date('Y-m-d H:i:s'));
    $pdf->Ln(10);
    $pdf->Cell(100, 10, 'ID de Orden: ' . $order_id);
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
        $pdf->Cell(40, 10, '$' . number_format($producto['precio'] * $producto['cantidad'], 2), 1, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 10, 'Total a pagar', 0, 0);
    $pdf->Cell(40, 10, '$' . number_format($total, 2), 1, 1, 'C');

    $pdf_filename = __DIR__ . "/recibos/recibo_" . $orden_id . ".pdf";
    $pdf->Output('F', 'recibos/' . $pdf_filename);

    // Obtener correo del cliente
    $stmt = $mysqli->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $email_cliente = $result->fetch_assoc()['email'];
    $stmt->close();

    // Generar QR con Python
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


    // Enviar correo
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/autoload.php';

    // Obtener el correo del cliente
    $stmt = $mysqli->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $email_cliente = $result->fetch_assoc()['email'];
    $stmt->close();

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
    $mail->Username = 'elecstoreceti@gmail.com';
    $mail->Password = 'cpqv ikfr xtnf omrn';
    $mail->SMTPSecure = 'ssl';  // O usa 'ssl' si es necesario
    $mail->Port = 465;

    $mail->setFrom('elecstoreceti@gmail.com', 'Elecstore');
    $mail->addAddress($email_cliente);  // El correo validado del cliente
    $mail->Subject = "Código QR y recibo de tu compra";
    $mail->Body = "Gracias por tu compra. Adjuntamos tu código QR y el recibo en PDF.";

    // Adjuntar los archivos (QR y PDF)
    $mail->addAttachment(trim($qr_path));  // Ruta del archivo QR
    $mail->addAttachment($pdf_filename);   // Ruta del archivo PDF

    // Enviar el correo
    $mail->send();

    $_SESSION['mensaje_qr'] = "¡Tu compra ha sido registrada! Te enviamos el QR y el recibo por correo.";
}
$mysqli->close();
