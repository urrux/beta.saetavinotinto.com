<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$name = request_value('name');
$email = strtolower(request_value('email'));
$message = request_value('message');
$honeypot = request_value('website');
$lastSent = (int) ($_SESSION['contact_last_sent'] ?? 0);

if ($honeypot !== '' || time() - $lastSent < 60) {
    header('Location: ../?contact=received#contacto');
    exit;
}

if (
    mb_strlen($name) < 2 || mb_strlen($name) > 120
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($message) < 10 || mb_strlen($message) > 3000
) {
    header('Location: ../?contact=error#contacto');
    exit;
}

$body = "Nuevo mensaje desde saetavinotinto.com\n\nNombre: {$name}\nCorreo: {$email}\n\nMensaje:\n{$message}";
if (send_portal_email('halamadrid@saetavinotinto.com', 'Nuevo contacto desde Saeta Vinotinto', $body, $email)) {
    $_SESSION['contact_last_sent'] = time();
    header('Location: ../?contact=received#contacto');
    exit;
}

header('Location: ../?contact=error#contacto');
exit;
