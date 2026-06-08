<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

$name = getenv('SAETA_ADMIN_NAME') ?: '';
$email = strtolower(getenv('SAETA_ADMIN_EMAIL') ?: '');
$password = getenv('SAETA_ADMIN_PASSWORD') ?: '';

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Define SAETA_ADMIN_NAME, SAETA_ADMIN_EMAIL y SAETA_ADMIN_PASSWORD (mínimo 12 caracteres).\n");
    exit(1);
}

$statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
$statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', 'active']);
fwrite(STDOUT, "Administrador creado correctamente.\n");
