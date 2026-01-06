<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_login();

$db = db();
$rows = $db->query("SELECT id, name, color FROM tags ORDER BY name")->fetchAll();

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'tags' => $rows]);
