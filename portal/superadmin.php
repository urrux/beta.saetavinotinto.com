<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$superadmin = require_superadmin();

$cmsDefaults = [
    'hero_blue' => 'Madridismo',
    'hero_wine' => 'Sin Fronteras.',
    'hero_text' => 'Creemos en un madridismo que une personas, culturas y generaciones. Nos inspiran los valores de esfuerzo, disciplina, respeto y compañerismo que han definido al Real Madrid a lo largo de su historia, y trabajamos para fortalecer esos lazos dentro y fuera de Venezuela.',
    'history_title' => 'Nacimos en Maracaibo.',
    'history_emphasis' => 'Nos une el Madrid.',
    'history_text_one' => 'Somos la Peña Madridista Oficial Saeta Vinotinto y la primera del Zulia: una familia nacida en Maracaibo y conectada alrededor del mundo por el Real Madrid.',
    'history_text_two' => 'Cada partido es una excusa para volver a encontrarnos. Cada victoria, un recuerdo que compartimos.',
    'contact_title' => 'Hablemos de',
    'contact_emphasis' => 'Madridismo.',
    'contact_text' => '¿Tienes una pregunta sobre Saeta Vinotinto? Escríbenos y La Peña te responderá.',
    'public_product_image' => 'images/BUFANDASV7.jpeg?v=20260607',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value('action');

    if ($action === 'update_cms') {
        $current = site_settings($pdo, $cmsDefaults);
        $uploadedPublicImage = null;
        if (!empty($_FILES['public_product_upload']['tmp_name']) && is_uploaded_file($_FILES['public_product_upload']['tmp_name'])) {
            try {
                $filename = optimized_uploaded_image($_FILES['public_product_upload'], __DIR__ . '/uploads/cms', 'public-product', 2200, 1400, 8 * 1024 * 1024);
                $uploadedPublicImage = 'portal/uploads/cms/' . $filename;
            } catch (RuntimeException $exception) {
                flash('error', $exception->getMessage());
                header('Location: ' . app_url('superadmin.php#cms-superadmin'));
                exit;
            }
        }
        foreach ($cmsDefaults as $key => $default) {
            $value = $key === 'public_product_image' && $uploadedPublicImage
                ? $uploadedPublicImage
                : trim(request_value($key));
            if ($value === '') $value = $current[$key] ?? $default;
            if ($key === 'public_product_image' && !preg_match('~^(https://|[a-zA-Z0-9_./?=&%-]+$)~', $value)) {
                $value = $current[$key] ?? $default;
            }
            $limit = $key === 'public_product_image' ? 500 : (str_contains($key, 'text') ? 1500 : 120);
            $value = mb_substr($value, 0, $limit, 'UTF-8');
            $exists = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?');
            $exists->execute([$key]);
            if ((int) $exists->fetchColumn() > 0) {
                $pdo->prepare('UPDATE site_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?')
                    ->execute([$value, $superadmin['id'], $key]);
            } else {
                $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)')
                    ->execute([$key, $value, $superadmin['id']]);
            }
        }
        audit_admin_action((int) $superadmin['id'], 'update_public_cms', 'site_settings', null);
        flash('success', 'Contenido público actualizado.');
        header('Location: ' . app_url('superadmin.php#cms-superadmin'));
        exit;
    }

    $targetId = (int) request_value('user_id');
    $targetStatement = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $targetStatement->execute([$targetId]);
    $target = $targetStatement->fetch();
    $protected = !$target || (int) $target['id'] === (int) $superadmin['id'] || is_superadmin($target);

    if ($action === 'change_user_role' && !$protected) {
        $role = request_value('role');
        if (in_array($role, ['member', 'admin'], true)) {
            $pdo->prepare('UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$role, $targetId]);
            $pdo->prepare('UPDATE member_records SET is_portal_admin = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')->execute([$role === 'admin' ? 1 : 0, $targetId]);
            audit_admin_action((int) $superadmin['id'], 'change_user_role', 'user', (string) $targetId, ['role' => $role]);
            flash('success', 'Rol actualizado.');
        }
    } elseif ($action === 'reset_user_password' && !$protected) {
        $password = request_value('temporary_password');
        if (password_is_valid($password)) {
            $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active', reset_token_hash = NULL, reset_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
            $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ?')->execute([hash('sha256', strtolower((string) $target['email']))]);
            audit_admin_action((int) $superadmin['id'], 'reset_user_password', 'user', (string) $targetId);
            flash('success', 'Contraseña temporal aplicada. No se envió correo.');
        } else {
            flash('error', 'La contraseña temporal debe tener 10 caracteres, mayúscula, minúscula, número y símbolo.');
        }
    } elseif ($action === 'delete_user' && !$protected) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        audit_admin_action((int) $superadmin['id'], 'delete_user', 'user', (string) $targetId, ['email' => $target['email']]);
        flash('success', 'Usuario eliminado.');
    } elseif (in_array($action, ['change_user_role', 'reset_user_password', 'delete_user'], true) && $protected) {
        flash('error', 'Esta cuenta está protegida y no puede modificarse desde aquí.');
    }
    redirect('superadmin.php#users-superadmin');
}

