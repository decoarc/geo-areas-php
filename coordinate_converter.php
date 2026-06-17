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

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Required: action']);
    exit();
}

$action = $input['action'];

try {
    switch ($action) {
        case 'utm':
            if (!isset($input['lat']) || !isset($input['lng'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Required: lat, lng']);
                exit();
            }
            echo json_encode(convertToUTM($proj4, floatval($input['lat']), floatval($input['lng'])));
            break;
        case 'gms':
            if (!isset($input['lat']) || !isset($input['lng'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Required: lat, lng']);
                exit();
            }
            echo json_encode(convertToGMS(floatval($input['lat']), floatval($input['lng'])));
            break;
        case 'from_utm':
            if (!isset($input['easting']) || !isset($input['northing']) || !isset($input['zone']) || !isset($input['hemisphere'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Required: easting, northing, zone, hemisphere']);
                exit();
            }
            echo json_encode(convertFromUTM(
                $proj4,
                floatval($input['easting']),
                floatval($input['northing']),
                intval($input['zone']),
                strtoupper($input['hemisphere'])
            ));
            break;
        case 'from_gms':
            if (!isset($input['gms_lat']) || !isset($input['gms_lng'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Required: gms_lat, gms_lng']);
                exit();
            }
            echo json_encode(convertFromGMS($input['gms_lat'], $input['gms_lng']));
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use "utm", "gms", "from_utm" or "from_gms"']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conversion error: ' . $e->getMessage()]);
}

function convertToUTM($proj4, $lat, $lng) {
    $zone = floor(($lng + 180) / 6) + 1;
    $hemisphere = $lat >= 0 ? 'N' : 'S';

    $south = $lat < 0 ? ' +south' : '';
    $utmDef = '+proj=utm +zone=' . $zone . $south . ' +datum=WGS84 +units=m +no_defs';

    $wgs84 = new Proj('EPSG:4326', $proj4);
    $utm = new Proj($utmDef, $proj4);

    $point = new Point($lng, $lat);
    $point->setProjection($wgs84);

    $utmPoint = $proj4->transform($wgs84, $utm, $point);

    return [
        'easting' => round($utmPoint->x),
        'northing' => round($utmPoint->y),
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

function convertFromUTM($proj4, $easting, $northing, $zone, $hemisphere) {
    // UTM padrão no hemisfério sul usa northing positivo (+south / false northing).
    // Saídas antigas sem +south vinham com northing negativo — aceitar os dois formatos.
    $useSouth = $hemisphere === 'S' && $northing >= 0;
    $south = $useSouth ? ' +south' : '';
    $utmDef = '+proj=utm +zone=' . $zone . $south . ' +datum=WGS84 +units=m +no_defs';

    $wgs84 = new Proj('EPSG:4326', $proj4);
    $utm = new Proj($utmDef, $proj4);

    $point = new Point($easting, $northing);
    $point->setProjection($utm);

    $wgs84Point = $proj4->transform($utm, $wgs84, $point);

    return [
        'lat' => round($wgs84Point->y, 8),
        'lng' => round($wgs84Point->x, 8),
    ];
}

function convertFromGMS($gmsLat, $gmsLng) {
    return [
        'lat' => parseGMSComponent($gmsLat),
        'lng' => parseGMSComponent($gmsLng),
    ];
}

function parseGMSComponent($gms) {
    $gms = trim($gms);
    if (!preg_match('/^(-?\d+(?:\.\d+)?)\s*°?\s*(\d+(?:\.\d+)?)\s*[\'′]?\s*(\d+(?:\.\d+)?)\s*["″]?\s*([NSEW])$/iu', $gms, $matches)) {
        throw new Exception('Formato GMS inválido: ' . $gms);
    }

    $degrees = floatval($matches[1]);
    $minutes = floatval($matches[2]);
    $seconds = floatval($matches[3]);
    $direction = strtoupper($matches[4]);

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($direction === 'S' || $direction === 'W') {
        $decimal *= -1;
    }

    return round($decimal, 8);
}
?>