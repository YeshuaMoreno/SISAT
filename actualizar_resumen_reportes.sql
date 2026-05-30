-- ============================================================
-- actualizar_resumen_reportes.sql
-- SISAT — Recalcula las 4 tablas resumen para reportes.php
--
-- Ejecutar después de importar datos nuevos o tras actualizar
-- el resumen principal del dashboard.
--
-- Uso rápido (CMD Windows):
--   "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < actualizar_resumen_reportes.sql
--
-- O desde phpMyAdmin / Navicat: pegar y ejecutar.
-- ============================================================

USE sisat;

-- ════════════════════════════════════════════════════════════════
-- 1. resumen_riesgo_municipio
-- ════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS resumen_riesgo_municipio (
    MUNICIPIO           VARCHAR(100) NOT NULL,
    TOTAL_ALUMNOS       BIGINT       NOT NULL DEFAULT 0,
    RIESGO_BAJO         BIGINT       NOT NULL DEFAULT 0,
    RIESGO_MEDIO        BIGINT       NOT NULL DEFAULT 0,
    RIESGO_ALTO         BIGINT       NOT NULL DEFAULT 0,
    RIESGO_CRITICO      BIGINT       NOT NULL DEFAULT 0,
    ALERTAS_ABIERTAS    BIGINT       NOT NULL DEFAULT 0,
    ALERTAS_CERRADAS    BIGINT       NOT NULL DEFAULT 0,
    FECHA_ACTUALIZACION DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (MUNICIPIO)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE resumen_riesgo_municipio;

INSERT INTO resumen_riesgo_municipio
    (MUNICIPIO, TOTAL_ALUMNOS,
     RIESGO_BAJO, RIESGO_MEDIO, RIESGO_ALTO, RIESGO_CRITICO,
     ALERTAS_ABIERTAS, ALERTAS_CERRADAS, FECHA_ACTUALIZACION)
SELECT
    COALESCE(esc.MUNICIPIO, '(Sin escuela)') AS MUNICIPIO,
    COUNT(DISTINCT a.ID_ALUMNO)              AS TOTAL_ALUMNOS,
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END), 0),
    -- Alertas: agrupadas por alumno para evitar multiplicar filas
    COALESCE(SUM(COALESCE(al_agg.abiertas,  0)), 0),
    COALESCE(SUM(COALESCE(al_agg.cerradas,  0)), 0),
    NOW()
FROM alumno a
-- Última evaluación de cada alumno
LEFT JOIN (
    SELECT ev.ID_ALUMNO, ev.NIVEL_RIESGO
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat
        GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
) ev_u ON ev_u.ID_ALUMNO = a.ID_ALUMNO
-- Escuela vía grupo
LEFT JOIN grupo   g   ON g.ID_GRUPO    = a.ID_GRUPO
LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
-- Conteo de alertas por alumno (sin multiplicar filas)
LEFT JOIN (
    SELECT ID_ALUMNO,
           SUM(CASE WHEN ESTATUS NOT IN ('ATENDIDA','CERRADA') THEN 1 ELSE 0 END) AS abiertas,
           SUM(CASE WHEN ESTATUS IN     ('ATENDIDA','CERRADA') THEN 1 ELSE 0 END) AS cerradas
    FROM alerta
    GROUP BY ID_ALUMNO
) al_agg ON al_agg.ID_ALUMNO = a.ID_ALUMNO
WHERE a.ACTIVO = 1
GROUP BY COALESCE(esc.MUNICIPIO, '(Sin escuela)')
ORDER BY TOTAL_ALUMNOS DESC;

SELECT CONCAT('resumen_riesgo_municipio: ', COUNT(*), ' filas') AS validacion FROM resumen_riesgo_municipio;

-- ════════════════════════════════════════════════════════════════
-- 2. resumen_riesgo_escuela
-- ════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS resumen_riesgo_escuela (
    ID_ESCUELA          INT          NOT NULL,
    NOMBRE_ESCUELA      VARCHAR(150) NOT NULL,
    MUNICIPIO           VARCHAR(100) NOT NULL DEFAULT '',
    NIVEL               VARCHAR(20)  NOT NULL DEFAULT '',
    ZONA_ESCOLAR        VARCHAR(30)  NOT NULL DEFAULT '',
    TOTAL_ALUMNOS       BIGINT       NOT NULL DEFAULT 0,
    RIESGO_BAJO         BIGINT       NOT NULL DEFAULT 0,
    RIESGO_MEDIO        BIGINT       NOT NULL DEFAULT 0,
    RIESGO_ALTO         BIGINT       NOT NULL DEFAULT 0,
    RIESGO_CRITICO      BIGINT       NOT NULL DEFAULT 0,
    TOTAL_ALERTAS       BIGINT       NOT NULL DEFAULT 0,
    FECHA_ACTUALIZACION DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID_ESCUELA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE resumen_riesgo_escuela;

INSERT INTO resumen_riesgo_escuela
    (ID_ESCUELA, NOMBRE_ESCUELA, MUNICIPIO, NIVEL, ZONA_ESCOLAR,
     TOTAL_ALUMNOS, RIESGO_BAJO, RIESGO_MEDIO, RIESGO_ALTO, RIESGO_CRITICO,
     TOTAL_ALERTAS, FECHA_ACTUALIZACION)
SELECT
    esc.ID_ESCUELA,
    esc.NOMBRE_ESCUELA,
    esc.MUNICIPIO,
    esc.NIVEL,
    esc.ZONA_ESCOLAR,
    COUNT(DISTINCT a.ID_ALUMNO)                                        AS TOTAL_ALUMNOS,
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END), 0),
    COALESCE(SUM(COALESCE(al_agg.total_alertas, 0)), 0)               AS TOTAL_ALERTAS,
    NOW()
