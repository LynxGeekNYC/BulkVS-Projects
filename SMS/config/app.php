<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'name' => 'bulkvs_sms',
    'user' => 'bulkvs_user',
    'pass' => 'change_me',
  ],

  'app' => [
    // Used to build public links for MMS media retrieval
    // Example: https://sms.yourdomain.com
    'base_url' => 'https://yourdomain.com',

    // Style only, this just makes outbound bubbles “iMessage-like”
    'use_blue_bubbles' => true,
  ],

  'bulkvs' => [
    // BulkVS message send endpoint
    'provider_url' => 'https://portal.bulkvs.com/api/v1.0/messageSend?pbx=3cx',
    // From BulkVS portal, REST API Basic Auth Header value
    'basic_auth_header' => 'Basic REPLACE_WITH_BASE64',
    'timeout_sec' => 20,
  ],

  'webhooks' => [
    'shared_secret' => 'replace-with-long-random-string',
    'allowed_ips' => [], // optional allowlist
  ],

  'media' => [
    'storage_dir' => __DIR__ . '/../storage/media',
    'max_upload_bytes' => 8 * 1024 * 1024,
    'max_remote_fetch_bytes' => 10 * 1024 * 1024,
    'allowed_mime' => [
      'image/jpeg',
      'image/png',
      'image/gif',
      'image/webp'
    ],
  ],
];
