<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int) request_value('product_id');
    $quantity = max(1, min(10, (int) request_value('quantity')));
    $statement = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $statement->execute([$productId]);
    $product = $statement->fetch();
    if ($product && ($product['stock'] === null || (int) $product['stock'] >= $quantity)) {
        $total = (float) $product['price'] * $quantity;
        $pdo->prepare('INSERT INTO product_orders (user_id, product_id, quantity, total, currency, delivery_notes) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$user['id'], $productId, $quantity, $total, $product['currency'], request_value('delivery_notes')]);
        flash('success', 'Recibimos tu pedido. La directiva te contactará para confirmar pago y entrega.');
    } else {
        flash('error', 'El producto no está disponible en la cantidad solicitada.');
    }
    redirect('store.php');
}

$products = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$orderStatement = $pdo->prepare('SELECT o.*, p.name AS product_name FROM product_orders o JOIN products p ON p.id = o.product_id WHERE o.user_id = ? ORDER BY o.created_at DESC');
$orderStatement->execute([$user['id']]);
$orders = $orderStatement->fetchAll();
$status = ['requested' => 'Solicitado', 'confirmed' => 'Confirmado', 'paid' => 'Pagado', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'];
render_header('Tienda Oficial SV7');
?>
<section class="page-heading"><p class="kicker">Tienda Oficial SV7</p><h1>Lleva La Peña contigo.</h1><p>Productos oficiales reservados para Peñistas de Saeta Vinotinto.</p></section>
<?php if (!$products): ?><article class="coming-product"><img src="../images/bufandaoficial.jpeg" alt="Bufanda oficial Saeta Vinotinto"><div><p class="kicker">Producto Oficial</p><h2>Bufanda Saeta Vinotinto</h2><p>La directiva está preparando precio, disponibilidad y opciones de entrega.</p><span>Próximamente</span></div></article><?php endif; ?>
<div class="shop-grid">
<?php foreach ($products as $product): ?><article class="product-card">
  <?php if ($product['image_url']): ?><img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>"><?php else: ?><div class="product-placeholder">SV</div><?php endif; ?>
      <p class="kicker">Producto Oficial</p><h2><?= e($product['name']) ?></h2><p><?= e($product['description']) ?></p>
  <strong><?= e($product['currency']) ?> <?= number_format((float) $product['price'], 2) ?></strong>
  <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <label>Cantidad<input type="number" name="quantity" min="1" max="10" value="1"></label>
    <label>Entrega o comentario<input name="delivery_notes" placeholder="Ciudad, persona de contacto..."></label>
      <button class="primary-button" type="submit">Solicitar Producto</button>
  </form>
</article><?php endforeach; ?>
</div>
<section class="content-section"><div class="section-title"><div><p class="kicker">Seguimiento</p><h2>Mis Pedidos</h2></div></div>
<?php if (!$orders): ?><p class="empty-state">Todavía no tienes pedidos.</p><?php endif; ?>
<?php foreach ($orders as $order): ?><article class="request-card"><div><h3><?= e($order['product_name']) ?></h3><p><?= (int) $order['quantity'] ?> unidad(es) · <?= e($order['currency']) ?> <?= number_format((float) $order['total'], 2) ?></p></div><span class="status"><?= e($status[$order['status']] ?? $order['status']) ?></span></article><?php endforeach; ?>
</section>
<?php render_footer(); ?>
