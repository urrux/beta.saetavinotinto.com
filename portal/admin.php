<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();

function uploaded_product_image(?string $currentUrl = null): ?string
{
    if (empty($_FILES['product_image']['tmp_name']) || !is_uploaded_file($_FILES['product_image']['tmp_name'])) {
        return request_value('image_url') ?: $currentUrl;
    }
    $uploadDir = __DIR__ . '/uploads/products';
    $filename = optimized_uploaded_image($_FILES['product_image'], $uploadDir, 'product', 1800, 1800, 8 * 1024 * 1024);
    return app_url('uploads/products/' . $filename);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value('action');
    $alreadyAudited = false;

    if ($action === 'invite_member') {
        $name = request_value('name');
        $email = strtolower(request_value('email'));
        $recordId = (int) request_value('record_id');
        $record = null;
        if ($recordId) {
            $recordStatement = $pdo->prepare('SELECT * FROM member_records WHERE id = ? AND user_id IS NULL');
            $recordStatement->execute([$recordId]);
            $record = $recordStatement->fetch();
            if ($record) {
                $name = $record['name'];
            }
        }
        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $token = bin2hex(random_bytes(32));
            $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
            $role = $record && !empty($record['is_portal_admin']) ? 'admin' : 'member';
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status, reset_token_hash, reset_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            try {
                $statement->execute([$name, $email, $hash, $role, 'invited', hash('sha256', $token), date('Y-m-d H:i:s', time() + 172800)]);
                $newUserId = (int) $pdo->lastInsertId();
                if ($record) {
                    $pdo->prepare('UPDATE users SET joined_at = ?, birth_date = ?, birth_city = ?, birth_country = ?, residence_city = ?, residence_country = ?, photo_url = ?, is_founder = ?, is_board_member = ? WHERE id = ?')
                        ->execute([$record['joined_at'], $record['birth_date'], $record['birth_city'], $record['birth_country'], $record['residence_city'], $record['residence_country'], $record['photo_url'], $record['is_founder'], $record['is_board_member'], $newUserId]);
                    $pdo->prepare('UPDATE member_records SET user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$newUserId, $record['id']]);
                }
                $link = app_url('reset-password.php?token=' . urlencode($token));
                send_portal_email($email, 'Invitación al portal de Saeta Vinotinto', "Hola {$name},\n\nLa directiva te invita al portal privado de Saeta Vinotinto.\nCrea tu contraseña durante las próximas 48 horas:\n{$link}\n\nMadridismo sin fronteras.");
                flash('success', 'Peñista invitado. Se envió un enlace para crear su contraseña.');
            } catch (PDOException $exception) {
                flash('error', 'No se pudo crear la cuenta. Verifica que el correo no esté registrado.');
            }
        } else {
            flash('error', 'Escribe un nombre y correo válidos.');
        }
    }

    if ($action === 'update_ticket') {
        $allowed = ['received', 'reviewing', 'approved', 'rejected', 'completed'];
        $status = request_value('status');
        if (in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE ticket_requests SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, request_value('admin_notes'), (int) request_value('ticket_id')]);
            flash('success', 'Solicitud actualizada.');
        }
    }

    if ($action === 'add_resource') {
        $title = request_value('title');
        $url = request_value('url');
        if ($title && filter_var($url, FILTER_VALIDATE_URL)) {
            $pdo->prepare('INSERT INTO resources (title, description, url, category, created_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$title, request_value('description'), $url, request_value('category') ?: 'General', $admin['id']]);
            flash('success', 'Recurso publicado para los Peñistas.');
        } else {
            flash('error', 'El recurso necesita título y un enlace válido.');
        }
    }

    if ($action === 'toggle_resource') {
        $pdo->prepare('UPDATE resources SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) request_value('resource_id')]);
        flash('success', 'Visibilidad del recurso actualizada.');
    }

    if ($action === 'delete_resource') {
        $pdo->prepare('DELETE FROM resources WHERE id = ?')->execute([(int) request_value('resource_id')]);
        flash('success', 'Recurso eliminado.');
    }

    if ($action === 'add_document') {
        $type = request_value('document_type');
        if (!in_array($type, ['rules', 'pillars', 'statutes', 'other'], true)) $type = 'other';
        if (request_value('title')) {
            $pdo->prepare('INSERT INTO governance_documents (title, document_type, summary, content, url, version, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([request_value('title'), $type, request_value('summary'), request_value('content'), request_value('url') ?: null, request_value('version'), $admin['id']]);
            flash('success', 'Documento institucional publicado.');
        }
    }

    if ($action === 'toggle_document') {
        $pdo->prepare('UPDATE governance_documents SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) request_value('document_id')]);
        flash('success', 'Visibilidad del documento actualizada.');
    }

    if ($action === 'delete_document') {
        $pdo->prepare('DELETE FROM governance_documents WHERE id = ?')->execute([(int) request_value('document_id')]);
        flash('success', 'Documento eliminado.');
    }

    if ($action === 'add_announcement') {
        $title = request_value('title');
        $body = request_value('body');
        $linkUrl = request_value('link_url');
        if ($title && $body && (!$linkUrl || filter_var($linkUrl, FILTER_VALIDATE_URL))) {
            $pdo->prepare('INSERT INTO announcements (title, body, link_url, link_label, created_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$title, $body, $linkUrl ?: null, request_value('link_label') ?: null, $admin['id']]);
            flash('success', 'Anuncio publicado.');
        } else {
            flash('error', 'El anuncio necesita título, contenido y un enlace válido si lo incluyes.');
        }
    }

    if ($action === 'toggle_announcement') {
        $pdo->prepare('UPDATE announcements SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) request_value('announcement_id')]);
        flash('success', 'Visibilidad del anuncio actualizada.');
    }

    if ($action === 'feature_announcement') {
        $announcementId = (int) request_value('announcement_id');
        $pdo->beginTransaction();
        $pdo->exec('UPDATE announcements SET is_featured = 0');
        if (request_value('feature') === '1') {
            $pdo->prepare('UPDATE announcements SET is_featured = 1, is_published = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$announcementId]);
        }
        $pdo->commit();
        flash('success', request_value('feature') === '1' ? 'Banner destacado actualizado.' : 'Banner retirado.');
    }

    if ($action === 'delete_announcement') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int) request_value('announcement_id')]);
        flash('success', 'Anuncio eliminado.');
    }

    if ($action === 'add_product') {
        if (request_value('name')) {
            try {
                $imageUrl = uploaded_product_image();
                $pdo->prepare('INSERT INTO products (name, description, price, currency, image_url, stock) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([request_value('name'), request_value('description'), (float) request_value('price'), strtoupper(request_value('currency') ?: 'USD'), $imageUrl, request_value('stock') === '' ? null : (int) request_value('stock')]);
                flash('success', 'Producto publicado.');
            } catch (RuntimeException $exception) {
                flash('error', $exception->getMessage());
            }
        }
    }

    if ($action === 'update_product') {
        $productId = (int) request_value('product_id');
        $statement = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if ($product && request_value('name')) {
            try {
                $imageUrl = uploaded_product_image($product['image_url']);
                $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, currency = ?, image_url = ?, stock = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([request_value('name'), request_value('description'), (float) request_value('price'), strtoupper(request_value('currency') ?: 'USD'), $imageUrl, request_value('stock') === '' ? null : (int) request_value('stock'), $productId]);
                flash('success', 'Producto actualizado.');
            } catch (RuntimeException $exception) {
                flash('error', $exception->getMessage());
            }
        }
    }

    if ($action === 'toggle_product') {
        $pdo->prepare('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) request_value('product_id')]);
        flash('success', 'Visibilidad del producto actualizada.');
    }

    if ($action === 'update_order') {
        $allowed = ['requested', 'confirmed', 'paid', 'delivered', 'cancelled'];
        if (in_array(request_value('status'), $allowed, true)) {
            $pdo->prepare('UPDATE product_orders SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([request_value('status'), request_value('admin_notes'), (int) request_value('order_id')]);
            flash('success', 'Pedido actualizado.');
        }
    }

    if ($action === 'toggle_member') {
        $targetId = (int) request_value('user_id');
        $targetStatement = $pdo->prepare('SELECT role, is_superadmin FROM users WHERE id = ?');
        $targetStatement->execute([$targetId]);
        $target = $targetStatement->fetch();
        $canManageTarget = $target
            && $targetId !== (int) $admin['id']
            && (is_superadmin($admin) || $target['role'] === 'member');
        if ($canManageTarget) {
            $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'active' END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$targetId]);
            flash('success', 'Estado del Peñista actualizado.');
        } else {
            flash('error', 'No tienes permiso para cambiar el estado de este administrador.');
        }
    }

    if ($action === 'update_membership_dates') {
        $changes = 0;
        $pdo->beginTransaction();
        foreach (['user_dates' => 'users', 'record_dates' => 'member_records'] as $field => $table) {
            $dates = $_POST[$field] ?? [];
            if (!is_array($dates)) continue;
            foreach ($dates as $targetId => $rawDate) {
                $targetId = (int) $targetId;
                if (!$targetId) continue;
                $joinedAt = valid_date_or_null(is_string($rawDate) ? $rawDate : '');
                $statement = $pdo->prepare("UPDATE {$table} SET joined_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND NOT (joined_at <=> ?)");
                if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                    $statement = $pdo->prepare("UPDATE {$table} SET joined_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND COALESCE(joined_at, '') <> COALESCE(?, '')");
                }
                $statement->execute([$joinedAt, $targetId, $joinedAt]);
                $changes += $statement->rowCount();
            }
        }
        $pdo->commit();
        audit_admin_action((int) $admin['id'], $action, 'membership_dates', null, ['changes' => $changes]);
        $alreadyAudited = true;
        flash('success', $changes ? "{$changes} fecha(s) de entrada actualizada(s)." : 'No había cambios de fechas por guardar.');
        header('Location: ' . app_url('admin.php#membership-admin'));
        exit;
    }

    if ($action === 'update_institutional_roles') {
        $targetType = request_value('target_type');
        $targetId = (int) request_value('target_id');
        if ($targetId && in_array($targetType, ['user', 'record'], true)) {
            $table = $targetType === 'user' ? 'users' : 'member_records';
            $founder = isset($_POST['is_founder']) ? 1 : 0;
            $board = isset($_POST['is_board_member']) ? 1 : 0;
            $pdo->prepare("UPDATE {$table} SET is_founder = ?, is_board_member = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$founder, $board, $targetId]);
            audit_admin_action((int) $admin['id'], $action, $targetType, (string) $targetId, ['is_founder' => $founder, 'is_board_member' => $board]);
            $alreadyAudited = true;
            flash('success', 'Reconocimientos institucionales actualizados.');
        }
    }

    if (!$alreadyAudited) {
        $targetId = request_value('target_id') ?: request_value('user_id') ?: request_value('ticket_id') ?: request_value('resource_id') ?: request_value('document_id') ?: request_value('announcement_id') ?: request_value('product_id') ?: request_value('order_id') ?: null;
        $safeDetails = array_intersect_key($_POST, array_flip(['status', 'title', 'name', 'category', 'document_type', 'version', 'stock', 'currency']));
        audit_admin_action((int) $admin['id'], $action, null, $targetId, $safeDetails);
    }
    redirect('admin.php');
}

$members = $pdo->query('SELECT id, name, email, role, is_superadmin, status, member_number, joined_at, last_login_at, admin_notified_at, is_founder, is_board_member FROM users ORDER BY name')->fetchAll();
$records = $pdo->query('SELECT id, name, joined_at, residence_country, is_founder, is_board_member FROM member_records WHERE user_id IS NULL ORDER BY name')->fetchAll();
$tickets = $pdo->query('SELECT t.*, u.name AS member_name, u.email AS member_email FROM ticket_requests t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC')->fetchAll();
$resources = $pdo->query('SELECT * FROM resources ORDER BY created_at DESC')->fetchAll();
$documents = $pdo->query('SELECT * FROM governance_documents ORDER BY created_at DESC')->fetchAll();
$announcements = $pdo->query('SELECT * FROM announcements ORDER BY is_featured DESC, created_at DESC')->fetchAll();
$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
$orders = $pdo->query('SELECT o.*, u.name AS member_name, p.name AS product_name FROM product_orders o JOIN users u ON u.id = o.user_id JOIN products p ON p.id = o.product_id ORDER BY o.created_at DESC')->fetchAll();
$statusLabels = ['received' => 'Recibida', 'reviewing' => 'En revisión', 'approved' => 'Aprobada', 'rejected' => 'No disponible', 'completed' => 'Completada'];

render_header('Administración');
?>
<section class="page-heading"><p class="kicker">Directiva</p><h1>Administración.</h1><p>Gestiona accesos, solicitudes y recursos privados de La Peña.</p></section>
<nav class="admin-jump-nav" aria-label="Secciones de administración">
  <a href="#members-admin">Peñistas</a><a href="#announcements-admin">Anuncios</a><a href="#roles-admin">Reconocimientos</a><a href="#membership-admin">Trayectoria</a><a href="#tickets-admin">Entradas</a><a href="#resources-admin">Recursos</a><a href="#governance-admin">Documentos</a><a href="#store-admin">Tienda</a>
</nav>

<section class="admin-section" id="members-admin">
  <div class="section-title"><div><p class="kicker">Accesos</p><h2>Peñistas</h2></div><span><?= count($members) ?> cuentas</span></div>
  <form method="post" class="inline-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="invite_member">
    <label>Ficha importada<select name="record_id"><option value="">Crear sin ficha</option><?php foreach ($records as $record): ?><option value="<?= (int) $record['id'] ?>"><?= e($record['name']) ?><?= $record['residence_country'] ? ' · ' . e($record['residence_country']) : '' ?></option><?php endforeach; ?></select></label>
    <label>Nombre, si no usa ficha<input name="name"></label><label>Correo<input type="email" name="email" required></label>
    <button class="primary-button" type="submit">Invitar Peñista</button>
  </form>
  <div class="table-wrap"><table><thead><tr><th>Peñista</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Aviso Admin</th><th></th></tr></thead><tbody>
  <?php foreach ($members as $member): ?><tr>
    <td><strong><?= e($member['name']) ?></strong><small><?= e($member['email']) ?></small></td>
    <td><?= e($member['role']) ?></td><td><span class="status"><?= e($member['status']) ?></span></td>
    <td><?= e($member['last_login_at'] ?: 'Sin acceso') ?></td><td><?= $member['role'] === 'admin' ? 'No aplica' : e($member['admin_notified_at'] ?: 'Pendiente') ?></td>
    <td><?php if ((int) $member['id'] !== (int) $admin['id'] && (is_superadmin($admin) || $member['role'] === 'member')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_member"><input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>"><button class="small-button" type="submit"><?= $member['status'] === 'active' ? 'Suspender' : 'Activar' ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="admin-section" id="announcements-admin">
  <div class="section-title"><div><p class="kicker">Comunicaciones</p><h2>Anuncios</h2></div><span><?= count($announcements) ?> históricos</span></div>
  <form method="post" class="inline-form resource-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_announcement">
    <label>Título<input name="title" required></label><label>Texto Del Botón<input name="link_label" placeholder="Ver Más"></label>
    <label class="wide">Contenido<textarea name="body" rows="4" required></textarea></label><label class="wide">Enlace Opcional<input type="url" name="link_url"></label>
    <button class="primary-button" type="submit">Publicar Anuncio</button>
  </form>
  <div class="announcement-admin-grid">
  <?php foreach ($announcements as $announcement): ?><article class="announcement-card <?= $announcement['is_featured'] ? 'featured' : '' ?>">
    <div class="announcement-meta"><span><?= $announcement['is_featured'] ? 'Banner Activo' : ($announcement['is_published'] ? 'Publicado' : 'Oculto') ?></span><time><?= e(date('d/m/Y', strtotime($announcement['created_at']))) ?></time></div>
    <h3><?= e($announcement['title']) ?></h3><p><?= nl2br(e($announcement['body'])) ?></p>
    <div class="admin-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="feature_announcement"><input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>"><input type="hidden" name="feature" value="<?= $announcement['is_featured'] ? '0' : '1' ?>"><button class="small-button" type="submit"><?= $announcement['is_featured'] ? 'Retirar Banner' : 'Destacar En Banner' ?></button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_announcement"><input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>"><button class="small-button" type="submit"><?= $announcement['is_published'] ? 'Ocultar' : 'Publicar' ?></button></form>
      <form method="post" data-confirm="¿Eliminar este anuncio permanentemente?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>"><button class="small-button danger-button" type="submit">Eliminar</button></form>
    </div>
  </article><?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="roles-admin">
  <div class="section-title"><div><p class="kicker">Identidad Institucional</p><h2>Fundadores Y Junta Directiva</h2></div><span>Solo Administradores</span></div>
  <p class="admin-section-intro">Estos reconocimientos alimentan automáticamente las fichas públicas. Ya no dependen de listas escritas en el código.</p>
  <div class="membership-record-grid">
    <?php foreach ($members as $member): ?><form method="post" class="membership-record-card role-record-card">
      <?= csrf_field() ?><input type="hidden" name="action" value="update_institutional_roles"><input type="hidden" name="target_type" value="user"><input type="hidden" name="target_id" value="<?= (int) $member['id'] ?>">
      <strong><?= e($member['name']) ?></strong>
      <label><input type="checkbox" name="is_founder" <?= $member['is_founder'] ? 'checked' : '' ?>> Fundador</label>
      <label><input type="checkbox" name="is_board_member" <?= $member['is_board_member'] ? 'checked' : '' ?>> Junta Directiva</label>
      <button class="small-button" type="submit">Guardar</button>
    </form><?php endforeach; ?>
    <?php foreach ($records as $record): ?><form method="post" class="membership-record-card role-record-card unregistered">
      <?= csrf_field() ?><input type="hidden" name="action" value="update_institutional_roles"><input type="hidden" name="target_type" value="record"><input type="hidden" name="target_id" value="<?= (int) $record['id'] ?>">
      <strong><?= e($record['name']) ?> · Sin cuenta</strong>
      <label><input type="checkbox" name="is_founder" <?= $record['is_founder'] ? 'checked' : '' ?>> Fundador</label>
      <label><input type="checkbox" name="is_board_member" <?= $record['is_board_member'] ? 'checked' : '' ?>> Junta Directiva</label>
      <button class="small-button" type="submit">Guardar</button>
    </form><?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="membership-admin">
  <div class="section-title"><div><p class="kicker">Trayectoria</p><h2>Fechas De Entrada A La Peña</h2></div><span>Solo Administradores</span></div>
  <p class="admin-section-intro">Registra o corrige varias fechas y guarda todos los cambios al final. Las fechas se reflejan en Mi Espacio y el directorio privado.</p>
  <form method="post" class="membership-dates-bulk-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_membership_dates">
  <div class="membership-record-grid">
    <?php foreach ($members as $member): ?><label class="membership-record-card">
      <strong><?= e($member['name']) ?><?= (int) $member['id'] === (int) $admin['id'] ? ' · Tú' : '' ?></strong><span><?= e(membership_duration($member['joined_at'])) ?></span>
      <input type="date" name="user_dates[<?= (int) $member['id'] ?>]" value="<?= e($member['joined_at']) ?>" min="2010-01-01" max="<?= date('Y-m-d') ?>" aria-label="Fecha de entrada de <?= e($member['name']) ?>">
    </label><?php endforeach; ?>
    <?php foreach ($records as $record): ?><label class="membership-record-card unregistered">
      <strong><?= e($record['name']) ?> · Sin cuenta</strong><span><?= e(membership_duration($record['joined_at'])) ?></span>
      <input type="date" name="record_dates[<?= (int) $record['id'] ?>]" value="<?= e($record['joined_at']) ?>" min="2010-01-01" max="<?= date('Y-m-d') ?>" aria-label="Fecha de entrada de <?= e($record['name']) ?>">
    </label><?php endforeach; ?>
  </div>
    <button class="primary-button" type="submit">Guardar Todos Los Cambios</button>
  </form>
</section>

<section class="admin-section" id="tickets-admin">
  <div class="section-title"><div><p class="kicker">Operaciones</p><h2>Solicitudes De Entradas</h2></div><span><?= count($tickets) ?> solicitudes</span></div>
  <?php if (!$tickets): ?><p class="empty-state">No hay solicitudes todavía.</p><?php endif; ?>
  <div class="admin-ticket-grid">
  <?php foreach ($tickets as $ticket): ?><form method="post" class="admin-ticket">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
    <p class="kicker"><?= e($ticket['member_name']) ?></p><h3><?= e($ticket['match_name']) ?></h3><p><?= e($ticket['match_date'] ?: 'Fecha por confirmar') ?> · <?= (int) $ticket['quantity'] ?> entrada(s)<?= $ticket['competition'] ? ' · ' . e($ticket['competition']) : '' ?></p>
    <?php if ($ticket['ticket_type'] || $ticket['budget_range'] || $ticket['availability_notes'] || $ticket['companion_names']): ?>
      <div class="ticket-admin-details">
        <?php if ($ticket['ticket_type']): ?><span>Tipo: <?= e($ticket['ticket_type']) ?></span><?php endif; ?>
        <?php if ($ticket['budget_range']): ?><span>Presupuesto: <?= e($ticket['budget_range']) ?></span><?php endif; ?>
        <?php if ($ticket['availability_notes']): ?><span>Viaje: <?= e($ticket['availability_notes']) ?></span><?php endif; ?>
        <?php if ($ticket['companion_names']): ?><span>Acompañantes: <?= e($ticket['companion_names']) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($ticket['notes']): ?><blockquote><?= e($ticket['notes']) ?></blockquote><?php endif; ?>
    <label>Estado<select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $ticket['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Nota para el Peñista<textarea name="admin_notes" rows="2"><?= e($ticket['admin_notes']) ?></textarea></label>
    <button class="small-button" type="submit">Actualizar</button>
  </form><?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="resources-admin">
  <div class="section-title"><div><p class="kicker">Biblioteca</p><h2>Recursos Privados</h2></div><span><?= count($resources) ?> recursos</span></div>
  <form method="post" class="inline-form resource-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_resource">
    <label>Título<input name="title" required></label><label>Categoría<input name="category" placeholder="Google Drive"></label>
    <label class="wide">Enlace<input type="url" name="url" required></label><label class="wide">Descripción<input name="description"></label>
    <button class="primary-button" type="submit">Publicar Recurso</button>
  </form>
  <div class="resource-grid">
  <?php foreach ($resources as $resource): ?><article class="resource-card">
    <span><?= e($resource['category']) ?></span><h3><?= e($resource['title']) ?></h3><p><?= e($resource['description']) ?></p>
    <div class="admin-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_resource"><input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>"><button class="small-button" type="submit"><?= $resource['is_active'] ? 'Ocultar' : 'Publicar' ?></button></form>
      <form method="post" data-confirm="¿Eliminar este recurso permanentemente?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_resource"><input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>"><button class="small-button danger-button" type="submit">Eliminar</button></form>
    </div>
  </article><?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="governance-admin">
  <div class="section-title"><div><p class="kicker">Identidad</p><h2>Reglas, Pilares Y Estatutos</h2></div><span><?= count($documents) ?> documentos</span></div>
  <form method="post" class="inline-form resource-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_document">
    <label>Título<input name="title" required></label><label>Tipo<select name="document_type"><option value="rules">Reglas</option><option value="pillars">Pilares</option><option value="statutes">Estatutos</option><option value="other">Otro</option></select></label>
    <label>Versión<input name="version" placeholder="Ej. 2026"></label><label>Enlace opcional<input type="url" name="url"></label>
    <label class="wide">Resumen<input name="summary"></label><label class="wide">Contenido<textarea name="content" rows="5"></textarea></label>
    <button class="primary-button" type="submit">Publicar Documento</button>
  </form>
  <div class="resource-grid">
  <?php foreach ($documents as $document): ?><article class="resource-card">
    <span><?= e($document['document_type']) ?> · <?= $document['is_active'] ? 'Publicado' : 'Oculto' ?></span>
    <h3><?= e($document['title']) ?></h3><p><?= e($document['summary']) ?></p>
    <?php if ($document['url']): ?><a class="admin-resource-link" href="<?= e($document['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir documento ↗</a><?php endif; ?>
    <div class="admin-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_document"><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="small-button" type="submit"><?= $document['is_active'] ? 'Ocultar' : 'Publicar' ?></button></form>
      <form method="post" data-confirm="¿Eliminar este documento permanentemente?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_document"><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="small-button danger-button" type="submit">Eliminar</button></form>
    </div>
  </article><?php endforeach; ?>
  </div>
</section>

<section class="admin-section" id="store-admin">
  <div class="section-title"><div><p class="kicker">Tienda</p><h2>Productos Y Pedidos</h2></div><span><?= count($orders) ?> pedidos</span></div>
  <form method="post" enctype="multipart/form-data" class="inline-form resource-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_product">
    <label>Producto<input name="name" value="Bufanda oficial Saeta Vinotinto" required></label><label>Precio<input type="number" step="0.01" min="0" name="price" required></label>
    <label>Moneda<input name="currency" value="USD" maxlength="3"></label><label>Stock opcional<input type="number" min="0" name="stock"></label>
    <label>Subir imagen<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WebP. Máximo 8 MB.</small></label><label>Imagen URL opcional<input type="url" name="image_url"></label>
    <label class="wide">Descripción<input name="description"></label>
    <button class="primary-button" type="submit">Publicar Producto</button>
  </form>
  <div class="product-admin-grid">
  <?php foreach ($products as $product): ?><article class="product-admin-card">
    <?php if ($product['image_url']): ?><img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?>
    <span><?= $product['is_active'] ? 'Publicado' : 'Oculto' ?></span>
    <form method="post" enctype="multipart/form-data" class="product-edit-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_product"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <label>Producto<input name="name" value="<?= e($product['name']) ?>" required></label>
    <div class="product-admin-fields"><label>Precio<input type="number" step="0.01" min="0" name="price" value="<?= e((string) $product['price']) ?>" required></label><label>Moneda<input name="currency" maxlength="3" value="<?= e($product['currency']) ?>"></label><label>Stock<input type="number" min="0" name="stock" value="<?= e($product['stock'] === null ? '' : (string) $product['stock']) ?>"></label></div>
    <label>Descripción<textarea name="description" rows="3"><?= e($product['description']) ?></textarea></label>
    <label>Nueva imagen desde la PC<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label>
    <label>Imagen URL<input type="url" name="image_url" value="<?= e($product['image_url']) ?>"></label>
        <div class="admin-actions"><button class="primary-button" type="submit">Guardar Cambios</button></div>
    </form>
        <form method="post" class="product-toggle-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="small-button" type="submit"><?= $product['is_active'] ? 'Ocultar Producto' : 'Publicar Producto' ?></button></form>
  </article><?php endforeach; ?>
  </div>
  <div class="section-title orders-title"><div><p class="kicker">Pedidos</p><h2>Solicitudes Recibidas</h2></div></div>
  <div class="admin-ticket-grid">
  <?php foreach ($orders as $order): ?><form method="post" class="admin-ticket"><?= csrf_field() ?><input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
    <p class="kicker"><?= e($order['member_name']) ?></p><h3><?= e($order['product_name']) ?></h3><p><?= (int) $order['quantity'] ?> unidad(es) · <?= e($order['currency']) ?> <?= number_format((float) $order['total'], 2) ?></p>
    <label>Estado<select name="status"><?php foreach (['requested'=>'Solicitado','confirmed'=>'Confirmado','paid'=>'Pagado','delivered'=>'Entregado','cancelled'=>'Cancelado'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $order['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Nota Administrativa<textarea name="admin_notes"><?= e($order['admin_notes']) ?></textarea></label><button class="small-button" type="submit">Actualizar Pedido</button>
  </form><?php endforeach; ?>
  </div>
</section>
<?php render_footer(); ?>
