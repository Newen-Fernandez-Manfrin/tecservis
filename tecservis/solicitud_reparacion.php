<?php
/**
 * Registra una solicitud de reparación y crea una notificación para el panel admin.
 */
require_once __DIR__ . '/conexion.php';

/* La solicitud de reparación requiere una cuenta iniciada, igual que la compra.
   Se valida acá también por si alguien arma el POST a mano saltándose la interfaz. */
if (!usuario_actual()) {
    flash('error', 'Necesitás iniciar sesión para solicitar una reparación.');
    header('Location: login.php?reparar=1&volver=' . urlencode('index.php#servicios'));
    exit;
}

/* Si la foto supera post_max_size, PHP descarta el formulario entero (POST y FILES vacíos). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'El formulario supera el tamaño que acepta el servidor (' . ini_get('post_max_size')
        . '). Probá con una foto más liviana o enviá la solicitud sin foto.');
    header('Location: index.php#servicios');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    flash('error', 'No se pudo procesar la solicitud. Volvé a intentarlo.');
    header('Location: index.php#servicios');
    exit;
}

$cliente          = trim((string) ($_POST['cliente'] ?? ''));
$email            = trim((string) ($_POST['email'] ?? ''));
$telefono         = trim((string) ($_POST['telefono'] ?? ''));
$tipoDispositivo  = trim((string) ($_POST['tipo_dispositivo'] ?? ''));
$marcaModelo      = trim((string) ($_POST['marca_modelo'] ?? ''));
$descripcion      = trim((string) ($_POST['descripcion'] ?? ''));

$tiposPermitidos = [
    'Notebook', 'PC de escritorio', 'Monitor',
    'Periférico', 'Celular', 'Otro'
];

if ($cliente === '' || $email === '' || $telefono === '' ||
    $tipoDispositivo === '' || $marcaModelo === '' || $descripcion === '') {
    flash('error', 'Completá todos los campos obligatorios de la reparación.');
    header('Location: index.php#servicios');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'El correo electrónico ingresado no es válido.');
    header('Location: index.php#servicios');
    exit;
}

if (!in_array($tipoDispositivo, $tiposPermitidos, true)) {
    flash('error', 'El tipo de dispositivo seleccionado no es válido.');
    header('Location: index.php#servicios');
    exit;
}

if (mb_strlen($descripcion) > 3000) {
    flash('error', 'La descripción es demasiado larga.');
    header('Location: index.php#servicios');
    exit;
}

/** Explica por qué falló la subida de un archivo. */
function motivo_error_subida(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE
            => 'La foto supera el tamaño que acepta el servidor (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_PARTIAL
            => 'La foto llegó incompleta.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION
            => 'El servidor no pudo guardar la foto (carpeta temporal o permisos).',
        default
            => 'No se pudo subir la foto.',
    };
}

function guardar_imagen_reparacion(array $archivo): ?string
{
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(motivo_error_subida((int) $error));
    }
    if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La foto no puede superar los 5 MB.');
    }

    $tipos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false || !isset($tipos[$info['mime']])) {
        throw new RuntimeException('La foto debe ser JPG, PNG o WEBP.');
    }

    $destino = __DIR__ . '/imagenes/reparaciones/';
    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        throw new RuntimeException('No se pudo crear la carpeta imagenes/reparaciones.');
    }

    $nombre = 'reparacion_' . bin2hex(random_bytes(10)) . '.' . $tipos[$info['mime']];
    if (!move_uploaded_file($archivo['tmp_name'], $destino . $nombre)) {
        throw new RuntimeException('No se pudo guardar la foto (revisá los permisos de imagenes/reparaciones).');
    }

    return 'reparaciones/' . $nombre;
}

$imagen = null;
$avisoFoto = '';

/* Un problema con la foto no debe hacer perder la solicitud: se guarda sin foto y se avisa. */
try {
    $imagen = guardar_imagen_reparacion($_FILES['imagen'] ?? []);
} catch (RuntimeException $e) {
    $avisoFoto = $e->getMessage();
    $imagen = null;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO solicitudes_reparacion
         (id_usuario, cliente, email, telefono, tipo_dispositivo, marca_modelo, descripcion, imagen, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        usuario_actual()['id'] ?? null,
        $cliente,
        $email,
        $telefono,
        $tipoDispositivo,
        $marcaModelo,
        $descripcion,
        $imagen,
        'pendiente'
    ]);

    $idSolicitud = (int) $pdo->lastInsertId();

    $mensaje = "Cliente: {$cliente}\n"
        . "Teléfono: {$telefono}\n"
        . "Correo: {$email}\n"
        . "Dispositivo: {$tipoDispositivo}\n"
        . "Marca y modelo: {$marcaModelo}\n"
        . "Descripción: {$descripcion}\n"
        . "Foto adjunta: " . ($imagen ? 'Sí' : 'No');

    /* La solicitud ya quedó guardada. El aviso al Super Usuario es independiente. */
    try {
        $pdo->prepare(
            'INSERT INTO notificaciones
             (id_usuario, id_pedido, id_solicitud_reparacion, titulo, mensaje)
             VALUES (NULL, NULL, ?, ?, ?)'
        )->execute([
            $idSolicitud,
            'Nueva solicitud de reparación #' . $idSolicitud,
            $mensaje
        ]);
    } catch (PDOException $e) {
        error_log('TEC SERVIS - error de notificación de reparación #' . $idSolicitud . ': ' . $e->getMessage());
    }

    $texto = 'Solicitud de reparación #' . $idSolicitud . ' enviada correctamente. Te contactaremos para coordinar la revisión.';
    if ($avisoFoto !== '') {
        $texto .= ' Aviso: ' . $avisoFoto . ' La solicitud quedó guardada sin foto; podés enviarla por WhatsApp.';
    }
    flash('ok', $texto);
} catch (PDOException $e) {
    if ($imagen) {
        $ruta = __DIR__ . '/imagenes/' . $imagen;
        if (is_file($ruta)) @unlink($ruta);
    }
    error_log('TEC SERVIS - error al registrar reparación: ' . $e->getMessage());
    $mensajeError = 'No se pudo guardar la solicitud en la base de datos.';
    if (MODO_DESARROLLO) {
        $mensajeError .= ' Error MySQL: ' . $e->getMessage();
    }
    flash('error', $mensajeError);
}

header('Location: index.php#servicios');
exit;
