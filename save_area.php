<?php
header('Content-Type: application/json');
require 'connection.php';

// Converte array JSON para formato WKT POLYGON
function coordsToWKT($coords) {
    $points = [];
    foreach ($coords as $coord) {
        $lng = floatval($coord['lng']);
        $lat = floatval($coord['lat']);
        $points[] = "$lng $lat";
    }
    // Fecha o polígono (repetir primeiro ponto)
    if (count($points) > 0) {
        $points[] = $points[0];
    }
    return 'POLYGON((' . implode(', ', $points) . '))';
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { echo json_encode(['error'=>'JSON inválido']); exit; }

$name = mysqli_real_escape_string($conn, $payload['name'] ?? '');
$desc = mysqli_real_escape_string($conn, $payload['description'] ?? '');
$coords = $payload['coords'] ?? null;

if (!is_array($coords) || count($coords) < 3) {
    echo json_encode(['error'=>'coords inválidos (>=3 points)']);
    exit;
}

// Converte para WKT antes de salvar
$coords_wkt = coordsToWKT($coords);
$coords_wkt_escaped = mysqli_real_escape_string($conn, $coords_wkt);

$sql = "INSERT INTO areas (name, description, coords) VALUES ('$name', '$desc', '$coords_wkt_escaped')";
if (mysqli_query($conn, $sql)) {
    echo json_encode(['success'=>true, 'id'=>mysqli_insert_id($conn)]);
} else {
    echo json_encode(['error'=>mysqli_error($conn)]);
}
?>