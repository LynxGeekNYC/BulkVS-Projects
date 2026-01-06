<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../lib/media_store.php';

$u = require_login();
$userId = (int)$u['id'];
csrf_verify();

$app = require __DIR__ . '/../config/app.php';
$db = db();

$convoId = (int)($_POST['conversation_id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));

$st = $db->prepare("
  SELECT c.id, c.did_id, c.remote_phone, d.did, ud.can_send
  FROM conversations c
  JOIN dids d ON d.id = c.did_id
  JOIN user_dids ud ON ud.did_id = c.did_id AND ud.user_id = ? AND ud.can_view=1
  WHERE c.id = ?
  LIMIT 1
");
$st->execute([$userId, $convoId]);
$convo = $st->fetch();
if (!$convo) { http_response_code(403); echo "Forbidden"; exit; }
if ((int)$convo['can_send'] !== 1) { http_response_code(403); echo "Send not allowed"; exit; }

$storedUploads = store_uploaded_images($app['media'], $_FILES['images'] ?? []);

/*
  For MMS, most carriers require a public URL. We do:
  - Create a placeholder outbound message row later by worker, but for media URLs we need IDs now.
  - Create a temp message row with status queued, attach media, generate /media.php?id=...&token=...
*/
$db->beginTransaction();

$db->prepare("
  INSERT INTO messages (conversation_id, direction, body, status, created_by_user_id, created_at)
  VALUES (?, 'out', ?, 'queued', ?, NOW())
")->execute([$convoId, $body, $userId]);
$msgId = (int)$db->lastInsertId();

$publicUrls = [];
foreach ($storedUploads as $uinfo) {
  $token = bin2hex(random_bytes(32));
  $db->prepare("
    INSERT INTO media (message_id, original_url, stored_path, mime_type, byte_size, sha256, access_token)
    VALUES (?, NULL, ?, ?, ?, ?, ?)
  ")->execute([$msgId, $uinfo['path'], $uinfo['mime'], (int)$uinfo['bytes'], $uinfo['sha256'], $token]);

  $mediaId = (int)$db->lastInsertId();
  $publicUrls[] = rtrim($app['app']['base_url'], '/') . "/media.php?id=" . $mediaId . "&token=" . $token;
}

$db->prepare("
  INSERT INTO send_queue (conversation_id, body, media_local_paths, tries, next_attempt_at, created_by_user_id)
  VALUES (?, ?, ?, 0, NOW(), ?)
")->execute([$convoId, $body, json_encode($publicUrls, JSON_UNESCAPED_SLASHES), $userId]);

$db->prepare("UPDATE conversations SET last_message_at=NOW() WHERE id=?")->execute([$convoId]);

$db->commit();

header("Location: /conversation.php?id=" . $convoId);
exit;
