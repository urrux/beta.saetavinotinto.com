<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

function client_ip_hash(): string
{
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip);
}

if (current_user()) {
    $destination = safe_portal_destination((string) ($_GET['next'] ?? $_SESSION['login_destination'] ?? ''));
    unset($_SESSION['login_destination']);
    header('Location: ' . ($destination ? app_url($destination) : '../?logged=1'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(request_value('email'));
    $password = request_value('password');
    $emailHash = hash('sha256', $email);
    $ipHash = client_ip_hash();
    $windowStart = date('Y-m-d H:i:s', time() - 15 * 60);
    $cleanupStart = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cleanupStart]);
    $attemptStatement = $pdo->prepare('SELECT COUNT(*) AS attempts FROM login_attempts WHERE successful = 0 AND attempted_at >= ? AND (email_hash = ? OR ip_hash = ?)');
    $attemptStatement->execute([$windowStart, $emailHash, $ipHash]);
    if ((int) $attemptStatement->fetchColumn() >= 5) {
        usleep(500000);
        flash('error', 'Demasiados intentos. Espera 15 minutos e intenta nuevamente.');
        redirect('login.php');
    }

    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();

    if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $firstLogin = $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ? AND last_login_at IS NULL');
        $firstLogin->execute([$user['id']]);
        if ($firstLogin->rowCount() === 1) {
            send_first_login_welcome($user);
        } else {
            $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
        }
        if ($user['role'] === 'member' && empty($user['admin_notified_at'])) {
            send_registration_notification($user);
        }
        $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ? OR ip_hash = ?')->execute([$emailHash, $ipHash]);
        $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash, successful) VALUES (?, ?, 1)')->execute([$emailHash, $ipHash]);
        $destination = safe_portal_destination(request_value('next') ?: (string) ($_SESSION['login_destination'] ?? ''));
        unset($_SESSION['login_destination']);
        header('Location: ' . ($destination ? app_url($destination) : '../?logged=1'));
        exit;
    }

    $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash, successful) VALUES (?, ?, 0)')->execute([$emailHash, $ipHash]);
    usleep(350000);
    flash('error', 'Correo o contraseña incorrectos.');
    $destination = safe_portal_destination(request_value('next'));
    redirect('login.php' . ($destination ? '?next=' . urlencode($destination) : ''));
}

render_header('Acceso de Peñistas', true);
?>
<section class="auth-card">
  <a class="auth-logo" href="../"><img src="../images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto"></a>
  <p class="kicker">Área Privada De Saetas</p>
  <h1>Bienvenido, Saeta.</h1>
  <p class="auth-intro">Accede a tu perfil, solicitudes de entradas y recursos exclusivos de La Peña.</p>
  <form method="post" class="form-stack">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e(safe_portal_destination((string) ($_GET['next'] ?? $_SESSION['login_destination'] ?? '')) ?? '') ?>">
    <label>Correo electrónico<input type="email" name="email" autocomplete="email" required></label>
    <label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="primary-button" type="submit">Iniciar Sesión</button>
  </form>
  <a class="text-link" href="<?= e(app_url('register.php')) ?>">Solicitar Acceso De Peñista</a>
  <a class="text-link" href="<?= e(app_url('forgot-password.php')) ?>">¿Olvidaste Tu Contraseña?</a>
  <a class="quiet-link" href="../">Volver Al Inicio</a>
</section>
<?php render_footer(); ?>
