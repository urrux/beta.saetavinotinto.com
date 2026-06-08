<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$user = current_user();
echo json_encode([
    'authenticated' => (bool) $user,
    'firstName' => $user ? explode(' ', $user['name'])[0] : null,
    'role' => $user['role'] ?? null,
], JSON_UNESCAPED_UNICODE);
