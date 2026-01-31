<?php
header('Content-Type: application/json');
require 'connection.php';

// Converte WKT POLYGON para array JSON no formato [{lat: x, lng: y}, ...]
function wktToCoords($wkt) {
    // Remove "POLYGON((" do início e "))" do final
    $wkt = preg_replace('/^POLYGON\(\(/', '', $wkt);
    $wkt = preg_replace('/\)\)$/', '', $wkt);
    
    // Divide por vírgulas
    $points = explode(', ', $wkt);
    
    $coords = [];
    foreach ($points as $point) {
        $parts = explode(' ', trim($point));
        if (count($parts) == 2) {
            $lng = floatval($parts[0]);
            $lat = floatval($parts[1]);
            $coords[] = ['lat' => $lat, 'lng' => $lng];
        }
    }
    
    // Remove o último ponto duplicado (fechamento do polígono)
    if (count($coords) > 0 && 
        $coords[0]['lat'] == $coords[count($coords)-1]['lat'] && 
        $coords[0]['lng'] == $coords[count($coords)-1]['lng']) {
        array_pop($coords);
    }
    
    return $coords;
}

$sql = "SELECT id, name, description, coords, area_poly, perimeter, created_at FROM areas ORDER BY created_at DESC";
$res = mysqli_query($conn, $sql);
$areas = [];
while ($row = mysqli_fetch_assoc($res)) {
    // Converte WKT para JSON para compatibilidade com o frontend
    $row['coords'] = json_encode(wktToCoords($row['coords']));
    $areas[] = $row;
}
echo json_encode($areas);
?>