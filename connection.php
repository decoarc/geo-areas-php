<?php

$envFile = __DIR__ . '/.env';
$config = is_readable($envFile) ? parse_ini_file($envFile) : [];
if ($config === false) {
    $config = [];
}

$host = getenv('DB_HOST') ?: ($config['DB_HOST'] ?? 'localhost');
$port = getenv('DB_PORT') ?: ($config['DB_PORT'] ?? '5432');
$user = getenv('DB_USER') ?: ($config['DB_USER'] ?? '');
$pass = getenv('DB_PASS') ?: ($config['DB_PASS'] ?? '');
$db   = getenv('DB_NAME') ?: ($config['DB_NAME'] ?? '');

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
