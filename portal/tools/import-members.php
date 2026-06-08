<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

$path = getenv('SAETA_MEMBERS_CSV') ?: '';
if (!$path || !is_file($path)) {
    fwrite(STDERR, "Define SAETA_MEMBERS_CSV con la ruta absoluta del archivo CSV.\n");
    exit(1);
}

$handle = fopen($path, 'rb');
$headers = fgetcsv($handle);
if (!$headers) {
    fwrite(STDERR, "El CSV no contiene encabezados.\n");
    exit(1);
}
$map = array_flip(array_map(static fn($value) => trim((string) $value), $headers));
if (!isset($map['Selecciona tu nombre'])) {
    fwrite(STDERR, "Falta la columna Selecciona tu nombre.\n");
    exit(1);
}

$emailColumns = ['Correo electrónico', 'Correo electronico', 'Email', 'email', 'Correo'];
$statement = $pdo->prepare('INSERT INTO member_records (name, email, birth_date, birth_country, birth_city, residence_country, residence_city, photo_url, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $value = static fn(string $column): ?string => isset($map[$column]) && trim((string) ($row[$map[$column]] ?? '')) !== '' ? trim((string) $row[$map[$column]]) : null;
    $firstValue = static function (array $columns) use ($map, $row): ?string {
        foreach ($columns as $column) {
            if (isset($map[$column]) && trim((string) ($row[$map[$column]] ?? '')) !== '') {
                return trim((string) $row[$map[$column]]);
            }
        }
        return null;
    };
    $name = $value('Selecciona tu nombre');
    if (!$name) continue;
    $email = strtolower((string) ($firstValue($emailColumns) ?? ''));
    $statement->execute([$name, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, $value('Fecha de nacimiento'), $value('Pais de nacimiento'), $value('Cuidad de nacimiento'), $value('Pais de residencia actual'), $value('Maracaibo, Zulia'), $value('Foto tipo carnet (o parecido)'), 'Google Sheets']);
    $count++;
}
fclose($handle);
fwrite(STDOUT, "Importadas {$count} fichas privadas.\n");
