<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$user = require_auth();

$ticketStatement = $pdo->prepare('SELECT (SELECT COUNT(*) FROM ticket_requests WHERE user_id = ?) + (SELECT COUNT(*) FROM imported_ticket_requests WHERE user_id = ? OR LOWER(requester_email) = LOWER(?))');
$ticketStatement->execute([$user['id'], $user['id'], $user['email']]);
$ticketCount = (int) $ticketStatement->fetchColumn();
$resourceCount = (int) $pdo->query('SELECT COUNT(*) FROM resources WHERE is_active = 1')->fetchColumn();
$orderStatement = $pdo->prepare('SELECT COUNT(*) FROM product_orders WHERE user_id = ?');
$orderStatement->execute([$user['id']]);
$orderCount = (int) $orderStatement->fetchColumn();
$tenure = membership_duration($user['joined_at'] ?? null);
$featuredAnnouncement = $pdo->query('SELECT * FROM announcements WHERE is_published = 1 AND is_featured = 1 ORDER BY updated_at DESC LIMIT 1')->fetch();

if ($user['role'] === 'admin') {
    $totalRequests = (int) $pdo->query("SELECT (SELECT COUNT(*) FROM ticket_requests t JOIN users u ON u.id = t.user_id WHERE COALESCE(u.member_number, '') <> 'TEST') + (SELECT COUNT(*) FROM imported_ticket_requests WHERE source <> 'TEST')")->fetchColumn();
    $totalTickets = (int) $pdo->query("SELECT COALESCE((SELECT SUM(t.quantity) FROM ticket_requests t JOIN users u ON u.id = t.user_id WHERE COALESCE(u.member_number, '') <> 'TEST'), 0) + COALESCE((SELECT SUM(quantity) FROM imported_ticket_requests WHERE source <> 'TEST'), 0)")->fetchColumn();
    $loggedInUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at IS NOT NULL AND COALESCE(member_number, '') <> 'TEST'")->fetchColumn();
}

render_header('Inicio');
?>
<section class="welcome-panel">
  <div>
    <p class="kicker">Área Privada De Saetas</p>
    <h1>Hola, Saeta <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
    <p>Este es tu espacio dentro de Saeta Vinotinto.</p>
  </div>
  <a class="secondary-button" href="<?= e(app_url('profile.php')) ?>">Completar Mi Perfil</a>
</section>

<?php if ($featuredAnnouncement): ?>
<aside class="announcement-banner">
  <div><p class="kicker">Anuncio Destacado</p><h2><?= e($featuredAnnouncement['title']) ?></h2><p><?= nl2br(e($featuredAnnouncement['body'])) ?></p></div>
  <div class="announcement-banner-actions">
    <?php if ($featuredAnnouncement['link_url']): ?><a class="primary-button" href="<?= e($featuredAnnouncement['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($featuredAnnouncement['link_label'] ?: 'Abrir Enlace') ?> ↗</a><?php endif; ?>
    <a class="secondary-button" href="<?= e(app_url('announcements.php')) ?>">Ver Todos Los Anuncios</a>
  </div>
</aside>
<?php endif; ?>

<section class="dashboard-search">
  <div><p class="kicker">Encuentra Lo Que Necesitas</p><h2>¿Qué quieres hacer?</h2></div>
  <form class="smart-search" method="get" action="<?= e(app_url('search.php')) ?>">
    <input type="search" name="q" placeholder="Ej. consultar reglas, solicitar entradas, abrir Google Drive..." aria-label="Buscar en el portal">
    <button class="primary-button" type="submit">Buscar</button>
  </form>
  <div class="search-suggestions"><a href="<?= e(app_url('governance.php')) ?>">Reglas Y Estatutos</a><a href="<?= e(app_url('tickets.php')) ?>">Solicitar Entradas</a><a href="<?= e(app_url('resources.php')) ?>">Google Drive</a></div>
</section>

