<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$user = require_auth();
$settingsStatement = $pdo->prepare('SELECT * FROM profile_settings WHERE user_id = ?');
$settingsStatement->execute([$user['id']]);
$settings = $settingsStatement->fetch() ?: ['show_publicly' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (request_value('action') === 'change_password') {
        $currentPassword = request_value('current_password');
        $newPassword = request_value('new_password');
        $confirmPassword = request_value('confirm_password');
        if (!password_verify($currentPassword, $user['password_hash'])) {
            flash('error', 'La contraseña actual no es correcta.');
        } elseif (!password_is_valid($newPassword)) {
            flash('error', 'La contraseña nueva debe cumplir todos los requisitos indicados.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'La confirmación no coincide con la contraseña nueva.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Tu contraseña fue actualizada.');
        }
        redirect('profile.php');
    }

    $photoUrl = $user['photo_url'];
    if (!empty($_FILES['profile_photo']['tmp_name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
        try {
            $filename = optimized_uploaded_image($_FILES['profile_photo'], __DIR__ . '/uploads/profiles', 'member-' . (int) $user['id'], 1200, 1200, 5 * 1024 * 1024);
            $photoUrl = app_url('uploads/profiles/' . $filename);
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('profile.php');
        }
    }
    $fields = [
        request_value('name'), request_value('birth_date') ?: null,
        request_value('birth_city'), request_value('birth_country'),
        request_value('residence_city'), request_value('residence_country'),
        request_value('phone'), request_value('bio'), $photoUrl, (int) $user['id'],
    ];
    $pdo->prepare('UPDATE users SET name = ?, birth_date = ?, birth_city = ?, birth_country = ?, residence_city = ?, residence_country = ?, phone = ?, bio = ?, photo_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
        ->execute($fields);
    $recordStatement = $pdo->prepare('SELECT id, is_founder, is_board_member FROM member_records WHERE user_id = ? OR (user_id IS NULL AND LOWER(email) = LOWER(?)) ORDER BY user_id DESC LIMIT 1');
    $recordStatement->execute([$user['id'], $user['email']]);
    $record = $recordStatement->fetch();
    if ($record) {
        $pdo->prepare('UPDATE member_records SET user_id = ?, name = ?, birth_date = ?, birth_city = ?, birth_country = ?, residence_city = ?, residence_country = ?, photo_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$user['id'], request_value('name'), request_value('birth_date') ?: null, request_value('birth_city'), request_value('birth_country'), request_value('residence_city'), request_value('residence_country'), $photoUrl, $record['id']]);
        $pdo->prepare('UPDATE users SET is_founder = ?, is_board_member = ? WHERE id = ?')->execute([$record['is_founder'], $record['is_board_member'], $user['id']]);
    }
    $showPublicly = isset($_POST['show_publicly']) ? 1 : 0;
    $existingSettings = $pdo->prepare('SELECT COUNT(*) FROM profile_settings WHERE user_id = ?');
    $existingSettings->execute([$user['id']]);
    if ((int) $existingSettings->fetchColumn() > 0) {
        $pdo->prepare('UPDATE profile_settings SET show_publicly = ?, show_photo = 0, show_birthplace = 0, show_residence = 0, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')->execute([$showPublicly, $user['id']]);
    } else {
        $pdo->prepare('INSERT INTO profile_settings (user_id, show_publicly, show_photo, show_birthplace, show_residence) VALUES (?, ?, 0, 0, 0)')->execute([$user['id'], $showPublicly]);
    }
    flash('success', 'Tu ficha fue actualizada.');
    redirect('profile.php');
}

render_header('Mi perfil');
?>
<section class="page-heading profile-heading">
  <?php if ($user['photo_url']): ?><img class="profile-photo" src="<?= e($user['photo_url']) ?>" alt="Foto de <?= e($user['name']) ?>" referrerpolicy="no-referrer"><?php else: ?><div class="profile-photo placeholder"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div><?php endif; ?>
    <div><p class="kicker">Mi Ficha De Peñista</p><h1><?= e($user['name']) ?></h1><p>Actualiza tu información y decide qué datos quieres mostrar en nuestra comunidad.</p></div>
</section>
<section class="form-panel">
  <form method="post" enctype="multipart/form-data" class="profile-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">
    <div class="field-grid">
      <label class="wide">Nombre completo<input name="name" value="<?= e($user['name']) ?>" required></label>
      <label class="wide">Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WebP. Máximo 5 MB.</small></label>
      <label>Correo electrónico<input value="<?= e($user['email']) ?>" disabled><small>Solicita a un administrador cambiarlo.</small></label>
      <label>Número de Peñista<input value="<?= e($user['member_number'] ?: 'Por asignar') ?>" disabled></label>
      <label>Tiempo siendo Saeta<input value="<?= e(membership_duration($user['joined_at'] ?? null)) ?>" disabled><small>La fecha de entrada solo puede ser modificada por un administrador.</small></label>
      <label>Fecha de nacimiento<input type="date" name="birth_date" value="<?= e($user['birth_date']) ?>"></label>
      <label>Teléfono<input type="tel" name="phone" value="<?= e($user['phone']) ?>"></label>
      <label>Ciudad de nacimiento<input name="birth_city" value="<?= e($user['birth_city']) ?>"></label>
      <label>País de nacimiento<input name="birth_country" value="<?= e($user['birth_country']) ?>"></label>
      <label>Ciudad de residencia<input name="residence_city" value="<?= e($user['residence_city']) ?>"></label>
      <label>País de residencia<input name="residence_country" value="<?= e($user['residence_country']) ?>"></label>
      <label class="wide">Sobre mí<textarea name="bio" rows="4" maxlength="800"><?= e($user['bio']) ?></textarea></label>
    </div>
    <fieldset class="visibility-settings">
      <legend>Privacidad pública</legend>
      <label><input type="checkbox" name="show_publicly" <?= $settings['show_publicly'] ? 'checked' : '' ?>> Mostrar mi nombre y país en la web pública</label>
      <p class="privacy-note">Si lo desactivas, aparecerás como “Peñista Saeta” y tu país como “Ubicación privada”. Tu correo, teléfono y ciudades nunca se muestran en los directorios.</p>
    </fieldset>
    <button class="primary-button" type="submit">Guardar Cambios</button>
  </form>
</section>
<section class="form-panel security-panel">
  <div class="section-title"><div><p class="kicker">Seguridad</p><h2>Cambiar Contraseña</h2></div></div>
  <form method="post" class="form-stack password-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
    <div class="field-grid">
      <label>Contraseña actual<input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>Contraseña nueva<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label>
      <label>Confirmar contraseña nueva<input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required></label>
    </div>
    <div class="password-requirements"><strong>Tu contraseña debe incluir:</strong><ul><li data-rule="length">10 caracteres</li><li data-rule="lowercase">Una minúscula</li><li data-rule="uppercase">Una mayúscula</li><li data-rule="number">Un número</li><li data-rule="symbol">Un símbolo</li></ul></div>
    <p class="password-match" aria-live="polite"></p>
    <button class="primary-button" type="submit">Actualizar Contraseña</button>
  </form>
</section>
<?php render_footer(); ?>
