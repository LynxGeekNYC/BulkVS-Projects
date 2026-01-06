<?php
require_once __DIR__ . '/../lib/db.php';

$db = db();
$dids = require __DIR__ . '/../config/dids.php';

foreach ($dids as $d) {
  $did = preg_replace('/\D+/', '', (string)$d['did']);
  $label = $d['label'] ?? null;
  if (!$did) continue;

  $st = $db->prepare("INSERT INTO dids (did, label, is_active) VALUES (?, ?, 1)
                      ON DUPLICATE KEY UPDATE label=VALUES(label), is_active=1");
  $st->execute([$did, $label]);
}

echo "Seeded DIDs.\n";
