<?php
require_once __DIR__ . '/../lib/security.php';

function page_header(string $title, array $user, array $dids, ?int $activeDidId = null): void {
  $cfg = require __DIR__ . '/../config/app.php';
  $useBlue = (bool)($cfg['app']['use_blue_bubbles'] ?? true);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .sidebar { width: 280px; }
    .msg-bubble { border-radius: 18px; padding: 10px 12px; max-width: 75%; }
    .msg-in { background: #f1f3f5; }
    .msg-out { background: <?= $useBlue ? '#0d6efd' : '#d1e7dd' ?>; color: <?= $useBlue ? '#fff' : '#000' ?>; }
    .tag-pill { font-size: 12px; border-radius: 999px; padding: 2px 8px; }
    .thumb { width: 140px; height: 140px; object-fit: cover; border-radius: 10px; }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/index.php">BulkVS SMS</a>
    <div class="d-flex align-items-center gap-3 text-white">
      <div><?= h($user['username']) ?> (<?= h($user['role']) ?>)</div>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a class="btn btn-sm btn-outline-light" href="/admin/users.php">Admin</a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-light" href="/logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index:1080;"></div>

<div class="container-fluid py-3">
  <div class="d-flex gap-3">
    <div class="sidebar">
      <div class="card shadow-sm">
        <div class="card-header">
          <strong>DIDs</strong>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach ($dids as $d): ?>
            <?php
              $active = ($activeDidId !== null && (int)$d['id'] === (int)$activeDidId);
              $label = $d['label'] ? ($d['label'] . ' ') : '';
              $text = $label . $d['did'];
            ?>
            <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
               href="/did.php?did_id=<?= (int)$d['id'] ?>">
              <?= h($text) ?>
              <?php if ((int)$d['can_send'] !== 1): ?>
                <span class="badge text-bg-warning float-end">View</span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="flex-grow-1">
<?php
}

function page_footer(): void {
?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let lastMsgId = Number(localStorage.getItem('lastMsgId') || 0);

  async function pollNotifications() {
    try {
      const res = await fetch(`/api/notifications.php?since_id=${encodeURIComponent(lastMsgId)}`, { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok) return;

      if (Array.isArray(data.messages) && data.messages.length) {
        data.messages.forEach(showToastForMessage);
      }
      lastMsgId = Number(data.latest_id || lastMsgId);
      localStorage.setItem('lastMsgId', String(lastMsgId));
    } catch (e) {}
  }

  function showToastForMessage(m) {
    const container = document.getElementById('toastContainer');
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role','alert');
    el.setAttribute('aria-live','assertive');
    el.setAttribute('aria-atomic','true');

    const title = `${m.contact_name} → ${m.did}${m.label ? ' ('+m.label+')' : ''}`;
    const body = (m.body || '').slice(0, 220);

    el.innerHTML = `
      <div class="toast-header">
        <strong class="me-auto">${escapeHtml(title)}</strong>
        <small class="text-muted">${escapeHtml(m.created_at)}</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        <div>${escapeHtml(body || '[No text body]')}</div>
        <div class="mt-2">
          <a class="btn btn-sm btn-primary" href="/conversation.php?id=${encodeURIComponent(m.conversation_id)}">Open</a>
        </div>
      </div>
    `;

    container.appendChild(el);
    const toast = new bootstrap.Toast(el, { delay: 7000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  setInterval(pollNotifications, 4000);
  pollNotifications();
</script>
</body>
</html>
<?php
}
