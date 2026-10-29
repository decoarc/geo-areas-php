<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'vendor/autoload.php';
require_once 'geo_helpers.php';

use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;

// Erro de conversão com código estável para o front-end traduzir.
class ConversionException extends Exception
{
    public function __construct(public string $errorCode, public array $params = [])
    {
        parent::__construct($errorCode);
    }
}

$proj4 = new Proj4php();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('method_not_allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['action'])) {
    jsonError('missing_action');
}

$action = $input['action'];

try {
    switch ($action) {
        case 'utm':
            requireFields($input, ['lat', 'lng']);
            [$lat, $lng] = requireLatLng($input['lat'], $input['lng']);
            echo json_encode(convertToUTM($proj4, $lat, $lng));
            break;
        case 'gms':
            requireFields($input, ['lat', 'lng']);
            [$lat, $lng] = requireLatLng($input['lat'], $input['lng']);
            echo json_encode(convertToGMS($lat, $lng));
            break;
        case 'from_utm':
            requireFields($input, ['easting', 'northing', 'zone', 'hemisphere']);
            if (!is_numeric($input['easting']) || !is_numeric($input['northing'])) {
                throw new ConversionException('invalid_coordinate');
            }
            $zone = (int) $input['zone'];
            $hemisphere = strtoupper((string) $input['hemisphere']);
            if ($zone < 1 || $zone > 60 || !in_array($hemisphere, ['N', 'S'], true)) {
                throw new ConversionException('invalid_coordinate');
            }
            echo json_encode(convertFromUTM(
                $proj4,
                (float) $input['easting'],
                (float) $input['northing'],
                $zone,
                $hemisphere
            ));
            break;
        case 'from_gms':
            requireFields($input, ['gms_lat', 'gms_lng']);
            echo json_encode(convertFromGMS((string) $input['gms_lat'], (string) $input['gms_lng']));
            break;
        default:
            jsonError('invalid_action');
    }
} catch (ConversionException $e) {
    jsonError($e->errorCode, $e->params);
} catch (Throwable $e) {
    error_log('coordinate_converter: ' . $e->getMessage());
    jsonError('conversion_failed', [], 500);
}

function requireFields(array $input, array $fields): void
{
    $missing = array_filter($fields, fn ($field) => !isset($input[$field]));
    if ($missing) {
        throw new ConversionException('missing_fields', ['fields' => implode(', ', $missing)]);
    }
}

function requireLatLng($lat, $lng): array
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        throw new ConversionException('invalid_coordinate');
    }
    $lat = (float) $lat;
    $lng = (float) $lng;
    if (abs($lat) > 90 || abs($lng) > 180) {
        throw new ConversionException('coordinate_out_of_bounds');
    }
    return [$lat, $lng];
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
        throw new ConversionException('gms_format', ['value' => $gms]);
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
