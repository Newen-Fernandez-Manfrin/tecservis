/* TEC SERVIS - comportamiento del sitio */
(function () {
  'use strict';

  var modal = document.getElementById('modalCompra');

  window.cerrarCompra = function () {
    if (!modal) return;
    modal.classList.remove('show');
    document.body.classList.remove('sin-scroll');
  };

  function abrirCompra(datos) {
    if (!modal) return;
    document.getElementById('inputIdProducto').value = datos.id;
    document.getElementById('resumenProducto').textContent = datos.nombre;
    document.getElementById('resumenPrecio').textContent = datos.precio;
    document.getElementById('resumenStock').textContent = 'Disponibles: ' + datos.stock;

    var cantidad = document.getElementById('inputCantidad');
    cantidad.value = 1;
    cantidad.max = datos.stock;

    modal.classList.add('show');
    document.body.classList.add('sin-scroll');
    modal.querySelector('input[name="cliente"]').focus();
  }

  /* Delegación: sirve para los productos ya cargados y para los que se agreguen. */
  document.addEventListener('click', function (ev) {
    var boton = ev.target.closest('.btn-buy');
    if (boton && boton.dataset.id) {
      ev.preventDefault();
      abrirCompra({
        id: boton.dataset.id,
        nombre: boton.dataset.nombre,
        precio: boton.dataset.precio,
        stock: boton.dataset.stock
      });
    }
  });

  if (modal) {
    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) window.cerrarCompra();
    });
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { window.cerrarCompra(); window.cerrarReparacion(); }
  });

  var modalReparacion = document.getElementById('modalReparacion');
  var formReparacion = document.getElementById('formReparacion');
  var errorReparacion = document.getElementById('reparacionError');

  window.abrirReparacion = function () {
    if (!modalReparacion) return;
    modalReparacion.hidden = false;
    modalReparacion.classList.add('show');
    document.body.classList.add('sin-scroll');
    var primero = modalReparacion.querySelector('input[name="cliente"]');
    if (primero) primero.focus();
  };

  window.cerrarReparacion = function () {
    if (!modalReparacion) return;
    modalReparacion.classList.remove('show');
    modalReparacion.hidden = true;
    document.body.classList.remove('sin-scroll');
    if (errorReparacion) {
      errorReparacion.hidden = true;
      errorReparacion.textContent = '';
    }
  };

  if (modalReparacion) {
    modalReparacion.addEventListener('click', function (ev) {
      if (ev.target === modalReparacion) window.cerrarReparacion();
    });
  }

  if (formReparacion) {
    formReparacion.addEventListener('submit', function (ev) {
      var cliente = formReparacion.querySelector('[name="cliente"]');
      var telefono = formReparacion.querySelector('[name="telefono"]');
      var email = formReparacion.querySelector('[name="email"]');
      var tipo = formReparacion.querySelector('[name="tipo_dispositivo"]');
      var marca = formReparacion.querySelector('[name="marca_modelo"]');
      var descripcion = formReparacion.querySelector('[name="descripcion"]');

      var foto = formReparacion.querySelector('[name="imagen"]');

      var campos = [cliente, telefono, email, tipo, marca, descripcion];
      var faltante = campos.some(function (campo) { return !campo || !campo.value.trim(); });
      var emailValido = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
      var fotoPesada = foto && foto.files && foto.files[0] && foto.files[0].size > 5 * 1024 * 1024;

      var mensaje = '';
      if (faltante) mensaje = 'Completá todos los campos obligatorios.';
      else if (!emailValido) mensaje = 'Ingresá un correo electrónico válido.';
      else if (fotoPesada) mensaje = 'La foto no puede superar los 5 MB.';

      if (mensaje) {
        ev.preventDefault();
        if (errorReparacion) {
          errorReparacion.textContent = mensaje;
          errorReparacion.hidden = false;
        }
      }
    });
  }

  /* Cierra el menú móvil al elegir una opción. */
  document.querySelectorAll('.nav-principal a').forEach(function (enlace) {
    enlace.addEventListener('click', function () {
      var header = document.querySelector('.header');
      if (header) header.classList.remove('nav-abierto');
    });
  });
})();
