<?php
/** Tarjeta de producto. Requiere el array $p con una fila de vista_productos. */
$img = imagen_producto($p['imagen'] ?? null);
$sinStock = (int) $p['stock'] <= 0;
?>
<article class="product-card <?= $sinStock ? 'agotado' : '' ?>">
  <div class="product-image">
    <?php if ($img): ?>
      <img src="<?= e($img) ?>" alt="<?= e($p['producto']) ?>" loading="lazy">
    <?php else: ?>
      <span class="placeholder">Sin imagen</span>
    <?php endif; ?>
    <?php if ($sinStock): ?><span class="badge-agotado">Sin stock</span><?php endif; ?>
  </div>

  <div class="product-info">
    <small class="brand"><?= e($p['marca']) ?> · <?= e($p['rubro']) ?></small>
    <h3><?= e($p['producto']) ?></h3>
    <p><?= e($p['descripcion']) ?></p>

    <div class="product-bottom">
      <strong class="price"><?= moneda($p['precio']) ?></strong>
      <span class="stock <?= $sinStock ? 'stock-cero' : '' ?>">
        <?= $sinStock ? 'Sin stock' : 'Stock: ' . (int) $p['stock'] ?>
      </span>
    </div>

    <?php if ($sinStock): ?>
      <button class="btn-buy" type="button" disabled>No disponible</button>
    <?php elseif (!usuario_actual()): ?>
      <a class="btn-buy" href="login.php?comprar=1">Comprar</a>
    <?php else: ?>
      <button class="btn-buy" type="button"
              data-id="<?= (int) $p['id_producto'] ?>"
              data-nombre="<?= e($p['producto']) ?>"
              data-precio="<?= e(moneda($p['precio'])) ?>"
              data-stock="<?= (int) $p['stock'] ?>">Comprar</button>
    <?php endif; ?>
  </div>
</article>
