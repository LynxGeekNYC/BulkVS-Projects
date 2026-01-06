<?php
final class BulkVSClient {
  private string $providerUrl;
  private string $authHeader;
  private int $timeout;

  public function __construct(array $cfg) {
    $this->providerUrl = $cfg['provider_url'];
    $this->authHeader  = $cfg['basic_auth_header'];
    $this->timeout     = (int)($cfg['timeout_sec'] ?? 20);
  }

  public function sendMessage(string $fromDid, string $to, ?string $text, array $mediaUrls = []): array {
    $payload = [
      'From' => $fromDid,
      'To' => $to,
      'Message' => $text ?? '',
    ];

    if (!empty($mediaUrls)) {
      $payload['MediaUrls'] = array_values($mediaUrls);
      $payload['MediaUrl']  = $mediaUrls[0];
    }

    $ch = curl_init($this->providerUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => [
        'Authorization: ' . $this->authHeader,
        'Content-Type: application/json',
        'Accept: application/json',
      ],
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
      CURLOPT_TIMEOUT        => $this->timeout,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
      return ['ok' => false, 'http' => $code, 'error' => $err ?: 'cURL error'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
      return ['ok' => ($code >= 200 && $code < 300), 'http' => $code, 'raw' => $raw];
    }

    return ['ok' => ($code >= 200 && $code < 300), 'http' => $code, 'data' => $json];
  }
}
