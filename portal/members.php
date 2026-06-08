<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth();

$members = $pdo->query("
  SELECT name, MAX(member_number) AS member_number, MAX(joined_at) AS joined_at,
         MAX(residence_country) AS residence_country, MAX(birth_country) AS birth_country,
         MAX(bio) AS bio, MAX(photo_url) AS photo_url,
         MAX(is_founder) AS is_founder, MAX(is_board_member) AS is_board_member
  FROM (
    SELECT u.name, u.member_number, u.joined_at, u.residence_country, u.birth_country, u.bio,
      COALESCE(NULLIF(u.photo_url, ''), (
        SELECT linked_record.photo_url FROM member_records linked_record
        WHERE linked_record.user_id = u.id AND COALESCE(linked_record.photo_url, '') <> ''
        ORDER BY linked_record.id DESC LIMIT 1
      )) AS photo_url, u.is_founder, u.is_board_member
    FROM users u WHERE u.status = 'active' AND COALESCE(u.member_number, '') <> 'TEST'
    UNION ALL
    SELECT name, NULL AS member_number, joined_at, residence_country, birth_country, NULL AS bio, photo_url, is_founder, is_board_member
    FROM member_records mr
    WHERE NOT EXISTS (
      SELECT 1 FROM users linked_user
      WHERE linked_user.id = mr.user_id
        AND linked_user.status = 'active'
        AND COALESCE(linked_user.member_number, '') <> 'TEST'
    )
  ) member_directory
  GROUP BY name ORDER BY name
")->fetchAll();

$sort = $_GET['sort'] ?? 'surname';
if (!in_array($sort, ['surname', 'name', 'tenure'], true)) {
    $sort = 'surname';
}

$nameParts = static function (string $name): array {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return [
        'name' => mb_strtolower($name, 'UTF-8'),
        'surname' => mb_strtolower((string) end($parts), 'UTF-8'),
    ];
};

usort($members, static function (array $a, array $b) use ($sort, $nameParts): int {
    if ($sort === 'tenure') {
        $aDate = $a['joined_at'] ?: '9999-12-31';
        $bDate = $b['joined_at'] ?: '9999-12-31';
        return strcmp($aDate, $bDate) ?: strnatcasecmp($a['name'], $b['name']);
    }
    $aParts = $nameParts($a['name']);
    $bParts = $nameParts($b['name']);
    return strnatcasecmp($aParts[$sort], $bParts[$sort]) ?: strnatcasecmp($a['name'], $b['name']);
});

$today = new DateTimeImmutable('today');
$datedMembers = array_values(array_filter($members, static fn(array $member): bool => !empty($member['joined_at'])));
$datedCount = count($datedMembers);
$memberCount = count($members);
$atLeastYears = static function (array $membersWithDates, int $years) use ($today): int {
    return count(array_filter($membersWithDates, static function (array $member) use ($today, $years): bool {
        return new DateTimeImmutable($member['joined_at']) <= $today->modify("-{$years} years");
    }));
};
$percent = static fn(int $count): int => $memberCount ? (int) round(($count / $memberCount) * 100) : 0;
$fiveYears = $atLeastYears($datedMembers, 5);
$sevenYears = $atLeastYears($datedMembers, 7);
$canonicalCountry = static function (?string $country): string {
    $country = trim((string) $country);
    $normalized = mb_strtolower($country, 'UTF-8');
    if (in_array($normalized, ['usa', 'eeuu', 'e.e.u.u.', 'united states', 'united states of america', 'estados unidos de américa'], true)) {
        return 'Estados Unidos';
    }
    return $country;
};
$countryCounts = [];
foreach ($members as &$member) {
    $member['filter_country'] = $canonicalCountry($member['residence_country']);
    if ($member['filter_country'] !== '') {
        $countryCounts[$member['filter_country']] = ($countryCounts[$member['filter_country']] ?? 0) + 1;
    }
}
unset($member);
uksort($countryCounts, static fn(string $a, string $b): int => strnatcasecmp($a, $b));

render_header('Peñistas');
?>
<section class="page-heading community-heading"><p class="kicker">Madridismo Sin Fronteras</p><h1>Nuestra Comunidad.</h1><p>Un espacio privado para reconocer a quienes hacen grande Saeta Vinotinto, estén donde estén.</p></section>
<section class="community-kpis" aria-label="Resumen de Nuestra Comunidad">
  <article><span>Comunidad</span><strong><?= $memberCount ?></strong><small>Saetas En La Peña</small></article>
  <article><span>Trayectoria</span><strong><?= $datedCount ?></strong><small>Con Fecha Registrada</small></article>
  <article><span>Experiencia</span><strong><?= $percent($fiveYears) ?>%</strong><small>Tiene Más De 5 Años Siendo Saeta</small></article>
  <article><span>Historia</span><strong><?= $percent($sevenYears) ?>%</strong><small>Tiene Más De 7 Años Siendo Saeta</small></article>
</section>
<section class="community-toolbar">
  <label class="community-search" for="member-directory-search"><span>Buscar Peñista</span><input id="member-directory-search" type="search" placeholder="Escribe un nombre..." autocomplete="off"></label>
  <p id="member-directory-count" aria-live="polite"><strong><?= $memberCount ?> Peñistas</strong><span>Mostrando toda La Peña.</span></p>
  <div class="community-sort">
    <label for="member-sort">Ordenar Por</label>
    <select id="member-sort" name="sort">
      <option value="surname" <?= $sort === 'surname' ? 'selected' : '' ?>>Apellido</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nombre</option>
      <option value="tenure" <?= $sort === 'tenure' ? 'selected' : '' ?>>Tiempo En La Peña</option>
    </select>
  </div>
</section>
<section class="community-filters" aria-label="Filtros Del Directorio">
  <div class="community-role-filters" role="group" aria-label="Filtrar por reconocimiento">
    <button class="community-filter-chip active" type="button" data-member-role-filter="all" aria-pressed="true">Todos <span><?= $memberCount ?></span></button>
    <button class="community-filter-chip founder" type="button" data-member-role-filter="founder" aria-pressed="false">Fundadores <span><?= count(array_filter($members, static fn(array $member): bool => (bool) $member['is_founder'])) ?></span></button>
    <button class="community-filter-chip board" type="button" data-member-role-filter="board" aria-pressed="false">Junta Directiva <span><?= count(array_filter($members, static fn(array $member): bool => (bool) $member['is_board_member'])) ?></span></button>
  </div>
  <label class="community-country-filter" for="member-country-filter"><span>Saetas Por País</span>
    <select id="member-country-filter">
      <option value="">Todos Los Países</option>
      <?php foreach ($countryCounts as $country => $count): ?><option value="<?= e($country) ?>"><?= e($country) ?> · <?= $count ?></option><?php endforeach; ?>
    </select>
  </label>
</section>
<section class="private-member-grid" id="member-directory-grid">
<?php foreach ($members as $member): ?>
  <?php
    $memberInitial = mb_strtoupper(mb_substr($member['name'], 0, 1, 'UTF-8'), 'UTF-8');
    $parts = preg_split('/\s+/u', trim($member['name'])) ?: [];
    $surname = (string) end($parts);
  ?>
  <details class="private-member-card" data-member-name="<?= e($member['name']) ?>" data-member-surname="<?= e($surname) ?>" data-member-joined="<?= e($member['joined_at'] ?: '9999-12-31') ?>" data-member-country="<?= e($member['filter_country']) ?>" data-member-founder="<?= $member['is_founder'] ? '1' : '0' ?>" data-member-board="<?= $member['is_board_member'] ? '1' : '0' ?>">
    <summary>
      <?php if ($member['photo_url']): ?><img class="member-card-photo member-photo-with-fallback" src="<?= e($member['photo_url']) ?>" alt="Foto de <?= e($member['name']) ?>" width="62" height="62" decoding="async" referrerpolicy="no-referrer" data-fallback-initial="<?= e($memberInitial) ?>"><?php else: ?><div class="member-initial"><?= e($memberInitial) ?></div><?php endif; ?>
      <span><?= e($member['member_number'] ? 'Peñista ' . $member['member_number'] : 'Saeta Vinotinto') ?></span>
      <h2><?= e($member['name']) ?></h2>
      <?php if ($member['is_founder'] || $member['is_board_member']): ?><div class="member-role-tags">
        <?php if ($member['is_founder']): ?><span class="member-role-founder">★ Fundador</span><?php endif; ?>
        <?php if ($member['is_board_member']): ?><span class="member-role-board">Junta Directiva</span><?php endif; ?>
      </div><?php endif; ?>
      <p><?= e(membership_duration($member['joined_at'])) ?> siendo Saeta</p>
      <b>Ver Ficha →</b>
    </summary>
    <div class="member-detail">
      <div class="member-detail-photo">
        <?php if ($member['photo_url']): ?><img class="member-photo-with-fallback" src="<?= e($member['photo_url']) ?>" alt="Foto de <?= e($member['name']) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-fallback-initial="<?= e($memberInitial) ?>" data-fallback-large="1"><?php else: ?><div class="member-initial large"><?= e($memberInitial) ?></div><?php endif; ?>
      </div>
      <div class="member-detail-copy">
        <p class="kicker">Ficha De Peñista</p><h2><?= e($member['name']) ?></h2>
        <?php if ($member['is_founder'] || $member['is_board_member']): ?><div class="member-role-tags detail-role-tags">
          <?php if ($member['is_founder']): ?><span class="member-role-founder">★ Fundador</span><?php endif; ?>
          <?php if ($member['is_board_member']): ?><span class="member-role-board">Junta Directiva</span><?php endif; ?>
        </div><?php endif; ?>
        <div class="member-facts">
          <article><span>Tiempo Siendo Saeta</span><strong><?= e(membership_duration($member['joined_at'])) ?></strong><?php if ($member['joined_at']): ?><small>Desde <?= e(date('d/m/Y', strtotime($member['joined_at']))) ?></small><?php endif; ?></article>
          <?php if ($member['residence_country']): ?><article><span>País De Residencia</span><strong><?= e($member['residence_country']) ?></strong></article><?php endif; ?>
          <?php if ($member['birth_country']): ?><article><span>País De Nacimiento</span><strong><?= e($member['birth_country']) ?></strong></article><?php endif; ?>
        </div>
        <?php if ($member['bio']): ?><p class="member-bio"><?= e($member['bio']) ?></p><?php endif; ?>
      </div>
    </div>
  </details>
<?php endforeach; ?>
</section>
<p class="empty-state member-directory-empty" id="member-directory-empty" hidden>No encontramos Saetas con ese nombre.</p>
<?php render_footer(); ?>
