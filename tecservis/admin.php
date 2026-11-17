<?php
/**
 * Panel de administración.
 * Sólo accesible para el Super Usuario.
 */
require_once __DIR__ . '/conexion.php';
requiere_admin();

$DIR_IMAGENES = __DIR__ . '/imagenes/productos/';
$TIPOS_PERMITIDOS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
const MAX_IMAGEN_BYTES = 5 * 1024 * 1024;

/**
 * Procesa la imagen subida y devuelve la ruta relativa guardada, o null si no se subió nada.
 */
function guardar_imagen(array $archivo, string $destino, array $tipos): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen (código ' . $archivo['error'] . ').');
    }
    if ($archivo['size'] > MAX_IMAGEN_BYTES) {
        throw new RuntimeException('La imagen no puede superar los 5 MB.');
    }

    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false || !isset($tipos[$info['mime']])) {
        throw new RuntimeException('Formato no permitido. Usá JPG, PNG, WEBP o GIF.');
    }

    if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
        throw new RuntimeException('No existe la carpeta imagenes/productos y no se pudo crear.');
    }

    $nombre = 'producto_' . bin2hex(random_bytes(8)) . '.' . $tipos[$info['mime']];
    if (!move_uploaded_file($archivo['tmp_name'], $destino . $nombre)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }
    return 'productos/' . $nombre;
}

/** Borra el archivo de una imagen si está dentro de la carpeta imagenes/. */
function borrar_imagen(?string $relativa): void
{
    if (!$relativa || str_contains($relativa, '..')) {
        return;
    }
    $ruta = __DIR__ . '/imagenes/' . $relativa;
    if (is_file($ruta) && str_starts_with(realpath($ruta) ?: '', realpath(__DIR__ . '/imagenes') ?: '')) {
        @unlink($ruta);
    }
}

$tab = $_GET['tab'] ?? 'productos';
if (!in_array($tab, ['productos', 'rubros', 'marcas', 'pedidos', 'reparaciones', 'usuarios'], true)) {
    $tab = 'productos';
}

