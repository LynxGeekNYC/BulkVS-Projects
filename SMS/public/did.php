<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/_layout.php';

$u = require_login();
$userId = (int)$u['id'];
$dids = user_accessible_dids($userId);

$didId = (int)($_GET['did_id'] ?? 0);
require_user_did_access($userId, $didId, false);

$db = db();

$rows = $db->prepare("
  SELECT
    c.*,
    d.did AS did_number,
    d.label AS did_label,
    COALESCE(ct.display_name, c.remote_phone) AS contact_name,
    (
      SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ')
      FROM contact_tags ctg
      JOIN tags t ON t.id = ctg.tag_id
      WHERE ctg.contact_id = c.contact_id
    ) AS contact_tags
  FROM conversations c
  JOIN dids d ON d.id = c.did_id
  LEFT JOIN contacts ct ON ct.id = c.contact_id
  WHERE c.did_id = ? AND c.is_archived=0
  ORDER BY COALESCE(c.last_message_at, '1970-01-01') DESC
  LIMIT 200
");
$rows->execute([$didId]);
$convos = $rows->fetchAll();

page_header("Inbox", $u, $dids, $didId);
?>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Conversations</strong>
    <form class="d-flex gap-2" method="get" action="/did.php">
      <input type="hidden" name="did_id" value="<?= (int)$didId ?>">
      <input class="form-control form-control-sm" name="q" placeholder="Search not wired here" disabled>
    </form>
  </div>
  <div class="list-group list-group-flush">
    <?php if (empty($convos)): ?>
      <div class="p-3 text-muted">No conversations yet.</div>
    <?php else: ?>
      <?php foreach ($convos as $c): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start"
           href="/conversation.php?id=<?= (int)$c['id'] ?>">
          <div>
            <div class="fw-semibold"><?= h($c['contact_name']) ?></div>
            <div class="text-muted small"><?= h($c['remote_phone']) ?></div>
            <?php if (!empty($c['contact_tags'])): ?>
              <div class="small mt-1">
                <span class="badge text-bg-secondary"><?= h($c['contact_tags']) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <?php if ((int)$c['unread_count'] > 0): ?>
              <span class="badge text-bg-danger"><?= (int)$c['unread_count'] ?></span>
            <?php endif; ?>
            <div class="text-muted small"><?= h($c['last_message_at'] ?? '') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php
page_footer();
