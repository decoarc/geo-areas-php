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