/* ======================= ACCIONES (POST) ======================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_valido()) {
            throw new RuntimeException('La sesión expiró. Volvé a intentarlo.');
        }
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {

            case 'producto_guardar':
                $id          = (int) ($_POST['id_producto'] ?? 0);
                $nombre      = trim((string) ($_POST['nombre'] ?? ''));
                $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
                $idRubro     = (int) ($_POST['id_rubro'] ?? 0);
                $idMarca     = (int) ($_POST['id_marca'] ?? 0);
                $precio      = (float) str_replace(',', '.', (string) ($_POST['precio'] ?? '0'));
                $stock       = max(0, (int) ($_POST['stock'] ?? 0));
                $estado      = ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

                if ($nombre === '' || $idRubro <= 0 || $idMarca <= 0) {
                    throw new RuntimeException('Completá nombre, rubro y marca.');
                }
                if ($precio < 0) {
                    throw new RuntimeException('El precio no puede ser negativo.');
                }

                $imagen = guardar_imagen($_FILES['imagen'] ?? [], $DIR_IMAGENES, $TIPOS_PERMITIDOS);

                if ($id > 0) {
                    $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id_producto = ?');
                    $stmt->execute([$id]);
                    $anterior = $stmt->fetchColumn();

                    if ($imagen !== null) {
                        $pdo->prepare(
                            'UPDATE productos SET nombre=?, descripcion=?, id_rubro=?, id_marca=?,
                             precio=?, stock=?, estado=?, imagen=? WHERE id_producto=?'
                        )->execute([$nombre, $descripcion, $idRubro, $idMarca, $precio, $stock, $estado, $imagen, $id]);
                        if ($anterior && str_starts_with((string) $anterior, 'productos/')) {
                            borrar_imagen((string) $anterior);
                        }
                    } else {
                        $pdo->prepare(
                            'UPDATE productos SET nombre=?, descripcion=?, id_rubro=?, id_marca=?,
                             precio=?, stock=?, estado=? WHERE id_producto=?'
                        )->execute([$nombre, $descripcion, $idRubro, $idMarca, $precio, $stock, $estado, $id]);
                    }
                    flash('ok', 'Producto actualizado correctamente.');
                } else {
                    $pdo->prepare(
                        'INSERT INTO productos (nombre, descripcion, id_rubro, id_marca, precio, stock, imagen, estado)
                         VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([$nombre, $descripcion, $idRubro, $idMarca, $precio, $stock, $imagen, $estado]);
                    flash('ok', 'Producto agregado correctamente.');
                }
                break;

            case 'producto_estado':
                $id = (int) ($_POST['id_producto'] ?? 0);
                $pdo->prepare(
                    "UPDATE productos
                     SET estado = IF(estado='activo','inactivo','activo')
                     WHERE id_producto = ?"
                )->execute([$id]);
                flash('ok', 'Se cambió la visibilidad del producto.');
                break;

            case 'producto_eliminar':
                $id = (int) ($_POST['id_producto'] ?? 0);
                $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id_producto = ?');
                $stmt->execute([$id]);
                $imagen = $stmt->fetchColumn();
                $pdo->prepare('DELETE FROM productos WHERE id_producto = ?')->execute([$id]);
                if ($imagen && str_starts_with((string) $imagen, 'productos/')) {
                    borrar_imagen((string) $imagen);
                }
                flash('ok', 'Producto eliminado.');
                break;

            case 'rubro_guardar':
            case 'marca_guardar':
                $esRubro = $accion === 'rubro_guardar';
                $tabla   = $esRubro ? 'rubros' : 'marcas';
                $pk      = $esRubro ? 'id_rubro' : 'id_marca';
                $id      = (int) ($_POST[$pk] ?? 0);
                $nombre  = trim((string) ($_POST['nombre'] ?? ''));
                $desc    = trim((string) ($_POST['descripcion'] ?? ''));

                if ($nombre === '') {
                    throw new RuntimeException('El nombre es obligatorio.');
                }
                if ($id > 0) {
                    $pdo->prepare("UPDATE {$tabla} SET nombre=?, descripcion=? WHERE {$pk}=?")
                        ->execute([$nombre, $desc, $id]);
                    flash('ok', ($esRubro ? 'Rubro' : 'Marca') . ' actualizado correctamente.');
                } else {
                    $pdo->prepare("INSERT INTO {$tabla} (nombre, descripcion) VALUES (?,?)")
                        ->execute([$nombre, $desc]);
                    flash('ok', ($esRubro ? 'Rubro' : 'Marca') . ' agregado correctamente.');
                }
                break;

            case 'rubro_eliminar':
            case 'marca_eliminar':
                $esRubro = $accion === 'rubro_eliminar';
                $tabla   = $esRubro ? 'rubros' : 'marcas';
                $pk      = $esRubro ? 'id_rubro' : 'id_marca';
                $id      = (int) ($_POST[$pk] ?? 0);

                $usados = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE {$pk} = ?");
                $usados->execute([$id]);
                if ((int) $usados->fetchColumn() > 0) {
                    throw new RuntimeException('No se puede eliminar: hay productos asociados. Cambialos de categoría primero.');
                }
                $pdo->prepare("DELETE FROM {$tabla} WHERE {$pk} = ?")->execute([$id]);
                flash('ok', 'Registro eliminado.');
                break;

            case 'reparacion_estado':
                $id = (int) ($_POST['id_solicitud'] ?? 0);
                $nuevo = $_POST['estado'] ?? '';
                if (!in_array($nuevo, ['pendiente', 'en_revision', 'presupuestado', 'reparado', 'cancelado'], true)) {
                    throw new RuntimeException('Estado de reparación no válido.');
                }
                $stmt = $pdo->prepare('SELECT id_usuario, tipo_dispositivo, marca_modelo FROM solicitudes_reparacion WHERE id_solicitud = ?');
                $stmt->execute([$id]);
                $rep = $stmt->fetch();

                if (!$rep) {
                    throw new RuntimeException('Solicitud de reparación no encontrada.');
                }

                $pdo->prepare('UPDATE solicitudes_reparacion SET estado = ? WHERE id_solicitud = ?')
                    ->execute([$nuevo, $id]);

                if (!empty($rep['id_usuario'])) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO notificaciones
                             (id_usuario, id_pedido, id_solicitud_reparacion, titulo, mensaje)
                             VALUES (?, NULL, ?, ?, ?)'
                        )->execute([
                            (int) $rep['id_usuario'],
                            $id,
                            'Actualización de reparación #' . $id,
                            'Tu reparación de ' . $rep['tipo_dispositivo'] . ' — ' . $rep['marca_modelo']
                            . ' ahora está: ' . str_replace('_', ' ', $nuevo) . '.'
                        ]);
                    } catch (PDOException $e) {
                        error_log('TEC SERVIS - error al avisar al usuario sobre reparación #' . $id . ': ' . $e->getMessage());
                    }
                }

                flash('ok', 'Solicitud de reparación #' . $id . ' marcada como ' . str_replace('_', ' ', $nuevo) . '.');
                break;

            case 'reparacion_presupuesto':
                $id = (int) ($_POST['id_solicitud'] ?? 0);
                $presupuestoTexto = trim((string) ($_POST['presupuesto'] ?? ''));
                $detalle = trim((string) ($_POST['detalle_presupuesto'] ?? ''));

                if ($id <= 0) {
                    throw new RuntimeException('Solicitud de reparación no válida.');
                }
                if ($presupuestoTexto === '') {
                    throw new RuntimeException('Ingresá un presupuesto.');
                }
                $presupuesto = str_replace(',', '.', $presupuestoTexto);
                if (!is_numeric($presupuesto) || (float) $presupuesto < 0) {
                    throw new RuntimeException('El presupuesto debe ser un importe válido.');
                }
                if (mb_strlen($detalle) > 3000) {
                    throw new RuntimeException('El detalle del presupuesto es demasiado largo.');
                }

                $stmt = $pdo->prepare('SELECT id_usuario, tipo_dispositivo, marca_modelo FROM solicitudes_reparacion WHERE id_solicitud = ?');
                $stmt->execute([$id]);
                $rep = $stmt->fetch();

                if (!$rep) {
                    throw new RuntimeException('Solicitud de reparación no encontrada.');
                }

                $pdo->prepare(
                    "UPDATE solicitudes_reparacion
                     SET presupuesto = ?, detalle_presupuesto = ?, estado = 'presupuestado'
                     WHERE id_solicitud = ?"
                )->execute([(float) $presupuesto, $detalle !== '' ? $detalle : null, $id]);

                if (!empty($rep['id_usuario'])) {
                    $mensajeAviso = 'Tu reparación de ' . $rep['tipo_dispositivo'] . ' — ' . $rep['marca_modelo']
                        . ' tiene un presupuesto de ' . moneda($presupuesto) . '.';
                    if ($detalle !== '') {
                        $mensajeAviso .= ' Detalle: ' . $detalle;
                    }

                    try {
                        $pdo->prepare(
                            'INSERT INTO notificaciones
                             (id_usuario, id_pedido, id_solicitud_reparacion, titulo, mensaje)
                             VALUES (?, NULL, ?, ?, ?)'
                        )->execute([
                            (int) $rep['id_usuario'],
                            $id,
                            'Presupuesto de reparación #' . $id,
                            $mensajeAviso
                        ]);
                    } catch (PDOException $e) {
                        error_log('TEC SERVIS - error al avisar presupuesto de reparación #' . $id . ': ' . $e->getMessage());
                    }
                }

                flash('ok', 'Presupuesto de reparación #' . $id . ' guardado.');
                break;

            case 'pedido_estado':
                $id = (int) ($_POST['id_pedido'] ?? 0);
                $nuevo = $_POST['estado'] ?? '';
                if (!in_array($nuevo, ['pendiente', 'confirmado', 'entregado', 'cancelado'], true)) {
                    throw new RuntimeException('Estado de pedido no válido.');
                }
                $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id_pedido = ?')->execute([$nuevo, $id]);
                flash('ok', 'Pedido #' . $id . ' marcado como ' . $nuevo . '.');
                break;

            case 'usuario_estado':
                $id = (int) ($_POST['id_usuario'] ?? 0);
                if ($id === (usuario_actual()['id'] ?? 0)) {
                    throw new RuntimeException('No podés desactivar tu propia cuenta.');
                }
                $pdo->prepare(
                    "UPDATE usuarios SET estado = IF(estado='activo','inactivo','activo') WHERE id_usuario = ?"
                )->execute([$id]);
                flash('ok', 'Se actualizó el estado del usuario.');
                break;

            case 'usuario_rol':
                $id  = (int) ($_POST['id_usuario'] ?? 0);
                $rol = (int) ($_POST['id_rol'] ?? 1);
                if ($id === (usuario_actual()['id'] ?? 0)) {
                    throw new RuntimeException('No podés cambiar tu propio rol.');
                }
                $pdo->prepare('UPDATE usuarios SET id_rol = ? WHERE id_usuario = ?')->execute([$rol, $id]);
                flash('ok', 'Se actualizó el rol del usuario.');
                break;

            default:
                throw new RuntimeException('Acción no reconocida.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', $e->getCode() === '23000'
            ? 'Ya existe un registro con ese nombre o está siendo utilizado.'
            : 'Error de base de datos. Revisá los datos ingresados.');
    }

    /* Patrón POST → Redirect → GET: evita reenviar el formulario al refrescar. */
    header('Location: admin.php?tab=' . urlencode($tab));
    exit;
}

