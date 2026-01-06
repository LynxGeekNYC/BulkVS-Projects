<?php
require_once __DIR__ . '/../lib/auth.php';

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = trim((string)($_POST['id'] ?? ''));
  $pw = (string)($_POST['pw'] ?? '');
  if (login_attempt($id, $pw)) {
    header("Location: /index.php");
    exit;
  }
  $err = "Invalid credentials.";
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:480px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3">Sign in</h1>
      <?php if ($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Email or Username</label>
          <input class="form-control" name="id" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="pw" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Login</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
