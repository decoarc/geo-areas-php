<?php
header('Content-Type: application/json');
require 'connection.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { echo json_encode(['error'=>'JSON inválido']); exit; }

$name = mysqli_real_escape_string($conn, $payload['name'] ?? '');
$desc = mysqli_real_escape_string($conn, $payload['description'] ?? '');
$coords = $payload['coords'] ?? null;

if (!is_array($coords) || count($coords) < 3) {
    echo json_encode(['error'=>'coords inválidos (>=3 points)']);
    exit;
}

$coords_json = mysqli_real_escape_string($conn, json_encode($coords));

$sql = "INSERT INTO areas (name, description, coords) VALUES ('$name', '$desc', '$coords_json')";
if (mysqli_query($conn, $sql)) {
    echo json_encode(['success'=>true, 'id'=>mysqli_insert_id($conn)]);
} else {
    echo json_encode(['error'=>mysqli_error($conn)]);
}
?>