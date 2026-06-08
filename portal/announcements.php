<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();

$announcements = $pdo->query('SELECT a.*, u.name AS author_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by WHERE a.is_published = 1 ORDER BY a.is_featured DESC, a.created_at DESC')->fetchAll();
render_header('Anuncios');
?>
<section class="page-heading"><p class="kicker">Actualidad De La Peña</p><h1>Anuncios.</h1><p>Novedades, avisos importantes y comunicaciones de la Junta Directiva.</p></section>
<?php if (!$announcements): ?><p class="empty-state large">No hay anuncios publicados por ahora.</p><?php endif; ?>
<section class="announcement-history">
<?php foreach ($announcements as $announcement): ?>
  <article class="announcement-card <?= $announcement['is_featured'] ? 'featured' : '' ?>">
    <div class="announcement-meta"><span><?= $announcement['is_featured'] ? 'Destacado' : 'Anuncio' ?></span><time><?= e(date('d/m/Y', strtotime($announcement['created_at']))) ?></time></div>
    <h2><?= e($announcement['title']) ?></h2>
    <p><?= nl2br(e($announcement['body'])) ?></p>
    <?php if ($announcement['link_url']): ?><a class="small-button" href="<?= e($announcement['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($announcement['link_label'] ?: 'Abrir Enlace') ?> ↗</a><?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
<?php render_footer(); ?>
