<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

$path = getenv('SAETA_TICKETS_CSV') ?: '';
if (!$path || !is_file($path)) {
    fwrite(STDERR, "Define SAETA_TICKETS_CSV con la ruta absoluta del CSV exportado de Google Forms.\n");
    exit(1);
}

$handle = fopen($path, 'rb');
$headers = fgetcsv($handle);
if (!$headers) exit("CSV sin encabezados.\n");
$normalized = array_map(static fn($h) => mb_strtolower(trim((string) $h)), $headers);
$find = static function(array $needles) use ($normalized): ?int {
    foreach ($normalized as $index => $header) foreach ($needles as $needle) if (str_contains($header, $needle)) return $index;
    return null;
};
$columns = [
    'name' => $find(['nombre', 'name']),
    'email' => $find(['correo', 'email']),
    'match' => $find(['partido', 'match', 'encuentro']),
    'date' => $find(['fecha del partido', 'match date']),
    'quantity' => $find(['cantidad', 'entradas', 'tickets']),
    'status' => $find(['estado', 'status']),
    'notes' => $find(['nota', 'comentario', 'observación', 'observacion']),
    'requested' => $find(['marca temporal', 'timestamp', 'fecha de solicitud']),
];
if ($columns['match'] === null) exit("No se encontró una columna de partido.\n");
$normalizeDate = static function(?string $value, bool $withTime = false): ?string {
    if (!$value) return null;
    try {
        $date = new DateTime($value);
        return $date->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    } catch (Throwable) {
        return null;
    }
};

$insert = $pdo->prepare('INSERT INTO imported_ticket_requests (user_id, requester_name, requester_email, match_name, match_date, quantity, status, notes, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$userByEmail = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $get = static fn(?int $index): ?string => $index !== null && trim((string) ($row[$index] ?? '')) !== '' ? trim((string) $row[$index]) : null;
    $match = $get($columns['match']);
    if (!$match) continue;
    $email = $get($columns['email']);
    $userId = null;
    if ($email) { $userByEmail->execute([$email]); $userId = $userByEmail->fetchColumn() ?: null; }
    $insert->execute([$userId, $get($columns['name']), $email, $match, $normalizeDate($get($columns['date'])), max(1, (int) ($get($columns['quantity']) ?: 1)), $get($columns['status']) ?: 'Histórica', $get($columns['notes']), $normalizeDate($get($columns['requested']), true)]);
    $count++;
}
fclose($handle);
fwrite(STDOUT, "Importadas {$count} solicitudes históricas.\n");
