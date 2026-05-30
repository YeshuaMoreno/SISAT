-- ============================================================
-- SISAT — Consultas SQL para Dashboard y Reportes
-- Base de datos: sisat
-- Optimizadas para datasets de tesis (~4.5M evaluaciones)
-- ============================================================

USE sisat;

-- ─────────────────────────────────────────────────────────────
-- 1. CONTEO GENERAL DEL SISTEMA
-- ─────────────────────────────────────────────────────────────

SELECT
    (SELECT COUNT(*) FROM alumno    WHERE ACTIVO = 1)              AS total_alumnos,
    (SELECT COUNT(*) FROM escuela   WHERE ACTIVA = 1)              AS total_escuelas,
    (SELECT COUNT(*) FROM grupo)                                   AS total_grupos,
    (SELECT COUNT(*) FROM evaluacion_sisat)                        AS total_evaluaciones,
    (SELECT COUNT(*) FROM alerta)                                  AS total_alertas,
    (SELECT COUNT(*) FROM alerta WHERE ESTATUS NOT IN
        ('ATENDIDA','CERRADA'))                                    AS alertas_abiertas,
    (SELECT COUNT(*) FROM alerta WHERE ESTATUS IN
        ('ATENDIDA','CERRADA'))                                    AS alertas_cerradas,
    (SELECT COUNT(*) FROM seguimiento)                             AS total_seguimientos;


-- ─────────────────────────────────────────────────────────────
-- 2. DISTRIBUCIÓN DE RIESGO (última evaluación por alumno)
-- ─────────────────────────────────────────────────────────────

SELECT
    ev.NIVEL_RIESGO,
    COUNT(*)                          AS total_alumnos,
    ROUND(COUNT(*) * 100.0
        / SUM(COUNT(*)) OVER(), 1)   AS porcentaje
FROM evaluacion_sisat ev
INNER JOIN (
    SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
    FROM evaluacion_sisat
    GROUP BY ID_ALUMNO
) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO
     AND ev.ID_EVALUACION = ult.ultima
GROUP BY ev.NIVEL_RIESGO
ORDER BY FIELD(ev.NIVEL_RIESGO, 'BAJO','MEDIO','ALTO','CRITICO');


-- ─────────────────────────────────────────────────────────────
-- 3. RIESGO POR MUNICIPIO (última evaluación)
-- ─────────────────────────────────────────────────────────────

SELECT
    esc.MUNICIPIO,
    COUNT(DISTINCT a.ID_ALUMNO)                                    AS total_alumnos,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END)  AS bajo,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END)  AS medio,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END)  AS alto,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END)  AS critico,
    ROUND(
        (SUM(CASE WHEN ev.NIVEL_RIESGO IN ('MEDIO','ALTO','CRITICO')
             THEN 1 ELSE 0 END) * 100.0)
        / COUNT(DISTINCT a.ID_ALUMNO), 1
    )                                                              AS pct_en_riesgo
FROM evaluacion_sisat ev
INNER JOIN (
    SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
    FROM evaluacion_sisat
    GROUP BY ID_ALUMNO
) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO
     AND ev.ID_EVALUACION = ult.ultima
INNER JOIN alumno  a   ON a.ID_ALUMNO   = ev.ID_ALUMNO   AND a.ACTIVO = 1
LEFT  JOIN grupo   g   ON g.ID_GRUPO    = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA= g.ID_ESCUELA
GROUP BY esc.MUNICIPIO
ORDER BY critico DESC, alto DESC;


-- ─────────────────────────────────────────────────────────────
-- 4. RIESGO POR ESCUELA (top 20 con más casos críticos)
-- ─────────────────────────────────────────────────────────────

SELECT
    esc.NOMBRE_ESCUELA,
    esc.MUNICIPIO,
    esc.NIVEL,
    COUNT(DISTINCT a.ID_ALUMNO)                                    AS total_alumnos,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END)  AS bajo,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END)  AS medio,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END)  AS alto,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END)  AS critico
FROM evaluacion_sisat ev
INNER JOIN (
    SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
    FROM evaluacion_sisat
    GROUP BY ID_ALUMNO
) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO
     AND ev.ID_EVALUACION = ult.ultima
INNER JOIN alumno  a   ON a.ID_ALUMNO    = ev.ID_ALUMNO   AND a.ACTIVO = 1
LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
GROUP BY esc.ID_ESCUELA
ORDER BY critico DESC, alto DESC
LIMIT 20;


-- ─────────────────────────────────────────────────────────────
-- 5. ALERTAS POR ESTATUS
-- ─────────────────────────────────────────────────────────────

SELECT
    ESTATUS,
    COUNT(*)                          AS total,
    ROUND(COUNT(*) * 100.0
        / SUM(COUNT(*)) OVER(), 1)   AS porcentaje
FROM alerta
GROUP BY ESTATUS
ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA');


-- ─────────────────────────────────────────────────────────────
-- 6. VOLUMEN DE EVALUACIONES POR CICLO ESCOLAR
--    (inferido desde FECHA_EVALUACION)
-- ─────────────────────────────────────────────────────────────

