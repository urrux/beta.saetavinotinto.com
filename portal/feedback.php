<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();

function send_anonymous_feedback(string $category, string $message): bool
{
    global $config;
    $recipient = 'halamadrid@saetavinotinto.com';
    $subject = 'Feedback Anónimo Para Mejorar La Peña';
    $body = "Categoría: {$category}\n\n{$message}\n\n"
        . 'Este mensaje fue enviado desde el buzón anónimo del portal. '
        . 'El portal no almacenó el contenido ni datos identificativos del remitente.';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $config['mail_from_name'] . ' <' . $config['mail_from'] . '>',
        'Reply-To: ' . $config['mail_from'],
    ];
    return mail($recipient, $subject, $body, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $category = request_value('category') ?: 'General';
    $message = request_value('message');
    $allowedCategories = ['General', 'Entradas', 'Portal', 'Actividades', 'Comunidad', 'Otro'];
    if (!in_array($category, $allowedCategories, true)) $category = 'General';

    if (mb_strlen($message, 'UTF-8') < 10) {
        flash('error', 'Escribe al menos 10 caracteres para enviar tu feedback.');
    } elseif (!empty($_SESSION['anonymous_feedback_at']) && time() - (int) $_SESSION['anonymous_feedback_at'] < 60) {
        flash('error', 'Espera un minuto antes de enviar otro mensaje.');
    } elseif (!send_anonymous_feedback($category, $message)) {
        flash('error', 'No pudimos entregar tu feedback en este momento. Intenta nuevamente más tarde.');
    } else {
        $_SESSION['anonymous_feedback_at'] = time();
        flash('success', 'Feedback enviado de forma anónima. El portal no conservó el mensaje ni creó un historial.');
    }
    redirect('feedback.php');
}

render_header('Feedback Anónimo');
?>
<section class="page-heading"><p class="kicker">Buzón Confidencial</p><h1>Feedback Anónimo.</h1><p>Comparte sugerencias, preocupaciones o ideas para mejorar La Peña.</p></section>
<section class="anonymous-feedback-layout">
  <aside class="privacy-promise">
    <p class="kicker">Anonimato Real</p>
    <h2>Tu mensaje se entrega y desaparece del portal.</h2>
    <ul>
      <li>No guardamos tu nombre de usuario.</li>
      <li>No guardamos tu correo.</li>
      <li>El feedback no registra ni asocia tu dirección IP.</li>
      <li>No creamos un historial de mensajes.</li>
    </ul>
    <p>El contenido se envía directamente al buzón institucional y no se almacena en la base de datos de la web.</p>
  </aside>
  <form method="post" class="form-panel anonymous-feedback-form">
    <?= csrf_field() ?>
    <label>Categoría<select name="category"><option>General</option><option>Entradas</option><option>Portal</option><option>Actividades</option><option>Comunidad</option><option>Otro</option></select></label>
    <label>Tu Feedback<textarea name="message" rows="8" minlength="10" maxlength="4000" required></textarea></label>
    <p class="privacy-note">Al enviarlo, el portal entrega el mensaje sin asociar tu cuenta y no conserva una copia.</p>
    <button class="primary-button" type="submit">Enviar De Forma Anónima</button>
  </form>
</section>
<?php render_footer(); ?>
