<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

enforceRateLimit(
    $conn,
    'read',
    configInt('RATE_LIMIT_READS_PER_MINUTE', DEFAULT_READS_PER_MINUTE),
    60
);

$sql = 'SELECT id, name, description, ST_AsText(coords) AS coords, area_poly, perimeter, created_at
        FROM areas
        ORDER BY created_at DESC';

$stmt = $conn->query($sql);
$areas = [];

while ($row = $stmt->fetch()) {
    $row['coords'] = json_encode(wktToCoords($row['coords']));
    $areas[] = $row;
}

echo json_encode($areas);
