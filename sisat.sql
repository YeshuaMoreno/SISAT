-- ============================================================
-- SISAT - Sistema de Alerta Temprana para Abandono Escolar
-- Base de datos completa con estructura y datos semilla
-- ============================================================

DROP DATABASE IF EXISTS sisat;
CREATE DATABASE sisat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sisat;

-- ============================================================
-- TABLA: rol
-- ============================================================
CREATE TABLE rol (
    ID_ROL      INT AUTO_INCREMENT PRIMARY KEY,
    NOMBRE_ROL  VARCHAR(30) NOT NULL UNIQUE
);

INSERT INTO rol (NOMBRE_ROL) VALUES
    ('ADMIN'),
    ('SEDU'),
    ('DIRECTOR'),
    ('DOCENTE'),
    ('ORIENTADOR'),
    ('ALUMNO');

-- ============================================================
-- TABLA: usuario
-- ============================================================
CREATE TABLE usuario (
    ID_USUARIO      INT AUTO_INCREMENT PRIMARY KEY,
    USUARIO         VARCHAR(60)  NOT NULL UNIQUE,
    CORREO          VARCHAR(120) NOT NULL UNIQUE,
    PWD             VARCHAR(255) NOT NULL,
    ID_ROL          INT NOT NULL,
    ACTIVO          TINYINT(1) NOT NULL DEFAULT 1,
    FECHA_CREACION  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (ID_ROL) REFERENCES rol(ID_ROL)
);

