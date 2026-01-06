<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login();
$userId = (int)$u['id'];

$sinceId = (int)($_GET['since_id'] ?? 0);

$db = db();
$st = $db->prepare("
  SELECT
    m.id,
    m.created_at,
    m.body,
    c.id AS conversation_id,
    c.remote_phone,
    d.did,
    d.label,
    COALESCE(ct.display_name, c.remote_phone) AS contact_name
  FROM messages m
  JOIN conversations c ON c.id = m.conversation_id
  JOIN dids d ON d.id = c.did_id
  JOIN user_dids ud ON ud.did_id = d.id AND ud.user_id = ? AND ud.can_view=1
  LEFT JOIN contacts ct ON ct.id = c.contact_id
  WHERE m.direction='in'
    AND m.id > ?
  ORDER BY m.id ASC
  LIMIT 50
");
$st->execute([$userId, $sinceId]);
$rows = $st->fetchAll();

header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'latest_id' => !empty($rows) ? (int)$rows[count($rows)-1]['id'] : $sinceId,
  'messages' => $rows
]);
