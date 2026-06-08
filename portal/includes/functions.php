<?php
declare(strict_types=1);

function connect_database(array $config): PDO
{
    if ($config['db_driver'] === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password']);
    } else {
        $storage = dirname((string) $config['db_name']);
        if (!is_dir($storage)) {
            mkdir($storage, 0770, true);
        }
        $pdo = new PDO('sqlite:' . $config['db_name']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $schema = $driver === 'mysql' ? 'schema.mysql.sql' : 'schema.sqlite.sql';
    $pdo->exec($driver === 'mysql'
        ? 'CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : 'CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $version = '2026-06-07-superadmin-cms';
    $statement = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
    $statement->execute([$version]);
    if ((int) $statement->fetchColumn() > 0) {
        return;
    }
    $pdo->exec((string) file_get_contents(__DIR__ . '/../database/' . $schema));
    ensure_member_record_email($pdo, $driver);
    ensure_membership_dates($pdo, $driver);
    ensure_ticket_request_details($pdo, $driver);
    ensure_institutional_roles($pdo, $driver);
    ensure_notification_tracking($pdo, $driver);
    ensure_access_capabilities($pdo, $driver);
    seed_institutional_roles($pdo);
    $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
}

function ensure_access_capabilities(PDO $pdo, string $driver): void
{
    $columns = [
        'users' => 'is_superadmin',
        'member_records' => 'is_portal_admin',
    ];
    foreach ($columns as $table => $column) {
        if (table_column_exists($pdo, $driver, $table, $column)) continue;
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'));
    }
}

function ensure_notification_tracking(PDO $pdo, string $driver): void
{
    if (table_column_exists($pdo, $driver, 'users', 'admin_notified_at')) return;
    $pdo->exec($driver === 'mysql'
        ? 'ALTER TABLE users ADD COLUMN admin_notified_at DATETIME NULL AFTER last_login_at'
        : 'ALTER TABLE users ADD COLUMN admin_notified_at TEXT');
}

function ensure_institutional_roles(PDO $pdo, string $driver): void
{
    foreach (['users', 'member_records'] as $table) {
        foreach (['is_founder', 'is_board_member'] as $column) {
            if (table_column_exists($pdo, $driver, $table, $column)) continue;
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'));
        }
    }
}

function table_column_exists(PDO $pdo, string $driver, string $table, string $column): bool
{
    if ($driver === 'mysql') {
        return (bool) $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column))->fetch();
    }
    foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll() as $existing) {
        if (($existing['name'] ?? '') === $column) return true;
    }
    return false;
}

function seed_institutional_roles(PDO $pdo): void
{
    $founders = ['Jose Urrutia', 'Rodolfo Urrutia'];
    $board = ['Rodolfo Urrutia', 'Jose Urrutia', 'Marcel París', 'Marcos Salas', 'Juan P Gutierrez', 'Roberto Rios', 'Manuel Fuenmayor', 'Eulise Ferrer', 'Cristobal Anania', 'Gustavo Ocando', 'Joaquín Paris', 'Javier Nuñez', 'Jose Flores', 'Juan Cochesa'];
    foreach (['users', 'member_records'] as $table) {
        foreach ($founders as $name) $pdo->prepare("UPDATE {$table} SET is_founder = 1 WHERE name = ?")->execute([$name]);
        foreach ($board as $name) $pdo->prepare("UPDATE {$table} SET is_board_member = 1 WHERE name = ?")->execute([$name]);
    }
}

function ensure_membership_dates(PDO $pdo, string $driver): void
{
    foreach (['users', 'member_records'] as $table) {
        if ($driver === 'mysql') {
            $statement = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'joined_at'");
            if (!$statement->fetch()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN joined_at DATE NULL AFTER " . ($table === 'users' ? 'member_number' : 'email'));
            }
            continue;
        }

        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        $exists = false;
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'joined_at') {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN joined_at TEXT");
        }
    }
}

function ensure_member_record_email(PDO $pdo, string $driver): void
{
    if ($driver === 'mysql') {
        $statement = $pdo->query("SHOW COLUMNS FROM member_records LIKE 'email'");
        if (!$statement->fetch()) {
            $pdo->exec('ALTER TABLE member_records ADD COLUMN email VARCHAR(190) NULL AFTER name');
            $pdo->exec('CREATE INDEX idx_record_email ON member_records(email)');
        }
        return;
    }

    $columns = $pdo->query('PRAGMA table_info(member_records)')->fetchAll();
    $hasEmail = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'email') {
            $hasEmail = true;
            break;
        }
    }
    if (!$hasEmail) {
        $pdo->exec('ALTER TABLE member_records ADD COLUMN email TEXT');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_record_email ON member_records(email)');
}

