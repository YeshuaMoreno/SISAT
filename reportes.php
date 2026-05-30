<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN','SEDU','DIRECTOR']);

// ── Totales generales ──────────────────────────────────────────
$totalAlumnos  = $pdo->query("SELECT COUNT(*) FROM alumno WHERE ACTIVO = 1")->fetchColumn();
$totalEscuelas = $pdo->query("SELECT COUNT(*) FROM escuela WHERE ACTIVA = 1")->fetchColumn();
$totalGrupos   = $pdo->query("SELECT COUNT(*) FROM grupo")->fetchColumn();
$totalAlertas  = $pdo->query("SELECT COUNT(*) FROM alerta")->fetchColumn();

$alertasAbiertas = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS NOT IN ('CERRADA','ATENDIDA')")->fetchColumn();
$alertasCerradas = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS IN ('CERRADA','ATENDIDA')")->fetchColumn();

// Distribución de riesgo
$distRiesgoRaw = $pdo->query("
    SELECT ev.NIVEL_RIESGO, COUNT(*) AS total
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    INNER JOIN alumno a ON a.ID_ALUMNO = ev.ID_ALUMNO AND a.ACTIVO = 1
    GROUP BY ev.NIVEL_RIESGO
")->fetchAll();
$distRiesgo = ['BAJO' => 0, 'MEDIO' => 0, 'ALTO' => 0, 'CRITICO' => 0];
foreach ($distRiesgoRaw as $r) $distRiesgo[$r['NIVEL_RIESGO']] = (int)$r['total'];

// ── Casos por escuela ──────────────────────────────────────────
$porEscuela = $pdo->query("
    SELECT esc.NOMBRE_ESCUELA, esc.MUNICIPIO, esc.NIVEL,
           COUNT(DISTINCT a.ID_ALUMNO) AS total_alumnos,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END) AS bajo,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END) AS medio,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM escuela esc
    LEFT JOIN grupo g ON g.ID_ESCUELA = esc.ID_ESCUELA
    LEFT JOIN alumno a ON a.ID_GRUPO = g.ID_GRUPO AND a.ACTIVO = 1
    LEFT JOIN (
        SELECT ev1.*
        FROM evaluacion_sisat ev1
        INNER JOIN (
            SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima FROM evaluacion_sisat GROUP BY ID_ALUMNO
        ) ult ON ev1.ID_ALUMNO = ult.ID_ALUMNO AND ev1.ID_EVALUACION = ult.ultima
    ) ev ON ev.ID_ALUMNO = a.ID_ALUMNO
    WHERE esc.ACTIVA = 1
    GROUP BY esc.ID_ESCUELA
    ORDER BY (SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) + SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO' THEN 1 ELSE 0 END)) DESC
")->fetchAll();

// ── Casos por municipio ────────────────────────────────────────
$porMunicipio = $pdo->query("
    SELECT esc.MUNICIPIO,
           COUNT(DISTINCT a.ID_ALUMNO) AS total_alumnos,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END) AS bajo,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END) AS medio,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
           SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM escuela esc
    LEFT JOIN grupo g ON g.ID_ESCUELA = esc.ID_ESCUELA
    LEFT JOIN alumno a ON a.ID_GRUPO = g.ID_GRUPO AND a.ACTIVO = 1
    LEFT JOIN (
        SELECT ev1.*
        FROM evaluacion_sisat ev1
        INNER JOIN (
            SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima FROM evaluacion_sisat GROUP BY ID_ALUMNO
        ) ult ON ev1.ID_ALUMNO = ult.ID_ALUMNO AND ev1.ID_EVALUACION = ult.ultima
    ) ev ON ev.ID_ALUMNO = a.ID_ALUMNO
    WHERE esc.ACTIVA = 1
    GROUP BY esc.MUNICIPIO
    ORDER BY esc.MUNICIPIO
")->fetchAll();