/* ========================= DATOS (GET) ========================= */

$productos = $pdo->query('SELECT * FROM vista_productos ORDER BY id_producto DESC')->fetchAll();
$rubros    = $pdo->query('SELECT * FROM rubros ORDER BY nombre')->fetchAll();
$marcas    = $pdo->query('SELECT * FROM marcas ORDER BY nombre')->fetchAll();
$pedidos   = $pdo->query('SELECT * FROM pedidos ORDER BY id_pedido DESC LIMIT 200')->fetchAll();
$reparaciones = $pdo->query('SELECT * FROM solicitudes_reparacion ORDER BY id_solicitud DESC LIMIT 200')->fetchAll();
$usuarios  = $pdo->query(
    'SELECT u.*, r.nombre AS rol FROM usuarios u
     INNER JOIN roles r ON u.id_rol = r.id_rol
     ORDER BY u.id_usuario'
)->fetchAll();
$roles = $pdo->query('SELECT * FROM roles ORDER BY id_rol')->fetchAll();

$resumen = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM productos)                            AS productos,
        (SELECT COUNT(*) FROM productos WHERE stock = 0)            AS sin_stock,
        (SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente')   AS pendientes,
        (SELECT COUNT(*) FROM solicitudes_reparacion WHERE estado = 'pendiente') AS reparaciones_pendientes,
        (SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado <> 'cancelado') AS facturado"
)->fetch();

