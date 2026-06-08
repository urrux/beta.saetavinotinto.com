<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$locations = $pdo->query("
    SELECT country, COUNT(*) AS member_count
    FROM (
      SELECT name, CASE WHEN MAX(is_private) = 1 THEN 'Ubicación privada' ELSE COALESCE(NULLIF(MAX(user_country), ''), NULLIF(MAX(record_country), ''), 'Venezuela') END AS country
      FROM (
      SELECT name, NULL AS user_country, residence_country AS record_country, 0 AS is_private FROM member_records
      UNION ALL
      SELECT u.name, u.residence_country AS user_country, NULL AS record_country, CASE WHEN COALESCE(s.show_publicly, 1) = 1 THEN 0 ELSE 1 END AS is_private
      FROM users u LEFT JOIN profile_settings s ON s.user_id = u.id
      WHERE u.status = 'active' AND COALESCE(u.member_number, '') <> 'TEST'
      ) all_locations
      GROUP BY name
    ) member_locations
    GROUP BY country
    ORDER BY member_count DESC, country
")->fetchAll();

echo json_encode(array_map(static fn(array $location): array => [
    'country' => $location['country'],
    'count' => (int) $location['member_count'],
], $locations), JSON_UNESCAPED_UNICODE);
