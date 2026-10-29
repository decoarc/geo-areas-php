<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('method_not_allowed', [], 405);
}

enforceRateLimit(
    $conn,
    'read',
    configInt('RATE_LIMIT_READS_PER_MINUTE', DEFAULT_READS_PER_MINUTE),
    60
);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonError('invalid_json');
}

$areaId = $payload['id'] ?? null;
$coords = $payload['coords'] ?? null;

try {
    if ($areaId) {
        $areaId = (int) $areaId;

        $stmt = $conn->prepare(
            'UPDATE areas
             SET perimeter = ST_Perimeter(coords::geography) / 1000
             WHERE id = ?
             RETURNING id, perimeter'
        );
        $stmt->execute([$areaId]);
        $row = $stmt->fetch();

        if (!$row) {
            jsonError('area_not_found', [], 404);
        }

        echo json_encode([
            'success' => true,
            'id' => (int) $row['id'],
            'perimeter_km' => (float) $row['perimeter'],
        ]);
        exit;
    }

    if (is_array($coords) && count($coords) >= 3) {
        $validation = validateAreaInput('', '', $coords);
        if ($validation !== null) {
            [$code, $params] = $validation;
            jsonError($code, $params);
        }

        $coords_wkt = coordsToWKT($coords);

        $stmt = $conn->prepare(
            'SELECT ST_Perimeter(ST_GeomFromText(?, 4326)::geography) / 1000 AS perimeter_km'
        );
        $stmt->execute([$coords_wkt]);
        $row = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'perimeter_km' => (float) $row['perimeter_km'],
        ]);
        exit;
    }

    jsonError('perimeter_input_required');
} catch (PDOException $e) {
    error_log('save_perimeter: ' . $e->getMessage());
    jsonError('perimeter_failed', [], 500);
}
