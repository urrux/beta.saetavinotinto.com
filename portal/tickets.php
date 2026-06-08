<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $match = request_value('match_name');
    $dateInput = request_value('match_date');
    $date = valid_date_or_null($dateInput);
    $quantity = max(1, min(4, (int) request_value('quantity')));
    $competition = request_value('competition') ?: null;
    $ticketType = request_value('ticket_type') ?: null;
    $budgetRange = request_value('budget_range') ?: null;
    $companionNames = request_value('companion_names') ?: null;
    $availabilityNotes = request_value('availability_notes') ?: null;
    $notes = request_value('notes');
    $minimumDays = $ticketType === 'Visitante' ? 20 : 15;
    $minimumDate = new DateTimeImmutable('today +' . $minimumDays . ' days');
    if ($match === '') {
        flash('error', 'Indica el partido para enviar la solicitud.');
    } elseif ($dateInput !== '' && !$date) {
        flash('error', 'La fecha indicada no es válida.');
    } elseif ($date && new DateTimeImmutable($date) < $minimumDate) {
        flash('error', 'Las solicitudes de ' . ($ticketType === 'Visitante' ? 'visitante' : 'casa o por confirmar') . ' requieren al menos ' . $minimumDays . ' días de antelación.');
    } else {
        $duplicate = $pdo->prepare("SELECT COUNT(*) FROM ticket_requests WHERE user_id = ? AND LOWER(match_name) = LOWER(?) AND COALESCE(match_date, '') = COALESCE(?, '') AND quantity = ? AND created_at >= ?");
        $duplicate->execute([$user['id'], $match, $date, $quantity, date('Y-m-d H:i:s', time() - 900)]);
        if ((int) $duplicate->fetchColumn() > 0) {
            flash('error', 'Ya recibimos una solicitud igual hace pocos minutos. Puedes verla en tu historial.');
        } else {
            $pdo->prepare('INSERT INTO ticket_requests (user_id, match_name, match_date, competition, ticket_type, budget_range, companion_names, availability_notes, quantity, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$user['id'], $match, $date, $competition, $ticketType, $budgetRange, $companionNames, $availabilityNotes, $quantity, $notes]);
            flash('success', 'Recibimos tu solicitud. La directiva revisará la disponibilidad.');
        }
    }
    redirect('tickets.php');
}

$statement = $pdo->prepare('SELECT * FROM ticket_requests WHERE user_id = ? ORDER BY created_at DESC');
$statement->execute([$user['id']]);
$requests = $statement->fetchAll();
$historyStatement = $pdo->prepare('SELECT * FROM imported_ticket_requests WHERE user_id = ? OR LOWER(requester_email) = LOWER(?) ORDER BY requested_at DESC, created_at DESC');
$historyStatement->execute([$user['id'], $user['email']]);
$historicalRequests = $historyStatement->fetchAll();

$allRequestsCount = count($requests) + count($historicalRequests);
$lastRequest = $requests[0] ?? $historicalRequests[0] ?? null;
$statusLabels = ['received' => 'Recibida', 'reviewing' => 'En revisión', 'approved' => 'Aprobada', 'rejected' => 'No disponible', 'completed' => 'Completada'];
render_header('Entradas oficiales');
?>
<section class="tickets-hero">
  <div>
  <p class="kicker">Entradas Oficiales</p>
    <h1>Solicita entradas sin perder el hilo.</h1>
    <p>Centralizamos el pedido, el estado y tu historial. La solicitud no garantiza asignación: la directiva confirma disponibilidad según reglas y comunicación del Real Madrid.</p>
  </div>
  <div class="ticket-summary">
    <article><span>Historial</span><strong><?= $allRequestsCount ?></strong><small>solicitudes vinculadas a tu cuenta</small></article>
    <article><span>Último movimiento</span><strong><?= $lastRequest ? e($lastRequest['match_name']) : 'Sin solicitudes' ?></strong><small><?= $lastRequest ? e($lastRequest['created_at'] ?? $lastRequest['requested_at'] ?? $lastRequest['match_date'] ?? 'Fecha no registrada') : 'Cuando pidas entradas aparecerá aquí.' ?></small></article>
  </div>
</section>

<section class="ticket-flow">
  <article><span>1</span><strong>Solicita</strong><small>Partido, cantidad y preferencias.</small></article>
  <article><span>2</span><strong>Revisión</strong><small>La directiva agrupa y valida disponibilidad.</small></article>
  <article><span>3</span><strong>Confirmación</strong><small>Recibes estado, notas y próximos pasos.</small></article>
</section>

<section class="ticket-rules">
  <article><strong>Casa</strong><span>Solicitar con mínimo 15 días de antelación.</span></article>
  <article><strong>Visitante</strong><span>Solicitar con mínimo 20 días de antelación.</span></article>
  <article><strong>Pago</strong><span>El Real Madrid confirma zona/precio y envía enlace de pago. Una vez pagado, no hay cambios ni devoluciones.</span></article>
