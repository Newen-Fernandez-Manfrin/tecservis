<?php
require_once __DIR__ . '/conexion.php';

if (usuario_actual()) {
    header('Location: index.php');
    exit;
}

$errores = [];
$datos = ['usuario' => '', 'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $_) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $repetir  = (string) ($_POST['password_repetir'] ?? '');

    if (!csrf_valido()) {
        $errores[] = 'La sesión expiró. Volvé a enviar el formulario.';
    }
    if ($datos['usuario'] === '' || $datos['nombre'] === '' || $datos['apellido'] === '') {
        $errores[] = 'Completá usuario, nombre y apellido.';
    }
    if ($datos['usuario'] !== '' && !preg_match('/^[a-zA-Z0-9._-]{4,40}$/', $datos['usuario'])) {
        $errores[] = 'El usuario debe tener entre 4 y 40 caracteres y sólo letras, números, punto, guion o guion bajo.';
    }
    if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $repetir) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ?');
        $stmt->execute([$datos['usuario']]);
        if ($stmt->fetch()) {
            $errores[] = 'Ese nombre de usuario ya está en uso. Elegí otro.';
        }
    }

    if (!$errores) {
        try {
            $pdo->prepare(
                'INSERT INTO usuarios (usuario, nombre, apellido, email, telefono, password_hash, id_rol)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $datos['usuario'],
                $datos['nombre'],
                $datos['apellido'],
                $datos['email'] !== '' ? $datos['email'] : null,
                $datos['telefono'] !== '' ? $datos['telefono'] : null,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: login.php?registrado=1');
            exit;
        } catch (PDOException $e) {
            $errores[] = 'No se pudo crear la cuenta. Intentá nuevamente.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crear cuenta | <?= e(NEGOCIO['nombre']) ?></title>
<link rel="stylesheet" href="css/tecservis.css?v=4">
</head>
<body class="login-body">
<div class="login-box ancho">
  <a class="logo centered" href="index.php">
    <span class="logo-mark">TS</span>
    <span class="logo-text"><strong><?= e(NEGOCIO['nombre']) ?></strong><small>Crear cuenta</small></span>
  </a>

  <h1>Crear cuenta</h1>
  <p class="login-sub">Registrate para agilizar tus compras y seguir tus pedidos.</p>

  <?php if ($errores): ?>
    <div class="error">
      <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_campo() ?>

    <label for="usuario">Usuario</label>
    <input type="text" id="usuario" name="usuario" value="<?= e($datos['usuario']) ?>"
           maxlength="40" autocomplete="username" required>
    <small class="ayuda">Con este nombre vas a iniciar sesión. Mínimo 4 caracteres, sin espacios.</small>

    <div class="form-row">
      <div>
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>" maxlength="80" required>
      </div>
      <div>
        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" value="<?= e($datos['apellido']) ?>" maxlength="80" required>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="email">Correo <span class="opcional">(opcional)</span></label>
        <input type="email" id="email" name="email" value="<?= e($datos['email']) ?>" maxlength="120">
      </div>
      <div>
        <label for="telefono">Teléfono <span class="opcional">(opcional)</span></label>
        <input type="text" id="telefono" name="telefono" value="<?= e($datos['telefono']) ?>" maxlength="40">
      </div>
    </div>

    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>

    <label for="password_repetir">Repetir contraseña</label>
    <input type="password" id="password_repetir" name="password_repetir" minlength="8" autocomplete="new-password" required>

    <button class="btn-primary full" type="submit">Crear cuenta</button>
  </form>

  <div class="divider">¿Ya tenés una cuenta?</div>
  <a class="btn-outline full" href="login.php">Iniciar sesión</a>
  <a class="back" href="index.php">← Volver al sitio</a>
</div>
</body>
</html>
