-- Collective Football — PostgreSQL initialization
-- Runs automatically on first container start

-- Enable PostGIS extension
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- For fuzzy text search on player names

-- Verify PostGIS installation
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'postgis') THEN
        RAISE NOTICE 'PostGIS installed: %', PostGIS_Version();
    ELSE
        RAISE EXCEPTION 'PostGIS installation failed';
    END IF;
END
$$;

-- Create read replica user (for reporting queries, Phase 2+)
-- CREATE USER collective_readonly WITH PASSWORD 'readonly_secret';
-- GRANT CONNECT ON DATABASE collective_football TO collective_readonly;
-- GRANT USAGE ON SCHEMA public TO collective_readonly;
-- GRANT SELECT ON ALL TABLES IN SCHEMA public TO collective_readonly;

SELECT 'Collective Football database initialized successfully' AS status;
