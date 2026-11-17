<?php
/**
 * Registra una compra en la base de datos.
 */
require_once __DIR__ . '/conexion.php';

$volverA = 'index.php';
if (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'catalogo.php')) {
    $volverA = 'catalogo.php';
}

if (!usuario_actual()) {
    flash('error', 'Necesitás iniciar sesión para completar la compra.');
    header('Location: login.php?comprar=1&volver=' . urlencode($volverA));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    flash('error', 'No se pudo procesar el pedido. Volvé a intentarlo.');
    header('Location: ' . $volverA);
    exit;
}

$idProducto    = (int) ($_POST['id_producto'] ?? 0);
$cantidad      = (int) ($_POST['cantidad'] ?? 1);
$cliente       = trim((string) ($_POST['cliente'] ?? ''));
$telefono      = trim((string) ($_POST['telefono'] ?? ''));
$email         = trim((string) ($_POST['email'] ?? ''));
$direccion     = trim((string) ($_POST['direccion'] ?? ''));
$alias         = trim((string) ($_POST['alias_pagador'] ?? ''));
$comprobante   = trim((string) ($_POST['comprobante'] ?? ''));
$observaciones = trim((string) ($_POST['observaciones'] ?? ''));

if ($cantidad < 1) {
    $cantidad = 1;
}

if ($idProducto <= 0 || $cliente === '' || $telefono === '' || $direccion === '') {
    flash('error', 'Completá nombre, teléfono, dirección y producto.');
    header('Location: ' . $volverA);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'El correo electrónico ingresado no es válido.');
    header('Location: ' . $volverA);
    exit;
}

$idUsuario = (int) (usuario_actual()['id'] ?? 0);

try {
    /* Verificamos que el usuario de la sesión realmente exista en esta base. */
    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND estado = "activo"');
    $stmt->execute([$idUsuario]);

    if (!$stmt->fetch()) {
        throw new RuntimeException('La sesión de usuario no coincide con la base de datos. Cerrá sesión y volvé a ingresar.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id_producto, nombre, precio, stock
         FROM productos
         WHERE id_producto = ? AND estado = 'activo'
         FOR UPDATE"
    );
    $stmt->execute([$idProducto]);
    $producto = $stmt->fetch();

    if (!$producto) {
        throw new RuntimeException('El producto no existe o ya no está disponible.');
    }

    if ((int) $producto['stock'] < $cantidad) {
        throw new RuntimeException(
            'No hay stock suficiente. Quedan ' . (int) $producto['stock'] . ' unidades.'
        );
    }

    $precio = (float) $producto['precio'];
    $total = $precio * $cantidad;

    $stmt = $pdo->prepare(
        'INSERT INTO pedidos
         (id_producto, id_usuario, producto, cantidad, precio_unitario, total,
          cliente, telefono, email, direccion, alias_pagador, comprobante, observaciones)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $idProducto,
        $idUsuario,
        $producto['nombre'],
        $cantidad,
        $precio,
        $total,
        $cliente,
        $telefono,
        $email !== '' ? $email : null,
        $direccion,
        $alias !== '' ? $alias : null,
        $comprobante !== '' ? $comprobante : null,
        $observaciones !== '' ? $observaciones : null,
    ]);

    $numero = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'UPDATE productos SET stock = stock - ? WHERE id_producto = ?'
    );
    $stmt->execute([$cantidad, $idProducto]);

    $pdo->commit();

    /*
     * La notificación se guarda DESPUÉS del pedido.
     * Si por algún motivo falla, la compra igualmente queda guardada.
     */
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notificaciones
             (id_usuario, id_pedido, id_solicitud_reparacion, titulo, mensaje)
             VALUES (NULL, ?, NULL, ?, ?)'
        );
        $stmt->execute([
            $numero,
            'Nuevo pedido #' . $numero,
            $cliente . ' pidió ' . $cantidad . ' × ' .
            $producto['nombre'] . ' (' . moneda($total) . ').'
        ]);
    } catch (PDOException $e) {
        error_log('TEC SERVIS - error de notificación del pedido #' . $numero . ': ' . $e->getMessage());
    }

    flash(
        'ok',
        'Pedido #' . $numero . ' registrado correctamente. Te contactamos al ' . $telefono . '.'
    );

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('TEC SERVIS - error al registrar pedido: ' . $e->getMessage());

    $mensaje = 'No se pudo guardar el pedido en la base de datos.';
    if (MODO_DESARROLLO) {
        $mensaje .= ' Error MySQL: ' . $e->getMessage();
    }

    flash('error', $mensaje);
}

header('Location: ' . $volverA);
exit;
