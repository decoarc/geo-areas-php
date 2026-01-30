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
if (!$payload) { 
    echo json_encode(['error'=>'JSON inválido']); 
    exit; 
}

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

// Calcula a área usando ST_Area do MySQL
// ST_Area com SRID 4326 - dividindo por 100 para converter para km²
$sql = "INSERT INTO areas (name, description, coords) 
        VALUES ('$name', '$desc', '$coords_wkt_escaped')";

if (mysqli_query($conn, $sql)) {
    $id = mysqli_insert_id($conn);
    
    // Calcula a área usando ST_Area - dividindo por 100 para converter para km²
    // Usando a mesma fórmula que você testou no MySQL
    $update_sql = "UPDATE areas 
                   SET area_poly = CAST(
                       ST_Area(
                           ST_SRID(
                               ST_GeomFromText(coords),
                               4326
                           )
                       ) / 100
                       AS DECIMAL(20, 6)
                   )
                   WHERE id = $id";
    
    if (mysqli_query($conn, $update_sql)) {
        // Busca a área calculada para retornar na resposta
        $area_query = "SELECT area_poly FROM areas WHERE id = $id";
        $area_result = mysqli_query($conn, $area_query);
        $area_row = mysqli_fetch_assoc($area_result);
        $area_km2 = $area_row['area_poly'] ?? 0;
        
        echo json_encode(['success'=>true, 'id'=>$id, 'area_km2'=>$area_km2]);
    } else {
        // Se o cálculo da área falhar, ainda retorna sucesso (área será NULL)
        echo json_encode(['success'=>true, 'id'=>$id, 'area_km2'=>0, 'warning'=>'Área não calculada: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['error'=>mysqli_error($conn)]);
}
?>