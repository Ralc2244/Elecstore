<?php
session_start();

// Configuración de la base de datos
$mysqli = new mysqli("sql308.infinityfree.com", "if0_39096654", "D6PMCsfj39K", "if0_39096654_elecstore");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// Verifica si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Primero verificamos y limpiamos el carrito expirado para este usuario
$usuario_id = $_SESSION['usuario_id'];

// Verificar y obtener los productos del carrito expirado (más de 2 minutos)
$query_verificar = "SELECT 
    c.id AS carrito_id,
    c.producto_id,
    c.cantidad,
    p.nombre
FROM 
    carrito c
JOIN 
    productos p ON c.producto_id = p.id
WHERE 
    c.usuario_id = ? AND 
    c.ultima_actualizacion < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
$stmt = $mysqli->prepare($query_verificar);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$carrito_expirado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($carrito_expirado)) {
    // Iniciar transacción para asegurar la integridad de los datos
    $mysqli->begin_transaction();

    try {
        // 1. Reponer el stock de cada producto
        foreach ($carrito_expirado as $item) {
            $query_reponer = "UPDATE productos 
                            SET existencia = existencia + ? 
                            WHERE id = ?";
            $stmt = $mysqli->prepare($query_reponer);
            $stmt->bind_param("ii", $item['cantidad'], $item['producto_id']);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Limpiar el carrito expirado
        $query_limpiar = "DELETE FROM carrito WHERE usuario_id = ?";
        $stmt = $mysqli->prepare($query_limpiar);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();

        // Actualizar sesión
        $_SESSION['carrito_cantidad'] = 0;

        // Mostrar mensaje al usuario con lista de productos repuestos
        $productos_repuestos = array_map(function ($item) {
            return $item['nombre'] . " (Cantidad: " . $item['cantidad'] . ")";
        }, $carrito_expirado);

        $_SESSION['mensaje_carrito'] = "Tu carrito ha sido vaciado automáticamente por inactividad. Se repuso el stock de: " .
            implode(", ", $productos_repuestos);
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error al limpiar carrito: " . $e->getMessage());
        $_SESSION['error_carrito'] = "Ocurrió un error al procesar tu carrito. Por favor intenta nuevamente.";
    }
}

// 2. Procesamiento normal del carrito (tu código original)
// Cuando se agrega un producto al carrito
if (isset($_POST['agregar_carrito'])) {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];

    // Verificar disponibilidad de stock primero
    $query_stock = "SELECT existencia FROM productos WHERE id = ?";
    $stmt = $mysqli->prepare($query_stock);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $stock = $stmt->get_result()->fetch_assoc()['existencia'];
    $stmt->close();

    if ($stock >= $cantidad) {
        // Actualizar el timestamp del carrito
        $query_actualizar = "UPDATE carrito SET ultima_actualizacion = NOW() WHERE usuario_id = ?";
        $stmt = $mysqli->prepare($query_actualizar);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $_SESSION['error_carrito'] = "No hay suficiente stock para el producto seleccionado.";
    }
}

// Obtener productos del carrito actual
$query = "SELECT 
    c.id AS carrito_id,
    p.id AS producto_id,
    p.nombre, 
    p.precio, 
    c.cantidad,
    p.ruta_imagen,
    p.existencia
FROM 
    carrito c
INNER JOIN 
    productos p 
ON 
    c.producto_id = p.id
WHERE 
    c.usuario_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$carrito = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Verificar stock actual y eliminar productos sin stock suficiente
foreach ($carrito as $key => $item) {
    if ($item['cantidad'] > $item['existencia']) {
        // Eliminar producto sin stock suficiente
        $query_eliminar = "DELETE FROM carrito WHERE id = ?";
        $stmt = $mysqli->prepare($query_eliminar);
        $stmt->bind_param("i", $item['carrito_id']);
        $stmt->execute();
        $stmt->close();

        unset($carrito[$key]);

        // Reponer el stock (en caso de que haya habido un cambio)
        $query_reponer = "UPDATE productos SET existencia = existencia + ? WHERE id = ?";
        $stmt = $mysqli->prepare($query_reponer);
        $stmt->bind_param("ii", $item['cantidad'], $item['producto_id']);
        $stmt->execute();
        $stmt->close();

        $_SESSION['error_carrito'] = "Algunos productos fueron eliminados de tu carrito por falta de stock.";
    }
}

// Agrupar los productos por producto_id y sumar las cantidades
$carrito_grouped = [];
foreach ($carrito as $item) {
    if (!isset($carrito_grouped[$item['producto_id']])) {
        $carrito_grouped[$item['producto_id']] = $item;
    } else {
        $carrito_grouped[$item['producto_id']]['cantidad'] += $item['cantidad'];
    }
}

// Obtener descuentos disponibles
$query_descuentos = "SELECT id_descuento, descripcion, producto_id, cantidad_minima, porcentaje_descuento
    FROM descuentos
    WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()";
$result_descuentos = $mysqli->query($query_descuentos);
$descuentos = $result_descuentos->fetch_all(MYSQLI_ASSOC);

// Función para verificar si se aplica un descuento específico para un producto
function obtener_descuento_aplicable($producto_id, $cantidad, $descuentos)
{
    foreach ($descuentos as $descuento) {
        if (($descuento['producto_id'] == NULL || $descuento['producto_id'] == $producto_id) && $cantidad >= $descuento['cantidad_minima']) {
            return $descuento['porcentaje_descuento'];
        }
    }
    return 0;
}

// Función para calcular cuántos productos faltan para el descuento
function productos_faltantes_para_descuento($producto_id, $cantidad, $descuentos)
{
    foreach ($descuentos as $descuento) {
        if (($descuento['producto_id'] == NULL || $descuento['producto_id'] == $producto_id) && $cantidad < $descuento['cantidad_minima']) {
            return $descuento['cantidad_minima'] - $cantidad;
        }
    }
    return 0;
}

