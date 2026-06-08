<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();

$query = trim((string) ($_GET['q'] ?? ''));
$documents = [];
$resources = [];
$quickResults = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $documentStatement = $pdo->prepare('SELECT title, summary, document_type FROM governance_documents WHERE is_active = 1 AND (title LIKE ? OR summary LIKE ? OR content LIKE ?) ORDER BY title LIMIT 12');
    $documentStatement->execute([$like, $like, $like]);
    $documents = $documentStatement->fetchAll();

    $resourceStatement = $pdo->prepare('SELECT title, description, category, url FROM resources WHERE is_active = 1 AND (title LIKE ? OR description LIKE ? OR category LIKE ?) ORDER BY title LIMIT 12');
    $resourceStatement->execute([$like, $like, $like]);
    $resources = $resourceStatement->fetchAll();

    $normalized = mb_strtolower($query);
    $intents = [
        ['words' => ['entrada', 'ticket', 'partido', 'solicitar'], 'title' => 'Solicitar o consultar entradas', 'description' => 'Envía una solicitud nueva o revisa tu historial.', 'url' => 'tickets.php'],
        ['words' => ['regla', 'estatuto', 'pilar', 'norma', 'identidad'], 'title' => 'Reglas, pilares y estatutos', 'description' => 'Consulta los documentos institucionales de La Peña.', 'url' => 'governance.php'],
        ['words' => ['drive', 'recurso', 'documento', 'archivo', 'foto'], 'title' => 'Drive y recursos privados', 'description' => 'Encuentra enlaces y archivos compartidos con Peñistas.', 'url' => 'resources.php'],
        ['words' => ['perfil', 'foto', 'datos', 'residencia', 'nacimiento'], 'title' => 'Editar mi perfil', 'description' => 'Actualiza tus datos y preferencias de privacidad.', 'url' => 'profile.php'],
        ['words' => ['tienda', 'bufanda', 'comprar', 'pedido'], 'title' => 'Tienda Oficial SV7', 'description' => 'Consulta productos y pedidos de La Peña.', 'url' => 'store.php'],
    ];
    foreach ($intents as $intent) {
        foreach ($intent['words'] as $word) {
            if (str_contains($normalized, $word)) {
                $quickResults[$intent['url']] = $intent;
                break;
            }
        }
    }
}

render_header('Buscar');
?>
<section class="page-heading"><p class="kicker">Buscador Privado</p><h1>¿Qué necesitas?</h1><p>Busca reglas, estatutos, recursos o simplemente escribe lo que quieres hacer.</p></section>
<form class="smart-search" method="get">
  <input type="search" name="q" value="<?= e($query) ?>" placeholder="Ej. quiero solicitar entradas, buscar las reglas..." autofocus>
  <button class="primary-button" type="submit">Buscar</button>
</form>
<?php if ($query === ''): ?>
<div class="search-suggestions"><a href="?q=reglas">Reglas</a><a href="?q=solicitar+entradas">Solicitar entradas</a><a href="?q=Google+Drive">Google Drive</a><a href="?q=editar+perfil">Editar perfil</a></div>
<?php else: ?>
<section class="search-results">
<?php foreach ($quickResults as $result): ?><a class="search-result featured" href="<?= e(app_url($result['url'])) ?>"><span>Acceso Recomendado</span><h2><?= e($result['title']) ?></h2><p><?= e($result['description']) ?></p></a><?php endforeach; ?>
<?php foreach ($documents as $document): ?><a class="search-result" href="<?= e(app_url('governance.php')) ?>"><span>Documento Institucional</span><h2><?= e($document['title']) ?></h2><p><?= e($document['summary']) ?></p></a><?php endforeach; ?>
  <?php foreach ($resources as $resource): ?><a class="search-result" href="<?= e($resource['url']) ?>" target="_blank" rel="noopener noreferrer"><span><?= e($resource['category']) ?></span><h2><?= e($resource['title']) ?></h2><p><?= e($resource['description']) ?></p></a><?php endforeach; ?>
  <?php if (!$quickResults && !$documents && !$resources): ?><p class="empty-state large">No encontramos resultados. Prueba con reglas, entradas, Drive, perfil o tienda.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php render_footer(); ?>
