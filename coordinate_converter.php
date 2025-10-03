<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'vendor/autoload.php';

use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;

$proj4 = new Proj4php();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || !isset($input['lat']) || !isset($input['lng'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Required: action, lat, lng']);
    exit();
}

$action = $input['action'];
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);

try {
    switch ($action) {
        case 'utm':
            echo json_encode(convertToUTM($proj4, $lat, $lng));
            break;
        case 'gms':
            echo json_encode(convertToGMS($lat, $lng));
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use "utm" or "gms"']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conversion error: ' . $e->getMessage()]);
}

function convertToUTM($proj4, $lat, $lng) {
    // Calculate UTM zone
    $zone = floor(($lng + 180) / 6) + 1;
    $hemisphere = $lat >= 0 ? 'N' : 'S';
    
    // Create UTM projection definition based on zone
    $utmDef = "+proj=utm +zone=" . $zone . " +datum=WGS84 +units=m +no_defs";
    
    // Create projections
    $wgs84 = new Proj('EPSG:4326', $proj4);
    $utm = new Proj($utmDef, $proj4);
    
    // Create point in WGS84
    $point = new Point($lng, $lat);
    $point->setProjection($wgs84);
    
    // Transform to UTM
    $utmPoint = $proj4->transform($wgs84, $utm, $point);
    
    // Apply false easting and northing adjustments for UTM
    $easting = round($utmPoint->x);
    $northing = round($utmPoint->y);
    
    return [
        'easting' => $easting,
        'northing' => $northing,
        'zone' => $zone,
        'hemisphere' => $hemisphere
    ];
}

function convertToGMS($lat, $lng) {
    // Convert to absolute values for calculation
    $latDeg = abs($lat);
    $lngDeg = abs($lng);
    
    // Latitude conversion
    $latD = floor($latDeg);
    $latM = floor(($latDeg - $latD) * 60);
    $latS = round((($latDeg - $latD) * 60 - $latM) * 60, 2);
    
    // Longitude conversion
    $lngD = floor($lngDeg);
    $lngM = floor(($lngDeg - $lngD) * 60);
    $lngS = round((($lngDeg - $lngD) * 60 - $lngM) * 60, 2);
    
    // Determine direction
    $latDir = $lat >= 0 ? "N" : "S";
    $lngDir = $lng >= 0 ? "E" : "W";
    
    return [
        'lat' => $latD . '°' . $latM . "'" . $latS . '"' . $latDir,
        'lng' => $lngD . '°' . $lngM . "'" . $lngS . '"' . $lngDir
    ];
}
?>