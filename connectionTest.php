<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/connection.php';

if (!isset($conn)) {
    die("❌ Variable \$conn not defined. Check connection.php\n");
}

echo "✅ Connected to PostgreSQL successfully!<br>";

$stmt = $conn->query('SELECT NOW() AS now, PostGIS_Version() AS postgis_version');
$row = $stmt->fetch();

echo "PostgreSQL Server Date/Time: " . ($row['now'] ?? 'desconhecido') . "<br>";
echo "PostGIS Version: " . ($row['postgis_version'] ?? 'extensão não encontrada');
