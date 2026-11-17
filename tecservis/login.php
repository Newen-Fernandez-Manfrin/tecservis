<?php
require_once __DIR__ . '/conexion.php';

/* Si ya hay sesión iniciada, no tiene sentido mostrar el formulario. */
if (usuario_actual()) {
    header('Location: ' . (es_admin() ? 'admin.php' : 'index.php'));
    exit;
}

const MAX_INTENTOS = 5;
const BLOQUEO_SEGUNDOS = 120;

$error = '';
$exito = '';
$usuarioIngresado = '';

if (isset($_GET['registrado'])) {
    $exito = 'Tu cuenta fue creada correctamente. Ya podés iniciar sesión.';
}
if (isset($_GET['requerido'])) {
    $error = 'Necesitás iniciar sesión como Super Usuario para entrar a esa sección.';
}
if (isset($_GET['comprar']) && $error === '') {
    $error = 'Necesitás iniciar sesión para completar la compra. Si no tenés cuenta, podés crear una en un minuto.';
}
if (isset($_GET['reparar']) && $error === '') {
    $error = 'Necesitás iniciar sesión para solicitar una reparación. Si no tenés cuenta, podés crear una en un minuto.';
}

/* Sólo se acepta volver a páginas propias conocidas, para evitar redirecciones externas. */
$volver = $_GET['volver'] ?? ($_POST['volver'] ?? '');
$destinosPermitidos = ['catalogo.php', 'index.php#servicios'];
$volver = in_array($volver, $destinosPermitidos, true) ? $volver : 'index.php';

$intentos = $_SESSION['login_intentos'] ?? 0;
$bloqueoHasta = $_SESSION['login_bloqueo'] ?? 0;
$bloqueado = $bloqueoHasta > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioIngresado = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valido()) {
        $error = 'La sesión expiró. Volvé a intentarlo.';
    } elseif ($bloqueado) {
        $error = 'Demasiados intentos fallidos. Esperá ' . ($bloqueoHasta - time()) . ' segundos.';
    } elseif ($usuarioIngresado === '' || $password === '') {
        $error = 'Completá el usuario y la contraseña.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.id_usuario, u.usuario, u.nombre, u.apellido, u.email, u.password_hash, r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id_rol
             WHERE u.usuario = ? AND u.estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$usuarioIngresado]);
        $u = $stmt->fetch();

        if ($u && password_verify($password, $u['password_hash'])) {
            /* Re-hash si el algoritmo quedó desactualizado. */
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id_usuario']]);
            }

            $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?')
                ->execute([$u['id_usuario']]);

            session_regenerate_id(true);
            unset($_SESSION['login_intentos'], $_SESSION['login_bloqueo']);

            $_SESSION['usuario'] = [
                'id'       => (int) $u['id_usuario'],
                'usuario'  => $u['usuario'],
                'nombre'   => $u['nombre'],
                'apellido' => $u['apellido'],
                'email'    => $u['email'],
                'rol'      => $u['rol'],
            ];

            flash('ok', 'Hola ' . $u['nombre'] . ', iniciaste sesión correctamente.');
            header('Location: ' . ($u['rol'] === ROL_ADMIN ? 'admin.php' : $volver));
            exit;
        }

        /* Mensaje genérico: no revelamos si el usuario existe o no. */
        $intentos++;
        $_SESSION['login_intentos'] = $intentos;
        if ($intentos >= MAX_INTENTOS) {
            $_SESSION['login_bloqueo'] = time() + BLOQUEO_SEGUNDOS;
            $_SESSION['login_intentos'] = 0;
            $error = 'Demasiados intentos fallidos. Esperá ' . BLOQUEO_SEGUNDOS . ' segundos.';
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión | <?= e(NEGOCIO['nombre']) ?></title>
<link rel="stylesheet" href="css/tecservis.css?v=4">
</head>
<body class="login-body">
<div class="login-box">
  <a class="logo centered" href="index.php">
    <span class="logo-mark">TS</span>
    <span class="logo-text"><strong><?= e(NEGOCIO['nombre']) ?></strong><small>Acceso al sistema</small></span>
  </a>

  <h1>Iniciar sesión</h1>
  <p class="login-sub">Ingresá con tu nombre de usuario y contraseña.</p>

  <?php if ($exito): ?><div class="alert-success"><?= e($exito) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="on">
    <?= csrf_campo() ?>
    <input type="hidden" name="volver" value="<?= e($volver) ?>">

    <label for="usuario">Usuario</label>
    <input type="text" id="usuario" name="usuario" value="<?= e($usuarioIngresado) ?>"
           autocomplete="username" maxlength="40" required autofocus>

    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>

    <button class="btn-primary full" type="submit">Ingresar</button>
  </form>

  <div class="divider">¿No tenés una cuenta?</div>
  <a class="btn-outline full" href="registro.php">Crear cuenta</a>
  <a class="back" href="index.php">← Volver al sitio</a>
</div>
</body>
</html>
