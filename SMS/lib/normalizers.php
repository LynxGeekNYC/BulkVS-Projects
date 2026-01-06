<?php
function normalize_phone(string $s): string {
  $digits = preg_replace('/\D+/', '', $s);
  if ($digits === null) return '';
  if (strlen($digits) === 10) return '1' . $digits;
  return $digits;
}

function extract_media_urls(array $payload): array {
  $urls = [];

  if (!empty($payload['MediaUrl']) && is_string($payload['MediaUrl'])) $urls[] = $payload['MediaUrl'];

  if (!empty($payload['MediaUrls']) && is_array($payload['MediaUrls'])) {
    foreach ($payload['MediaUrls'] as $u) {
      if (is_string($u) && $u !== '') $urls[] = $u;
    }
  }

  for ($i = 0; $i < 10; $i++) {
    $k = 'MediaUrl' . $i;
    if (!empty($payload[$k]) && is_string($payload[$k])) $urls[] = $payload[$k];
  }

  if (!empty($payload['Attachments']) && is_array($payload['Attachments'])) {
    foreach ($payload['Attachments'] as $a) {
      if (is_string($a)) $urls[] = $a;
      if (is_array($a) && !empty($a['url']) && is_string($a['url'])) $urls[] = $a['url'];
    }
  }

  $urls = array_values(array_unique(array_filter($urls)));
  return $urls;
}