SELECT
    CASE
        WHEN MONTH(FECHA_EVALUACION) >= 9
             THEN CONCAT(YEAR(FECHA_EVALUACION),'-',YEAR(FECHA_EVALUACION)+1)
        ELSE CONCAT(YEAR(FECHA_EVALUACION)-1,'-',YEAR(FECHA_EVALUACION))
    END                                AS ciclo_escolar,
    COUNT(*)                           AS total_evaluaciones,
    SUM(CASE WHEN NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END) AS bajo,
    SUM(CASE WHEN NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END) AS medio,
    SUM(CASE WHEN NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
    SUM(CASE WHEN NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico,
    ROUND(AVG(ASISTENCIA_PORCENTAJE), 2)                       AS asistencia_promedio,
    ROUND(AVG(PROMEDIO_GENERAL), 2)                            AS promedio_academico
FROM evaluacion_sisat
GROUP BY ciclo_escolar
ORDER BY ciclo_escolar;


-- ─────────────────────────────────────────────────────────────
-- 7. INDICADORES MÁS FRECUENTES
-- ─────────────────────────────────────────────────────────────

SELECT
    ir.NOMBRE_INDICADOR,
    ir.PESO,
    COUNT(ed.ID_DETALLE)              AS veces_activo,
    ROUND(COUNT(ed.ID_DETALLE) * 100.0
        / (SELECT COUNT(*) FROM evaluacion_sisat), 2) AS pct_evaluaciones
FROM evaluacion_detalle ed
INNER JOIN indicador_riesgo ir ON ir.ID_INDICADOR = ed.ID_INDICADOR
WHERE ed.VALOR = 1
GROUP BY ir.ID_INDICADOR
ORDER BY veces_activo DESC;


-- ─────────────────────────────────────────────────────────────
-- 8. CASOS CRÍTICOS POR MUNICIPIO
-- ─────────────────────────────────────────────────────────────

SELECT
    esc.MUNICIPIO,
    COUNT(al.ID_ALERTA)               AS alertas_criticas,
    SUM(CASE WHEN al.ESTATUS NOT IN ('ATENDIDA','CERRADA')
             THEN 1 ELSE 0 END)       AS sin_resolver
FROM alerta al
INNER JOIN alumno  a   ON a.ID_ALUMNO    = al.ID_ALUMNO
LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
WHERE al.NIVEL_RIESGO = 'CRITICO'
GROUP BY esc.MUNICIPIO
ORDER BY alertas_criticas DESC;


-- ─────────────────────────────────────────────────────────────
-- 9. TOP 10 ESCUELAS CON MÁS CASOS CRÍTICOS ABIERTOS
-- ─────────────────────────────────────────────────────────────

SELECT
    esc.NOMBRE_ESCUELA,
    esc.MUNICIPIO,
    COUNT(al.ID_ALERTA)               AS criticos_abiertos
FROM alerta al
INNER JOIN alumno  a   ON a.ID_ALUMNO    = al.ID_ALUMNO
LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
WHERE al.NIVEL_RIESGO = 'CRITICO'
  AND al.ESTATUS NOT IN ('ATENDIDA','CERRADA')
GROUP BY esc.ID_ESCUELA
ORDER BY criticos_abiertos DESC
LIMIT 10;


-- ─────────────────────────────────────────────────────────────
-- 10. COMPARATIVO DE RIESGO: SECUNDARIA vs BACHILLERATO
-- ─────────────────────────────────────────────────────────────

SELECT
    esc.NIVEL,
    COUNT(DISTINCT a.ID_ALUMNO)                                   AS alumnos,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END) AS bajo,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END) AS medio,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico,
    ROUND(AVG(ev.ASISTENCIA_PORCENTAJE), 2)                       AS asistencia_prom,
    ROUND(AVG(ev.PROMEDIO_GENERAL), 2)                            AS promedio_prom
FROM evaluacion_sisat ev
INNER JOIN (
    SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
    FROM evaluacion_sisat GROUP BY ID_ALUMNO
) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO
     AND ev.ID_EVALUACION = ult.ultima
INNER JOIN alumno  a   ON a.ID_ALUMNO    = ev.ID_ALUMNO
LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
GROUP BY esc.NIVEL;


-- ─────────────────────────────────────────────────────────────
-- 11. ÍNDICES RECOMENDADOS PARA RENDIMIENTO CON 4.5M FILAS
-- ─────────────────────────────────────────────────────────────

-- Ejecutar DESPUÉS de importar todos los datos
-- (antes de la importación masiva, estos índices ralentizan los INSERTs)

CREATE INDEX IF NOT EXISTS idx_eval_alumno
    ON evaluacion_sisat (ID_ALUMNO);

CREATE INDEX IF NOT EXISTS idx_eval_nivel
    ON evaluacion_sisat (NIVEL_RIESGO);

CREATE INDEX IF NOT EXISTS idx_eval_fecha
    ON evaluacion_sisat (FECHA_EVALUACION);

CREATE INDEX IF NOT EXISTS idx_alerta_nivel
    ON alerta (NIVEL_RIESGO, ESTATUS);

CREATE INDEX IF NOT EXISTS idx_alerta_alumno
    ON alerta (ID_ALUMNO);

CREATE INDEX IF NOT EXISTS idx_alumno_grupo
    ON alumno (ID_GRUPO);

-- Actualizar estadísticas para el optimizador
ANALYZE TABLE evaluacion_sisat;
ANALYZE TABLE alerta;
ANALYZE TABLE alumno;
