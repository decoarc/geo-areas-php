-- PostgreSQL + PostGIS schema for geo-areas-php
-- Run: psql -U postgres -f schema.sql

CREATE DATABASE geo_mapa_app;

\c geo_mapa_app

CREATE EXTENSION IF NOT EXISTS postgis;

CREATE TABLE areas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    coords GEOMETRY(Polygon, 4326) NOT NULL,
    area_poly NUMERIC(20, 6),
    perimeter NUMERIC(20, 6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_areas_coords ON areas USING GIST (coords);
CREATE INDEX idx_areas_created_at ON areas (created_at);

CREATE TABLE rate_limits (
    ip_hash TEXT NOT NULL,
    bucket TEXT NOT NULL,
    window_start BIGINT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (ip_hash, bucket, window_start)
);

CREATE INDEX idx_rate_limits_window ON rate_limits (window_start);
