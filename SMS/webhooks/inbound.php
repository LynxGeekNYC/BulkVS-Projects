<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/normalizers.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/media_store.php';

$app = require __DIR__ . '/../config/app.php';
enforce_webhook_security($app['webhooks']);

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  http_response_code(400);
  echo "Invalid JSON";
  exit;
}

$from = normalize_phone((string)($payload['From'] ?? ''));
$toArr = $payload['To'] ?? [];
$to = is_array($toArr) ? normalize_phone((string)($toArr[0] ?? '')) : normalize_phone((string)$toArr);
$body = (string)($payload['Message'] ?? '');

if ($from === '' || $to === '') {
  http_response_code(400);
  echo "Missing From/To";
  exit;
}

$mediaUrls = extract_media_urls($payload);

$db = db();

/* Ensure DID exists */
$st = $db->prepare("SELECT id FROM dids WHERE did=? LIMIT 1");
$st->execute([$to]);
$didId = (int)($st->fetchColumn() ?: 0);
if ($didId === 0) {
  $ins = $db->prepare("INSERT INTO dids (did, label, is_active) VALUES (?, NULL, 1)");
  $ins->execute([$to]);
  $didId = (int)$db->lastInsertId();
}

/* Ensure contact */
$st = $db->prepare("SELECT id FROM contacts WHERE phone=? LIMIT 1");
$st->execute([$from]);
$contactId = (int)($st->fetchColumn() ?: 0);
if ($contactId === 0) {
  $ins = $db->prepare("INSERT INTO contacts (phone, display_name) VALUES (?, NULL)");
  $ins->execute([$from]);
  $contactId = (int)$db->lastInsertId();
}

/* Ensure conversation */
$st = $db->prepare("SELECT id FROM conversations WHERE did_id=? AND remote_phone=? LIMIT 1");
$st->execute([$didId, $from]);
$convoId = (int)($st->fetchColumn() ?: 0);
if ($convoId === 0) {
  $ins = $db->prepare("
    INSERT INTO conversations (did_id, remote_phone, contact_id, last_message_at, unread_count)
    VALUES (?, ?, ?, NOW(), 0)
  ");
  $ins->execute([$didId, $from, $contactId]);
  $convoId = (int)$db->lastInsertId();
} else {
  $upd = $db->prepare("UPDATE conversations SET contact_id=COALESCE(contact_id, ?) WHERE id=?");
  $upd->execute([$contactId, $convoId]);
}

/* Insert inbound message */
$ins = $db->prepare("
  INSERT INTO messages (conversation_id, direction, body, status, created_at)
  VALUES (?, 'in', ?, 'received', NOW())
");
$ins->execute([$convoId, $body]);
$msgId = (int)$db->lastInsertId();

/* Store inbound media */
foreach ($mediaUrls as $url) {
  $saved = download_and_store_media($app['media'], $url);
  if (!$saved['ok']) continue;

  $token = bin2hex(random_bytes(32));
  $insM = $db->prepare("
    INSERT INTO media (message_id, original_url, stored_path, mime_type, byte_size, sha256, access_token)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $insM->execute([
    $msgId,
    $url,
    $saved['path'],
    $saved['mime'],
    (int)$saved['bytes'],
    $saved['sha256'],
    $token
  ]);
}

/*
  Inbound enhancements:
  - increment conversation unread_count
  - update per-user conversation_user_state unread_count for all users assigned to this DID
*/
$db->prepare("UPDATE conversations SET last_message_at=NOW(), unread_count=unread_count+1 WHERE id=?")
   ->execute([$convoId]);

$stUsers = $db->prepare("SELECT user_id FROM user_dids WHERE did_id=? AND can_view=1");
$stUsers->execute([$didId]);
$users = $stUsers->fetchAll();

foreach ($users as $u) {
  $uid = (int)$u['user_id'];
  $db->prepare("
    INSERT INTO conversation_user_state (conversation_id, user_id, last_seen_message_id, unread_count)
    VALUES (?, ?, NULL, 1)
    ON DUPLICATE KEY UPDATE unread_count = unread_count + 1
  ")->execute([$convoId, $uid]);
}

http_response_code(200);
echo "OK";
