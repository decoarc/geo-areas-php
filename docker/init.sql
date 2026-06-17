CREATE EXTENSION IF NOT EXISTS postgis;

CREATE TABLE IF NOT EXISTS areas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    coords GEOMETRY(Polygon, 4326) NOT NULL,
    area_poly NUMERIC(20, 6),
    perimeter NUMERIC(20, 6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_areas_coords ON areas USING GIST (coords);