-- Contraseñas hasheadas con password_hash(pass, PASSWORD_DEFAULT)
-- admin     / Admin123*
-- sedu      / Sedu123*
-- director  / Director123*
-- docente   / Docente123*
-- orientador/ Orientador123*
INSERT INTO usuario (USUARIO, CORREO, PWD, ID_ROL) VALUES
    ('admin',      'admin@sisat.edu.mx',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    ('sedu',       'sedu@sisat.edu.mx',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
    ('director',   'director@sisat.edu.mx',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
    ('docente',    'docente@sisat.edu.mx',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4),
    ('orientador', 'orientador@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5);

-- Nota: los hashes anteriores corresponden a "password" (de Laravel).
-- Al importar se deben regenerar con el script hash_sisat.php
-- o usar los valores reales indicados abajo (generados con PHP):
-- Ejecutar hash_sisat.php para obtener hashes reales de cada contraseña.

-- ============================================================
-- TABLA: escuela
-- ============================================================
CREATE TABLE escuela (
    ID_ESCUELA      INT AUTO_INCREMENT PRIMARY KEY,
    CCT             VARCHAR(15)  NOT NULL UNIQUE,
    NOMBRE_ESCUELA  VARCHAR(150) NOT NULL,
    MUNICIPIO       VARCHAR(80)  NOT NULL,
    NIVEL           ENUM('PREESCOLAR','PRIMARIA','SECUNDARIA','BACHILLERATO','OTRO') NOT NULL DEFAULT 'SECUNDARIA',
    ZONA_ESCOLAR    VARCHAR(30)  NOT NULL,
    ACTIVA          TINYINT(1)   NOT NULL DEFAULT 1
);

INSERT INTO escuela (CCT, NOMBRE_ESCUELA, MUNICIPIO, NIVEL, ZONA_ESCOLAR) VALUES
    ('18DST0001A', 'Escuela Secundaria Técnica No. 1',       'Tepic',         'SECUNDARIA', 'ZONA 01'),
    ('18DES0002B', 'Preparatoria Estatal No. 5 "Benito Juárez"', 'Tepic',    'BACHILLERATO','ZONA 02');

-- ============================================================
-- TABLA: grupo
-- ============================================================
CREATE TABLE grupo (
    ID_GRUPO        INT AUTO_INCREMENT PRIMARY KEY,
    ID_ESCUELA      INT NOT NULL,
    GRADO           VARCHAR(10)  NOT NULL,
    GRUPO           VARCHAR(5)   NOT NULL,
    CICLO_ESCOLAR   VARCHAR(10)  NOT NULL,
    ID_DOCENTE      INT NULL,
    CONSTRAINT fk_grupo_escuela  FOREIGN KEY (ID_ESCUELA) REFERENCES escuela(ID_ESCUELA),
    CONSTRAINT fk_grupo_docente  FOREIGN KEY (ID_DOCENTE) REFERENCES usuario(ID_USUARIO)
);

INSERT INTO grupo (ID_ESCUELA, GRADO, GRUPO, CICLO_ESCOLAR, ID_DOCENTE) VALUES
    (1, '2', 'A', '2024-2025', 4),
    (2, '1', 'B', '2024-2025', 4);

-- ============================================================
-- TABLA: alumno
-- ============================================================
CREATE TABLE alumno (
    ID_ALUMNO           INT AUTO_INCREMENT PRIMARY KEY,
    MATRICULA           VARCHAR(20)  NOT NULL UNIQUE,
    CURP                VARCHAR(18)  NULL UNIQUE,
    NOMBRE              VARCHAR(80)  NOT NULL,
    APELLIDO_PATERNO    VARCHAR(60)  NOT NULL,
    APELLIDO_MATERNO    VARCHAR(60)  NOT NULL DEFAULT '',
    FECHA_NACIMIENTO    DATE         NULL,
    SEXO                ENUM('M','F','OTRO') NOT NULL DEFAULT 'M',
    TELEFONO            VARCHAR(20)  NULL,
    CORREO              VARCHAR(120) NULL,
    DIRECCION           VARCHAR(200) NULL,
    ID_GRUPO            INT NULL,
    ID_USUARIO          INT NULL,
    ACTIVO              TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_alumno_grupo   FOREIGN KEY (ID_GRUPO)   REFERENCES grupo(ID_GRUPO),
    CONSTRAINT fk_alumno_usuario FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO)
);

-- Crear usuarios de sesión para alumnos
INSERT INTO usuario (USUARIO, CORREO, PWD, ID_ROL) VALUES
    ('alumno01', 'alumno01@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6),
    ('alumno02', 'alumno02@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6),
    ('alumno03', 'alumno03@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6),
    ('alumno04', 'alumno04@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6),
    ('alumno05', 'alumno05@sisat.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6);

INSERT INTO alumno (MATRICULA, CURP, NOMBRE, APELLIDO_PATERNO, APELLIDO_MATERNO, FECHA_NACIMIENTO, SEXO, TELEFONO, CORREO, ID_GRUPO, ID_USUARIO) VALUES
    ('2024001', 'GAMA100305HNLRZN01', 'Ana',      'García',    'Martínez',  '2010-03-05', 'F', '3111234567', 'alumno01@sisat.edu.mx', 1, 6),
    ('2024002', 'LOPB110712HNLPPD02', 'Brenda',   'López',     'Pérez',     '2011-07-12', 'F', '3119876543', 'alumno02@sisat.edu.mx', 1, 7),
    ('2024003', 'RAMJ090820HNLMNN03', 'Carlos',   'Ramírez',   'Jiménez',   '2009-08-20', 'M', '3115551234', 'alumno03@sisat.edu.mx', 2, 8),
    ('2024004', 'HERG120115HNLRRB04', 'Diana',    'Hernández', 'Reyes',     '2012-01-15', 'F', '3113334455', 'alumno04@sisat.edu.mx', 2, 9),
    ('2024005', 'TORC081030HNLRJR05', 'Eduardo',  'Torres',    'Cruz',      '2008-10-30', 'M', '3116667788', 'alumno05@sisat.edu.mx', 1, 10);

-- ============================================================
-- TABLA: indicador_riesgo
-- ============================================================
CREATE TABLE indicador_riesgo (
    ID_INDICADOR        INT AUTO_INCREMENT PRIMARY KEY,
    NOMBRE_INDICADOR    VARCHAR(120) NOT NULL,
    DESCRIPCION         VARCHAR(300) NOT NULL DEFAULT '',
    PESO                INT NOT NULL DEFAULT 1,
    ACTIVO              TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO indicador_riesgo (NOMBRE_INDICADOR, DESCRIPCION, PESO) VALUES
    ('Inasistencias frecuentes',        'El alumno falta con regularidad sin justificación.',               2),
    ('Bajo rendimiento académico',      'Promedio por debajo del mínimo aprobatorio.',                     2),
    ('Rezago en lectura',               'Dificultad notoria en comprensión lectora.',                      1),
    ('Rezago en escritura',             'Problemas de escritura y redacción básica.',                      1),
    ('Rezago en matemáticas',           'Bajo desempeño en operaciones matemáticas fundamentales.',        1),
    ('Problemas de conducta',           'Reportes de disciplina o conflictos con pares.',                  1),
    ('Falta de participación',          'El alumno no participa en clases ni actividades.',                1),
    ('Situación socioemocional',        'Indicios de problemas emocionales, depresión o ansiedad.',        3),
    ('Riesgo económico/familiar',       'Situación familiar o económica que pone en riesgo la asistencia.',3),
    ('Reprobación recurrente',          'El alumno ha reprobado uno o más grados anteriores.',             2);

-- ============================================================
-- TABLA: evaluacion_sisat
-- ============================================================
CREATE TABLE evaluacion_sisat (
    ID_EVALUACION           INT AUTO_INCREMENT PRIMARY KEY,
    ID_ALUMNO               INT NOT NULL,
    ID_USUARIO_CAPTURA      INT NOT NULL,
    FECHA_EVALUACION        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ASISTENCIA_PORCENTAJE   DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    PROMEDIO_GENERAL        DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    OBSERVACIONES           TEXT NULL,
    PUNTAJE_RIESGO          INT NOT NULL DEFAULT 0,
    NIVEL_RIESGO            ENUM('BAJO','MEDIO','ALTO','CRITICO') NOT NULL DEFAULT 'BAJO',
    CONSTRAINT fk_eval_alumno   FOREIGN KEY (ID_ALUMNO)          REFERENCES alumno(ID_ALUMNO),
    CONSTRAINT fk_eval_usuario  FOREIGN KEY (ID_USUARIO_CAPTURA) REFERENCES usuario(ID_USUARIO)
);

-- ============================================================
-- TABLA: evaluacion_detalle
-- ============================================================
CREATE TABLE evaluacion_detalle (
    ID_DETALLE      INT AUTO_INCREMENT PRIMARY KEY,
    ID_EVALUACION   INT NOT NULL,
    ID_INDICADOR    INT NOT NULL,
    VALOR           TINYINT(1) NOT NULL DEFAULT 0,
    OBSERVACION     VARCHAR(300) NULL,
    CONSTRAINT fk_detalle_eval  FOREIGN KEY (ID_EVALUACION) REFERENCES evaluacion_sisat(ID_EVALUACION),
    CONSTRAINT fk_detalle_ind   FOREIGN KEY (ID_INDICADOR)  REFERENCES indicador_riesgo(ID_INDICADOR)
);

-- ============================================================
-- TABLA: alerta
-- ============================================================
CREATE TABLE alerta (
    ID_ALERTA       INT AUTO_INCREMENT PRIMARY KEY,
    ID_ALUMNO       INT NOT NULL,
    ID_EVALUACION   INT NOT NULL,
    NIVEL_RIESGO    ENUM('BAJO','MEDIO','ALTO','CRITICO') NOT NULL,
    ESTATUS         ENUM('NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA') NOT NULL DEFAULT 'NUEVA',
    FECHA_CREACION  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FECHA_CIERRE    DATETIME NULL,
    DESCRIPCION     TEXT NULL,
    CONSTRAINT fk_alerta_alumno FOREIGN KEY (ID_ALUMNO)     REFERENCES alumno(ID_ALUMNO),
    CONSTRAINT fk_alerta_eval   FOREIGN KEY (ID_EVALUACION) REFERENCES evaluacion_sisat(ID_EVALUACION)
);

-- ============================================================
-- TABLA: seguimiento
-- ============================================================
CREATE TABLE seguimiento (
    ID_SEGUIMIENTO          INT AUTO_INCREMENT PRIMARY KEY,
    ID_ALERTA               INT NOT NULL,
    ID_USUARIO              INT NOT NULL,
    FECHA_SEGUIMIENTO       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ACCION_REALIZADA        TEXT NOT NULL,
    RESULTADO               TEXT NULL,
    PROXIMA_ACCION          TEXT NULL,
    FECHA_PROXIMA_ACCION    DATE NULL,
    CONSTRAINT fk_seg_alerta  FOREIGN KEY (ID_ALERTA)  REFERENCES alerta(ID_ALERTA),
    CONSTRAINT fk_seg_usuario FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO)
);

-- ============================================================
-- DATOS SEMILLA: evaluaciones y alertas de prueba
-- ============================================================

-- Evaluación BAJO (alumno 1 - Ana García)
INSERT INTO evaluacion_sisat (ID_ALUMNO, ID_USUARIO_CAPTURA, FECHA_EVALUACION, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO) VALUES
    (1, 4, DATE_SUB(NOW(), INTERVAL 30 DAY), 92.00, 8.50, 'Alumna con buen rendimiento general.', 1, 'BAJO');
INSERT INTO evaluacion_detalle (ID_EVALUACION, ID_INDICADOR, VALOR, OBSERVACION) VALUES
    (1, 7, 1, 'Poca participación en clase de historia.');

-- Evaluación MEDIO (alumno 2 - Brenda López)
INSERT INTO evaluacion_sisat (ID_ALUMNO, ID_USUARIO_CAPTURA, FECHA_EVALUACION, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO) VALUES
    (2, 4, DATE_SUB(NOW(), INTERVAL 20 DAY), 84.00, 6.80, 'Presenta rezago en matemáticas y baja participación.', 4, 'MEDIO');
INSERT INTO evaluacion_detalle (ID_EVALUACION, ID_INDICADOR, VALOR, OBSERVACION) VALUES
    (2, 5, 1, 'Reprueba evaluaciones de matemáticas.'),
    (2, 7, 1, 'Raramente participa en clase.'),
    (2, 3, 1, 'Dificultad para comprender textos.');

-- Evaluación ALTO (alumno 3 - Carlos Ramírez)
INSERT INTO evaluacion_sisat (ID_ALUMNO, ID_USUARIO_CAPTURA, FECHA_EVALUACION, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO) VALUES
    (3, 4, DATE_SUB(NOW(), INTERVAL 15 DAY), 74.00, 5.20, 'Múltiples inasistencias y bajo promedio. Requiere atención urgente.', 7, 'ALTO');
INSERT INTO evaluacion_detalle (ID_EVALUACION, ID_INDICADOR, VALOR, OBSERVACION) VALUES
    (3, 1, 1, 'Más de 10 faltas sin justificar este mes.'),
    (3, 2, 1, 'Promedio inferior a 6.'),
    (3, 5, 1, 'Reprueba matemáticas consecutivamente.'),
    (3, 6, 1, 'Reportes de conflictos con compañeros.'),
    (3, 10, 1, 'Reprobó el ciclo anterior.');

-- Evaluación CRITICO (alumno 4 - Diana Hernández)
INSERT INTO evaluacion_sisat (ID_ALUMNO, ID_USUARIO_CAPTURA, FECHA_EVALUACION, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO) VALUES
    (4, 5, DATE_SUB(NOW(), INTERVAL 5 DAY),  62.00, 4.10, 'Situación crítica. Problemas socioemocionales y económicos graves. Riesgo alto de abandono.', 11, 'CRITICO');
INSERT INTO evaluacion_detalle (ID_EVALUACION, ID_INDICADOR, VALOR, OBSERVACION) VALUES
    (4, 1, 1, 'Ausente más de un tercio del ciclo.'),
    (4, 2, 1, 'Promedio muy bajo.'),
    (4, 8, 1, 'Posible situación de violencia en el hogar.'),
    (4, 9, 1, 'Familia con situación económica vulnerable.'),
    (4, 6, 1, 'Reportes de conducta disruptiva.'),
    (4, 3, 1, 'No puede leer con fluidez.'),
    (4, 10, 1, 'Reprobó segundo grado el ciclo anterior.');

-- Evaluación BAJO (alumno 5 - Eduardo Torres)
INSERT INTO evaluacion_sisat (ID_ALUMNO, ID_USUARIO_CAPTURA, FECHA_EVALUACION, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO) VALUES
    (5, 4, DATE_SUB(NOW(), INTERVAL 10 DAY), 96.00, 9.20, 'Alumno con excelente asistencia y promedio.', 0, 'BAJO');

-- ============================================================
-- ALERTAS (solo para riesgo MEDIO, ALTO, CRITICO)
-- ============================================================
INSERT INTO alerta (ID_ALUMNO, ID_EVALUACION, NIVEL_RIESGO, ESTATUS, DESCRIPCION) VALUES
    (2, 2, 'MEDIO',  'EN_REVISION',   'Alumna Brenda López con rezago en matemáticas y baja participación. Requiere seguimiento.'),
    (3, 3, 'ALTO',   'EN_SEGUIMIENTO','Alumno Carlos Ramírez con múltiples inasistencias y bajo rendimiento. Contactar a padres.'),
    (4, 4, 'CRITICO','NUEVA',         'Alumna Diana Hernández en situación crítica. Intervención urgente requerida por orientación y dirección.');

-- ============================================================
-- SEGUIMIENTOS
-- ============================================================
INSERT INTO seguimiento (ID_ALERTA, ID_USUARIO, FECHA_SEGUIMIENTO, ACCION_REALIZADA, RESULTADO, PROXIMA_ACCION, FECHA_PROXIMA_ACCION) VALUES
    (1, 5, DATE_SUB(NOW(), INTERVAL 18 DAY), 'Se realizó entrevista con la alumna Brenda para identificar causas del rezago en matemáticas.', 'La alumna indicó que no comprende los temas desde el bloque 2. Se le recomendó tutoría.', 'Dar seguimiento a asistencia a tutoría y calificaciones del siguiente parcial.', DATE_ADD(NOW(), INTERVAL 14 DAY)),
    (2, 5, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Se citó a los padres de Carlos Ramírez. Asistió solamente la madre.', 'La madre informó que Carlos trabaja por las tardes y llega tarde en las mañanas. Se acordó horario flexible.', 'Verificar asistencia y comunicarse nuevamente con la familia.', DATE_ADD(NOW(), INTERVAL 7 DAY)),
    (2, 4, DATE_SUB(NOW(), INTERVAL 8 DAY),  'Se realizó llamada telefónica de seguimiento al alumno Carlos.', 'El alumno comprometió mejorar su asistencia. Se presentó 4 días completos esta semana.', 'Continuar monitoreo semanal de asistencia.', DATE_ADD(NOW(), INTERVAL 7 DAY));

-- ============================================================
-- VISTA: reporte_sedu (estadísticas generales)
-- ============================================================
CREATE OR REPLACE VIEW vista_reporte_sedu AS
SELECT
    e.MUNICIPIO,
    esc.NOMBRE_ESCUELA,
    esc.NIVEL,
    COUNT(DISTINCT a.ID_ALUMNO)                                               AS total_alumnos,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END)             AS riesgo_bajo,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END)             AS riesgo_medio,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END)             AS riesgo_alto,
    SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END)             AS riesgo_critico,
    COUNT(DISTINCT al.ID_ALERTA)                                              AS total_alertas,
    SUM(CASE WHEN al.ESTATUS NOT IN ('CERRADA','ATENDIDA') THEN 1 ELSE 0 END) AS alertas_abiertas,
    SUM(CASE WHEN al.ESTATUS IN ('CERRADA','ATENDIDA')     THEN 1 ELSE 0 END) AS alertas_cerradas
FROM escuela esc
LEFT JOIN grupo g   ON g.ID_ESCUELA = esc.ID_ESCUELA
LEFT JOIN alumno a  ON a.ID_GRUPO   = g.ID_GRUPO AND a.ACTIVO = 1
LEFT JOIN (
    SELECT ev1.*
    FROM evaluacion_sisat ev1
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat
        GROUP BY ID_ALUMNO
    ) ult ON ev1.ID_ALUMNO = ult.ID_ALUMNO AND ev1.ID_EVALUACION = ult.ultima
) ev ON ev.ID_ALUMNO = a.ID_ALUMNO
LEFT JOIN alerta al ON al.ID_ALUMNO = a.ID_ALUMNO
CROSS JOIN escuela e ON e.ID_ESCUELA = esc.ID_ESCUELA
WHERE esc.ID_ESCUELA = e.ID_ESCUELA
GROUP BY esc.ID_ESCUELA, esc.MUNICIPIO, esc.NOMBRE_ESCUELA, esc.NIVEL;

-- ============================================================
-- NOTAS SOBRE TABLAS OBSOLETAS DEL SISTEMA ANTERIOR
-- ============================================================
-- Las siguientes tablas del simulador de manejo quedan obsoletas:
--   banco_imagenes, preguntas, respuestas, examen_estudiante,
--   estudiante, historial_estudiante
-- No se recrean en esta base de datos.
-- Si se requiere migración de datos de usuarios, hacerlo manualmente
-- adaptando la tabla 'estudiante' a 'usuario'+'alumno' de SISAT.
