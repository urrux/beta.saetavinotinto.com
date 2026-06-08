<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es-VE">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#1e3a8a">
  <title><?= e($title) ?> | Saeta Vinotinto</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png?v=20260607">
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png?v=20260607">
  <link rel="shortcut icon" href="/favicon.ico?v=20260607">
  <link rel="apple-touch-icon" href="/images/saeta-isotipo-crop.png?v=20260607">
  <link rel="stylesheet" href="<?= e(app_url('assets/portal.css?v=20260607-19')) ?>">
  <script src="<?= e(app_url('assets/portal.js?v=20260607-10')) ?>" defer></script>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-1NB1SB0LYS"></script>
  <script src="../analytics.js?v=20260606"></script>
</head>
<body class="<?= $guest ? 'guest-page' : 'portal-page' ?>">
<?php if (!$guest && $user): ?>
<?php $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')); ?>
<div class="portal-layout">
  <aside class="portal-sidebar">
    <a class="portal-brand" href="<?= e(app_url('index.php')) ?>">
      <img src="../images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto">
    </a>
    <div class="sidebar-user"><span>Área Privada De Saetas</span><strong><?= e($user['name']) ?></strong></div>
    <a class="portal-home-button" href="../" aria-label="Volver al inicio público" title="Volver al inicio">
      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 11.2 12 4l9 7.2v8.3a.5.5 0 0 1-.5.5h-5.7v-5.8H9.2V20H3.5a.5.5 0 0 1-.5-.5z"/></svg>
      <span>Inicio Público</span>
    </a>
    <button class="portal-menu-toggle" type="button" aria-expanded="false" aria-controls="portal-navigation">
      <span class="portal-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span><strong>Menú</strong>
    </button>
    <nav class="sidebar-nav" id="portal-navigation">
      <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= e(app_url('index.php')) ?>"><span>Inicio</span><small>Resumen Y Búsqueda</small></a>
      <a class="<?= $currentPage === 'tickets.php' ? 'active' : '' ?>" href="<?= e(app_url('tickets.php')) ?>"><span>Entradas</span><small>Solicitudes E Historial</small></a>
      <a class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>" href="<?= e(app_url('announcements.php')) ?>"><span>Anuncios</span><small>Novedades De La Peña</small></a>
      <a class="<?= $currentPage === 'feedback.php' ? 'active' : '' ?>" href="<?= e(app_url('feedback.php')) ?>"><span>Feedback</span><small>Buzón Anónimo</small></a>
      <a class="<?= $currentPage === 'governance.php' ? 'active' : '' ?>" href="<?= e(app_url('governance.php')) ?>"><span>Reglas Y Estatutos</span><small>Documentos Institucionales</small></a>
      <a class="<?= $currentPage === 'resources.php' ? 'active' : '' ?>" href="<?= e(app_url('resources.php')) ?>"><span>Drive Y Recursos</span><small>Enlaces Privados</small></a>
      <a class="<?= $currentPage === 'members.php' ? 'active' : '' ?>" href="<?= e(app_url('members.php')) ?>"><span>Peñistas</span><small>Directorio Completo</small></a>
      <a class="<?= $currentPage === 'store.php' ? 'active' : '' ?>" href="<?= e(app_url('store.php')) ?>"><span>Tienda</span><small>Productos Y Pedidos</small></a>
      <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= e(app_url('profile.php')) ?>"><span>Mi Perfil</span><small>Datos Y Privacidad</small></a>
      <?php if ($user['role'] === 'admin'): ?><a class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>" href="<?= e(app_url('admin.php')) ?>"><span>Administración</span><small>Gestión Del Portal</small></a><?php endif; ?>
      <?php if (is_superadmin($user)): ?><a class="<?= $currentPage === 'superadmin.php' ? 'active' : '' ?>" href="<?= e(app_url('superadmin.php')) ?>"><span>SuperAdmin</span><small>Global, CMS Y Trazabilidad</small></a><?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <a href="../">Volver Al Inicio ↗</a>
      <form method="post" action="<?= e(app_url('logout.php')) ?>">
      <?= csrf_field() ?>
        <button class="link-button" type="submit">Salir</button>
      </form>
    </div>
  </aside>
  <div class="portal-workspace">
<?php endif; ?>
<main class="<?= $guest ? 'guest-shell' : 'portal-shell' ?>">
<?php foreach ($flashes as $flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
