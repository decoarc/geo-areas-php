<?php

$config = parse_ini_file(__DIR__ . '/.env');
if ($config === false) {
    throw new RuntimeException('Não foi possível ler o arquivo .env');
}

$host = $config['DB_HOST'] ?? 'localhost';
$port = $config['DB_PORT'] ?? '5432';
$user = $config['DB_USER'] ?? '';
$pass = $config['DB_PASS'] ?? '';
$db   = $config['DB_NAME'] ?? '';

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
