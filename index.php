<?php
declare(strict_types=1);

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'www.saetavinotinto.com'));
$isBeta = str_starts_with($host, 'beta.');
$siteUrl = $isBeta ? 'https://beta.saetavinotinto.com/' : 'https://www.saetavinotinto.com/';
$canonicalUrl = $isBeta ? 'https://www.saetavinotinto.com/' : $siteUrl;
$robotsContent = $isBeta ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

$officialMemberNames = [
    'Miguel Acosta', 'Fran Adrianza', 'Antonio Aguera', 'Javier Altimari',
    'Cristobal Anania', 'Juan Arribas', 'Leonardo Arteaga', 'Carlos Belloso',
    'Freddy Bozo', 'Madwin Cerezo', 'Juan Cochesa', 'Jesus De Abreu',
    'Luis De Abreu', 'Rafael Echeverria', 'Roberto Fernandez', 'Eulise Ferrer',
    'Jose Flores', 'Manuel Fuenmayor', 'Osvaldo Garcia', 'Miguel Gomez',
    'Jose Guerra', 'Juan D Gutierrez', 'Juan P Gutierrez', 'Julius Jessurun',
    'Andoni Jimenez', 'Guillermo Melendez', 'Carlos Muñoz', 'Javier Muñoz',
    'Jose Muñoz', 'Javier Nuñez', 'Gustavo Ocando', 'Pedro Olivares',
    'Joaquín Paris', 'Gabriel Paris', 'Marcel París', 'Ivan Patino',
    'Alejandro Paz', 'Andres Pozo', 'Luis Pulgar', 'Luis Rincon', 'Juan Rios',
    'Roberto Rios', 'Jose Romero', 'Manuel Rosales', 'Freddy Rumbos',
    'Marcos Salas', 'Juan Salas', 'Jose Urrutia', 'Rodolfo Urrutia',
    'Nelson Valbuena', 'Howard Villalobos', 'Santiago Viloria', 'Andres Virla',
];