$cms = site_settings($pdo, $cmsDefaults);
$users = $pdo->query('SELECT id, name, email, role, status, last_login_at, is_superadmin FROM users ORDER BY name')->fetchAll();
$auditLog = $pdo->query('SELECT a.*, u.name AS admin_name FROM admin_audit_log a LEFT JOIN users u ON u.id = a.admin_user_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll();
$emailLog = $pdo->query('SELECT * FROM email_delivery_log ORDER BY created_at DESC LIMIT 50')->fetchAll();

render_header('SuperAdmin');
?>
<section class="page-heading"><p class="kicker">Control Global</p><h1>SuperAdmin.</h1><p>CMS, usuarios, seguridad y trazabilidad del portal.</p></section>
<nav class="admin-jump-nav" aria-label="Secciones de SuperAdmin">
  <a href="#cms-superadmin">CMS Público</a><a href="#users-superadmin">Usuarios</a><a href="#audit-superadmin">Trazabilidad</a><a href="#email-superadmin">Últimos Envíos</a>
</nav>

<section class="admin-section" id="cms-superadmin">
  <div class="section-title"><div><p class="kicker">Contenido Público</p><h2>CMS Seguro</h2></div><span>Sin Editar Código</span></div>
  <p class="admin-section-intro">Edita campos aprobados de la página pública. El diseño, HTML y SEO estructural permanecen protegidos.</p>
  <form method="post" enctype="multipart/form-data" class="inline-form resource-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_cms">
    <label>Título Hero Azul<input name="hero_blue" maxlength="120" value="<?= e($cms['hero_blue']) ?>" required></label>
    <label>Título Hero Vinotinto<input name="hero_wine" maxlength="120" value="<?= e($cms['hero_wine']) ?>" required></label>
    <label class="wide">Texto Hero<textarea name="hero_text" rows="4" maxlength="1500" required><?= e($cms['hero_text']) ?></textarea></label>
    <label>Título Historia<input name="history_title" maxlength="120" value="<?= e($cms['history_title']) ?>" required></label>
    <label>Énfasis Historia<input name="history_emphasis" maxlength="120" value="<?= e($cms['history_emphasis']) ?>" required></label>
    <label class="wide">Historia Principal<textarea name="history_text_one" rows="3" maxlength="1500" required><?= e($cms['history_text_one']) ?></textarea></label>
    <label class="wide">Historia Secundaria<textarea name="history_text_two" rows="3" maxlength="1500" required><?= e($cms['history_text_two']) ?></textarea></label>
    <label>Título Contacto<input name="contact_title" maxlength="120" value="<?= e($cms['contact_title']) ?>" required></label>
    <label>Énfasis Contacto<input name="contact_emphasis" maxlength="120" value="<?= e($cms['contact_emphasis']) ?>" required></label>
    <label class="wide">Texto Contacto<textarea name="contact_text" rows="3" maxlength="1500" required><?= e($cms['contact_text']) ?></textarea></label>
    <label class="wide">Imagen Pública Del Producto<input name="public_product_image" maxlength="500" value="<?= e($cms['public_product_image']) ?>" required><small>Ruta local o URL HTTPS.</small></label>
    <label class="wide">Subir Nueva Imagen Del Producto<input type="file" name="public_product_upload" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WebP. Máximo 8 MB.</small></label>
    <button class="primary-button" type="submit">Guardar Contenido Público</button>
  </form>
</section>

<section class="admin-section" id="users-superadmin">
  <div class="section-title"><div><p class="kicker">Control De Acceso</p><h2>Usuarios Registrados</h2></div><span><?= count($users) ?> cuentas</span></div>
  <p class="admin-section-intro">Cambiar rol, aplicar contraseña temporal o eliminar cuentas. Tu cuenta SuperAdmin está protegida.</p>
  <div class="membership-record-grid">
  <?php foreach ($users as $account): $protected = (int) $account['id'] === (int) $superadmin['id'] || is_superadmin($account); ?>
    <article class="membership-record-card">
      <strong><?= e($account['name']) ?><?= $protected ? ' · Protegida' : '' ?></strong>
      <span><?= e($account['email']) ?> · <?= e($account['status']) ?> · <?= e($account['last_login_at'] ?: 'Sin acceso') ?></span>
      <?php if (!$protected): ?>
      <form method="post" class="admin-actions"><?= csrf_field() ?><input type="hidden" name="action" value="change_user_role"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><select name="role"><option value="member" <?= $account['role'] === 'member' ? 'selected' : '' ?>>Member</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select><button class="small-button" type="submit">Cambiar Rol</button></form>
      <form method="post" class="admin-actions"><?= csrf_field() ?><input type="hidden" name="action" value="reset_user_password"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><input type="password" name="temporary_password" minlength="10" placeholder="Contraseña temporal" required><button class="small-button" type="submit">Resetear Contraseña</button></form>
      <form method="post" data-confirm="¿Eliminar permanentemente esta cuenta y sus datos asociados?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><button class="small-button danger-button" type="submit">Eliminar Usuario</button></form>
      <?php else: ?><span>Rol visible: <?= e($account['role']) ?></span><?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="audit-superadmin">
  <div class="section-title"><div><p class="kicker">Trazabilidad</p><h2>Auditoría Administrativa</h2></div><span>Últimas <?= count($auditLog) ?> acciones</span></div>
  <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Administrador</th><th>Acción</th><th>Objetivo</th><th>Detalle</th></tr></thead><tbody>
  <?php foreach ($auditLog as $entry): ?><tr><td><?= e($entry['created_at']) ?></td><td><?= e($entry['admin_name'] ?: 'Sistema') ?></td><td><?= e($entry['action']) ?></td><td><?= e(trim(($entry['target_type'] ?: '') . ' ' . ($entry['target_id'] ?: ''))) ?></td><td><small><?= e($entry['details'] ?: '—') ?></small></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="admin-section" id="email-superadmin">
  <div class="section-title email-log-title"><div><p class="kicker">Correo</p><h2>Últimos Envíos</h2></div><span><?= count($emailLog) ?> registros</span></div>
  <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Destinatario</th><th>Tipo</th><th>Asunto</th><th>Resultado</th></tr></thead><tbody>
  <?php foreach ($emailLog as $entry): ?><tr><td><?= e($entry['created_at']) ?></td><td><?= e($entry['recipient']) ?></td><td><?= e($entry['message_type']) ?></td><td><?= e($entry['subject']) ?></td><td><?= $entry['sent'] ? 'Aceptado por servidor' : 'Falló' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php render_footer(); ?>
