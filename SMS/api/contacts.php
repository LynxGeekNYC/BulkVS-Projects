<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login();
csrf_verify();

$db = db();
$contactId = (int)($_POST['contact_id'] ?? 0);
$displayName = trim((string)($_POST['display_name'] ?? ''));
$tagIds = $_POST['tag_ids'] ?? [];

if ($contactId <= 0) {
  http_response_code(400);
  echo "Bad request";
  exit;
}

/* Update name */
$db->prepare("UPDATE contacts SET display_name=? WHERE id=?")->execute([$displayName !== '' ? $displayName : null, $contactId]);

/* Replace tags */
$db->prepare("DELETE FROM contact_tags WHERE contact_id=?")->execute([$contactId]);

if (is_array($tagIds)) {
  $ins = $db->prepare("INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)");
  foreach ($tagIds as $tid) {
    $tid = (int)$tid;
    if ($tid > 0) $ins->execute([$contactId, $tid]);
  }
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