function public_member_records(array $officialMemberNames): array
{
    $members = array_map(static fn(string $name): array => ['name' => $name, 'private' => false, 'badges' => []], $officialMemberNames);
    try {
        require_once __DIR__ . '/portal/includes/functions.php';
        $config = require __DIR__ . '/portal/config.php';
        $pdo = connect_database($config);
        ensure_schema($pdo);
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
        $privacy = [];
        foreach ($records as $record) {
            if (!empty($record['name'])) $privacy[$record['name']] = $record;
        }
        foreach ($members as &$member) {
            $record = $privacy[$member['name']] ?? [];
            $member['private'] = (bool) ($record['is_private'] ?? false);
            $member['badges'] = array_values(array_filter([($record['is_founder'] ?? false) ? 'Fundador' : null, ($record['is_board_member'] ?? false) ? 'Junta Directiva' : null]));
        }
        unset($member);
        foreach ($privacy as $name => $record) {
            if (!in_array($name, $officialMemberNames, true)) $members[] = ['name' => $name, 'private' => (bool) $record['is_private'], 'badges' => array_values(array_filter([$record['is_founder'] ? 'Fundador' : null, $record['is_board_member'] ? 'Junta Directiva' : null]))];
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    usort($members, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
    return array_map(static fn(array $member): array => [
        'name' => $member['private'] ? 'Peñista Saeta' : $member['name'],
        'badges' => $member['private'] ? [] : $member['badges'],
    ], $members);
}

function public_site_settings(): array
{
    $defaults = [
        'hero_blue' => 'Madridismo',
        'hero_wine' => 'Sin Fronteras.',
        'hero_text' => 'Creemos en un madridismo que une personas, culturas y generaciones. Nos inspiran los valores de esfuerzo, disciplina, respeto y compañerismo que han definido al Real Madrid a lo largo de su historia, y trabajamos para fortalecer esos lazos dentro y fuera de Venezuela.',
        'history_title' => 'Nacimos en Maracaibo.',
        'history_emphasis' => 'Nos une el Madrid.',
        'history_text_one' => 'Somos la Peña Madridista Oficial Saeta Vinotinto y la primera del Zulia: una familia nacida en Maracaibo y conectada alrededor del mundo por el Real Madrid.',
        'history_text_two' => 'Cada partido es una excusa para volver a encontrarnos. Cada victoria, un recuerdo que compartimos.',
        'contact_title' => 'Hablemos de',
        'contact_emphasis' => 'Madridismo.',
        'contact_text' => '¿Tienes una pregunta sobre Saeta Vinotinto? Escríbenos y La Peña te responderá.',
        'public_product_image' => 'images/BUFANDASV7.jpeg?v=20260607',
    ];
    try {
        require_once __DIR__ . '/portal/includes/functions.php';
        $config = require __DIR__ . '/portal/config.php';
        $pdo = connect_database($config);
        ensure_schema($pdo);
        return site_settings($pdo, $defaults);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        return $defaults;
    }
}

function sv_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function sv_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        $initials .= mb_substr($part, 0, 1, 'UTF-8');
        if (mb_strlen($initials, 'UTF-8') >= 2) break;
    }
    return mb_strtoupper($initials ?: 'SV', 'UTF-8');
}

$publicMembers = public_member_records($officialMemberNames);
$site = public_site_settings();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Saeta Vinotinto | Peña Madridista Oficial de Maracaibo</title>
  <meta name="description" content="Saeta Vinotinto es la Peña Madridista Oficial de Maracaibo, Venezuela. Una comunidad madridista conectada alrededor del mundo. Madridismo sin fronteras.">
  <meta name="keywords" content="Saeta Vinotinto, peña madridista Maracaibo, peña Real Madrid Venezuela, madridistas Venezuela, Real Madrid Maracaibo">
  <meta name="author" content="Peña Madridista Oficial Saeta Vinotinto">
  <meta name="robots" content="<?= sv_e($robotsContent) ?>">
  <meta name="googlebot" content="<?= sv_e($robotsContent) ?>">
  <meta name="theme-color" content="#163a8a">
  <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png?v=20260607">
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png?v=20260607">
  <link rel="shortcut icon" href="/favicon.ico?v=20260607">
  <link rel="apple-touch-icon" href="/images/saeta-isotipo-crop.png?v=20260607">
  <link rel="canonical" href="<?= sv_e($canonicalUrl) ?>">
  <link rel="alternate" hreflang="es-VE" href="<?= sv_e($canonicalUrl) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= sv_e($canonicalUrl) ?>">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_VE">
  <meta property="og:site_name" content="Saeta Vinotinto">
  <meta property="og:title" content="Saeta Vinotinto | Madridismo sin fronteras">
  <meta property="og:description" content="Peña Madridista Oficial de Maracaibo, Venezuela. Una comunidad unida por el Real Madrid alrededor del mundo.">
  <meta property="og:url" content="<?= sv_e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= sv_e($canonicalUrl) ?>images/saeta-imagotipo-crop.png">
  <meta property="og:image:alt" content="Logo oficial de la Peña Madridista Saeta Vinotinto">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@saetavinotinto">
  <meta name="twitter:title" content="Saeta Vinotinto | Madridismo sin fronteras">
  <meta name="twitter:description" content="Peña Madridista Oficial de Maracaibo, Venezuela.">
  <meta name="twitter:image" content="<?= sv_e($canonicalUrl) ?>images/saeta-imagotipo-crop.png">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "SportsOrganization",
        "@id": "https://www.saetavinotinto.com/#organization",
        "name": "Peña Madridista Oficial Saeta Vinotinto",
        "alternateName": "Saeta Vinotinto",
        "url": "<?= sv_e($canonicalUrl) ?>",
        "logo": "<?= sv_e($canonicalUrl) ?>images/saeta-imagotipo-crop.png",
        "description": "Peña Madridista Oficial de Maracaibo, Venezuela, conectada alrededor del mundo.",
        "slogan": "Madridismo sin fronteras",
        "sport": "Fútbol",
        "additionalProperty": {
          "@type": "PropertyValue",
          "name": "Número de miembros",
          "value": 53
        },
        "address": {
          "@type": "PostalAddress",
          "addressLocality": "Maracaibo",
          "addressRegion": "Zulia",
          "addressCountry": "VE"
        },
        "sameAs": [
          "https://www.instagram.com/saetavinotinto/",
          "https://twitter.com/saetavinotinto",
          "https://www.realmadrid.com/es-ES/el-club/penas-real-madrid/venezuela--saeta-vinotinto"
        ]
      },
      {
        "@type": "WebSite",
        "@id": "https://www.saetavinotinto.com/#website",
        "url": "https://www.saetavinotinto.com/",
        "name": "Saeta Vinotinto",
        "inLanguage": "es-VE",
        "publisher": { "@id": "https://www.saetavinotinto.com/#organization" }
      },
      {
        "@type": "FAQPage",
        "@id": "https://www.saetavinotinto.com/#preguntas",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "¿Qué es Saeta Vinotinto?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Saeta Vinotinto es una Peña Madridista Oficial de Maracaibo, Venezuela, formada por seguidores del Real Madrid."
            }
          },
          {
            "@type": "Question",
            "name": "¿Saeta Vinotinto es una Peña Oficial?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Sí. Saeta Vinotinto aparece en el listado oficial de peñas del Real Madrid."
            }
          },
          {
            "@type": "Question",
            "name": "¿Cuál es el lema de Saeta Vinotinto?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "El lema de Saeta Vinotinto es Madridismo sin fronteras."
            }
          }
        ]
      }
    ]
  }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=20260607-mobile-headings-2">
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-1NB1SB0LYS"></script>
  <script src="analytics.js?v=20260606"></script>
