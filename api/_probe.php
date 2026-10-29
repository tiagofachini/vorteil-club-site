<?php
header('Content-Type: application/json; charset=utf-8');
if (($_GET['k'] ?? '') !== '7ec18a9bddc51faec946050b') { http_response_code(404); exit; }
echo json_encode([
  'php' => PHP_VERSION,
  'sapi' => PHP_SAPI,
  'mail_exists' => function_exists('mail'),
  'disabled' => ini_get('disable_functions'),
  'sendmail_path' => ini_get('sendmail_path'),
  'host' => gethostname(),
  'server_addr' => $_SERVER['SERVER_ADDR'] ?? null,
  'docroot' => $_SERVER['DOCUMENT_ROOT'] ?? null,
  'software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
]);
