<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('method_not_allowed', [], 405);
}

// Conta a tentativa antes de validar, para que requisições inválidas também contem.
enforceRateLimit(
    $conn,
    'write',
    configInt('RATE_LIMIT_WRITES_PER_HOUR', DEFAULT_WRITES_PER_HOUR),
    3600
);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonError('invalid_json');
}

$name = $payload['name'] ?? '';
$desc = $payload['description'] ?? '';
$coords = $payload['coords'] ?? null;

if (!is_string($name) || !is_string($desc)) {
    jsonError('text_required');
}

if (!is_array($coords)) {
    jsonError('too_few_points');
}

$validation = validateAreaInput($name, $desc, $coords) ?? validateContent($name, $desc);
if ($validation !== null) {
    [$code, $params] = $validation;
    jsonError($code, $params);
}

$coords_wkt = coordsToWKT($coords);
$limit = areaLimit();

if ($limit < 1) {
    jsonError('area_limit', ['max' => $limit], 403);
}

try {
    $conn->beginTransaction();

    // Serializa as gravações concorrentes para o teto e a poda serem respeitados.
    $conn->query("SELECT pg_advisory_xact_lock(hashtext('areas_limit'))");

    purgeExpiredAreas($conn);
    makeRoomForArea($conn, $limit);

    $stmt = $conn->prepare(
        'INSERT INTO areas (name, description, coords, area_poly)
         VALUES (?, ?, ST_GeomFromText(?, 4326),
                 ST_Area(ST_GeomFromText(?, 4326)::geography) / 1000000)
         RETURNING id, area_poly'
    );
    $stmt->execute([$name, $desc, $coords_wkt, $coords_wkt]);
    $row = $stmt->fetch();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'id' => (int) $row['id'],
        'area_km2' => (float) $row['area_poly'],
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('save_area: ' . $e->getMessage());
    jsonError('save_failed', [], 500);
}
