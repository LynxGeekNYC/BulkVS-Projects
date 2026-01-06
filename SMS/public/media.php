<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$token = (string)($_GET['token'] ?? '');
if ($id <= 0 || $token === '') { http_response_code(404); exit; }

$db = db();
$st = $db->prepare("SELECT stored_path, mime_type, access_token FROM media WHERE id=? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit; }

if (!hash_equals((string)$row['access_token'], $token)) {
  http_response_code(403);
  exit;
}

$path = (string)$row['stored_path'];
if (!is_file($path)) { http_response_code(404); exit; }

$mime = (string)($row['mime_type'] ?? 'application/octet-stream');
header("Content-Type: " . $mime);
header("X-Content-Type-Options: nosniff");
readfile($path);
