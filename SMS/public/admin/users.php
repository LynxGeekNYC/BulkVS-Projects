<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/security.php';

$admin = require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  if (($_POST['action'] ?? '') === 'create_user') {
    $email = trim((string)($_POST['email'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

    if ($email && $username && $password) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $db->prepare("INSERT INTO users (email, username, password_hash, role) VALUES (?, ?, ?, ?)")
         ->execute([$email, $username, $hash, $role]);
    }
  }

  if (($_POST['action'] ?? '') === 'assign_dids') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $didIds = $_POST['did_ids'] ?? [];

    $db->prepare("DELETE FROM user_dids WHERE user_id=?")->execute([$userId]);

    if (is_array($didIds)) {
      $ins = $db->prepare("INSERT INTO user_dids (user_id, did_id, can_send, can_view) VALUES (?, ?, 1, 1)");
      foreach ($didIds as $didId) {
        $didId = (int)$didId;
        if ($didId > 0) $ins->execute([$userId, $didId]);
      }
    }
  }
}

$users = $db->query("SELECT id, email, username, role, is_active FROM users ORDER BY id DESC")->fetchAll();
$dids  = $db->query("SELECT id, did, label FROM dids WHERE is_active=1 ORDER BY COALESCE(label,did)")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Admin, Users</h1>
    <a class="btn btn-outline-secondary btn-sm" href="/index.php">Back</a>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Create user</strong></div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_user">
        <div class="col-md-3"><input class="form-control" name="email" placeholder="email" required></div>
        <div class="col-md-2"><input class="form-control" name="username" placeholder="username" required></div>
        <div class="col-md-3"><input class="form-control" name="password" placeholder="password" required></div>
        <div class="col-md-2">
          <select class="form-select" name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Create</button></div>
      </form>
    </div>
  </div>

  <?php foreach ($users as $u): ?>
    <?php
      $st = $db->prepare("SELECT did_id FROM user_dids WHERE user_id=?");
      $st->execute([(int)$u['id']]);
      $assigned = array_map(fn($r) => (int)$r['did_id'], $st->fetchAll());
    ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= h($u['username']) ?></strong>
        <span class="badge text-bg-<?= ($u['role'] === 'admin') ? 'dark' : 'secondary' ?>"><?= h($u['role']) ?></span>
      </div>
      <div class="card-body">
        <div class="text-muted small mb-2"><?= h($u['email']) ?></div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="assign_dids">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($dids as $d): ?>
              <?php $checked = in_array((int)$d['id'], $assigned, true); ?>
              <label class="border rounded px-2 py-1 small">
                <input type="checkbox" name="did_ids[]" value="<?= (int)$d['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                <?= h(($d['label'] ? $d['label'] . ' ' : '') . $d['did']) ?>
              </label>
            <?php endforeach; ?>
          </div>

          <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Save DID access</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
