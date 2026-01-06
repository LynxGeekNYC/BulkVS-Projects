<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/_layout.php';

$u = require_login();
$userId = (int)$u['id'];
$dids = user_accessible_dids($userId);

$convoId = (int)($_GET['id'] ?? 0);
if ($convoId <= 0) { http_response_code(404); exit; }

$db = db();

$st = $db->prepare("
  SELECT
    c.*,
    d.did AS did_number,
    d.label AS did_label,
    ud.can_send,
    COALESCE(ct.display_name, c.remote_phone) AS contact_name
  FROM conversations c
  JOIN dids d ON d.id = c.did_id
  JOIN user_dids ud ON ud.did_id = c.did_id AND ud.user_id = ? AND ud.can_view=1
  LEFT JOIN contacts ct ON ct.id = c.contact_id
  WHERE c.id = ?
  LIMIT 1
");
$st->execute([$userId, $convoId]);
$convo = $st->fetch();
if (!$convo) { http_response_code(403); echo "Forbidden"; exit; }

$didId = (int)$convo['did_id'];

$tags = $db->query("SELECT id, name FROM tags ORDER BY name")->fetchAll();

$contactTagIds = [];
if (!empty($convo['contact_id'])) {
  $stt = $db->prepare("SELECT tag_id FROM contact_tags WHERE contact_id=?");
  $stt->execute([(int)$convo['contact_id']]);
  $contactTagIds = array_map(fn($r) => (int)$r['tag_id'], $stt->fetchAll());
}

$st = $db->prepare("
  SELECT m.*
  FROM messages m
  WHERE m.conversation_id = ?
  ORDER BY m.created_at ASC
  LIMIT 400
");
$st->execute([$convoId]);
$messages = $st->fetchAll();

$messageIds = array_map(fn($m) => (int)$m['id'], $messages);
$mediaByMsg = [];
if (!empty($messageIds)) {
  $in = implode(',', array_fill(0, count($messageIds), '?'));
  $stm = $db->prepare("SELECT * FROM media WHERE message_id IN ($in) ORDER BY id ASC");
  $stm->execute($messageIds);
  foreach ($stm->fetchAll() as $r) {
    $mediaByMsg[(int)$r['message_id']][] = $r;
  }
}

$lastMsgId = !empty($messages) ? (int)$messages[count($messages)-1]['id'] : 0;

page_header("Conversation", $u, $dids, $didId);
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-semibold"><?= h($convo['contact_name']) ?></div>
          <div class="text-muted small"><?= h($convo['remote_phone']) ?>, DID: <?= h($convo['did_number']) ?></div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="/did.php?did_id=<?= (int)$didId ?>">Back</a>
      </div>

      <div class="card-body" style="height: 60vh; overflow:auto;" id="thread">
        <?php foreach ($messages as $m): ?>
          <?php $isOut = ($m['direction'] === 'out'); ?>
          <div class="d-flex mb-3 <?= $isOut ? 'justify-content-end' : 'justify-content-start' ?>">
            <div>
              <div class="msg-bubble <?= $isOut ? 'msg-out' : 'msg-in' ?>">
                <?= nl2br(h($m['body'] ?? '')) ?>
              </div>
              <?php if (!empty($mediaByMsg[(int)$m['id']])): ?>
                <div class="mt-2 d-flex gap-2 flex-wrap <?= $isOut ? 'justify-content-end' : 'justify-content-start' ?>">
                  <?php foreach ($mediaByMsg[(int)$m['id']] as $med): ?>
                    <a href="/media.php?id=<?= (int)$med['id'] ?>&token=<?= h($med['access_token']) ?>" target="_blank">
                      <img class="thumb border" src="/media.php?id=<?= (int)$med['id'] ?>&token=<?= h($med['access_token']) ?>" alt="image">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="text-muted small mt-1 <?= $isOut ? 'text-end' : '' ?>">
                <?= h($m['created_at']) ?>
                <?php if ($isOut): ?>
                  , <?= h($m['status']) ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card-footer">
        <?php if ((int)$convo['can_send'] !== 1): ?>
          <div class="alert alert-warning mb-0">You have view-only access to this DID.</div>
        <?php else: ?>
          <form method="post" action="/send.php" enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="conversation_id" value="<?= (int)$convoId ?>">
            <textarea class="form-control" name="body" rows="2" placeholder="Type message"></textarea>
            <div style="min-width:220px;">
              <input class="form-control form-control-sm mb-2" type="file" name="images[]" multiple accept="image/*">
              <button class="btn btn-primary w-100" type="submit">Send</button>
            </div>
          </form>
          <div class="text-muted small mt-2">
            Sends are queued, the worker delivers with retries and backoff.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header"><strong>Contact</strong></div>
      <div class="card-body">
        <?php if (empty($convo['contact_id'])): ?>
          <div class="text-muted">No contact linked.</div>
        <?php else: ?>
          <form id="contactForm">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="contact_id" value="<?= (int)$convo['contact_id'] ?>">

            <div class="mb-3">
              <label class="form-label">Display name</label>
              <input class="form-control" name="display_name" value="<?= h($convo['contact_name']) ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Tags</label>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($tags as $t): ?>
                  <?php $checked = in_array((int)$t['id'], $contactTagIds, true); ?>
                  <label class="border rounded px-2 py-1 small">
                    <input type="checkbox" name="tag_ids[]" value="<?= (int)$t['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                    <?= h($t['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button class="btn btn-outline-primary" type="submit">Save</button>
            <div class="text-muted small mt-2" id="contactStatus"></div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  // Scroll thread to bottom
  const thread = document.getElementById('thread');
  if (thread) thread.scrollTop = thread.scrollHeight;

  // Read receipt, set last seen and reset per-user unread
  (async function markSeen() {
    const lastSeen = <?= (int)$lastMsgId ?>;
    if (!lastSeen) return;
    const fd = new FormData();
    fd.append('conversation_id', String(<?= (int)$convoId ?>));
    fd.append('last_seen_message_id', String(lastSeen));
    const res = await fetch('/api/conversation_state.php', { method: 'POST', body: fd });
    try { await res.json(); } catch (e) {}
  })();

  // Contact editor AJAX
  const form = document.getElementById('contactForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const status = document.getElementById('contactStatus');
      status.textContent = 'Saving...';
      const fd = new FormData(form);
      const res = await fetch('/api/contacts.php', { method: 'POST', body: fd });
      if (!res.ok) { status.textContent = 'Save failed'; return; }
      const data = await res.json();
      status.textContent = data.ok ? 'Saved' : 'Save failed';
    });
  }
</script>
<?php
page_footer();