// Calcular el total con descuento
$total = 0;
foreach ($carrito_grouped as $key => $item) {
    $descuento = obtener_descuento_aplicable($item['producto_id'], $item['cantidad'], $descuentos);
    $precio_con_descuento = $item['precio'] * (1 - $descuento / 100);
    $total += $precio_con_descuento * $item['cantidad'];
    $carrito_grouped[$key]['precio_con_descuento'] = $precio_con_descuento;
    $carrito_grouped[$key]['faltan_para_descuento'] = productos_faltantes_para_descuento($item['producto_id'], $item['cantidad'], $descuentos);
}

// Guardar cantidad total en sesión para mostrar en "Mis pedidos"
$_SESSION['carrito_cantidad'] = array_sum(array_column($carrito_grouped, 'cantidad'));

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito | Elecstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="carrito.css">
</head>

<body>
    <!-- Navbar negro conservado como en tu versión original -->
    <header class="mb-3"> <!-- Reducido el margen inferior -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-black py-1"> <!-- Añadido py-1 para controlar padding vertical -->
            <div class="container-fluid px-2 px-sm-3"> <!-- Ajustado el padding horizontal -->
                <a class="navbar-brand fs-5" href="principal.php">ELECSTORE</a> <!-- Añadido fs-5 para tamaño de fuente -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link py-2" href="principal.php">Principal</a></li>
                        <li class="nav-item"><a class="nav-link py-2" href="para_ti.php">Para ti</a></li>
                        <li class="nav-item position-relative">
                            <a class="nav-link active py-2" href="carrito.php">
                                Mis Pedidos
                                <?php if (isset($_SESSION['carrito_cantidad']) && $_SESSION['carrito_cantidad'] > 0): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                                        <?php echo $_SESSION['carrito_cantidad']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link py-2" href="historial.php">Mis Compras</a></li>
                        <li class="nav-item"><a class="nav-link py-2" href="comentarios.php">Comentarios</a></li>
                        <li class="nav-item"><a class="nav-link py-2" href="perfil_usuario.php">Mi Perfil</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><i class="fas fa-shopping-cart me-2"></i>Mi Carrito</h1>
            <a href="principal.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Seguir comprando
            </a>
        </div>

        <?php if (isset($_SESSION['mensaje_carrito'])): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
                <?= $_SESSION['mensaje_carrito'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['mensaje_carrito']); ?>
        <?php endif; ?>

        <?php if (!empty($carrito_grouped)): ?>
            <div class="table-responsive">
                <table class="table cart-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Precio Unitario</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Descuento</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carrito_grouped as $item): ?>
                            <tr data-id="<?= $item['carrito_id'] ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($item['ruta_imagen']) ?>"
                                            class="product-image me-3"
                                            alt="<?= htmlspecialchars($item['nombre']) ?>">
                                        <span><?= htmlspecialchars($item['nombre']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center">$<?= number_format($item['precio'], 2) ?></td>
                                <td class="text-center"><?= $item['cantidad'] ?></td>
                                <td class="text-center">
                                    <?php if ($descuento = obtener_descuento_aplicable($item['producto_id'], $item['cantidad'], $descuentos)): ?>
                                        <span class="badge bg-success"><?= $descuento ?>% OFF</span>
                                    <?php else: ?>
                                        <span class="text-muted">Sin descuento</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">$<?= number_format($item['precio_con_descuento'] * $item['cantidad'], 2) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?= $item['carrito_id'] ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php if ($item['faltan_para_descuento'] > 0): ?>
                                <tr class="discount-info">
                                    <td colspan="6" class="text-center text-warning">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Faltan <?= $item['faltan_para_descuento'] ?> unidades para aplicar descuento
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="4" class="text-end"><strong>Total:</strong></td>
                            <td id="total" class="text-end fw-bold">$<?= number_format($total, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-between mt-5">
                <form action="pago_efectivo.php" method="POST" class="w-100 me-3">
                    <button type="submit" class="btn btn-payment btn-lg w-100">
                        <i class="fas fa-money-bill-wave me-2"></i>Pagar en Efectivo
                    </button>
                </form>
                <form action="pago_paypal.php" method="POST" class="w-100 ms-3">
                    <button type="submit" class="btn btn-payment btn-lg w-100">
                        <i class="fab fa-paypal me-2"></i>Pagar con PayPal
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-cart text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h2 class="h4">Tu carrito está vacío</h2>
                <p class="text-muted">Agrega productos para comenzar a comprar</p>
                <a href="principal.php" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Volver a la tienda
                </a>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.eliminar-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const carritoId = this.dataset.id;
                const fila = this.closest('tr');

                if (confirm('¿Estás seguro de eliminar este producto de tu carrito?')) {
                    fetch('eliminar_carrito.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'carrito_id=' + carritoId
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Eliminar la fila del producto
                                fila.remove();

                                // Actualizar total
                                document.getElementById('total').textContent = "$" + data.nuevo_total;

                                // Actualizar badge del carrito
                                const badge = document.querySelector('.badge');
                                if (badge) {
                                    if (data.nueva_cantidad > 0) {
                                        badge.textContent = data.nueva_cantidad;
                                    } else {
                                        badge.remove();
                                        // Si no hay más productos, recargar para mostrar estado vacío
                                        location.reload();
                                    }
                                }
                            } else {
                                alert("Error al eliminar el producto: " + (data.message || ''));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Ocurrió un error al eliminar el producto');
                        });
                }
            });
        });
    </script>
</body>

</html>