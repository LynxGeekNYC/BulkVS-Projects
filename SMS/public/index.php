<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/permissions.php';

$u = require_login();
$dids = user_accessible_dids((int)$u['id']);

if (empty($dids)) {
  http_response_code(403);
  echo "No DIDs assigned to this user.";
  exit;
}

header("Location: /did.php?did_id=" . (int)$dids[0]['id']);
exit;
