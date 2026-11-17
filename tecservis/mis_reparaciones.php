<?php
require_once __DIR__ . '/conexion.php';

$u = usuario_actual();
if (!$u) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id_solicitud, cliente, email, telefono, tipo_dispositivo, marca_modelo,
            descripcion, imagen, estado, presupuesto, detalle_presupuesto,
            fecha, fecha_actualizacion
     FROM solicitudes_reparacion
     WHERE id_usuario = ?
     ORDER BY id_solicitud DESC'
);
$stmt->execute([(int) $u['id']]);
$reparaciones = $stmt->fetchAll();

$estados = [
    'pendiente' => 'Pendiente',
    'en_revision' => 'En revisión',
    'presupuestado' => 'Presupuestado',
    'reparado' => 'Reparado',
    'cancelado' => 'Cancelado',
];

$titulo = 'Mis reparaciones';
$activo = '';
require __DIR__ . '/partes/cabecera.php';
?>

<main class="cuenta-page">
  <section class="section-head center">
    <div>
      <p class="tag">Mi cuenta</p>
      <h1>Mis reparaciones</h1>
      <p>Acá podés consultar el estado y el presupuesto de cada equipo que dejaste a reparar.</p>
    </div>
  </section>

  <section class="admin-card" id="avisosReparacion" hidden style="max-width:900px;margin:0 auto 24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <h2 style="margin:0;">Avisos de tus reparaciones</h2>
      <button class="btn-small" type="button" id="marcarAvisos">Marcar como leídos</button>
    </div>
    <div id="listaAvisosReparacion" style="margin-top:14px;"></div>
  </section>


  <?php if (!$reparaciones): ?>
    <section class="admin-card cuenta-vacio">
      <h2>No tenés reparaciones registradas</h2>
      <p>Cuando envíes una solicitud, va a aparecer en esta sección.</p>
      <a class="btn-primary" href="index.php#servicios">Solicitar reparación</a>
    </section>
  <?php else: ?>
    <section class="mis-reparaciones">
      <?php foreach ($reparaciones as $rep): ?>
        <article class="reparacion-cliente">
          <div class="reparacion-cliente-head">
            <div>
              <p class="tag">Solicitud #<?= (int) $rep['id_solicitud'] ?></p>
              <h2><?= e($rep['tipo_dispositivo']) ?> — <?= e($rep['marca_modelo']) ?></h2>
              <small>Enviada el <?= date('d/m/Y H:i', strtotime($rep['fecha'])) ?></small>
            </div>
            <span class="pill pill-<?= $rep['estado'] === 'cancelado' ? 'off' : ($rep['estado'] === 'reparado' ? 'ok' : 'info') ?>">
              <?= e($estados[$rep['estado']] ?? $rep['estado']) ?>
            </span>
          </div>

          <div class="reparacion-cliente-grid">
            <div>
              <h3>Problema informado</h3>
              <p><?= nl2br(e($rep['descripcion'])) ?></p>
            </div>

            <div class="presupuesto-cliente">
              <h3>Presupuesto</h3>
              <?php if ($rep['presupuesto'] !== null): ?>
                <strong><?= moneda($rep['presupuesto']) ?></strong>
                <?php if (!empty($rep['detalle_presupuesto'])): ?>
                  <p><?= nl2br(e($rep['detalle_presupuesto'])) ?></p>
                <?php endif; ?>
              <?php else: ?>
                <p class="presupuesto-pendiente">Todavía no hay un presupuesto cargado.</p>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($rep['imagen'])): ?>
            <a class="btn-small" href="imagenes/<?= e($rep['imagen']) ?>" target="_blank" rel="noopener">Ver foto enviada</a>
          <?php endif; ?>

          <div class="reparacion-actualizacion">
            Última actualización: <?= date('d/m/Y H:i', strtotime($rep['fecha_actualizacion'])) ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partes/pie.php'; ?>

<script>
(function () {
  var box = document.getElementById('avisosReparacion');
  var lista = document.getElementById('listaAvisosReparacion');
  var marcar = document.getElementById('marcarAvisos');
  var token = document.querySelector('meta[name="csrf-token"]').content;

  function escapar(texto) {
    var d = document.createElement('div');
    d.textContent = texto == null ? '' : texto;
    return d.innerHTML;
  }

  function cargarAvisos() {
    fetch('notificaciones_usuario.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = data.notificaciones || [];
        if (!items.length) {
          box.hidden = true;
          return;
        }

        box.hidden = false;
        lista.innerHTML = items.map(function (n) {
          return '<div class="notif-item' + (Number(n.leida) === 0 ? ' no-leida' : '') + '">' +
            '<strong>' + escapar(n.titulo) + '</strong>' +
            '<span>' + escapar(n.mensaje) + '</span>' +
            '<time>' + escapar(n.fecha) + '</time>' +
          '</div>';
        }).join('');
      })
      .catch(function () {});
  }

  marcar.addEventListener('click', function () {
    fetch('notificaciones_usuario.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: 'marcar_leidas', _token: token })
    }).then(cargarAvisos);
  });

  cargarAvisos();
  setInterval(cargarAvisos, 15000);
})();
</script>
