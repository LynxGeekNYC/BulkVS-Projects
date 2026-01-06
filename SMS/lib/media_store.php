<?php
require_once __DIR__ . '/security.php';

function ensure_media_dir(string $dir): void {
  if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
  }
}

function sha256_file_safe(string $path): ?string {
  $hash = hash_file('sha256', $path);
  return $hash ?: null;
}

function store_uploaded_images(array $mediaCfg, array $files): array {
  $dir = $mediaCfg['storage_dir'];
  $maxBytes = (int)$mediaCfg['max_upload_bytes'];
  $allowed = $mediaCfg['allowed_mime'] ?? [];

  ensure_media_dir($dir);

  $stored = [];
  if (empty($files['name']) || !is_array($files['name'])) return $stored;

  for ($i = 0; $i < count($files['name']); $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

    $tmp = $files['tmp_name'][$i];
    $size = (int)($files['size'][$i] ?? 0);
    if ($size <= 0 || $size > $maxBytes) continue;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) continue;

    $ext = match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/gif'  => 'gif',
      'image/webp' => 'webp',
      default      => 'bin',
    };

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = rtrim($dir, '/') . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) continue;

    $stored[] = [
      'path' => $dest,
      'mime' => $mime,
      'bytes' => $size,
      'sha256' => sha256_file_safe($dest),
    ];
  }

  return $stored;
}

function download_and_store_media(array $mediaCfg, string $url): array {
  $dir = $mediaCfg['storage_dir'];
  $maxBytes = (int)$mediaCfg['max_remote_fetch_bytes'];
  $allowed = $mediaCfg['allowed_mime'] ?? [];

  ensure_media_dir($dir);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
  ]);
  $data = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);

  if ($data === false || $code < 200 || $code >= 300) {
    return ['ok' => false, 'error' => $err ?: ("HTTP " . $code)];
  }

  if (strlen($data) > $maxBytes) {
    return ['ok' => false, 'error' => 'Remote media too large'];
  }

  $mime = trim(explode(';', $ctype)[0]);
  if (!in_array($mime, $allowed, true)) {
    return ['ok' => false, 'error' => 'Disallowed mime ' . $mime];
  }

  $ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'bin',
  };

  $name = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = rtrim($dir, '/') . '/' . $name;

  if (file_put_contents($dest, $data) === false) {
    return ['ok' => false, 'error' => 'Failed to write file'];
  }

  return [
    'ok' => true,
    'path' => $dest,
    'mime' => $mime,
    'bytes' => strlen($data),
    'sha256' => sha256_file_safe($dest),
  ];
}