FROM escuela esc
LEFT JOIN grupo   g ON g.ID_ESCUELA = esc.ID_ESCUELA
LEFT JOIN alumno  a ON a.ID_GRUPO   = g.ID_GRUPO AND a.ACTIVO = 1
LEFT JOIN (
    SELECT ev.ID_ALUMNO, ev.NIVEL_RIESGO
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
) ev_u ON ev_u.ID_ALUMNO = a.ID_ALUMNO
LEFT JOIN (
    SELECT ID_ALUMNO, COUNT(*) AS total_alertas
    FROM alerta
    GROUP BY ID_ALUMNO
) al_agg ON al_agg.ID_ALUMNO = a.ID_ALUMNO
WHERE esc.ACTIVA = 1
GROUP BY esc.ID_ESCUELA
ORDER BY (COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO='CRITICO' THEN 1 ELSE 0 END),0) +
          COALESCE(SUM(CASE WHEN ev_u.NIVEL_RIESGO='ALTO'    THEN 1 ELSE 0 END),0)) DESC;

SELECT CONCAT('resumen_riesgo_escuela: ', COUNT(*), ' filas') AS validacion FROM resumen_riesgo_escuela;

-- ════════════════════════════════════════════════════════════════
-- 3. resumen_alertas_estatus
-- ════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS resumen_alertas_estatus (
    ESTATUS             VARCHAR(30)   NOT NULL,
    TOTAL               BIGINT        NOT NULL DEFAULT 0,
    PORCENTAJE          DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    FECHA_ACTUALIZACION DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ESTATUS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE resumen_alertas_estatus;

INSERT INTO resumen_alertas_estatus (ESTATUS, TOTAL, PORCENTAJE, FECHA_ACTUALIZACION)
SELECT
    ESTATUS,
    COUNT(*)                                                            AS TOTAL,
    ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM alerta), 0), 2) AS PORCENTAJE,
    NOW()
FROM alerta
GROUP BY ESTATUS
ORDER BY FIELD(ESTATUS, 'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA');

SELECT CONCAT('resumen_alertas_estatus: ', COUNT(*), ' filas') AS validacion FROM resumen_alertas_estatus;

-- ════════════════════════════════════════════════════════════════
-- 4. resumen_top_escuelas_criticas
-- ════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS resumen_top_escuelas_criticas (
    ID_ESCUELA          INT          NOT NULL,
    NOMBRE_ESCUELA      VARCHAR(150) NOT NULL,
    MUNICIPIO           VARCHAR(100) NOT NULL DEFAULT '',
    NIVEL               VARCHAR(20)  NOT NULL DEFAULT '',
    CASOS_CRITICOS      BIGINT       NOT NULL DEFAULT 0,
    ALERTAS_ABIERTAS    BIGINT       NOT NULL DEFAULT 0,
    FECHA_ACTUALIZACION DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID_ESCUELA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE resumen_top_escuelas_criticas;

INSERT INTO resumen_top_escuelas_criticas
    (ID_ESCUELA, NOMBRE_ESCUELA, MUNICIPIO, NIVEL,
     CASOS_CRITICOS, ALERTAS_ABIERTAS, FECHA_ACTUALIZACION)
SELECT
    esc.ID_ESCUELA,
    esc.NOMBRE_ESCUELA,
    esc.MUNICIPIO,
    esc.NIVEL,
    COUNT(al.ID_ALERTA)                                                          AS CASOS_CRITICOS,
    SUM(CASE WHEN al.ESTATUS NOT IN ('ATENDIDA','CERRADA') THEN 1 ELSE 0 END)   AS ALERTAS_ABIERTAS,
    NOW()
FROM alerta al
INNER JOIN alumno  a   ON a.ID_ALUMNO    = al.ID_ALUMNO
LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
WHERE al.NIVEL_RIESGO = 'CRITICO'
  AND esc.ID_ESCUELA IS NOT NULL
GROUP BY esc.ID_ESCUELA
ORDER BY CASOS_CRITICOS DESC
LIMIT 100;  -- Guardamos top 100, reportes.php mostrará los que quiera

SELECT CONCAT('resumen_top_escuelas_criticas: ', COUNT(*), ' filas') AS validacion
FROM resumen_top_escuelas_criticas;

-- ════════════════════════════════════════════════════════════════
-- RESUMEN FINAL
-- ════════════════════════════════════════════════════════════════
SELECT
    'resumen_riesgo_municipio'       AS tabla, COUNT(*) AS filas, MAX(FECHA_ACTUALIZACION) AS actualizado
    FROM resumen_riesgo_municipio
UNION ALL
SELECT 'resumen_riesgo_escuela',      COUNT(*), MAX(FECHA_ACTUALIZACION) FROM resumen_riesgo_escuela
UNION ALL
SELECT 'resumen_alertas_estatus',     COUNT(*), MAX(FECHA_ACTUALIZACION) FROM resumen_alertas_estatus
UNION ALL
SELECT 'resumen_top_escuelas_criticas', COUNT(*), MAX(FECHA_ACTUALIZACION) FROM resumen_top_escuelas_criticas;
