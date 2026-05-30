<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireLogin();

$rol = rolActual();

// ── Métricas generales
$totalAlumnos   = $pdo->query("SELECT COUNT(*) FROM alumno WHERE ACTIVO = 1")->fetchColumn();
$alertasNuevas  = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS = 'NUEVA'")->fetchColumn();
$enSeguimiento  = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS = 'EN_SEGUIMIENTO'")->fetchColumn();
$criticos       = $pdo->query("SELECT COUNT(*) FROM alerta WHERE NIVEL_RIESGO = 'CRITICO' AND ESTATUS NOT IN ('CERRADA','ATENDIDA')")->fetchColumn();

// Distribución de riesgo (última evaluación de cada alumno)
$distRiesgo = $pdo->query("
    SELECT ev.NIVEL_RIESGO, COUNT(*) AS total
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    GROUP BY ev.NIVEL_RIESGO
")->fetchAll();

$riesgos = ['BAJO' => 0, 'MEDIO' => 0, 'ALTO' => 0, 'CRITICO' => 0];
foreach ($distRiesgo as $r) $riesgos[$r['NIVEL_RIESGO']] = (int)$r['total'];

// Últimas alertas
$ultimasAlertas = $pdo->query("
    SELECT al.ID_ALERTA, al.NIVEL_RIESGO, al.ESTATUS, al.FECHA_CREACION,
           CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO) AS alumno_nombre,
           esc.NOMBRE_ESCUELA
    FROM alerta al
    INNER JOIN alumno a ON a.ID_ALUMNO = al.ID_ALUMNO
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    ORDER BY al.FECHA_CREACION DESC
    LIMIT 8
")->fetchAll();

// Alumnos en situación crítica
$alumnosCriticos = $pdo->query("
    SELECT a.ID_ALUMNO, CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO) AS nombre_completo,
           a.MATRICULA, g.GRADO, g.GRUPO AS letra_grupo,
           esc.NOMBRE_ESCUELA, ev.NIVEL_RIESGO, ev.PUNTAJE_RIESGO, ev.FECHA_EVALUACION
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    INNER JOIN alumno a ON a.ID_ALUMNO = ev.ID_ALUMNO AND a.ACTIVO = 1
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE ev.NIVEL_RIESGO IN ('ALTO','CRITICO')
    ORDER BY ev.PUNTAJE_RIESGO DESC
    LIMIT 6
")->fetchAll();

$paginaActual = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISAT — Panel principal</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">

    <?php require_once 'sidebar.php'; ?>

    <div class="main-content">

        <div class="topbar">
            <div class="topbar-titulo">Panel principal</div>
            <span class="topbar-rol"><?= h($rol) ?></span>
            <span style="font-size:14px;color:#64748b;">
                Bienvenido, <strong><?= h($_SESSION['usuario']['nombre']) ?></strong>
            </span>
        </div>

        <div class="pagina">

            <?php if (isset($_GET['acceso']) && $_GET['acceso'] === 'denegado'): ?>
                <div class="alerta alerta-error">No tienes permiso para acceder a ese módulo.</div>
            <?php endif; ?>

            <!-- Métricas principales -->
            <div class="grid-metricas">
                <div class="card-metrica azul">
                    <div class="label">Total de alumnos</div>
                    <div class="valor"><?= $totalAlumnos ?></div>
                    <div class="ico">👤</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Alertas nuevas</div>
                    <div class="valor"><?= $alertasNuevas ?></div>
                    <div class="ico">🔔</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">En seguimiento</div>
                    <div class="valor"><?= $enSeguimiento ?></div>
                    <div class="ico">📝</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Casos críticos</div>
                    <div class="valor"><?= $criticos ?></div>
                    <div class="ico">⚠️</div>
                </div>
            </div>

            <!-- Distribución de riesgo -->
            <div class="grid-metricas">
                <div class="card-metrica verde">
                    <div class="label">Riesgo bajo</div>
                    <div class="valor"><?= $riesgos['BAJO'] ?></div>
                    <div class="ico">✅</div>
                </div>
                <div class="card-metrica amarillo">
                    <div class="label">Riesgo medio</div>
                    <div class="valor"><?= $riesgos['MEDIO'] ?></div>
                    <div class="ico">⚡</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Riesgo alto</div>
                    <div class="valor"><?= $riesgos['ALTO'] ?></div>
                    <div class="ico">🔥</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Riesgo crítico</div>
                    <div class="valor"><?= $riesgos['CRITICO'] ?></div>
                    <div class="ico">🚨</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">

                <!-- Últimas alertas -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>🔔 Últimas alertas</h3>
                        <a class="btn btn-secundario btn-sm" href="alertas.php">Ver todas</a>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Nivel</th>
                                    <th>Estatus</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($ultimasAlertas)): ?>
                                <tr><td colspan="4" class="texto-centro texto-gris" style="padding:20px;">Sin alertas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimasAlertas as $al): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($al['alumno_nombre']) ?></strong>
                                        <?php if ($al['NOMBRE_ESCUELA']): ?>
                                            <div class="texto-gris"><?= h($al['NOMBRE_ESCUELA']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= badgeRiesgo($al['NIVEL_RIESGO']) ?></td>
                                    <td><?= badgeEstatus($al['ESTATUS']) ?></td>
                                    <td class="texto-gris"><?= fechaCorta($al['FECHA_CREACION']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Alumnos en riesgo alto/crítico -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>⚠️ Alumnos en riesgo alto / crítico</h3>
                        <a class="btn btn-secundario btn-sm" href="alumnos.php">Ver todos</a>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Escuela</th>
                                    <th>Nivel</th>
                                    <th>Puntaje</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($alumnosCriticos)): ?>
                                <tr><td colspan="4" class="texto-centro texto-gris" style="padding:20px;">Sin alumnos en riesgo alto o crítico.</td></tr>
                            <?php else: ?>
                                <?php foreach ($alumnosCriticos as $ac): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($ac['nombre_completo']) ?></strong>
                                        <div class="texto-gris"><?= h($ac['MATRICULA']) ?></div>
                                    </td>
                                    <td class="texto-gris"><?= h($ac['NOMBRE_ESCUELA'] ?? '—') ?></td>
                                    <td><?= badgeRiesgo($ac['NIVEL_RIESGO']) ?></td>
                                    <td style="font-weight:700;color:#dc2626;"><?= $ac['PUNTAJE_RIESGO'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Accesos rápidos -->
            <?php if (in_array($rol, ['ADMIN','DOCENTE','ORIENTADOR'])): ?>
            <div class="panel mt-20">
                <div class="panel-header"><h3>⚡ Accesos rápidos</h3></div>
                <div class="panel-body flex-gap">
                    <a class="btn btn-primario" href="captura_sisat.php">📋 Nueva evaluación SISAT</a>
                    <a class="btn btn-exito"    href="alumnos.php?accion=nuevo">👤 Registrar alumno</a>
                    <a class="btn btn-secundario" href="alertas.php">🔔 Ver alertas</a>
                    <a class="btn btn-secundario" href="seguimiento.php">📝 Seguimiento</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /pagina -->
    </div><!-- /main-content -->
</div><!-- /layout -->
</body>
</html>
