<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();
$documents = $pdo->query('SELECT * FROM governance_documents WHERE is_active = 1 ORDER BY document_type, title')->fetchAll();
$labels = ['rules' => 'Reglas', 'pillars' => 'Pilares', 'statutes' => 'Estatutos', 'other' => 'Documento'];
function document_embed_url(?string $url): ?string
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    if (preg_match('~drive\.google\.com/file/d/([^/]+)~', $url, $matches)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($matches[1]) . '/preview';
    }
    if (preg_match('~drive\.google\.com/(?:open|uc)\?.*?[?&]id=([^&]+)~', $url, $matches)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($matches[1]) . '/preview';
    }
    if (preg_match('~docs\.google\.com/document/d/([^/]+)~', $url, $matches)) {
        return 'https://docs.google.com/document/d/' . rawurlencode($matches[1]) . '/edit?embedded=true';
    }
    if (preg_match('~docs\.google\.com/spreadsheets/d/([^/]+)~', $url, $matches)) {
        return 'https://docs.google.com/spreadsheets/d/' . rawurlencode($matches[1]) . '/preview';
    }
    if (preg_match('~docs\.google\.com/presentation/d/([^/]+)~', $url, $matches)) {
        return 'https://docs.google.com/presentation/d/' . rawurlencode($matches[1]) . '/embed';
    }
    if (preg_match('~\.pdf(?:\?.*)?$~i', $url)) return $url;
    return null;
}
render_header('Nuestra Peña');
?>
<section class="page-heading"><p class="kicker">Nuestra Identidad</p><h1>Lo que nos une.</h1><p>Reglas, pilares y estatutos que orientan la convivencia y el propósito de Saeta Vinotinto.</p></section>
<?php if (!$documents): ?><p class="empty-state large">La directiva está preparando los documentos institucionales.</p><?php endif; ?>
<div class="document-grid">
<?php foreach ($documents as $document): $embedUrl = document_embed_url($document['url']); ?><article class="document-card <?= $embedUrl ? 'has-preview' : '' ?>">
  <span><?= e($labels[$document['document_type']] ?? 'Documento') ?><?= $document['version'] ? ' · ' . e($document['version']) : '' ?></span>
  <h2><?= e($document['title']) ?></h2><p><?= e($document['summary']) ?></p>
  <?php if ($document['content']): ?><div class="document-content"><?= nl2br(e($document['content'])) ?></div><?php endif; ?>
  <?php if ($embedUrl): ?><div class="document-preview"><iframe src="<?= e($embedUrl) ?>" title="<?= e($document['title']) ?>" allow="autoplay" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe></div><p class="document-fallback">Si el visor no carga en tu navegador, abre el documento desde el enlace inferior.</p><?php endif; ?>
  <?php if ($document['url']): ?><a class="small-button" href="<?= e($document['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir En Una Pestaña Nueva ↗</a><?php endif; ?>
</article><?php endforeach; ?>
</div>
<?php render_footer(); ?>
