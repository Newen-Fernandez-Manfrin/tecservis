<?php
require_once __DIR__ . '/conexion.php';

$buscar  = trim((string) ($_GET['buscar'] ?? ''));
$rubroId = (int) ($_GET['rubro'] ?? 0);
$orden   = $_GET['orden'] ?? 'antiguos';

$ordenes = [
    'antiguos' => 'p.id_rubro ASC, p.id_producto ASC',
    'nuevos'   => 'p.id_producto DESC',
    'precio'   => 'p.precio ASC',
    'preciod'  => 'p.precio DESC',
];
$orderBy = $ordenes[$orden] ?? $ordenes['antiguos'];

$sql = "SELECT p.id_producto, p.nombre AS producto, p.descripcion, p.id_rubro, p.id_marca,
               r.nombre AS rubro, m.nombre AS marca, p.precio, p.stock, p.imagen, p.estado
        FROM productos p
        INNER JOIN rubros r ON p.id_rubro = r.id_rubro
        INNER JOIN marcas m ON p.id_marca = m.id_marca
        WHERE p.estado = 'activo'";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (p.nombre LIKE :q1 OR p.descripcion LIKE :q2 OR r.nombre LIKE :q3 OR m.nombre LIKE :q4)";
    $like = '%' . $buscar . '%';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
}
if ($rubroId > 0) {
    $sql .= " AND p.id_rubro = :rubro";
    $params[':rubro'] = $rubroId;
}
$sql .= ' ORDER BY ' . $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$rubros = $pdo->query("SELECT * FROM rubros WHERE estado='activo' ORDER BY nombre")->fetchAll();

/* Agrupar por categoría manteniendo el orden del SELECT. */
$categorias = [];
foreach ($productos as $fila) {
    $categorias[$fila['id_rubro']]['nombre'] = $fila['rubro'];
    $categorias[$fila['id_rubro']]['productos'][] = $fila;
}

$titulo = 'Productos';
$activo = 'productos';
require __DIR__ . '/partes/cabecera.php';
?>

<main class="catalog-page">
  <div class="catalog-title">
    <p class="tag">Catálogo</p>
    <h1>Componentes y equipos</h1>
    <p>Encontrá lo que buscás por categoría, marca o nombre.</p>
  </div>

  <form class="filtros" method="GET" action="catalogo.php">
    <div class="buscador">
      <input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar producto, marca o categoría…">
      <button type="submit" aria-label="Buscar">Buscar</button>
    </div>

    <select name="rubro" onchange="this.form.submit()">
      <option value="0">Todas las categorías</option>
      <?php foreach ($rubros as $r): ?>
        <option value="<?= (int) $r['id_rubro'] ?>" <?= $rubroId === (int) $r['id_rubro'] ? 'selected' : '' ?>>
          <?= e($r['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="orden" onchange="this.form.submit()">
      <option value="antiguos" <?= $orden === 'antiguos' ? 'selected' : '' ?>>Por categoría</option>
      <option value="nuevos"   <?= $orden === 'nuevos'   ? 'selected' : '' ?>>Más nuevos primero</option>
      <option value="precio"   <?= $orden === 'precio'   ? 'selected' : '' ?>>Menor precio</option>
      <option value="preciod"  <?= $orden === 'preciod'  ? 'selected' : '' ?>>Mayor precio</option>
    </select>

    <?php if ($buscar !== '' || $rubroId > 0 || $orden !== 'antiguos'): ?>
      <a class="btn-outline" href="catalogo.php">Limpiar</a>
    <?php endif; ?>
  </form>

  <p class="resultado-info">
    <?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?>
    <?= $buscar !== '' ? 'para "' . e($buscar) . '"' : '' ?>
  </p>

  <?php if (!$productos): ?>
    <div class="empty">No se encontraron productos con esos criterios. Probá con otra búsqueda.</div>
  <?php endif; ?>

  <?php foreach ($categorias as $cat): ?>
    <section class="category-section">
      <div class="category-title">
        <h2><?= e($cat['nombre']) ?></h2>
        <span><?= count($cat['productos']) ?> producto<?= count($cat['productos']) === 1 ? '' : 's' ?></span>
      </div>
      <div class="products catalog-products">
        <?php foreach ($cat['productos'] as $p) { require __DIR__ . '/partes/tarjeta-producto.php'; } ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>

<?php require __DIR__ . '/partes/pie.php'; ?>
