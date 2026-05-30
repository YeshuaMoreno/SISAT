-- ============================================================
-- actualizar_resumen_dashboard.sql
-- SISAT — Recalcula la tabla resumen para el dashboard.
-- Ejecutar después de importar datos nuevos o semanalmente.
--
-- Uso:
--   mysql -u root -p sisat < actualizar_resumen_dashboard.sql
--   (o pegar en phpMyAdmin / MySQL Workbench y ejecutar)
-- ============================================================

USE sisat;

-- ── 1. Crear tabla si no existe (no destruye si ya existe) ───────
CREATE TABLE IF NOT EXISTS resumen_dashboard_sisat (
    ID_RESUMEN          INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    TOTAL_ALUMNOS       BIGINT   NOT NULL DEFAULT 0,
    TOTAL_ESCUELAS      BIGINT   NOT NULL DEFAULT 0,
    TOTAL_EVALUACIONES  BIGINT   NOT NULL DEFAULT 0,
    TOTAL_ALERTAS       BIGINT   NOT NULL DEFAULT 0,
    ALERTAS_ABIERTAS    BIGINT   NOT NULL DEFAULT 0,
    ALERTAS_CERRADAS    BIGINT   NOT NULL DEFAULT 0,
    RIESGO_BAJO         BIGINT   NOT NULL DEFAULT 0,
    RIESGO_MEDIO        BIGINT   NOT NULL DEFAULT 0,
    RIESGO_ALTO         BIGINT   NOT NULL DEFAULT 0,
    RIESGO_CRITICO      BIGINT   NOT NULL DEFAULT 0,
    FECHA_ACTUALIZACION DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Vaciar fila anterior (TRUNCATE es instantáneo) ────────────
TRUNCATE TABLE resumen_dashboard_sisat;

-- ── 3. Recalcular e insertar ──────────────────────────────────────
--
--  RIESGO_* cuenta la última evaluación de cada alumno,
--  que es el dato real de su estado actual.
--  Si prefieres contar TODAS las evaluaciones (más rápido),
--  reemplaza el bloque FROM ... INNER JOIN por:
--      FROM evaluacion_sisat
--      GROUP BY NIVEL_RIESGO
--  y usa SUM(CASE ...) directamente.
--
INSERT INTO resumen_dashboard_sisat (
    TOTAL_ALUMNOS,
    TOTAL_ESCUELAS,
    TOTAL_EVALUACIONES,
    TOTAL_ALERTAS,
    ALERTAS_ABIERTAS,
    ALERTAS_CERRADAS,
    RIESGO_BAJO,
    RIESGO_MEDIO,
    RIESGO_ALTO,
    RIESGO_CRITICO,
    FECHA_ACTUALIZACION
)
SELECT
    -- Totales directos (tablas pequeñas, rápido)
    (SELECT COUNT(*) FROM alumno  WHERE ACTIVO = 1)                 AS TOTAL_ALUMNOS,
    (SELECT COUNT(*) FROM escuela WHERE ACTIVA = 1)                 AS TOTAL_ESCUELAS,
    (SELECT COUNT(*) FROM evaluacion_sisat)                         AS TOTAL_EVALUACIONES,
    (SELECT COUNT(*) FROM alerta)                                   AS TOTAL_ALERTAS,
    (SELECT COUNT(*) FROM alerta
        WHERE ESTATUS NOT IN ('ATENDIDA','CERRADA'))                AS ALERTAS_ABIERTAS,
    (SELECT COUNT(*) FROM alerta
        WHERE ESTATUS IN ('ATENDIDA','CERRADA'))                    AS ALERTAS_CERRADAS,

    -- Distribución de riesgo: última evaluación por alumno
    COALESCE(SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END), 0),

    NOW()

FROM evaluacion_sisat ev
INNER JOIN (
    SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
    FROM evaluacion_sisat
    GROUP BY ID_ALUMNO
) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima;

-- ── 4. Mostrar resultado ─────────────────────────────────────────
SELECT
    TOTAL_ALUMNOS,
    TOTAL_ESCUELAS,
    TOTAL_EVALUACIONES,
    TOTAL_ALERTAS,
    ALERTAS_ABIERTAS,
    ALERTAS_CERRADAS,
    RIESGO_BAJO,
    RIESGO_MEDIO,
    RIESGO_ALTO,
    RIESGO_CRITICO,
    FECHA_ACTUALIZACION
FROM resumen_dashboard_sisat
LIMIT 1;