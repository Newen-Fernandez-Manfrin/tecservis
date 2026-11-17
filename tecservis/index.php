<?php
require_once __DIR__ . '/conexion.php';

$destacados = $pdo->query(
    "SELECT * FROM vista_productos
     WHERE estado = 'activo' AND stock > 0
     ORDER BY id_producto DESC
     LIMIT 3"
)->fetchAll();

$totales = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM productos WHERE estado='activo') AS productos,
        (SELECT COUNT(*) FROM rubros   WHERE estado='activo') AS rubros,
        (SELECT COUNT(*) FROM marcas   WHERE estado='activo') AS marcas"
)->fetch();

$titulo = 'Hardware y servicio técnico';
$activo = 'inicio';
require __DIR__ . '/partes/cabecera.php';
?>

<main>
  <section class="hero">
    <div class="hero-text">
      <p class="tag">Tecnología · Gaming · Hardware</p>
      <h1>Tu PC empieza<br><span>acá.</span></h1>
      <p>Componentes, equipos armados y servicio técnico en Mar del Plata. Asesoramiento real antes de comprar.</p>
      <div class="hero-actions">
        <a class="btn-primary" href="catalogo.php">Ver productos</a>
        <a class="btn-outline hero-outline" href="#servicios">Servicio técnico</a>
      </div>
      <ul class="hero-datos">
        <li><strong><?= (int) $totales['productos'] ?></strong><span>Productos en catálogo</span></li>
        <li><strong><?= (int) $totales['rubros'] ?></strong><span>Categorías</span></li>
        <li><strong><?= (int) $totales['marcas'] ?></strong><span>Marcas</span></li>
      </ul>
    </div>
  </section>

  <section class="highlights">
    <article class="highlight-card">
      <div class="highlight-icon">🛒</div>
      <div><h3>Compra simple</h3><p>Elegí el producto, completá tus datos y enviá el pedido.</p></div>
    </article>
    <article class="highlight-card">
      <div class="highlight-icon">🔧</div>
      <div><h3>Servicio técnico</h3><p>Armado, mantenimiento, instalación y puesta a punto.</p></div>
    </article>
    <article class="highlight-card">
      <div class="highlight-icon">💬</div>
      <div><h3>Atención personalizada</h3><p>Te asesoramos para que compres lo que realmente necesitás.</p></div>
    </article>
  </section>

  <section class="home-products">
    <div class="section-head">
      <div>
        <p class="tag">Selección TEC SERVIS</p>
        <h2>Productos destacados</h2>
        <p>Una muestra de nuestros ingresos más recientes con stock disponible.</p>
      </div>
      <a class="btn-outline" href="catalogo.php">Ver catálogo</a>
    </div>

    <div class="products featured-products">
      <?php if (!$destacados): ?>
        <div class="empty">Todavía no hay productos cargados.</div>
      <?php endif; ?>
      <?php foreach ($destacados as $p) { require __DIR__ . '/partes/tarjeta-producto.php'; } ?>
    </div>

    <div class="catalog-link"><a class="btn-primary" href="catalogo.php">Ver todos los productos</a></div>
  </section>

  <section class="services" id="servicios">
    <div class="section-head center">
      <div>
        <p class="tag">Servicios</p>
        <h2>Servicio técnico</h2>
        <p>Soluciones para que tu equipo vuelva a funcionar como corresponde.</p>
      </div>
    </div>
    <div class="service-grid">
      <article class="service">
        <div class="icon">💻</div>
        <h3>PC y notebooks</h3>
        <p>Limpieza, mantenimiento, formateo, instalación de Windows, optimización y armado a medida.</p>
      </article>
      <article class="service">
        <div class="icon">⚙️</div>
        <h3>Instalaciones</h3>
        <p>Drivers, programas, configuración de periféricos y puesta a punto completa del equipo.</p>
      </article>
      <article class="service">
        <div class="icon">📱</div>
        <h3>Celulares</h3>
        <p>Reparaciones, cambio de módulos, baterías y conectores de carga.</p>
      </article>
    </div>
    <div class="service-request-action">
      <?php if (usuario_actual()): ?>
        <button type="button" class="btn-primary" onclick="abrirReparacion()">Solicitar reparación</button>
      <?php else: ?>
        <a class="btn-primary" href="login.php?reparar=1&amp;volver=<?= urlencode('index.php#servicios') ?>">Iniciar sesión para reparar</a>
      <?php endif; ?>
      <p>Completá el formulario y recibimos tu equipo para revisarlo.</p>
    </div>
  </section>

  <section class="contact" id="contacto">
    <div class="contact-inner">
      <div>
        <p class="tag">Atención</p>
        <h2>¿Necesitás ayuda?</h2>
        <p>Consultanos antes de comprar o para coordinar un servicio técnico. Respondemos en el día.</p>
      </div>
      <div class="contact-data">
        <p><strong>WhatsApp</strong><span><?= e(NEGOCIO['whatsapp']) ?></span></p>
        <p><strong>Ubicación</strong><span><?= e(NEGOCIO['ubicacion']) ?></span></p>
        <p><strong>Horario</strong><span><?= e(NEGOCIO['horario']) ?></span></p>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partes/pie.php'; ?>
