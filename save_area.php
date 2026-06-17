<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$name = $payload['name'] ?? '';
$desc = $payload['description'] ?? '';
$coords = $payload['coords'] ?? null;

if (!is_array($coords) || count($coords) < 3) {
    echo json_encode(['error' => 'coords inválidos (>=3 points)']);
    exit;
}

$coords_wkt = coordsToWKT($coords);

try {
    $stmt = $conn->prepare(
        'INSERT INTO areas (name, description, coords, area_poly)
         VALUES (?, ?, ST_GeomFromText(?, 4326),
                 ST_Area(ST_GeomFromText(?, 4326)::geography) / 1000000)
         RETURNING id, area_poly'
    );
    $stmt->execute([$name, $desc, $coords_wkt, $coords_wkt]);
    $row = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'id' => (int) $row['id'],
        'area_km2' => (float) $row['area_poly'],
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
