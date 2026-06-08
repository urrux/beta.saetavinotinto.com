<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: ../');
    exit;
}

function send_activation_link(array $user): void
{
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_expires_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
        ->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 172800), $user['id']]);
    $link = app_url('reset-password.php?token=' . urlencode($token));
    send_portal_email($user['email'], 'Activa tu acceso a Saeta Vinotinto', "Hola {$user['name']},\n\nUsa este enlace durante las próximas 48 horas para crear tu contraseña:\n{$link}\n\nMadridismo sin fronteras.");
}

function find_member_record_by_email(string $email): ?array
{
    global $pdo;
    $statement = $pdo->prepare('SELECT * FROM member_records WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $statement->execute([$email]);
    return $statement->fetch() ?: null;
}

function find_ticket_identity_by_email(string $email): ?array
{
    global $pdo;
    $statement = $pdo->prepare('SELECT requester_name AS name, requester_email AS email FROM imported_ticket_requests WHERE LOWER(requester_email) = LOWER(?) AND requester_name IS NOT NULL ORDER BY requested_at DESC, created_at DESC LIMIT 1');
    $statement->execute([$email]);
    return $statement->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(request_value('email'));
    $limited = request_rate_limited('register', $email ?: 'invalid');
    if (!$limited && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $userStatement = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $userStatement->execute([$email]);
        $user = $userStatement->fetch();
        if ($user) {
            send_activation_link($user);
        } else {
            $record = find_member_record_by_email($email);
            $identity = $record ?: find_ticket_identity_by_email($email);
            if ($identity && !empty($identity['name'])) {
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                $role = $record && !empty($record['is_portal_admin']) ? 'admin' : 'member';
                $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$identity['name'], $email, $hash, $role, 'invited']);
                $userId = (int) $pdo->lastInsertId();
                if ($record) {
                    $pdo->prepare('UPDATE users SET joined_at = ?, birth_date = ?, birth_city = ?, birth_country = ?, residence_city = ?, residence_country = ?, photo_url = ?, is_founder = ?, is_board_member = ? WHERE id = ?')
                        ->execute([$record['joined_at'], $record['birth_date'], $record['birth_city'], $record['birth_country'], $record['residence_city'], $record['residence_country'], $record['photo_url'], $record['is_founder'], $record['is_board_member'], $userId]);
                    $pdo->prepare('UPDATE member_records SET user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId, $record['id']]);
                }
                $newUserStatement = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $newUserStatement->execute([$userId]);
                send_activation_link($newUserStatement->fetch());
            }
        }
    }
    flash('success', 'Si el correo coincide con una ficha de Peñista, recibirás un enlace para activar tu acceso. Revisa también Spam o Correo no deseado.');
    redirect('register.php');
}

render_header('Solicitar Acceso', true);
?>
<section class="auth-card">
  <a class="auth-logo" href="../"><img src="../images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto"></a>
  <p class="kicker">Acceso De Peñistas</p>
  <h1>Solicita tu acceso.</h1>
  <p class="auth-intro">Escribe el correo asociado a tu ficha o historial de solicitudes. Si coincide, te enviaremos un enlace para crear tu contraseña.</p>
  <form method="post" class="form-stack">
    <?= csrf_field() ?>
    <label>Correo electrónico<input type="email" name="email" autocomplete="email" required></label>
    <button class="primary-button" type="submit">Enviar Enlace De Acceso</button>
  </form>
  <p class="auth-delivery-note"><strong>¿No ves el correo?</strong> Revisa Spam o Correo no deseado y marca a Saeta Vinotinto como remitente seguro.</p>
  <a class="text-link" href="<?= e(app_url('login.php')) ?>">Ya Tengo Cuenta</a>
  <a class="quiet-link" href="../">Volver Al Inicio</a>
</section>
<?php render_footer(); ?>