/* Producto / rubro / marca en edición */
$editarProducto = null;
if (($id = (int) ($_GET['editar'] ?? 0)) > 0) {
    foreach ($productos as $p) {
        if ((int) $p['id_producto'] === $id) {
            $editarProducto = $p;
            break;
        }
    }
}
$editarRubro = null;
if (($id = (int) ($_GET['editar_rubro'] ?? 0)) > 0) {
    foreach ($rubros as $r) {
        if ((int) $r['id_rubro'] === $id) { $editarRubro = $r; break; }
    }
}
$editarMarca = null;
if (($id = (int) ($_GET['editar_marca'] ?? 0)) > 0) {
    foreach ($marcas as $m) {
        if ((int) $m['id_marca'] === $id) { $editarMarca = $m; break; }
    }
}

$estadosPedido = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado'];
$mensaje = flash_tomar();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de administración | <?= e(NEGOCIO['nombre']) ?></title>
<link rel="stylesheet" href="css/tecservis.css?v=4">
</head>
<body class="admin-body" data-token="<?= e(csrf_token()) ?>">

<header class="header">
  <a class="logo" href="admin.php">
    <span class="logo-mark">TS</span>
    <span class="logo-text"><strong><?= e(NEGOCIO['nombre']) ?></strong><small>Panel de administración</small></span>
  </a>
  <nav class="nav-principal">
    <div class="campana-wrap">
      <button class="btn-campana" id="btnNotificaciones" type="button" aria-label="Notificaciones">
        🔔<span class="badge-campana" id="badgeNotificaciones" hidden>0</span>
      </button>
      <div class="panel-notificaciones" id="panelNotificaciones" hidden>
        <div class="panel-notificaciones-head"><h3>Notificaciones</h3></div>
        <div class="panel-notificaciones-lista" id="listaNotificaciones">
          <p class="notif-vacio">Cargando…</p>
        </div>
      </div>
    </div>
    <span class="nav-usuario">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr(usuario_actual()['usuario'], 0, 1))) ?></span>
      <?= e(usuario_actual()['usuario']) ?>
    </span>
    <a href="index.php">Ver sitio</a>
    <a class="btn-login btn-danger" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="admin">
  <section class="admin-title">
    <p class="tag">Administración</p>
    <h1>Panel de control</h1>
  </section>

  <?php if ($mensaje): ?>
    <div class="alert-admin <?= $mensaje['tipo'] === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= e($mensaje['texto']) ?></div>
  <?php endif; ?>

  <div class="stats">
    <div><strong><?= (int) $resumen['productos'] ?></strong><span>Productos cargados</span></div>
    <div><strong><?= (int) $resumen['sin_stock'] ?></strong><span>Sin stock</span></div>
    <div><strong><?= (int) $resumen['pendientes'] ?></strong><span>Pedidos pendientes</span></div>
    <div><strong><?= (int) $resumen['reparaciones_pendientes'] ?></strong><span>Reparaciones pendientes</span></div>
    <div><strong><?= moneda($resumen['facturado']) ?></strong><span>Total en pedidos</span></div>
  </div>

  <nav class="admin-tabs">
    <a class="<?= $tab === 'productos' ? 'active' : '' ?>" href="?tab=productos">Productos</a>
    <a class="<?= $tab === 'pedidos'   ? 'active' : '' ?>" href="?tab=pedidos">Pedidos</a>
    <a class="<?= $tab === 'reparaciones' ? 'active' : '' ?>" href="?tab=reparaciones">Reparaciones</a>
    <a class="<?= $tab === 'rubros'    ? 'active' : '' ?>" href="?tab=rubros">Rubros</a>
    <a class="<?= $tab === 'marcas'    ? 'active' : '' ?>" href="?tab=marcas">Marcas</a>
    <a class="<?= $tab === 'usuarios'  ? 'active' : '' ?>" href="?tab=usuarios">Usuarios</a>
  </nav>

  <!-- ============================ PRODUCTOS ============================ -->
  <?php if ($tab === 'productos'): ?>
  <section class="admin-section">
    <div class="admin-card">
      <h2><?= $editarProducto ? 'Editar producto #' . (int) $editarProducto['id_producto'] : 'Agregar producto' ?></h2>
      <form class="admin-form" method="POST" enctype="multipart/form-data">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="producto_guardar">
        <input type="hidden" name="id_producto" value="<?= (int) ($editarProducto['id_producto'] ?? 0) ?>">

        <div><label>Nombre</label>
          <input name="nombre" maxlength="120" required value="<?= e($editarProducto['producto'] ?? '') ?>"></div>

        <div><label>Precio</label>
          <input type="number" step="0.01" min="0" name="precio" required
                 value="<?= e($editarProducto['precio'] ?? '') ?>"></div>

        <div><label>Rubro</label>
          <select name="id_rubro" required>
            <option value="">Seleccionar…</option>
            <?php foreach ($rubros as $r): ?>
              <option value="<?= (int) $r['id_rubro'] ?>"
                <?= (int) ($editarProducto['id_rubro'] ?? 0) === (int) $r['id_rubro'] ? 'selected' : '' ?>>
                <?= e($r['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select></div>

        <div><label>Marca</label>
          <select name="id_marca" required>
            <option value="">Seleccionar…</option>
            <?php foreach ($marcas as $m): ?>
              <option value="<?= (int) $m['id_marca'] ?>"
                <?= (int) ($editarProducto['id_marca'] ?? 0) === (int) $m['id_marca'] ? 'selected' : '' ?>>
                <?= e($m['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select></div>

        <div><label>Stock</label>
          <input type="number" min="0" name="stock" required value="<?= (int) ($editarProducto['stock'] ?? 0) ?>"></div>

        <div><label>Estado</label>
          <select name="estado">
            <option value="activo"   <?= ($editarProducto['estado'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Visible en el sitio</option>
            <option value="inactivo" <?= ($editarProducto['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Oculto</option>
          </select></div>

        <div class="wide"><label>Descripción</label>
          <textarea name="descripcion" maxlength="800"><?= e($editarProducto['descripcion'] ?? '') ?></textarea></div>

        <div class="wide"><label>Foto <span class="opcional">(JPG, PNG, WEBP o GIF · máx. 5 MB)</span></label>
          <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
          <?php if ($img = imagen_producto($editarProducto['imagen'] ?? null)): ?>
            <div class="preview-actual">
              <img src="<?= e($img) ?>" alt="">
              <small>Imagen actual. Subí otra sólo si querés reemplazarla.</small>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn-primary" type="submit"><?= $editarProducto ? 'Guardar cambios' : 'Agregar producto' ?></button>
          <?php if ($editarProducto): ?><a class="btn-outline" href="?tab=productos">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="admin-card admin-table">
      <h2>Productos cargados (<?= count($productos) ?>)</h2>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th>Foto</th><th>Producto</th><th>Rubro</th><th>Marca</th>
            <th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($productos as $p): ?>
            <tr>
              <td><?php if ($img = imagen_producto($p['imagen'])): ?>
                    <img class="thumb" src="<?= e($img) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
              <td><?= e($p['producto']) ?></td>
              <td><?= e($p['rubro']) ?></td>
              <td><?= e($p['marca']) ?></td>
              <td><?= moneda($p['precio']) ?></td>
              <td class="<?= (int) $p['stock'] === 0 ? 'texto-alerta' : '' ?>"><?= (int) $p['stock'] ?></td>
              <td><span class="pill <?= $p['estado'] === 'activo' ? 'pill-ok' : 'pill-off' ?>"><?= e($p['estado']) ?></span></td>
              <td>
                <div class="fila-acciones">
                  <a class="btn-small" href="?tab=productos&editar=<?= (int) $p['id_producto'] ?>">Editar</a>
                  <form method="POST">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="producto_estado">
                    <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                    <button class="btn-small"><?= $p['estado'] === 'activo' ? 'Ocultar' : 'Mostrar' ?></button>
                  </form>
                  <form method="POST" onsubmit="return confirm('¿Eliminar definitivamente este producto?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="producto_eliminar">
                    <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                    <button class="btn-small danger">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================= PEDIDOS ============================= -->
  <?php if ($tab === 'pedidos'): ?>
  <section class="admin-section">
    <div class="admin-card admin-table">
      <h2>Pedidos recibidos (<?= count($pedidos) ?>)</h2>
      <?php if (!$pedidos): ?>
        <p class="vacio">Todavía no hay pedidos registrados.</p>
      <?php else: ?>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th>#</th><th>Fecha</th><th>Cliente</th><th>Contacto</th><th>Producto</th>
            <th>Cant.</th><th>Total</th><th>Pago</th><th>Estado</th>
          </tr></thead>
          <tbody>
          <?php foreach ($pedidos as $ped): ?>
            <tr>
              <td><?= (int) $ped['id_pedido'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ped['fecha'])) ?></td>
              <td>
                <strong><?= e($ped['cliente']) ?></strong>
                <small class="celda-sub"><?= e($ped['direccion']) ?></small>
              </td>
              <td>
                <?= e($ped['telefono']) ?>
                <?php if ($ped['email']): ?><small class="celda-sub"><?= e($ped['email']) ?></small><?php endif; ?>
              </td>
              <td><?= e($ped['producto']) ?></td>
              <td><?= (int) $ped['cantidad'] ?></td>
              <td><?= moneda($ped['total']) ?></td>
              <td>
                <?= e($ped['alias_pagador'] ?: '—') ?>
                <?php if ($ped['comprobante']): ?><small class="celda-sub">N.º <?= e($ped['comprobante']) ?></small><?php endif; ?>
              </td>
              <td>
                <form method="POST" class="form-estado">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="accion" value="pedido_estado">
                  <input type="hidden" name="id_pedido" value="<?= (int) $ped['id_pedido'] ?>">
                  <select name="estado" onchange="this.form.submit()">
                    <?php foreach ($estadosPedido as $valor => $texto): ?>
                      <option value="<?= $valor ?>" <?= $ped['estado'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ========================== REPARACIONES =========================== -->
  <?php if ($tab === 'reparaciones'): ?>
  <section class="admin-section">
    <div class="admin-card admin-table">
      <h2>Solicitudes de reparación (<?= count($reparaciones) ?>)</h2>
      <?php if (!$reparaciones): ?>
        <p class="vacio">Todavía no hay solicitudes de reparación.</p>
      <?php else: ?>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th>#</th><th>Fecha</th><th>Cliente</th><th>Contacto</th>
            <th>Dispositivo</th><th>Problema</th><th>Foto</th><th>Presupuesto</th><th>Estado</th>
          </tr></thead>
          <tbody>
          <?php foreach ($reparaciones as $rep): ?>
            <tr>
              <td><?= (int) $rep['id_solicitud'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($rep['fecha'])) ?></td>
              <td><strong><?= e($rep['cliente']) ?></strong></td>
              <td>
                <?= e($rep['telefono']) ?>
                <small class="celda-sub"><?= e($rep['email']) ?></small>
              </td>
              <td>
                <?= e($rep['tipo_dispositivo']) ?>
                <small class="celda-sub"><?= e($rep['marca_modelo']) ?></small>
              </td>
              <td class="celda-descripcion"><?= nl2br(e($rep['descripcion'])) ?></td>
              <td>
                <?php if ($rep['imagen']): ?>
                  <a class="btn-small" href="imagenes/<?= e($rep['imagen']) ?>" target="_blank" rel="noopener">Ver foto</a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <form method="POST" class="form-presupuesto">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="accion" value="reparacion_presupuesto">
                  <input type="hidden" name="id_solicitud" value="<?= (int) $rep['id_solicitud'] ?>">
                  <input type="number" name="presupuesto" min="0" step="0.01"
                         value="<?= ($rep['presupuesto'] ?? null) !== null ? e($rep['presupuesto']) : '' ?>"
                         placeholder="Importe" required>
                  <textarea name="detalle_presupuesto" rows="2" maxlength="3000"
                            placeholder="Detalle para el cliente"><?= e($rep['detalle_presupuesto'] ?? '') ?></textarea>
                  <button class="btn-small" type="submit">Guardar presupuesto</button>
                </form>
              </td>
              <td>
                <form method="POST" class="form-estado">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="accion" value="reparacion_estado">
                  <input type="hidden" name="id_solicitud" value="<?= (int) $rep['id_solicitud'] ?>">
                  <select name="estado" onchange="this.form.submit()">
                    <?php
                      $estadosReparacion = [
                        'pendiente' => 'Pendiente',
                        'en_revision' => 'En revisión',
                        'presupuestado' => 'Presupuestado',
                        'reparado' => 'Reparado',
                        'cancelado' => 'Cancelado'
                      ];
                      foreach ($estadosReparacion as $valor => $texto):
                    ?>
                      <option value="<?= $valor ?>" <?= $rep['estado'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ======================== RUBROS Y MARCAS ========================= -->
  <?php if ($tab === 'rubros' || $tab === 'marcas'): ?>
    <?php
      $esRubro   = $tab === 'rubros';
      $titulo    = $esRubro ? 'rubro' : 'marca';
      $lista     = $esRubro ? $rubros : $marcas;
      $pk        = $esRubro ? 'id_rubro' : 'id_marca';
      $editando  = $esRubro ? $editarRubro : $editarMarca;
      $paramEdit = $esRubro ? 'editar_rubro' : 'editar_marca';
    ?>
  <section class="admin-section">
    <div class="admin-card">
      <h2><?= $editando ? 'Editar ' . $titulo : 'Agregar ' . $titulo ?></h2>
      <form class="admin-form" method="POST">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="<?= $esRubro ? 'rubro_guardar' : 'marca_guardar' ?>">
        <input type="hidden" name="<?= $pk ?>" value="<?= (int) ($editando[$pk] ?? 0) ?>">
        <div><label>Nombre</label>
          <input name="nombre" maxlength="80" required value="<?= e($editando['nombre'] ?? '') ?>"></div>
        <div class="wide"><label>Descripción</label>
          <textarea name="descripcion" maxlength="255"><?= e($editando['descripcion'] ?? '') ?></textarea></div>
        <div class="form-actions">
          <button class="btn-primary"><?= $editando ? 'Guardar cambios' : 'Agregar' ?></button>
          <?php if ($editando): ?><a class="btn-outline" href="?tab=<?= $tab ?>">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="admin-card admin-table">
      <h2><?= $esRubro ? 'Rubros' : 'Marcas' ?> (<?= count($lista) ?>)</h2>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Nombre</th><th>Descripción</th><th>Productos</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php foreach ($lista as $item):
            $cantidad = 0;
            foreach ($productos as $p) {
              if ((int) $p[$pk] === (int) $item[$pk]) { $cantidad++; }
            } ?>
            <tr>
              <td><?= e($item['nombre']) ?></td>
              <td><?= e($item['descripcion']) ?></td>
              <td><?= $cantidad ?></td>
              <td>
                <div class="fila-acciones">
                  <a class="btn-small" href="?tab=<?= $tab ?>&<?= $paramEdit ?>=<?= (int) $item[$pk] ?>">Editar</a>
                  <form method="POST" onsubmit="return confirm('¿Eliminar este registro?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="<?= $esRubro ? 'rubro_eliminar' : 'marca_eliminar' ?>">
                    <input type="hidden" name="<?= $pk ?>" value="<?= (int) $item[$pk] ?>">
                    <button class="btn-small danger" <?= $cantidad > 0 ? 'disabled title="Tiene productos asociados"' : '' ?>>Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================ USUARIOS ============================ -->
  <?php if ($tab === 'usuarios'): ?>
  <section class="admin-section">
    <div class="admin-card admin-table">
      <h2>Usuarios registrados (<?= count($usuarios) ?>)</h2>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th>Usuario</th><th>Nombre</th><th>Contacto</th><th>Rol</th>
            <th>Estado</th><th>Último acceso</th><th>Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($usuarios as $usr): $propio = (int) $usr['id_usuario'] === (usuario_actual()['id'] ?? 0); ?>
            <tr>
              <td><strong><?= e($usr['usuario']) ?></strong><?php if ($propio): ?> <small>(vos)</small><?php endif; ?></td>
              <td><?= e($usr['nombre'] . ' ' . $usr['apellido']) ?></td>
              <td><?= e($usr['email'] ?: '—') ?><?php if ($usr['telefono']): ?><small class="celda-sub"><?= e($usr['telefono']) ?></small><?php endif; ?></td>
              <td>
                <form method="POST" class="form-estado">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="accion" value="usuario_rol">
                  <input type="hidden" name="id_usuario" value="<?= (int) $usr['id_usuario'] ?>">
                  <select name="id_rol" onchange="this.form.submit()" <?= $propio ? 'disabled' : '' ?>>
                    <?php foreach ($roles as $rol): ?>
                      <option value="<?= (int) $rol['id_rol'] ?>" <?= $usr['rol'] === $rol['nombre'] ? 'selected' : '' ?>>
                        <?= e($rol['nombre']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td><span class="pill <?= $usr['estado'] === 'activo' ? 'pill-ok' : 'pill-off' ?>"><?= e($usr['estado']) ?></span></td>
              <td><?= $usr['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usr['ultimo_acceso'])) : 'Nunca' ?></td>
              <td>
                <?php if (!$propio): ?>
                  <form method="POST">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="usuario_estado">
                    <input type="hidden" name="id_usuario" value="<?= (int) $usr['id_usuario'] ?>">
                    <button class="btn-small"><?= $usr['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                  </form>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>

<script src="js/admin.js"></script>
</body>
</html>
