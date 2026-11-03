<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Rome');
function config(): array {
    static $config;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config.php';
        if (!is_file($path)) throw new RuntimeException('Configuration missing. Follow README.md.');
        $config = require $path;
    }
    return $config;
}
function db(): PDO {
    static $pdo;
    if (!$pdo) $pdo = new PDO(config()['dsn'], config()['username'], config()['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    return $pdo;
}
function rows(string $sql, array $params = []): array { $s = db()->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function run(string $sql, array $params = []): void { $s = db()->prepare($sql); $s->execute($params); }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function session_start_secure(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    session_name('inborgo_admin');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/admin','secure'=>config()['secure_cookies'],'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid form token. Reload and try again.'); } }
function redirect(string $url): void { header('Location: '.$url); exit; }
function security_headers(): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY'); header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://tile.openstreetmap.org; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
}
set_exception_handler(function (Throwable $error): void {
    error_log((string)$error); http_response_code(500);
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) { header('Content-Type: application/json'); echo json_encode(['error'=>'Service unavailable. Please try again later.']); }
    else echo 'Service unavailable. Check server configuration and logs.';
});
security_headers();