</head>
<body>
  <header class="site-header">
    <a class="brand" href="#inicio" aria-label="Saeta Vinotinto, inicio">
      <img src="images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto">
    </a>
    <nav id="main-nav">
      <a href="#nosotros">La Peña</a>
      <a href="#miembros">Miembros</a>
      <a href="#mapa">Mapa</a>
      <a href="#partidos">Partidos</a>
    </nav>
    <a class="account-pill" id="account-link" href="portal/login.php">
      <span class="account-status"></span>
      <strong>Iniciar Sesión</strong>
    </a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </button>
  </header>

  <main>
    <section class="hero" id="inicio">
      <div class="hero-copy">
        <p class="eyebrow"><span></span> Nuestro Lema</p>
        <h1 class="hero-motto"><span class="brand-blue"><?= sv_e($site['hero_blue']) ?></span><br><em class="brand-wine"><?= sv_e($site['hero_wine']) ?></em></h1>
        <p class="hero-text"><?= sv_e($site['hero_text']) ?></p>
        <div class="hero-actions">
          <a class="button button-primary" href="#miembros">Conoce A La Peña</a>
          <a class="button button-ghost" href="#partidos">Próximos Partidos <span>→</span></a>
        </div>
      </div>
      <div class="hero-visual" aria-label="Comunidad Saeta Vinotinto">
        <div class="orbit orbit-one"></div>
        <div class="orbit orbit-two"></div>
        <div class="hero-badge">
          <small>Peña Madridista</small>
          <strong>53</strong>
          <span>Maracaibo · Venezuela</span>
        </div>
        <div class="floating-card card-one"><span>🇻🇪</span> Maracaibo</div>
        <div class="floating-card card-two"><span>🇪🇸</span> Madrid</div>
        <div class="floating-card card-three"><span>✦</span> Peña Oficial</div>
      </div>
      <div class="hero-scroll">Desliza Para Conocernos <span>↓</span></div>
    </section>

    <section class="manifesto section" id="nosotros">
      <div>
        <p class="eyebrow"><span></span> Nuestra Historia</p>
          <h2><?= sv_e($site['history_title']) ?><br><em><?= sv_e($site['history_emphasis']) ?></em></h2>
      </div>
      <div class="manifesto-copy">
        <p><?= sv_e($site['history_text_one']) ?></p>
        <p><?= sv_e($site['history_text_two']) ?></p>
        <a class="text-link" href="https://www.realmadrid.com/es-ES/el-club/penas-real-madrid/venezuela--saeta-vinotinto" target="_blank" rel="noreferrer">Ver Ficha Oficial En Real Madrid <span>↗</span></a>
      </div>
      <div class="stat"><strong>53</strong><span>miembros<br>en el mundo</span></div>
      <div class="stat"><strong>1</strong><span>pasión<br>que nos une</span></div>
      <div class="stat"><strong>∞</strong><span>historias<br>por hacer</span></div>
    </section>

    <section class="video-story section" id="video">
      <div>
        <p class="eyebrow"><span></span> Nuestra Esencia</p>
        <h2>Esto es<br><span class="brand-blue">Saeta</span> <em class="brand-wine">Vinotinto.</em></h2>
        <p>La Peña se entiende mejor cuando se vive. Dale play y conoce un poco más de nuestra historia, nuestra gente y nuestra pasión.</p>
      </div>
      <div class="video-frame">
        <iframe src="https://drive.google.com/file/d/1g-lrx7zDrK-Zjypi-IaRYD339P5quZLd/preview" title="Video de Saeta Vinotinto" allow="autoplay; fullscreen" loading="lazy"></iframe>
      </div>
    </section>

    <section class="members section" id="miembros">
      <div class="section-heading">
        <div>
          <p class="eyebrow"><span></span> Nuestra Gente</p>
          <h2>El corazón<br><em>de La Peña.</em></h2>
        </div>
        <p>Detrás de cada nombre hay una historia madridista. Encuentra a quienes hacen grande esta familia.</p>
      </div>
      <div class="member-tools">
        <label class="search-box"><span>⌕</span><input id="member-search" type="search" placeholder="Buscar miembro..." autocomplete="off"></label>
        <p id="member-count" aria-live="polite"><?= count($publicMembers) ?> miembros</p>
      </div>
      <div class="member-grid" id="member-grid">
        <?php foreach ($publicMembers as $member): ?>
        <article class="member-card" data-member-name="<?= sv_e(mb_strtolower($member['name'], 'UTF-8')) ?>">
          <div class="member-avatar"><?= sv_e(sv_initials($member['name'])) ?></div>
          <div>
            <h3><?= sv_e($member['name']) ?></h3>
            <?php if ($member['badges']): ?><div class="member-recognitions">
              <?php foreach ($member['badges'] as $badge): ?><span class="<?= $badge === 'Fundador' ? 'badge-founder' : 'badge-board' ?>"><?= $badge === 'Fundador' ? '★ ' : '' ?><?= sv_e($badge) ?></span><?php endforeach; ?>
            </div><?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="members-footer"><a class="button button-ghost" href="portal/members.php">Ver Directorio Completo <span>→</span></a></div>
    </section>

    <section class="world section" id="mapa">
      <div class="section-heading light">
        <div><p class="eyebrow"><span></span> Nuestra Comunidad Global</p><h2>Una Peña.<br><em>Todo el mundo.</em></h2></div>
        <p>Desde Maracaibo, Saeta Vinotinto está presente en distintos países.</p>
      </div>
      <div class="world-layout">
        <div class="world-map" id="world-map" aria-label="Mapa mundial de miembros">
          <svg viewBox="0 0 1000 500" role="img" aria-hidden="true">
            <path d="M70 115 145 70 245 88 290 135 255 178 205 166 175 220 120 205 98 160Z"/>
            <path d="M245 230 300 250 325 315 290 430 250 365 230 285Z"/>
            <path d="M455 105 510 80 555 105 540 145 585 170 550 215 490 195 450 155Z"/>
            <path d="M505 225 570 215 620 280 600 390 540 420 500 330Z"/>
            <path d="M565 95 700 70 840 110 905 165 850 220 760 205 715 250 635 205 585 155Z"/>
            <path d="M785 315 865 300 930 345 895 405 815 390Z"/>
          </svg>
          <div id="map-markers"></div>
        </div>
        <div class="country-list" id="country-list"></div>
      </div>
    </section>

    <section class="matches section" id="partidos">
      <div class="section-heading light">
        <div>
          <p class="eyebrow"><span></span> El Próximo Encuentro</p>
          <h2>Nos vemos<br><em>en el partido.</em></h2>
        </div>
        <p>Consulta los próximos compromisos y prepárate para vivirlos junto a La Peña.</p>
      </div>
      <article class="next-match">
        <div class="match-meta">
          <span>Próximo Partido</span>
          <span>Calendario 2026/27 Por Confirmar</span>
        </div>
        <div class="teams">
          <div class="team">
            <div class="team-crest madrid">RM</div>
            <strong>Real Madrid</strong>
          </div>
          <div class="match-center">
            <span>Próximamente</span>
            <strong>VS</strong>
            <small>Hora De Maracaibo</small>
          </div>
          <div class="team">
            <div class="team-crest rival">?</div>
            <strong>Por Confirmar</strong>
          </div>
        </div>
        <div class="match-actions">
          <a class="button button-white" href="https://www.realmadrid.com/es-ES/futbol/calendario" target="_blank" rel="noreferrer">Calendario Oficial <span>↗</span></a>
          <a class="button button-dark-ghost" href="portal/tickets.php">Entradas De La Peña <span>→</span></a>
        </div>
      </article>
      <div class="match-note">
        <span class="live-dot"></span>
        <p><a href="https://www.realmadrid.com/es-ES/futbol/primer-equipo/calendario" target="_blank" rel="noopener">Fuente: Calendario Oficial Del Real Madrid ↗</a></p>
      </div>
    </section>

    <section class="join section">
      <p class="eyebrow"><span></span> Más Que Noventa Minutos</p>
      <h2>Donde estés,<br><em>estás en casa.</em></h2>
      <p>Comparte la pasión, mantén tus datos al día y sigue conectado con la familia Saeta Vinotinto.</p>
      <a class="button button-primary" href="portal/login.php">Entrar Al Área De Saetas <span>→</span></a>
      <a class="button button-ghost" href="portal/register.php">Solicitar Acceso <span>→</span></a>
    </section>

    <section class="shop-preview section">
      <div><p class="eyebrow"><span></span> Producto Oficial</p><h2>La bufanda<br><em>de La Peña.</em></h2></div>
      <div class="scarf-art"><img src="<?= sv_e($site['public_product_image']) ?>" alt="Bufanda Oficial SV7 de Saeta Vinotinto"></div>
      <div><p>Lleva nuestros colores contigo. La compra está reservada para Peñistas autenticados.</p><a class="button button-primary" href="portal/store.php">Ver Tienda Oficial SV7 <span>→</span></a></div>
    </section>

    <section class="faq section" id="preguntas">
      <div class="section-heading">
        <div>
          <p class="eyebrow"><span></span> Conoce La Peña</p>
          <h2>Preguntas<br><em>frecuentes.</em></h2>
        </div>
        <p>Información esencial sobre la Peña Madridista Oficial Saeta Vinotinto.</p>
      </div>
      <div class="faq-grid">
        <article>
          <h3>¿Qué es Saeta Vinotinto?</h3>
          <p>Somos una Peña Madridista Oficial de Maracaibo, Venezuela, formada por seguidores del Real Madrid.</p>
        </article>
        <article>
          <h3>¿Nuestra Peña es oficial?</h3>
          <p>Sí. Saeta Vinotinto aparece en el <a class="text-link" href="https://www.realmadrid.com/es-ES/el-club/penas-real-madrid/venezuela--saeta-vinotinto" target="_blank" rel="noreferrer">listado oficial de peñas del Real Madrid <span>↗</span></a>.</p>
        </article>
        <article>
          <h3>¿Qué significa “Madridismo sin fronteras”?</h3>
          <p>Es la idea que nos une: la distancia nunca limita nuestra pasión ni nuestro sentido de comunidad.</p>
        </article>
      </div>
    </section>

    <section class="contact section" id="contacto">
      <div class="contact-copy">
        <p class="eyebrow"><span></span> Contáctanos</p>
        <h2><?= sv_e($site['contact_title']) ?><br><em><?= sv_e($site['contact_emphasis']) ?></em></h2>
        <p><?= sv_e($site['contact_text']) ?></p>
      </div>
      <form class="contact-form" method="post" action="portal/contact.php">
        <label>Nombre<input name="name" maxlength="120" autocomplete="name" required></label>
        <label>Correo<input type="email" name="email" maxlength="190" autocomplete="email" required></label>
        <label class="contact-wide">Mensaje<textarea name="message" rows="4" minlength="10" maxlength="3000" required></textarea></label>
        <label class="contact-trap" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <button class="button button-primary" type="submit">Enviar Mensaje <span>→</span></button>
        <?php if (($_GET['contact'] ?? '') === 'received'): ?><p class="contact-status success">Mensaje recibido. Te responderemos pronto.</p><?php endif; ?>
        <?php if (($_GET['contact'] ?? '') === 'error'): ?><p class="contact-status error">No pudimos enviar el mensaje. Revisa los datos e intenta nuevamente.</p><?php endif; ?>
      </form>
    </section>
  </main>

  <footer>
    <a class="brand" href="#inicio"><img src="images/saeta-imagotipo-clean.png" alt="Saeta Vinotinto"></a>
    <p>Peña Madridista Oficial · Maracaibo, Venezuela</p>
    <div>
      <a href="https://www.instagram.com/saetavinotinto/" target="_blank" rel="noreferrer">Instagram</a>
      <a href="https://twitter.com/saetavinotinto" target="_blank" rel="noreferrer">X / Twitter</a>
      <a href="https://www.realmadrid.com/es-ES/el-club/penas-real-madrid/venezuela--saeta-vinotinto" target="_blank" rel="noreferrer">Ficha oficial</a>
      <a href="#contacto">Contáctanos</a>
    </div>
    <small class="creator-credit">Web creada por <a href="https://sietebit.com" target="_blank" rel="noopener noreferrer">Sietebit</a></small>
  </footer>

<script src="app.js?v=20260606-title-case"></script>
</body>
</html>
