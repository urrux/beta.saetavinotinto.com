<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$statement = $pdo->prepare("SELECT id, name, email, status FROM users WHERE reset_token_hash = ? AND reset_expires_at > CURRENT_TIMESTAMP AND status IN ('active', 'invited')");
$statement->execute([$tokenHash]);
$target = $statement->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = request_value('password');
    $passwordConfirmation = request_value('password_confirmation');
    if (!$target) {
        flash('error', 'Este enlace expiró o ya fue utilizado.');
    } elseif (!password_is_valid($password)) {
        flash('error', 'La contraseña todavía no cumple todos los requisitos.');
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        flash('error', 'Las contraseñas no coinciden.');
    } else {
        $activation = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active', reset_token_hash = NULL, reset_expires_at = NULL WHERE id = ? AND reset_token_hash = ?");
        $activation->execute([password_hash($password, PASSWORD_DEFAULT), $target['id'], $tokenHash]);
        if ($activation->rowCount() === 1 && $target['status'] === 'invited') {
            send_registration_notification($target);
        }
        flash('success', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
        redirect('login.php');
    }
}

render_header('Nueva Contraseña', true);
?>
<section class="auth-card">
  <a class="auth-logo" href="../"><img src="../images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto"></a>
  <p class="kicker">Seguridad</p>
  <h1>Nueva contraseña.</h1>
  <?php if ($target): ?>
  <form method="post" class="form-stack password-form">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <label>Contraseña nueva<input type="password" name="password" minlength="10" autocomplete="new-password" aria-describedby="password-requirements" required></label>
    <div class="password-requirements" id="password-requirements" aria-live="polite">
      <strong>Tu contraseña debe incluir:</strong>
      <ul><li data-requirement="length">10 caracteres o más</li><li data-requirement="uppercase">Una mayúscula</li><li data-requirement="lowercase">Una minúscula</li><li data-requirement="number">Un número</li><li data-requirement="symbol">Un símbolo</li></ul>
    </div>
    <label>Confirmar contraseña<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
    <p class="password-match" aria-live="polite"></p>
    <button class="primary-button" type="submit">Guardar Contraseña</button>
  </form>
  <?php else: ?><p class="auth-intro">Este enlace expiró o ya fue utilizado.</p><?php endif; ?>
  <a class="quiet-link" href="<?= e(app_url('login.php')) ?>">Volver Al Acceso</a>
</section>
<?php render_footer(); ?>
