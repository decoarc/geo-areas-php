<?php
header('Content-Type: application/json');
require 'connection.php';
require 'geo_helpers.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['error' => 'JSON inválido']);
    exit;
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
            echo json_encode(['error' => 'Área não encontrada']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'id' => (int) $row['id'],
            'perimeter_km' => (float) $row['perimeter'],
        ]);
        exit;
    }

    if ($coords && is_array($coords) && count($coords) >= 3) {
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

    echo json_encode(['error' => 'Forneça um ID de área ou coordenadas válidas']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao calcular perímetro: ' . $e->getMessage()]);
}