function ensure_ticket_request_details(PDO $pdo, string $driver): void
{
    $columns = [
        'competition' => $driver === 'mysql' ? 'VARCHAR(120) NULL AFTER match_date' : 'TEXT',
        'ticket_type' => $driver === 'mysql' ? 'VARCHAR(80) NULL AFTER competition' : 'TEXT',
        'budget_range' => $driver === 'mysql' ? 'VARCHAR(80) NULL AFTER ticket_type' : 'TEXT',
        'companion_names' => $driver === 'mysql' ? 'TEXT NULL AFTER budget_range' : 'TEXT',
        'availability_notes' => $driver === 'mysql' ? 'TEXT NULL AFTER companion_names' : 'TEXT',
    ];

    foreach ($columns as $column => $definition) {
        if (ticket_request_column_exists($pdo, $driver, $column)) {
            continue;
        }
        $pdo->exec(sprintf('ALTER TABLE ticket_requests ADD COLUMN %s %s', $column, $definition));
    }
}

function ticket_request_column_exists(PDO $pdo, string $driver, string $column): bool
{
    if ($driver === 'mysql') {
        $statement = $pdo->query('SHOW COLUMNS FROM ticket_requests LIKE ' . $pdo->quote($column));
        return (bool) $statement->fetch();
    }

    $columns = $pdo->query('PRAGMA table_info(ticket_requests)')->fetchAll();
    foreach ($columns as $existing) {
        if (($existing['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function audit_admin_action(int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, array $details = []): void
{
    global $pdo;
    $pdo->prepare('INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
        ->execute([$adminId, $action, $targetType, $targetId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
}

function normalize_uploaded_image_orientation($source, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $source;
    }

    $exif = @exif_read_data($path);
    $orientation = (int) ($exif['Orientation'] ?? 1);
    $rotated = null;

    if ($orientation === 2) imageflip($source, IMG_FLIP_HORIZONTAL);
    if ($orientation === 3) $rotated = imagerotate($source, 180, 0);
    if ($orientation === 4) imageflip($source, IMG_FLIP_VERTICAL);
    if ($orientation === 5) {
        imageflip($source, IMG_FLIP_HORIZONTAL);
        $rotated = imagerotate($source, -90, 0);
    }
    if ($orientation === 6) $rotated = imagerotate($source, -90, 0);
    if ($orientation === 7) {
        imageflip($source, IMG_FLIP_HORIZONTAL);
        $rotated = imagerotate($source, 90, 0);
    }
    if ($orientation === 8) $rotated = imagerotate($source, 90, 0);

    if ($rotated) {
        imagedestroy($source);
        return $rotated;
    }
    return $source;
}

function optimized_uploaded_image(array $file, string $uploadDir, string $prefix, int $maxWidth, int $maxHeight, int $maxBytes): string
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('No se recibió una imagen válida.');
    $info = getimagesize($file['tmp_name']);
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || (int) $file['size'] > $maxBytes) {
        throw new RuntimeException('La imagen debe ser JPG, PNG o WebP y respetar el tamaño máximo.');
    }
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
    $extension = function_exists('imagewebp') ? 'webp' : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']];
    $filename = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;
    if (!function_exists('imagewebp')) {
        if (!move_uploaded_file($file['tmp_name'], $destination)) throw new RuntimeException('No se pudo guardar la imagen.');
        return $filename;
    }
    $source = match ($info['mime']) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
    };
    if (!$source) throw new RuntimeException('No se pudo procesar la imagen.');
    $source = normalize_uploaded_image_orientation($source, $file['tmp_name'], $info['mime']);
    $scale = min(1, $maxWidth / imagesx($source), $maxHeight / imagesy($source));
    $width = max(1, (int) round(imagesx($source) * $scale));
    $height = max(1, (int) round(imagesy($source) * $scale));
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false); imagesavealpha($canvas, true);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
    imagewebp($canvas, $destination, 82);
    imagedestroy($source); imagedestroy($canvas);
    return $filename;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    global $config;
    return rtrim((string) $config['app_url'], '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function safe_portal_destination(?string $destination): ?string
{
    $destination = trim((string) $destination);
    if ($destination === '' || str_contains($destination, '://') || str_starts_with($destination, '//')) return null;
    $path = parse_url($destination, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('/^[A-Za-z0-9_-]+\.php$/', ltrim($path, '/'))) return null;
    $query = parse_url($destination, PHP_URL_QUERY);
    return ltrim($path, '/') . ($query ? '?' . $query : '');
}

function current_user(): ?array
{
    global $pdo;
    static $user = false;

    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? AND status = ?');
    $statement->execute([(int) $_SESSION['user_id'], 'active']);
    $user = $statement->fetch() ?: null;
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $_SESSION['login_destination'] = safe_portal_destination($script . ($query ? '?' . $query : ''));
        flash('error', 'Inicia sesión para ingresar al área privada de Saetas.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección.');
    }
    return $user;
}

function is_superadmin(array $user): bool
{
    return !empty($user['is_superadmin'])
        && strtolower((string) ($user['email'] ?? '')) === 'urrutiajm@gmail.com';
}

function require_superadmin(): array
{
    $user = require_auth();
    if (!is_superadmin($user)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección.');
    }
    return $user;
}

function site_settings(PDO $pdo, array $defaults = []): array
{
    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
        foreach ($rows as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    return $defaults;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = (string) ($_POST['csrf_token'] ?? '');
    $current = (string) ($_SESSION['csrf_token'] ?? '');

    if ($current !== '' && hash_equals($current, $provided)) {
        return;
    }

    unset($_SESSION['csrf_token']);
    flash('error', 'La sesión expiró. Intenta nuevamente.');

    $target = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php'));
    if ($target === '' || $target === 'logout.php') {
        $target = 'login.php';
    }

    if (!headers_sent()) {
        redirect($target);
    }

    http_response_code(419);
    exit('La sesión expiró. Recarga la página e intenta nuevamente.');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_header(string $title, bool $guest = false): void
{
    $user = current_user();
    $flashes = consume_flashes();
    require __DIR__ . '/header.php';
}

function render_footer(): void
{
    require __DIR__ . '/footer.php';
}

function request_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function valid_date_or_null(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function membership_duration(?string $joinedAt): string
{
    if (!$joinedAt) {
        return 'Fecha por registrar';
    }
    try {
        $joined = new DateTimeImmutable($joinedAt);
        $today = new DateTimeImmutable('today');
        if ($joined > $today) {
            return 'Fecha por registrar';
        }
        $difference = $joined->diff($today);
        $parts = [];
        if ($difference->y) {
            $parts[] = $difference->y . ' ' . ($difference->y === 1 ? 'año' : 'años');
        }
        if ($difference->m) {
            $parts[] = $difference->m . ' ' . ($difference->m === 1 ? 'mes' : 'meses');
        }
        return $parts ? implode(' y ', $parts) : 'Menos de un mes';
    } catch (Throwable $exception) {
        return 'Fecha por registrar';
    }
}

function password_requirements(string $password): array
{
    return [
        'length' => strlen($password) >= 10,
        'lowercase' => (bool) preg_match('/[a-z]/', $password),
        'uppercase' => (bool) preg_match('/[A-Z]/', $password),
        'number' => (bool) preg_match('/\d/', $password),
        'symbol' => (bool) preg_match('/[^A-Za-z0-9]/', $password),
    ];
}

function password_is_valid(string $password): bool
{
    return !in_array(false, password_requirements($password), true);
}

function request_rate_limited(string $action, string $identity, int $limit = 3, int $windowSeconds = 1800): bool
{
    global $pdo;
    $keyHash = hash('sha256', strtolower(trim($identity)));
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $cleanup = date('Y-m-d H:i:s', time() - 86400);

    $pdo->prepare('DELETE FROM request_limits WHERE attempted_at < ?')->execute([$cleanup]);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM request_limits WHERE action = ? AND attempted_at >= ? AND (key_hash = ? OR ip_hash = ?)');
    $statement->execute([$action, $cutoff, $keyHash, $ipHash]);
    if ((int) $statement->fetchColumn() >= $limit) {
        return true;
    }

    $pdo->prepare('INSERT INTO request_limits (action, key_hash, ip_hash, attempted_at) VALUES (?, ?, ?, ?)')
        ->execute([$action, $keyHash, $ipHash, date('Y-m-d H:i:s')]);
    return false;
}

function portal_email_html(string $subject, string $body): string
{
    global $config;
    $logo = dirname(rtrim((string) $config['app_url'], '/')) . '/images/saeta-imagotipo-clean.png';
    $safeBody = e($body);
    $safeBody = preg_replace_callback(
        '~https?://[^\s<]+~',
        static function (array $match): string {
            $url = $match[0];
            $label = str_contains($url, 'reset-password.php') ? 'Crear Mi Contraseña'
                : (str_contains($url, 'admin.php') ? 'Abrir Administración'
                : (str_contains($url, '/portal/index.php') ? 'Entrar A Mi Espacio' : 'Abrir Enlace Seguro'));
            return '<a href="' . e($url) . '" style="display:inline-block;margin:14px 0 5px;padding:13px 18px;border-radius:9px;color:#fff;background:#1e3a8a;font-weight:700;text-decoration:none">' . $label . '</a>';
        },
        $safeBody
    );

    return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f5f6f9;font-family:Arial,sans-serif;color:#11131a">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f6f9;padding:28px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #e4e7ee;border-radius:18px;overflow:hidden;box-shadow:0 24px 55px rgba(30,58,138,.12)">'
        . '<tr><td style="height:4px;background:linear-gradient(90deg,#1e3a8a 0 46%,#ffd700 46% 54%,#820000 54% 100%)"></td></tr>'
        . '<tr><td style="padding:27px 30px;background:#fff;border-bottom:1px solid #e4e7ee">'
        . '<img src="' . e($logo) . '" alt="Saeta Vinotinto" width="230" style="display:block;width:230px;max-width:100%;height:auto">'
        . '</td></tr>'
        . '<tr><td style="padding:30px 34px 34px"><h1 style="margin:0 0 22px;color:#11131a;font-size:27px;line-height:1.2;letter-spacing:-.5px">' . e($subject) . '</h1>'
        . '<div style="color:#424752;font-size:15px;line-height:1.75">' . nl2br($safeBody) . '</div></td></tr>'
        . '<tr><td style="padding:21px 34px;border-top:1px solid #e4e7ee;border-left:4px solid #820000;color:#6d7380;background:#fff;font-size:11px;line-height:1.7"><strong style="color:#1e3a8a">Saeta Vinotinto · Peña Madridista Oficial</strong><br>Maracaibo, Venezuela · Mensaje automático</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function send_portal_email(string $to, string $subject, string $body, ?string $replyTo = null, string $messageType = 'general'): bool
{
    global $config;
    $boundary = 'saeta_' . bin2hex(random_bytes(12));
    $replyTo = $replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : $config['mail_from'];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $config['mail_from_name'] . ' <' . $config['mail_from'] . '>',
        'Reply-To: ' . $replyTo,
    ];
    $message = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . portal_email_html($subject, $body) . "\r\n\r\n"
        . "--{$boundary}--";
    $sent = mail($to, $subject, $message, implode("\r\n", $headers));
    global $pdo;
    if (isset($pdo)) {
        try {
            $pdo->prepare('INSERT INTO email_delivery_log (recipient, subject, message_type, sent) VALUES (?, ?, ?, ?)')
                ->execute([$to, $subject, $messageType, $sent ? 1 : 0]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
    return $sent;
}

function send_registration_notification(array $user): bool
{
    global $config;
    $adminEmail = (string) ($config['admin_notification_email'] ?? '');
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $body = "Hola,\n\n{$user['name']} completó su registro y activó su cuenta de Peñista.\n\n"
        . "Correo: {$user['email']}\n"
        . 'Fecha: ' . date('d/m/Y H:i') . "\n\n"
        . "Puedes revisar las cuentas desde Administración:\n" . app_url('admin.php#members-admin');

    $sent = send_portal_email($adminEmail, 'Nuevo Peñista registrado: ' . $user['name'], $body, null, 'registration_admin');
    if ($sent && !empty($user['id'])) {
        global $pdo;
        $pdo->prepare('UPDATE users SET admin_notified_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
    }
    return $sent;
}

function send_first_login_welcome(array $user): bool
{
    $body = "Hola, {$user['name']}.\n\n"
        . "Tu cuenta está lista. En Mi Espacio encontrarás todo lo necesario para mantenerte conectado con La Peña:\n\n"
        . "• Solicitar entradas oficiales y consultar todo tu historial.\n"
        . "• Actualizar tu perfil y consultar el directorio de Peñistas.\n"
        . "• Acceder a documentos, recursos y la Tienda Oficial SV7.\n"
        . "• Enviar feedback anónimo para ayudar a mejorar La Peña.\n\n"
        . app_url('index.php');

    return send_portal_email($user['email'], 'Te Estábamos Esperando, Saeta', $body);
}