</section>

<div class="tickets-layout">
  <section class="form-panel ticket-form-panel">
  <div class="section-title compact"><div><p class="kicker">Pedido Rápido</p><h2>Nueva Solicitud</h2></div></div>
    <form method="post" class="form-stack ticket-form" data-confirm="¿Enviar esta solicitud de entradas a la directiva?">
      <?= csrf_field() ?>
      <label class="wide">Rival del Real Madrid C.F.<input name="match_name" placeholder="Ej. Sevilla, Barcelona, Juventus..." required></label>
      <div class="field-grid">
        <label>Fecha del partido<input type="date" name="match_date" min="<?= date('Y-m-d', strtotime('+15 days')) ?>"><small>Casa: mínimo 15 días. Visitante: mínimo 20 días.</small></label>
        <label>Competición<select name="competition"><option value="">Por confirmar</option><option>Liga</option><option>Champions League</option><option>Copa del Rey</option><option>Otro</option></select></label>
        <label>Total de entradas<select name="quantity"><option>1</option><option>2</option><option>3</option><option>4</option></select></label>
        <label>Casa o visitante<select name="ticket_type"><option value="">Por confirmar</option><option>Casa</option><option>Visitante</option></select></label>
        <label>Zona / referencia de precio<select name="budget_range"><option value="">Sin preferencia</option><option>4to Anfiteatro lateral · aprox. 135 EUR p/p</option><option>Grada baja lateral oeste · aprox. 145 EUR p/p</option><option>Grada baja de fondo</option><option>3er Anfiteatro de fondo</option><option>4to Anfiteatro de fondo</option><option>Visitante · según asignación RM</option><option>Flexible</option></select></label>
        <label>Jornada / contexto<input name="availability_notes" placeholder="Ej. Jornada 12, ya tengo viaje, necesito confirmar antes de..."></label>
      </div>
      <label>Acompañantes<textarea name="companion_names" rows="3" placeholder="Nombres de Peñistas o acompañantes si quieres entradas juntas"></textarea></label>
      <label>Notas para la directiva<textarea name="notes" rows="4" placeholder="Necesidades, contexto o información útil"></textarea></label>
      <div class="ticket-submit-row">
    <button class="primary-button" type="submit">Enviar Solicitud</button>
        <small>Queda registrada con fecha, estado y trazabilidad en tu historial.</small>
      </div>
    </form>
  </section>
  <section class="list-panel ticket-status-panel">
  <div class="section-title"><div><p class="kicker">Seguimiento</p><h2>Mis Solicitudes</h2></div></div>
    <?php if (!$requests): ?><p class="empty-state">Todavía no tienes solicitudes.</p><?php endif; ?>
    <?php foreach ($requests as $request): ?>
      <article class="request-card">
        <div><h3><?= e($request['match_name']) ?></h3><p><?= e($request['match_date'] ?: 'Fecha por confirmar') ?> · <?= (int) $request['quantity'] ?> entrada(s)<?= $request['competition'] ? ' · ' . e($request['competition']) : '' ?></p></div>
        <span class="status status-<?= e($request['status']) ?>"><?= e($statusLabels[$request['status']] ?? $request['status']) ?></span>
        <?php if ($request['ticket_type'] || $request['budget_range']): ?><small><?= e(trim(($request['ticket_type'] ?: '') . ' · ' . ($request['budget_range'] ?: ''), " ·")) ?></small><?php endif; ?>
        <?php if ($request['companion_names']): ?><small>Acompañantes: <?= e($request['companion_names']) ?></small><?php endif; ?>
        <?php if ($request['admin_notes']): ?><small><?= e($request['admin_notes']) ?></small><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
</div>
<section class="content-section">
  <div class="section-title"><div><p class="kicker">Archivo Histórico</p><h2>Solicitudes Anteriores</h2></div><span><?= count($historicalRequests) ?> importadas</span></div>
  <?php if (!$historicalRequests): ?><p class="empty-state">No hay solicitudes históricas vinculadas a tu correo todavía.</p><?php endif; ?>
  <div class="history-grid">
  <?php foreach ($historicalRequests as $request): ?><article class="history-card">
    <span><?= e($request['requested_at'] ?: $request['match_date'] ?: 'Fecha no registrada') ?></span>
    <h3><?= e($request['match_name']) ?></h3>
    <p><?= (int) $request['quantity'] ?> entrada(s) · <?= e($request['status']) ?></p>
    <?php if ($request['notes']): ?><small><?= e($request['notes']) ?></small><?php endif; ?>
  </article><?php endforeach; ?>
  </div>
</section>
<?php render_footer(); ?>
