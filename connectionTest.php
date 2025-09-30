<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/connection.php'; 

if (!isset($conn)) {
    die("❌ Variável \$conn não definida. Verifique connection.php\n");
}


if ($conn->connect_error) {
    die("❌ Erro de conexão MySQL: (" . $conn->connect_errno . ") " . $conn->connect_error);
}

echo "✅ Conectado ao MySQL com sucesso!<br>";


if ($result = $conn->query("SELECT NOW() AS now")) {
    $row = $result->fetch_assoc();
    echo "Data/hora do servidor MySQL: " . ($row['now'] ?? 'desconhecido');
    $result->free();
} else {
    echo "❌ Erro ao executar query: " . $conn->error;
}


$conn->close();
