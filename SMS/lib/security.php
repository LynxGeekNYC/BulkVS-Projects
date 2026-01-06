<?php
function enforce_webhook_security(array $cfg): void {
  $secret = $cfg['shared_secret'] ?? '';
  $allowedIps = $cfg['allowed_ips'] ?? [];

  if (!empty($allowedIps)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, $allowedIps, true)) {
      http_response_code(403);
      echo "Forbidden";
      exit;
    }
  }

  if (!empty($secret)) {
    $provided = $_GET['secret'] ?? '';
    if (!hash_equals($secret, (string)$provided)) {
      http_response_code(403);
      echo "Forbidden";
      exit;
    }
  }
}

function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_verify(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $t = $_POST['csrf'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
    http_response_code(400);
    echo "Bad CSRF";
    exit;
  }
}

function h(?string $s): string {
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
