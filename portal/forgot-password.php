<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(request_value('email'));
    $limited = request_rate_limited('forgot_password', $email ?: 'invalid');
    $user = null;
    if (!$limited && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? AND status = ?');
        $statement->execute([$email, 'active']);
        $user = $statement->fetch();
    }

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_expires_at = ? WHERE id = ?')
            ->execute([hash('sha256', $token), $expires, $user['id']]);
        $link = app_url('reset-password.php?token=' . urlencode($token));
        send_portal_email($user['email'], 'Restablece tu acceso a Saeta Vinotinto', "Hola {$user['name']},\n\nUsa este enlace durante la próxima hora:\n{$link}\n\nSi no solicitaste el cambio, ignora este mensaje.");
    }

    flash('success', 'Si el correo pertenece a un Peñista activo, enviaremos instrucciones de acceso. Revisa también Spam o Correo no deseado.');
    redirect('forgot-password.php');
}

render_header('Recuperar Acceso', true);
?>
<section class="auth-card">
  <a class="auth-logo" href="../"><img src="../images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto"></a>
  <p class="kicker">Recuperar Acceso</p>
  <h1>Volvamos a conectarte.</h1>
  <p class="auth-intro">Escribe el correo asociado a tu ficha de Peñista.</p>
  <form method="post" class="form-stack">
    <?= csrf_field() ?>
    <label>Correo electrónico<input type="email" name="email" autocomplete="email" required></label>
    <button class="primary-button" type="submit">Enviar Instrucciones</button>
  </form>
  <p class="auth-delivery-note"><strong>¿No ves el correo?</strong> Revisa Spam o Correo no deseado y marca a Saeta Vinotinto como remitente seguro.</p>
  <a class="quiet-link" href="<?= e(app_url('login.php')) ?>">Volver Al Acceso</a>
</section>
<?php render_footer(); ?>
