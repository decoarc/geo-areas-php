<?php

function coordsToWKT(array $coords): string
{
    $points = [];
    foreach ($coords as $coord) {
        $lng = floatval($coord['lng']);
        $lat = floatval($coord['lat']);
        $points[] = "{$lng} {$lat}";
    }

    if (count($points) > 0) {
        $points[] = $points[0];
    }

    return 'POLYGON((' . implode(', ', $points) . '))';
}

function wktToCoords(string $wkt): array
{
    $wkt = preg_replace('/^POLYGON\(\(/', '', $wkt);
    $wkt = preg_replace('/\)\)$/', '', $wkt);

    $points = preg_split('/,\s*/', $wkt);
    $coords = [];

    foreach ($points as $point) {
        $parts = preg_split('/\s+/', trim($point));
        if (count($parts) === 2) {
            $coords[] = [
                'lat' => floatval($parts[1]),
                'lng' => floatval($parts[0]),
            ];
        }
    }

    if (
        count($coords) > 0
        && $coords[0]['lat'] == $coords[count($coords) - 1]['lat']
        && $coords[0]['lng'] == $coords[count($coords) - 1]['lng']
    ) {
        array_pop($coords);
    }

    return $coords;
}
