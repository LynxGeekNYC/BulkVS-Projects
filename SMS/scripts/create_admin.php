<?php
require_once __DIR__ . '/../lib/db.php';

$db = db();
$username = 'admin';
$email = 'admin@example.com';
$password = 'ChangeThisNow123!';

$hash = password_hash($password, PASSWORD_DEFAULT);

$db->prepare("INSERT INTO users (email, username, password_hash, role) VALUES (?, ?, ?, 'admin')")
   ->execute([$email, $username, $hash]);

echo "Created admin: $username / $password\n";
