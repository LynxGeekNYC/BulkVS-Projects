<?php
require_once __DIR__ . '/db.php';

function auth_start_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
      'httponly' => true,
      'secure' => isset($_SERVER['HTTPS']),
      'samesite' => 'Lax'
    ]);
    session_start();
  }
}

function auth_user(): ?array {
  auth_start_session();
  if (empty($_SESSION['user_id'])) return null;

  $db = db();
  $st = $db->prepare("SELECT id, email, username, role, is_active FROM users WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['user_id']]);
  $u = $st->fetch();
  if (!$u || (int)$u['is_active'] !== 1) return null;
  return $u;
}

function require_login(): array {
  $u = auth_user();
  if (!$u) {
    header("Location: /login.php");
    exit;
  }
  return $u;
}

function require_admin(): array {
  $u = require_login();
  if (($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
  return $u;
}

function login_attempt(string $usernameOrEmail, string $password): bool {
  $db = db();
  $st = $db->prepare("SELECT * FROM users WHERE (email=? OR username=?) LIMIT 1");
  $st->execute([$usernameOrEmail, $usernameOrEmail]);
  $u = $st->fetch();
  if (!$u || (int)$u['is_active'] !== 1) return false;
  if (!password_verify($password, $u['password_hash'])) return false;

  auth_start_session();
  $_SESSION['user_id'] = (int)$u['id'];

  $upd = $db->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?");
  $upd->execute([(int)$u['id']]);

  return true;
}

function logout(): void {
  auth_start_session();
  session_unset();
  session_destroy();
}
