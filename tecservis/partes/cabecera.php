<?php
/**
 * Cabecera común del sitio.
 * Antes de incluirla se pueden definir:
 *   $titulo        -> título de la pestaña
 *   $activo        -> 'inicio' | 'productos' | 'servicios' | 'contacto'
 *   $claseBody     -> clase extra para <body>
 */
$titulo    = $titulo    ?? NEGOCIO['nombre'];
$activo    = $activo    ?? '';
$claseBody = $claseBody ?? '';
$u         = usuario_actual();
$enInicio  = basename($_SERVER['SCRIPT_NAME']) === 'index.php';
$base      = $enInicio ? '' : 'index.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="TEC SERVIS: venta de hardware, componentes de PC y servicio técnico en Mar del Plata.">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($titulo) ?> | <?= e(NEGOCIO['nombre']) ?></title>
<link rel="stylesheet" href="css/tecservis.css?v=5">
<script src="js/ui.js?v=1"></script>
</head>
<body class="<?= e($claseBody) ?>">

<header class="header">
  <a class="logo" href="index.php" aria-label="<?= e(NEGOCIO['nombre']) ?> - Inicio">
    <span class="logo-mark" aria-hidden="true"></span>
    <span class="logo-text">
      <strong><?= e(NEGOCIO['nombre']) ?></strong>
      <small><?= e(NEGOCIO['lema']) ?></small>
    </span>
  </a>

  <button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false"
          onclick="document.querySelector('.header').classList.toggle('nav-abierto');
                   this.setAttribute('aria-expanded', document.querySelector('.header').classList.contains('nav-abierto'))">
    <span></span><span></span><span></span>
  </button>

  <nav class="nav-principal">
    <a href="index.php" class="<?= $activo === 'inicio' ? 'active' : '' ?>">Inicio</a>
    <a href="catalogo.php" class="<?= $activo === 'productos' ? 'active' : '' ?>">Productos</a>
    <a href="<?= $base ?>#servicios">Servicio técnico</a>
    <a href="<?= $base ?>#contacto">Atención</a>

    <?php if ($u): ?>
      <span class="nav-usuario" title="Sesión iniciada">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['usuario'], 0, 1))) ?></span>
        <?= e($u['usuario']) ?>
      </span>
      <?php if (es_admin()): ?>
        <a class="btn-login" href="admin.php">Panel admin</a>
      <?php else: ?>
        <a class="btn-login" href="mis_reparaciones.php">Mis reparaciones</a>
      <?php endif; ?>
      <a class="btn-login btn-danger" href="logout.php">Salir</a>
    <?php else: ?>
      <a class="btn-outline nav-btn" href="registro.php">Crear cuenta</a>
      <a class="btn-login" href="login.php">Ingresar</a>
    <?php endif; ?>
    <div class="ui-tools" aria-label="Preferencias de visualización">
      <button class="ui-tool" type="button" onclick="zoomOut()" title="Reducir tamaño" aria-label="Reducir tamaño">−</button>
      <button class="ui-tool ui-zoom-label" id="zoomValue" type="button" onclick="zoomReset()" title="Restablecer zoom" aria-label="Restablecer zoom">100%</button>
      <button class="ui-tool" type="button" onclick="zoomIn()" title="Aumentar tamaño" aria-label="Aumentar tamaño">+</button>
      <button class="ui-tool" id="themeToggle" type="button" onclick="toggleTheme()" aria-label="Activar modo oscuro" title="Modo oscuro">☾</button>
    </div>
  </nav>
</header>

<?php if ($f = flash_tomar()): ?>
  <div class="flash flash-<?= e($f['tipo']) ?>"><?= e($f['texto']) ?></div>
<?php endif; ?>
