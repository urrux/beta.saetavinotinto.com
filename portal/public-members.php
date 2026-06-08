<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$records = $pdo->query("
    SELECT name, is_private, MAX(is_founder) AS is_founder, MAX(is_board_member) AS is_board_member
    FROM (
      SELECT name, 0 AS is_private, is_founder, is_board_member FROM member_records WHERE user_id IS NULL
      UNION ALL
      SELECT u.name, CASE WHEN COALESCE(s.show_publicly, 1) = 1 THEN 0 ELSE 1 END AS is_private, u.is_founder, u.is_board_member
      FROM users u LEFT JOIN profile_settings s ON s.user_id = u.id
      WHERE u.status = 'active' AND COALESCE(u.member_number, '') <> 'TEST'
    ) public_directory
    GROUP BY name, is_private
    ORDER BY name
")->fetchAll();

echo json_encode(array_map(static function (array $member): array {
    $badges = [];
    $private = (bool) $member['is_private'];
    if (!$private && $member['is_founder']) $badges[] = 'Fundador';
    if (!$private && $member['is_board_member']) $badges[] = 'Junta Directiva';
    return [
        'name' => $private ? 'Peñista Saeta' : $member['name'],
        'badges' => $badges,
    ];
}, $records), JSON_UNESCAPED_UNICODE);
