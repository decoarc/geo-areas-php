<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/connection.php'; 

if (!isset($conn)) {
    die("❌ Variable \$conn not defined. Check connection.php\n");
}


if ($conn->connect_error) {
    die("❌ MySQL connection error: (" . $conn->connect_errno . ") " . $conn->connect_error);
}

echo "✅ Connected to MySQL successfully!<br>";


if ($result = $conn->query("SELECT NOW() AS now")) {
    $row = $result->fetch_assoc();
    echo "MySQL Server Date/Time: " . ($row['now'] ?? 'desconhecido');
    $result->free();
} else {
    echo "❌ Error executing query: " . $conn->error;
}


$conn->close();
