/* TEC SERVIS - notificaciones del panel admin */
(function () {
  'use strict';

  var btn   = document.getElementById('btnNotificaciones');
  var badge = document.getElementById('badgeNotificaciones');
  var panel = document.getElementById('panelNotificaciones');
  var lista = document.getElementById('listaNotificaciones');
  if (!btn || !panel) return;

  var token = document.body.dataset.token || '';

  function escapar(texto) {
    var d = document.createElement('div');
    d.textContent = texto == null ? '' : texto;
    return d.innerHTML;
  }

  function formatearFecha(fecha) {
    try {
      var d = new Date(fecha.replace(' ', 'T'));
      return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return fecha;
    }
  }

  function pintar(items) {
    if (!items || !items.length) {
      lista.innerHTML = '<p class="notif-vacio">Todavía no hay pedidos ni solicitudes.</p>';
      return;
    }
    lista.innerHTML = items.map(function (n) {
      var clase = 'notif-item' + (Number(n.leida) === 0 ? ' no-leida' : '');
      var destino = Number(n.id_solicitud_reparacion || 0) > 0
        ? 'admin.php?tab=reparaciones'
        : 'admin.php?tab=pedidos';
      return '<a class="' + clase + '" href="' + destino + '">' +
             '<strong>' + escapar(n.titulo) + '</strong>' +
             '<span>' + escapar(n.mensaje) + '</span>' +
             '<time>' + escapar(formatearFecha(n.fecha)) + '</time>' +
             '</a>';
    }).join('');
  }

  function actualizar() {
    fetch('notificaciones.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var n = Number(data.no_leidas || 0);
        if (n > 0) {
          badge.hidden = false;
          badge.textContent = n > 9 ? '9+' : String(n);
          document.title = '(' + n + ') Panel admin | TEC SERVIS';
        } else {
          badge.hidden = true;
          document.title = 'Panel admin | TEC SERVIS';
        }
        pintar(data.notificaciones);
      })
      .catch(function () { /* silencioso: no interrumpe el uso del panel */ });
  }

  function marcarLeidas() {
    fetch('notificaciones.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: 'marcar_leidas', _token: token }),
    }).then(actualizar);
  }

  btn.addEventListener('click', function () {
    var estabaCerrado = panel.hidden;
    panel.hidden = !estabaCerrado;
    if (estabaCerrado && !badge.hidden) {
      marcarLeidas();
    }
  });

  document.addEventListener('click', function (ev) {
    if (!panel.hidden && !panel.contains(ev.target) && ev.target !== btn) {
      panel.hidden = true;
    }
  });

  actualizar();
  setInterval(actualizar, 15000);
})();
