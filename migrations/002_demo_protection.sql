-- Migração 002: proteção da demo (rate limit, índice de data e de janela).
--
-- Idempotente: pode ser executada mais de uma vez sem efeito colateral.
-- Não altera nem apaga linhas de `areas`.
-- Pré-requisito: a tabela `areas` já existe (versão anterior do projeto).
--
-- Aplicação recomendada (com backup antes):
--   psql "$DATABASE_URL" -f migrations/002_demo_protection.sql

BEGIN;

CREATE TABLE IF NOT EXISTS rate_limits (
    ip_hash TEXT NOT NULL,
    bucket TEXT NOT NULL,
    window_start BIGINT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (ip_hash, bucket, window_start)
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_window ON rate_limits (window_start);
CREATE INDEX IF NOT EXISTS idx_areas_created_at ON areas (created_at);

COMMIT;
