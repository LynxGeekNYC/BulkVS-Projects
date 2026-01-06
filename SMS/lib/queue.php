<?php
function compute_backoff_seconds(int $tries): int {
  $base = max(2, (int)pow(2, min($tries, 10)));
  $cap = 15 * 60;
  $sec = min($base, $cap);
  $jitter = random_int(0, 3);
  return $sec + $jitter;
}
