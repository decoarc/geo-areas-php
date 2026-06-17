<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

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
