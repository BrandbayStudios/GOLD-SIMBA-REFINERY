<?php
// HANDY NOW one-time configuration: writes ../.env from the encrypted ../.env.enc.
// Needs the 256-bit key (never stored in the repository). Does nothing once .env exists.
$root = dirname(__DIR__);
if (file_exists($root.'/.env')) { http_response_code(404); exit; }
$key = (string) ($_POST['key'] ?? '');
if (!preg_match('/^[0-9a-f]{64}$/', $key) || !is_file($root.'/.env.enc')) { http_response_code(404); exit; }
$blob = base64_decode(trim(file_get_contents($root.'/.env.enc')), true);
$plain = $blob === false ? false : openssl_decrypt(substr($blob, 28), 'aes-256-gcm', hex2bin($key), OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16));
if ($plain === false) { http_response_code(404); exit; }
foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/public', 'storage/app/private', 'bootstrap/cache'] as $d) { @mkdir($root.'/'.$d, 0755, true); }
file_put_contents($root.'/.env', $plain);
@chmod($root.'/.env', 0600);
header('Content-Type: text/plain');
echo "OK PHP ".PHP_VERSION."\n";
