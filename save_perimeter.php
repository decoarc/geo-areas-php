<?php
header('Content-Type: application/json');
require 'connection.php';

/**
 * Calculates the distance between two points on the Earth's surface
 * using Vincenty's inverse formula (ellipsoidal distance)
 * @param float $lat1 Latitude of first point in degrees
 * @param float $lng1 Longitude of first point in degrees
 * @param float $lat2 Latitude of second point in degrees
 * @param float $lng2 Longitude of second point in degrees
 * @return float Distance in meters
 */
function vincentyDistance($lat1, $lng1, $lat2, $lng2) {
    // WGS84 ellipsoid parameters
    $a = 6378137.0; // Semi-major axis in meters
    $f = 1 / 298.257223563; // Flattening
    $b = (1 - $f) * $a; // Semi-minor axis

    // Convert degrees to radians
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δλ = deg2rad($lng2 - $lng1);

    // Reduced latitudes
    $U1 = atan((1 - $f) * tan($φ1));
    $U2 = atan((1 - $f) * tan($φ2));

    $sinU1 = sin($U1);
    $cosU1 = cos($U1);
    $sinU2 = sin($U2);
    $cosU2 = cos($U2);

    $λ = $Δλ;
    $λP = 2 * M_PI;
    $iterationLimit = 100;

    do {
        $sinλ = sin($λ);
        $cosλ = cos($λ);

        $sinσ = sqrt(
            pow($cosU2 * $sinλ, 2) +
            pow($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosλ, 2)
        );

        if ($sinσ == 0) {
            return 0; // Co-incident points
        }

        $cosσ = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosλ;
        $σ = atan2($sinσ, $cosσ);

        $sinα = ($cosU1 * $cosU2 * $sinλ) / $sinσ;
        $cos2α = 1 - pow($sinα, 2);

        if ($cos2α == 0) {
            $cos2σM = 0; // Equatorial line
        } else {
            $cos2σM = $cosσ - (2 * $sinU1 * $sinU2) / $cos2α;
        }

        $C = ($f / 16) * $cos2α * (4 + $f * (4 - 3 * $cos2α));

        $λP = $λ;
        $λ = $Δλ + (1 - $C) * $f * $sinα *
            ($σ + $C * $sinσ * ($cos2σM + $C * $cosσ * (-1 + 2 * pow($cos2σM, 2))));

        $λDiff = abs($λ - $λP);
    } while ($λDiff > 1e-12 && --$iterationLimit > 0);

    if ($iterationLimit == 0) {
        // Formula failed to converge, fallback to Haversine
        return haversineDistance($lat1, $lng1, $lat2, $lng2);
    }

    $u2 = $cos2α * (pow($a, 2) - pow($b, 2)) / pow($b, 2);
    $A = 1 + ($u2 / 16384) * (4096 + $u2 * (-768 + $u2 * (320 - 175 * $u2)));
    $B = ($u2 / 1024) * (256 + $u2 * (-128 + $u2 * (74 - 47 * $u2)));
    $Δσ = $B * $sinσ * (
        $cos2σM + ($B / 4) * (
            $cosσ * (-1 + 2 * pow($cos2σM, 2)) -
            ($B / 6) * $cos2σM * (-3 + 4 * pow($sinσ, 2)) * (-3 + 4 * pow($cos2σM, 2))
        )
    );

    $s = $b * $A * ($σ - $Δσ);

    return $s;
}

/**
 * Fallback to Haversine formula if Vincenty fails
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000; // Earth radius in meters
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lng2 - $lng1);

    $a = pow(sin($Δφ / 2), 2) +
        cos($φ1) * cos($φ2) * pow(sin($Δλ / 2), 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $R * $c;
}

/**
 * Calculates perimeter using Vincenty method
 * @param array $coords Array of coordinates [['lat' => x, 'lng' => y], ...]
 * @return float Perimeter in kilometers
 */
function calculatePerimeter($coords) {
    if (count($coords) < 2) {
        return 0;
    }

    $totalDistance = 0;
    $numPoints = count($coords);

    // Calculate distance between consecutive points
    for ($i = 0; $i < $numPoints; $i++) {
        $current = $coords[$i];
        $next = $coords[($i + 1) % $numPoints]; // Wrap around to close polygon

        $distance = vincentyDistance(
            $current['lat'],
            $current['lng'],
            $next['lat'],
            $next['lng']
        );

        $totalDistance += $distance;
    }

    // Convert from meters to kilometers
    return $totalDistance / 1000;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { 
    echo json_encode(['error'=>'JSON inválido']); 
    exit; 
}

// Pode receber ID da área ou coordenadas diretamente
$areaId = $payload['id'] ?? null;
$coords = $payload['coords'] ?? null;

if ($areaId) {
    // Modo 1: Atualizar perímetro de uma área existente pelo ID
    $areaId = intval($areaId);
    
    // Busca as coordenadas da área
    $query = "SELECT coords FROM areas WHERE id = $areaId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        echo json_encode(['error'=>'Área não encontrada']);
        exit;
    }
    
    $row = mysqli_fetch_assoc($result);
    $wkt = $row['coords'];
    
    // Converte WKT para array de coordenadas
    $wkt = preg_replace('/^POLYGON\(\(/', '', $wkt);
    $wkt = preg_replace('/\)\)$/', '', $wkt);
    $points = explode(', ', $wkt);
    
    $coords = [];
    foreach ($points as $point) {
        $parts = explode(' ', trim($point));
        if (count($parts) == 2) {
            $coords[] = [
                'lng' => floatval($parts[0]),
                'lat' => floatval($parts[1])
            ];
        }
    }
    
    // Remove o último ponto duplicado (fechamento do polígono)
    if (count($coords) > 0 && 
        $coords[0]['lat'] == $coords[count($coords)-1]['lat'] && 
        $coords[0]['lng'] == $coords[count($coords)-1]['lng']) {
        array_pop($coords);
    }
    
} elseif ($coords && is_array($coords) && count($coords) >= 3) {
    // Modo 2: Calcular perímetro a partir de coordenadas fornecidas
    // (sem salvar no banco, apenas retorna o valor)
    $perimeterKm = calculatePerimeter($coords);
    echo json_encode([
        'success' => true,
        'perimeter_km' => $perimeterKm
    ]);
    exit;
} else {
    echo json_encode(['error'=>'Forneça um ID de área ou coordenadas válidas']);
    exit;
}

// Calcula o perímetro
$perimeterKm = calculatePerimeter($coords);

// Atualiza o perímetro no banco
$update_sql = "UPDATE areas 
               SET perimeter = " . floatval($perimeterKm) . "
               WHERE id = $areaId";

if (mysqli_query($conn, $update_sql)) {
    echo json_encode([
        'success' => true,
        'id' => $areaId,
        'perimeter_km' => $perimeterKm
    ]);
} else {
    echo json_encode([
        'error' => 'Erro ao atualizar perímetro: ' . mysqli_error($conn)
    ]);
}
?>

