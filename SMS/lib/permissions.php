<?php
require_once __DIR__ . '/db.php';

function user_accessible_dids(int $userId): array {
  $db = db();
  $st = $db->prepare("
    SELECT d.id, d.did, d.label, ud.can_send, ud.can_view
    FROM user_dids ud
    JOIN dids d ON d.id = ud.did_id
    WHERE ud.user_id = ? AND d.is_active=1 AND ud.can_view=1
    ORDER BY COALESCE(d.label, d.did), d.did
  ");
  $st->execute([$userId]);
  return $st->fetchAll();
}

function require_user_did_access(int $userId, int $didId, bool $requireSend=false): array {
  $db = db();
  $st = $db->prepare("
    SELECT d.id, d.did, d.label, ud.can_send, ud.can_view
    FROM user_dids ud
    JOIN dids d ON d.id = ud.did_id
    WHERE ud.user_id=? AND ud.did_id=? AND ud.can_view=1 AND d.is_active=1
    LIMIT 1
  ");
  $st->execute([$userId, $didId]);
  $row = $st->fetch();
  if (!$row) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
  if ($requireSend && (int)$row['can_send'] !== 1) {
    http_response_code(403);
    echo "Send not permitted";
    exit;
  }
  return $row;
}
