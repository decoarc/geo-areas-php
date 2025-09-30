<?php

// read the .env file
$config = parse_ini_file(__DIR__ . '/.env');

// Connect to Local MySQL

$host = $config['DB_HOST'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];
$db   = $config['DB_NAME'];

// connect to the database
$conn = new mysqli($host, $user, $pass, $db);


?>