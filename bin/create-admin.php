<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/src/bootstrap.php';
$username = trim($argv[1] ?? '');
if (!$username || strlen($username)>190) exit("Usage: php bin/create-admin.php email-or-username\nPassword is read from stdin (minimum 12 characters).\n");
fwrite(STDERR, 'Password (input is visible; use a private terminal): ');
$password = rtrim(fgets(STDIN), "\r\n");
if (strlen($password)<12) exit("Password must contain at least 12 characters.\n");
run('INSERT INTO users (username,password_hash) VALUES (?,?)', [$username,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrator created. Sign in at /admin/.\n";
