<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

$pdo->beginTransaction();
try {
    $email = 'smoke-test-' . bin2hex(random_bytes(4)) . '@example.invalid';
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)')
        ->execute(['Prueba temporal', $email, password_hash('Temporary-Test-Password!', PASSWORD_DEFAULT), 'member', 'active']);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO profile_settings (user_id, show_publicly, show_photo, show_birthplace, show_residence) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, 1, 1, 1, 1]);

    $pdo->prepare('INSERT INTO ticket_requests (user_id, match_name, quantity, notes) VALUES (?, ?, ?, ?)')
        ->execute([$userId, 'Partido temporal', 1, 'Prueba']);
    $pdo->prepare('INSERT INTO imported_ticket_requests (user_id, requester_name, requester_email, match_name) VALUES (?, ?, ?, ?)')
        ->execute([$userId, 'Prueba temporal', $email, 'Partido histórico temporal']);
    $pdo->prepare('INSERT INTO governance_documents (title, document_type, summary, created_by) VALUES (?, ?, ?, ?)')
        ->execute(['Documento temporal', 'rules', 'Prueba', $userId]);
    $pdo->prepare('INSERT INTO resources (title, url, created_by) VALUES (?, ?, ?)')
        ->execute(['Recurso temporal', 'https://example.com', $userId]);
    $pdo->prepare('INSERT INTO products (name, price, currency, stock) VALUES (?, ?, ?, ?)')
        ->execute(['Bufanda temporal', 25, 'USD', 1]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_orders (user_id, product_id, quantity, total, currency) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $productId, 1, 25, 'USD']);

    foreach (['users', 'profile_settings', 'ticket_requests', 'imported_ticket_requests', 'governance_documents', 'resources', 'products', 'product_orders'] as $table) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        if ($count < 1) {
            throw new RuntimeException("La tabla {$table} no pasó la prueba.");
        }
    }

    $pdo->rollBack();
    fwrite(STDOUT, "Prueba funcional completada; cambios temporales revertidos.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
