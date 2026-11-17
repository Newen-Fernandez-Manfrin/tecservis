<?php
/**
 * Pie del sitio + modal de compra.
 * Definí $conModal = false antes de incluirlo si la página no muestra productos.
 */
$conModal = $conModal ?? true;
$u = usuario_actual();
?>
<footer class="footer">
  <div class="footer-col">
    <div class="logo">
      <span class="logo-mark" aria-hidden="true"></span>
      <span class="logo-text"><strong><?= e(NEGOCIO['nombre']) ?></strong><small><?= e(NEGOCIO['lema']) ?></small></span>
    </div>
    <p>Venta de componentes, equipos armados y servicio técnico con atención personalizada.</p>
  </div>
  <div class="footer-col">
    <h4>Secciones</h4>
    <a href="index.php">Inicio</a>
    <a href="catalogo.php">Productos</a>
    <a href="index.php#servicios">Servicio técnico</a>
    <a href="index.php#contacto">Atención</a>
  </div>
  <div class="footer-col">
    <h4>Contacto</h4>
    <p><?= e(NEGOCIO['whatsapp']) ?></p>
    <p><?= e(NEGOCIO['ubicacion']) ?></p>
    <p><?= e(NEGOCIO['horario']) ?></p>
  </div>
  <div class="footer-legal">
    <span>© <?= date('Y') ?> <?= e(NEGOCIO['nombre']) ?>. Todos los derechos reservados.</span>
  </div>
</footer>

<?php if ($conModal): ?>
<div class="modal" id="modalCompra" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
  <div class="modal-box">
    <div class="modal-head">
      <h2 id="modalTitulo">Finalizar pedido</h2>
      <button class="modal-close" type="button" aria-label="Cerrar" onclick="cerrarCompra()">&times;</button>
    </div>

    <div class="modal-resumen">
      <div>
        <strong id="resumenProducto">—</strong>
        <small id="resumenStock"></small>
      </div>
      <span class="price" id="resumenPrecio">—</span>
    </div>

    <form class="buy-form" method="POST" action="pedido.php" novalidate>
      <?= csrf_campo() ?>
      <input type="hidden" name="id_producto" id="inputIdProducto">

      <div class="campo-doble">
        <label>Nombre y apellido
          <input name="cliente" required maxlength="120"
                 value="<?= e($u ? trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) : '') ?>">
        </label>
        <label>Teléfono
          <input name="telefono" required maxlength="40" inputmode="tel">
        </label>
      </div>

      <label>Correo electrónico <span class="opcional">(opcional)</span>
        <input type="email" name="email" maxlength="120" value="<?= e($u['email'] ?? '') ?>">
      </label>

      <label>Dirección de entrega
        <input name="direccion" required maxlength="255">
      </label>

      <div class="campo-doble">
        <label>Cantidad
          <input type="number" name="cantidad" id="inputCantidad" value="1" min="1" required>
        </label>
        <label>Alias del pagador <span class="opcional">(opcional)</span>
          <input name="alias_pagador" maxlength="80">
        </label>
      </div>

      <label>Número de comprobante <span class="opcional">(opcional)</span>
        <input name="comprobante" maxlength="80">
      </label>

      <label>Observaciones <span class="opcional">(opcional)</span>
        <textarea name="observaciones" maxlength="255" rows="2"></textarea>
      </label>

      <p class="modal-nota">Al enviar el pedido queda registrado en el sistema. Te contactamos para coordinar el pago y la entrega.</p>

      <div class="modal-actions">
        <button class="btn-primary" type="submit">Enviar pedido</button>
        <button class="btn-outline" type="button" onclick="cerrarCompra()">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal" id="modalReparacion" role="dialog" aria-modal="true" aria-labelledby="tituloReparacion" hidden>
  <div class="modal-box modal-reparacion">
    <div class="modal-head">
      <h2 id="tituloReparacion">Solicitud de reparación</h2>
      <button class="modal-close" type="button" aria-label="Cerrar" onclick="cerrarReparacion()">&times;</button>
    </div>

    <form id="formReparacion" class="buy-form" method="POST" action="solicitud_reparacion.php"
          enctype="multipart/form-data" novalidate>
      <?= csrf_campo() ?>

      <div class="campo-doble">
        <label>Nombre y apellido
          <input name="cliente" required maxlength="120"
                 value="<?= e($u ? trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) : '') ?>">
        </label>
        <label>Teléfono
          <input name="telefono" required maxlength="40" inputmode="tel"
                 value="<?= e($u['telefono'] ?? '') ?>">
        </label>
      </div>

      <label>Correo electrónico
        <input type="email" name="email" required maxlength="120" value="<?= e($u['email'] ?? '') ?>">
      </label>

      <div class="campo-doble">
        <label>Tipo de dispositivo
          <select name="tipo_dispositivo" required>
            <option value="">Seleccionar…</option>
            <option value="Notebook">Notebook</option>
            <option value="PC de escritorio">PC de escritorio</option>
            <option value="Monitor">Monitor</option>
            <option value="Periférico">Periférico</option>
            <option value="Celular">Celular</option>
            <option value="Otro">Otro</option>
          </select>
        </label>
        <label>Marca y modelo
          <input name="marca_modelo" required maxlength="160" placeholder="Ej.: Lenovo IdeaPad 3">
        </label>
      </div>

      <label>Descripción del problema / falla
        <textarea name="descripcion" required maxlength="3000" rows="5"
                  placeholder="Contanos qué falla presenta el equipo, cuándo ocurre y qué probaste."></textarea>
      </label>

      <label>Foto del estado del equipo <span class="opcional">(opcional)</span>
        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
      </label>

      <p class="modal-nota">La solicitud queda registrada como <strong>Pendiente</strong>. El equipo será revisado antes de presupuestar cualquier reparación.</p>

      <div id="reparacionError" class="form-error" hidden></div>

      <div class="modal-actions">
        <button class="btn-primary" type="submit">Enviar solicitud</button>
        <button class="btn-outline" type="button" onclick="cerrarReparacion()">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script src="js/tecservis.js?v=4"></script>
</body>
</html>