// ── Alumnos críticos ───────────────────────────────────────────
$alumnosCriticos = $pdo->query("
    SELECT a.MATRICULA, CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO,' ',a.APELLIDO_MATERNO) AS nombre_completo,
           ev.PUNTAJE_RIESGO, ev.ASISTENCIA_PORCENTAJE, ev.PROMEDIO_GENERAL, ev.FECHA_EVALUACION,
           g.GRADO, g.GRUPO AS letra_grupo, esc.NOMBRE_ESCUELA, esc.MUNICIPIO,
           al.ESTATUS AS estatus_alerta
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    INNER JOIN alumno a ON a.ID_ALUMNO = ev.ID_ALUMNO AND a.ACTIVO = 1
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    LEFT JOIN alerta al ON al.ID_EVALUACION = ev.ID_EVALUACION
    WHERE ev.NIVEL_RIESGO = 'CRITICO'
    ORDER BY ev.PUNTAJE_RIESGO DESC
")->fetchAll();

// ── Tendencia de alertas por mes (últimos 6 meses) ─────────────
$tendencia = $pdo->query("
    SELECT DATE_FORMAT(FECHA_CREACION, '%Y-%m') AS mes,
           COUNT(*) AS total,
           SUM(CASE WHEN NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
           SUM(CASE WHEN NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM alerta
    WHERE FECHA_CREACION >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes
")->fetchAll();

$paginaActual = 'reportes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Panel SEDU / Reportes</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">📊 Panel SEDU — Reportes estadísticos</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <!-- ═══ Métricas generales ════════════════════════════════ -->
            <div class="grid-metricas">
                <div class="card-metrica azul">
                    <div class="label">Total de alumnos</div>
                    <div class="valor"><?= $totalAlumnos ?></div>
                    <div class="ico">👤</div>
                </div>
                <div class="card-metrica gris">
                    <div class="label">Escuelas activas</div>
                    <div class="valor"><?= $totalEscuelas ?></div>
                    <div class="ico">🏫</div>
                </div>
                <div class="card-metrica azul">
                    <div class="label">Grupos</div>
                    <div class="valor"><?= $totalGrupos ?></div>
                    <div class="ico">📚</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Total alertas</div>
                    <div class="valor"><?= $totalAlertas ?></div>
                    <div class="ico">🔔</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Alertas abiertas</div>
                    <div class="valor"><?= $alertasAbiertas ?></div>
                    <div class="ico">⚠️</div>
                </div>
                <div class="card-metrica verde">
                    <div class="label">Alertas cerradas</div>
                    <div class="valor"><?= $alertasCerradas ?></div>
                    <div class="ico">✅</div>
                </div>
            </div>

            <!-- Distribución de riesgo -->
            <div class="grid-metricas">
                <div class="card-metrica verde">
                    <div class="label">Riesgo bajo</div>
                    <div class="valor"><?= $distRiesgo['BAJO'] ?></div>
                </div>
                <div class="card-metrica amarillo">
                    <div class="label">Riesgo medio</div>
                    <div class="valor"><?= $distRiesgo['MEDIO'] ?></div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Riesgo alto</div>
                    <div class="valor"><?= $distRiesgo['ALTO'] ?></div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Riesgo crítico</div>
                    <div class="valor"><?= $distRiesgo['CRITICO'] ?></div>
                </div>
            </div>

            <!-- ═══ Casos por escuela ═══════════════════════════════ -->
            <div class="panel">
                <div class="panel-header"><h3>🏫 Distribución de riesgo por escuela</h3></div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Escuela</th>
                                <th>Municipio</th>
                                <th>Nivel</th>
                                <th>Alumnos</th>
                                <th>🟢 Bajo</th>
                                <th>🟡 Medio</th>
                                <th>🟠 Alto</th>
                                <th>🔴 Crítico</th>
                                <th>% en riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($porEscuela)): ?>
                            <tr><td colspan="9" class="texto-centro texto-gris" style="padding:20px;">Sin datos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porEscuela as $e): ?>
                            <?php
                                $enRiesgo = $e['medio'] + $e['alto'] + $e['critico'];
                                $pct = $e['total_alumnos'] > 0 ? round($enRiesgo / $e['total_alumnos'] * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?= h($e['NOMBRE_ESCUELA']) ?></strong></td>
                                <td><?= h($e['MUNICIPIO']) ?></td>
                                <td><?= h($e['NIVEL']) ?></td>
                                <td style="text-align:center;"><?= $e['total_alumnos'] ?></td>
                                <td style="text-align:center;color:#166534;font-weight:700;"><?= $e['bajo'] ?></td>
                                <td style="text-align:center;color:#854d0e;font-weight:700;"><?= $e['medio'] ?></td>
                                <td style="text-align:center;color:#9a3412;font-weight:700;"><?= $e['alto'] ?></td>
                                <td style="text-align:center;color:#991b1b;font-weight:700;"><?= $e['critico'] ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                                            <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct > 50 ? '#dc2626' : ($pct > 25 ? '#ea580c' : '#16a34a') ?>;border-radius:4px;"></div>
                                        </div>
                                        <span style="font-size:12px;font-weight:700;"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══ Casos por municipio ══════════════════════════════ -->
            <div class="panel">
                <div class="panel-header"><h3>📍 Distribución de riesgo por municipio</h3></div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Municipio</th>
                                <th>Alumnos</th>
                                <th>🟢 Bajo</th>
                                <th>🟡 Medio</th>
                                <th>🟠 Alto</th>
                                <th>🔴 Crítico</th>
                                <th>En riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($porMunicipio)): ?>
                            <tr><td colspan="7" class="texto-centro texto-gris" style="padding:20px;">Sin datos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porMunicipio as $m): ?>
                            <tr>
                                <td><strong><?= h($m['MUNICIPIO']) ?></strong></td>
                                <td style="text-align:center;"><?= $m['total_alumnos'] ?></td>
                                <td style="text-align:center;color:#166534;font-weight:700;"><?= $m['bajo'] ?></td>
                                <td style="text-align:center;color:#854d0e;font-weight:700;"><?= $m['medio'] ?></td>
                                <td style="text-align:center;color:#9a3412;font-weight:700;"><?= $m['alto'] ?></td>
                                <td style="text-align:center;color:#991b1b;font-weight:700;"><?= $m['critico'] ?></td>
                                <td style="text-align:center;font-weight:700;color:#dc2626;"><?= $m['medio'] + $m['alto'] + $m['critico'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══ Tendencia de alertas por mes ════════════════════ -->
            <?php if (!empty($tendencia)): ?>
            <div class="panel">
                <div class="panel-header"><h3>📈 Alertas generadas (últimos 6 meses)</h3></div>
                <div class="panel-body" style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Total alertas</th>
                                <th>Alto</th>
                                <th>Crítico</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tendencia as $t): ?>
                            <tr>
                                <td><?= h($t['mes']) ?></td>
                                <td style="font-weight:700;"><?= $t['total'] ?></td>
                                <td style="color:#9a3412;font-weight:700;"><?= $t['alto'] ?></td>
                                <td style="color:#991b1b;font-weight:700;"><?= $t['critico'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ Alumnos críticos ══════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🚨 Alumnos en nivel CRÍTICO</h3>
                    <span class="badge-riesgo badge-critico"><?= count($alumnosCriticos) ?> casos</span>
                </div>
                <?php if (empty($alumnosCriticos)): ?>
                    <div class="panel-body texto-gris texto-centro">No hay alumnos en nivel crítico.</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre</th>
                                <th>Escuela</th>
                                <th>Municipio</th>
                                <th>Asist.</th>
                                <th>Prom.</th>
                                <th>Puntaje</th>
                                <th>Alerta</th>
                                <th>Última eval.</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($alumnosCriticos as $ac): ?>
                            <tr>
                                <td><?= h($ac['MATRICULA']) ?></td>
                                <td><strong><?= h($ac['nombre_completo']) ?></strong></td>
                                <td><?= h($ac['NOMBRE_ESCUELA'] ?? '—') ?></td>
                                <td><?= h($ac['MUNICIPIO'] ?? '—') ?></td>
                                <td style="color:<?= $ac['ASISTENCIA_PORCENTAJE'] < 80 ? '#dc2626' : '#1e293b' ?>;font-weight:600;">
                                    <?= number_format($ac['ASISTENCIA_PORCENTAJE'], 1) ?>%
                                </td>
                                <td style="color:<?= $ac['PROMEDIO_GENERAL'] < 6 ? '#dc2626' : '#1e293b' ?>;font-weight:600;">
                                    <?= number_format($ac['PROMEDIO_GENERAL'], 1) ?>
                                </td>
                                <td style="font-weight:900;color:#dc2626;font-size:18px;"><?= $ac['PUNTAJE_RIESGO'] ?></td>
                                <td><?= $ac['estatus_alerta'] ? badgeEstatus($ac['estatus_alerta']) : '<span class="texto-gris">—</span>' ?></td>
                                <td class="texto-gris"><?= fechaCorta($ac['FECHA_EVALUACION']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