<section class="metric-grid">
<?php if ($user['role'] === 'admin'): ?>
  <a class="metric-card accent-wine" href="<?= e(app_url('admin.php#tickets-admin')) ?>"><span>Operaciones</span><strong><?= $totalRequests ?></strong><small>solicitudes históricas y nuevas</small></a>
  <a class="metric-card accent-blue" href="<?= e(app_url('admin.php#tickets-admin')) ?>"><span>Entradas</span><strong><?= $totalTickets ?></strong><small>entradas solicitadas</small></a>
  <a class="metric-card accent-gold" href="<?= e(app_url('admin.php#members-admin')) ?>"><span>Uso Del Portal</span><strong><?= $loggedInUsers ?></strong><small>usuarios que han ingresado</small></a>
<?php else: ?>
  <a class="metric-card accent-blue" href="<?= e(app_url('tickets.php')) ?>"><span>Mis Solicitudes</span><strong><?= $ticketCount ?></strong><small>solicitudes de entradas</small></a>
  <a class="metric-card accent-gold" href="<?= e(app_url('resources.php')) ?>"><span>Biblioteca</span><strong><?= $resourceCount ?></strong><small>recursos disponibles</small></a>
  <a class="metric-card accent-wine" href="<?= e(app_url('store.php')) ?>"><span>Mi Tienda</span><strong><?= $orderCount ?></strong><small>pedidos realizados</small></a>
<?php endif; ?>
  <a class="metric-card accent-blue tenure-card" href="<?= e(app_url('profile.php')) ?>"><span>Tiempo Siendo Saeta</span><strong><?= e($tenure) ?></strong><small><?= !empty($user['joined_at']) ? 'Desde ' . e(date('d/m/Y', strtotime($user['joined_at']))) : 'Fecha por registrar' ?></small></a>
</section>

<section class="dashboard-tools">
  <div class="section-title compact"><div><p class="kicker">Accesos Directos</p><h2>Todo Mi Espacio</h2></div></div>
  <div class="tool-grid">
    <a class="tool-card accent-blue" href="<?= e(app_url('tickets.php')) ?>"><span>Prioridad</span><strong>Solicitar Entradas</strong><b>→</b></a>
    <a class="tool-card accent-wine" href="<?= e(app_url('announcements.php')) ?>"><span>Actualidad</span><strong>Anuncios</strong><b>→</b></a>
    <a class="tool-card accent-gold" href="<?= e(app_url('governance.php')) ?>"><span>Institucional</span><strong>Reglas Y Estatutos</strong><b>→</b></a>
    <a class="tool-card accent-blue" href="<?= e(app_url('resources.php')) ?>"><span>Biblioteca</span><strong>Drive Y Recursos</strong><b>→</b></a>
    <a class="tool-card accent-blue" href="<?= e(app_url('members.php')) ?>"><span>Comunidad</span><strong>Directorio De Peñistas</strong><b>→</b></a>
    <a class="tool-card accent-wine" href="<?= e(app_url('feedback.php')) ?>"><span>Confidencial</span><strong>Feedback Anónimo</strong><b>→</b></a>
    <a class="tool-card accent-gold" href="<?= e(app_url('store.php')) ?>"><span>Producto Oficial</span><strong>Tienda</strong><b>→</b></a>
    <a class="tool-card accent-blue" href="<?= e(app_url('profile.php')) ?>"><span>Cuenta</span><strong>Mi Perfil</strong><b>→</b></a>
    <?php if ($user['role'] === 'admin'): ?><a class="tool-card accent-wine" href="<?= e(app_url('admin.php')) ?>"><span>Solo Admin</span><strong>Administración</strong><b>→</b></a><?php endif; ?>
    <?php if (is_superadmin($user)): ?><a class="tool-card accent-gold" href="<?= e(app_url('superadmin.php')) ?>"><span>Solo SuperAdmin</span><strong>Global Y Trazabilidad</strong><b>→</b></a><?php endif; ?>
  </div>
</section>

<?php render_footer(); ?>
