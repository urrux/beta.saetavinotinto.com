<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();
$resources = $pdo->query('SELECT r.* FROM resources r WHERE r.is_active = 1 AND NOT EXISTS (SELECT 1 FROM governance_documents g WHERE g.is_active = 1 AND g.url IS NOT NULL AND g.url = r.url) ORDER BY r.category, r.title')->fetchAll();
$grouped = [];
foreach ($resources as $resource) {
    $grouped[$resource['category']][] = $resource;
}
render_header('Recursos privados');
?>
<section class="page-heading"><p class="kicker">Biblioteca Privada</p><h1>Recursos de La Peña.</h1><p>Documentos, carpetas de Google Drive y enlaces disponibles exclusivamente para Peñistas.</p></section>
<?php if (!$resources): ?><div class="empty-state large">La directiva todavía no ha publicado recursos.</div><?php endif; ?>
<?php foreach ($grouped as $category => $items): ?>
<section class="content-section">
  <div class="section-title"><div><p class="kicker">Colección</p><h2><?= e($category) ?></h2></div><span><?= count($items) ?> recurso(s)</span></div>
  <div class="resource-grid">
    <?php foreach ($items as $resource): ?>
    <a class="resource-card" href="<?= e($resource['url']) ?>" target="_blank" rel="noopener noreferrer">
      <span>Enlace Privado</span><h3><?= e($resource['title']) ?></h3><p><?= e($resource['description']) ?></p><b>Abrir Recurso ↗</b>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
<?php render_footer(); ?>
