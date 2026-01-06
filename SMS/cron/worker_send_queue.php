<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/bulkvs_client.php';
require_once __DIR__ . '/../lib/queue.php';

$app = require __DIR__ . '/../config/app.php';
$db = db();
$client = new BulkVSClient($app['bulkvs']);

$db->beginTransaction();

$st = $db->prepare("
  SELECT sq.*, c.did_id, c.remote_phone, d.did
  FROM send_queue sq
  JOIN conversations c ON c.id = sq.conversation_id
  JOIN dids d ON d.id = c.did_id
  WHERE sq.locked_at IS NULL
    AND sq.next_attempt_at <= NOW()
    AND sq.tries < sq.max_tries
  ORDER BY sq.next_attempt_at ASC
  LIMIT 1
  FOR UPDATE
");
$st->execute();
$job = $st->fetch();

if (!$job) {
  $db->commit();
  exit;
}

$db->prepare("UPDATE send_queue SET locked_at=NOW() WHERE id=?")->execute([(int)$job['id']]);
$db->commit();

$jobId = (int)$job['id'];
$convoId = (int)$job['conversation_id'];
$fromDid = (string)$job['did'];
$to = (string)$job['remote_phone'];
$body = (string)($job['body'] ?? '');

$mediaUrls = [];
$mediaLocal = $job['media_local_paths'] ?? '';
if ($mediaLocal) {
  $decoded = json_decode($mediaLocal, true);
  if (is_array($decoded)) {
    foreach ($decoded as $u) {
      if (is_string($u) && str_starts_with($u, 'http')) $mediaUrls[] = $u;
    }
  }
}

$res = $client->sendMessage($fromDid, $to, $body, $mediaUrls);

$db = db();
if ($res['ok']) {
  $providerRef = null;
  if (!empty($res['data']) && is_array($res['data'])) {
    $providerRef = $res['data']['RefId'] ?? $res['data']['refId'] ?? null;
  }

  $db->prepare("
    INSERT INTO messages (conversation_id, direction, body, status, provider_ref, created_by_user_id, created_at)
    VALUES (?, 'out', ?, 'sent', ?, ?, NOW())
  ")->execute([$convoId, $body, $providerRef, $job['created_by_user_id'] ?? null]);

  $db->prepare("UPDATE conversations SET last_message_at=NOW() WHERE id=?")->execute([$convoId]);
  $db->prepare("DELETE FROM send_queue WHERE id=?")->execute([$jobId]);

} else {
  $tries = (int)$job['tries'] + 1;
  $httpCode = (int)($res['http'] ?? 0);
  $err = (string)($res['error'] ?? ($res['raw'] ?? 'Send failed'));

  $delay = compute_backoff_seconds($tries);

  $db->prepare("
    UPDATE send_queue
    SET tries=?,
        last_http_code=?,
        last_error=?,
        next_attempt_at=DATE_ADD(NOW(), INTERVAL ? SECOND),
        locked_at=NULL
    WHERE id=?
  ")->execute([$tries, $httpCode, mb_substr($err, 0, 255), $delay, $jobId]);

  if ($tries >= (int)$job['max_tries']) {
    $db->prepare("
      INSERT INTO messages (conversation_id, direction, body, status, error_text, created_by_user_id, created_at)
      VALUES (?, 'out', ?, 'failed', ?, ?, NOW())
    ")->execute([$convoId, $body, mb_substr($err, 0, 255), $job['created_by_user_id'] ?? null]);
  }
}
