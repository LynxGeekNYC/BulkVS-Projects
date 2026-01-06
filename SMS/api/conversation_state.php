<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login();
$userId = (int)$u['id'];

$conversationId = (int)($_POST['conversation_id'] ?? 0);
$lastSeen = (int)($_POST['last_seen_message_id'] ?? 0);

if ($conversationId <= 0 || $lastSeen <= 0) {
  http_response_code(400);
  echo "Bad request";
  exit;
}

$db = db();

/* Ensure user has access via DID mapping */
$st = $db->prepare("
  SELECT c.did_id
  FROM conversations c
  JOIN user_dids ud ON ud.did_id = c.did_id AND ud.user_id = ? AND ud.can_view=1
  WHERE c.id = ?
  LIMIT 1
");
$st->execute([$userId, $conversationId]);
$row = $st->fetch();
if (!$row) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

$db->prepare("
  INSERT INTO conversation_user_state (conversation_id, user_id, last_seen_message_id, unread_count)
  VALUES (?, ?, ?, 0)
  ON DUPLICATE KEY UPDATE last_seen_message_id=GREATEST(COALESCE(last_seen_message_id,0), VALUES(last_seen_message_id)), unread_count=0
")->execute([$conversationId, $userId, $lastSeen]);

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
