<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN', 'SEDU', 'DIRECTOR']);

// ── Conteo general ────────────────────────────────────────────
$totalAlumnos    = $pdo->query("SELECT COUNT(*) FROM alumno WHERE ACTIVO = 1")->fetchColumn();
$totalEscuelas   = $pdo->query("SELECT COUNT(*) FROM escuela WHERE ACTIVA = 1")->fetchColumn();
$totalGrupos     = $pdo->query("SELECT COUNT(*) FROM grupo")->fetchColumn();
$totalEvals      = $pdo->query("SELECT COUNT(*) FROM evaluacion_sisat")->fetchColumn();
$totalAlertas    = $pdo->query("SELECT COUNT(*) FROM alerta")->fetchColumn();
$alertasAbiertas = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS NOT IN ('ATENDIDA','CERRADA')")->fetchColumn();
$alertasCerradas = $pdo->query("SELECT COUNT(*) FROM alerta WHERE ESTATUS IN ('ATENDIDA','CERRADA')")->fetchColumn();
$totalSeguim     = $pdo->query("SELECT COUNT(*) FROM seguimiento")->fetchColumn();

// ── Distribución de riesgo (última eval por alumno) ───────────
$distRaw = $pdo->query("
    SELECT ev.NIVEL_RIESGO, COUNT(*) AS total
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    GROUP BY ev.NIVEL_RIESGO
")->fetchAll();
$dist = ['BAJO' => 0, 'MEDIO' => 0, 'ALTO' => 0, 'CRITICO' => 0];
foreach ($distRaw as $r) $dist[$r['NIVEL_RIESGO']] = (int)$r['total'];

// ── Riesgo por municipio ──────────────────────────────────────
$porMunicipio = $pdo->query("
    SELECT
        esc.MUNICIPIO,
        COUNT(DISTINCT a.ID_ALUMNO) AS total_alumnos,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END) AS bajo,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END) AS medio,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END) AS alto,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    INNER JOIN alumno a ON a.ID_ALUMNO = ev.ID_ALUMNO AND a.ACTIVO = 1
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE esc.MUNICIPIO IS NOT NULL
    GROUP BY esc.MUNICIPIO
    ORDER BY (SUM(CASE WHEN ev.NIVEL_RIESGO='CRITICO' THEN 1 ELSE 0 END) +
              SUM(CASE WHEN ev.NIVEL_RIESGO='ALTO' THEN 1 ELSE 0 END)) DESC
    LIMIT 20
")->fetchAll();

// ── Alertas por estatus ───────────────────────────────────────
$alertasEstatus = $pdo->query("
    SELECT ESTATUS, COUNT(*) AS total
    FROM alerta
    GROUP BY ESTATUS
    ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA')
")->fetchAll();

// ── Evaluaciones por ciclo escolar ────────────────────────────
$porCiclo = $pdo->query("
    SELECT
        CASE
            WHEN MONTH(FECHA_EVALUACION) >= 9
                THEN CONCAT(YEAR(FECHA_EVALUACION),'-',YEAR(FECHA_EVALUACION)+1)
            ELSE CONCAT(YEAR(FECHA_EVALUACION)-1,'-',YEAR(FECHA_EVALUACION))
        END AS ciclo,
        COUNT(*) AS total,
        SUM(CASE WHEN NIVEL_RIESGO='MEDIO'   THEN 1 ELSE 0 END) AS medio,
        SUM(CASE WHEN NIVEL_RIESGO='ALTO'    THEN 1 ELSE 0 END) AS alto,
        SUM(CASE WHEN NIVEL_RIESGO='CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM evaluacion_sisat
    GROUP BY ciclo
    ORDER BY ciclo
")->fetchAll();

// ── Top 10 escuelas críticas ──────────────────────────────────
$top10 = $pdo->query("
    SELECT
        esc.NOMBRE_ESCUELA, esc.MUNICIPIO,
        COUNT(al.ID_ALERTA) AS criticos
    FROM alerta al
    INNER JOIN alumno a ON a.ID_ALUMNO = al.ID_ALUMNO
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE al.NIVEL_RIESGO = 'CRITICO'
      AND al.ESTATUS NOT IN ('ATENDIDA','CERRADA')
    GROUP BY esc.ID_ESCUELA
    ORDER BY criticos DESC
    LIMIT 10
")->fetchAll();

// ── Preparar datos para Chart.js ──────────────────────────────
$labMun   = json_encode(array_column($porMunicipio, 'MUNICIPIO'));
$datMedio = json_encode(array_column($porMunicipio, 'medio'));
$datAlto  = json_encode(array_column($porMunicipio, 'alto'));
$datCrit  = json_encode(array_column($porMunicipio, 'critico'));

$labEstatus = json_encode(array_map(function($r) {
    $mapa = ['NUEVA'=>'Nueva','EN_REVISION'=>'En revisión',
             'EN_SEGUIMIENTO'=>'En seguimiento','ATENDIDA'=>'Atendida','CERRADA'=>'Cerrada'];
    return $mapa[$r['ESTATUS']] ?? $r['ESTATUS'];
}, $alertasEstatus));
$datEstatus = json_encode(array_column($alertasEstatus, 'total'));

$labCiclo   = json_encode(array_column($porCiclo, 'ciclo'));
$datCicloM  = json_encode(array_column($porCiclo, 'medio'));
$datCicloA  = json_encode(array_column($porCiclo, 'alto'));
$datCicloC  = json_encode(array_column($porCiclo, 'critico'));

$datRiesgo = json_encode([
    $dist['BAJO'], $dist['MEDIO'], $dist['ALTO'], $dist['CRITICO']
]);

$paginaActual = 'dashboard_sisat.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISAT — Dashboard Simulación</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .chart-container {
            position: relative;
            background: #fff;
            border-radius: 13px;
            border: 1px solid #dce3ef;
            box-shadow: 0 3px 10px rgba(0,0,0,.05);
            padding: 20px 22px;
            margin-bottom: 22px;
        }
        .chart-container h3 {
            font-size: 15px;
            font-weight: 700;
            color: #061f45;
            margin-bottom: 16px;
        }
        .charts-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media(max-width:900px) { .charts-2col { grid-template-columns: 1fr; } }
        .badge-sim {
            display: inline-block;
            background: linear-gradient(135deg, #1e3a5f, #0b63ce);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .5px;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">
                📊 Dashboard Simulación SISAT
                <span class="badge-sim">TESIS</span>
            </div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <!-- ── Tarjetas de métricas ─────────────────────── -->
            <div class="grid-metricas">
                <div class="card-metrica azul">
                    <div class="label">Total alumnos</div>
                    <div class="valor"><?= number_format($totalAlumnos) ?></div>
                    <div class="ico">👤</div>
                </div>
                <div class="card-metrica gris">
                    <div class="label">Escuelas activas</div>
                    <div class="valor"><?= number_format($totalEscuelas) ?></div>
                    <div class="ico">🏫</div>
                </div>
                <div class="card-metrica azul">
                    <div class="label">Total evaluaciones</div>
                    <div class="valor"><?= number_format($totalEvals) ?></div>
                    <div class="ico">📋</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Total alertas</div>
                    <div class="valor"><?= number_format($totalAlertas) ?></div>
                    <div class="ico">🔔</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Alertas abiertas</div>
                    <div class="valor"><?= number_format($alertasAbiertas) ?></div>
                    <div class="ico">⚠️</div>
                </div>
                <div class="card-metrica verde">
                    <div class="label">Alertas cerradas</div>
                    <div class="valor"><?= number_format($alertasCerradas) ?></div>
                    <div class="ico">✅</div>
                </div>
            </div>

            <!-- Distribución de riesgo (tarjetas) -->
            <div class="grid-metricas">
                <div class="card-metrica verde">
                    <div class="label">Riesgo bajo</div>
                    <div class="valor"><?= number_format($dist['BAJO']) ?></div>
                </div>
                <div class="card-metrica amarillo">
                    <div class="label">Riesgo medio</div>
                    <div class="valor"><?= number_format($dist['MEDIO']) ?></div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Riesgo alto</div>
                    <div class="valor"><?= number_format($dist['ALTO']) ?></div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Riesgo crítico</div>
                    <div class="valor"><?= number_format($dist['CRITICO']) ?></div>
                </div>
            </div>

            <!-- ── Gráficas ─────────────────────────────────── -->
            <div class="charts-2col">

                <!-- Dona: distribución de riesgo -->
                <div class="chart-container">
                    <h3>🍩 Distribución de nivel de riesgo</h3>
                    <canvas id="chartDonaRiesgo" height="260"></canvas>
                </div>

                <!-- Dona: alertas por estatus -->
                <div class="chart-container">
                    <h3>🔔 Alertas por estatus</h3>
                    <canvas id="chartDonaEstatus" height="260"></canvas>
                </div>

            </div>

            <!-- Barras: riesgo por municipio -->
            <div class="chart-container">
                <h3>📍 Nivel de riesgo por municipio (top 20)</h3>
                <canvas id="chartBarMunicipio" height="120"></canvas>
            </div>

            <!-- Línea: evaluaciones por ciclo escolar -->
            <div class="chart-container">
                <h3>📈 Evaluaciones por nivel de riesgo — serie histórica por ciclo escolar</h3>
                <canvas id="chartLineaCiclo" height="100"></canvas>
            </div>

            <!-- ── Top 10 escuelas críticas ─────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🚨 Top 10 escuelas con más casos críticos abiertos</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Escuela</th>
                                <th>Municipio</th>
                                <th>Críticos abiertos</th>
                                <th>Barra</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($top10)): ?>
                            <tr><td colspan="5" class="texto-centro texto-gris" style="padding:20px;">Sin datos disponibles.</td></tr>
                        <?php else: ?>
                            <?php $maxCrit = max(array_column($top10, 'criticos') ?: [1]); ?>
                            <?php foreach ($top10 as $i => $t): ?>
                            <tr>
                                <td style="color:#64748b;font-weight:700;"><?= $i + 1 ?></td>
                                <td><strong><?= h($t['NOMBRE_ESCUELA'] ?? '—') ?></strong></td>
                                <td><?= h($t['MUNICIPIO'] ?? '—') ?></td>
                                <td style="font-size:20px;font-weight:900;color:#dc2626;"><?= number_format($t['criticos']) ?></td>
                                <td style="min-width:120px;">
                                    <div style="height:12px;background:#fee2e2;border-radius:6px;overflow:hidden;">
                                        <div style="height:100%;width:<?= round($t['criticos']/$maxCrit*100) ?>%;background:#dc2626;border-radius:6px;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Tabla: riesgo por municipio ─────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📍 Detalle de riesgo por municipio</h3>
                    <a class="btn btn-secundario btn-sm" href="reportes.php">Ver reportes completos</a>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Municipio</th>
                                <th>Alumnos eval.</th>
                                <th>🟢 Bajo</th>
                                <th>🟡 Medio</th>
                                <th>🟠 Alto</th>
                                <th>🔴 Crítico</th>
                                <th>% en riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($porMunicipio)): ?>
                            <tr><td colspan="7" class="texto-centro texto-gris" style="padding:20px;">
                                Sin datos. Genera e importa la simulación primero.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porMunicipio as $m): ?>
                            <?php
                                $total = $m['total_alumnos'] ?: 1;
                                $enRiesgo = $m['medio'] + $m['alto'] + $m['critico'];
                                $pct = round($enRiesgo / $total * 100, 1);
                            ?>
                            <tr>
                                <td><strong><?= h($m['MUNICIPIO'] ?? '—') ?></strong></td>
                                <td style="text-align:center;"><?= number_format($m['total_alumnos']) ?></td>
                                <td style="text-align:center;color:#166534;font-weight:700;"><?= number_format($m['bajo']) ?></td>
                                <td style="text-align:center;color:#854d0e;font-weight:700;"><?= number_format($m['medio']) ?></td>
                                <td style="text-align:center;color:#9a3412;font-weight:700;"><?= number_format($m['alto']) ?></td>
                                <td style="text-align:center;color:#991b1b;font-weight:700;"><?= number_format($m['critico']) ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                                            <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= $pct>50?'#dc2626':($pct>25?'#ea580c':'#16a34a') ?>;"></div>
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

            <!-- Info de generación -->
            <div class="panel">
                <div class="panel-header"><h3>ℹ️ Acerca de este dashboard</h3></div>
                <div class="panel-body">
                    <p style="font-size:14px;color:#475569;line-height:1.7;">
                        Los datos provienen de la base de datos <strong>sisat</strong> en MySQL.<br>
                        Para datos de simulación masiva (modo TESIS: ~150,000 alumnos, ~4.5M evaluaciones),
                        ejecuta el generador:
                    </p>
                    <pre style="background:#1e293b;color:#e2e8f0;padding:14px 18px;border-radius:9px;font-size:13px;margin-top:12px;overflow-x:auto;">
python simulacion_sisat/generar_datos_sisat.py --modo tesis
python simulacion_sisat/importar_csv_mysql.py</pre>
                    <div class="flex-gap mt-20">
                        <a class="btn btn-primario" href="reportes.php">📊 Reportes SEDU</a>
                        <a class="btn btn-secundario" href="alertas.php">🔔 Ver alertas</a>
                        <a class="btn btn-secundario" href="alumnos.php">👤 Ver alumnos</a>
                    </div>
                </div>
            </div>

        </div><!-- /pagina -->
    </div><!-- /main-content -->
</div><!-- /layout -->

<!-- ── Chart.js ─────────────────────────────────────────────── -->
<script>
Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#475569';

// ── 1. DONA: Distribución de riesgo ────────────────────────────
new Chart(document.getElementById('chartDonaRiesgo'), {
    type: 'doughnut',
    data: {
        labels: ['Bajo', 'Medio', 'Alto', 'Crítico'],
        datasets: [{
            data: <?= $datRiesgo ?>,
            backgroundColor: ['#16a34a', '#ca8a04', '#ea580c', '#dc2626'],
            borderWidth: 2, borderColor: '#fff',
        }],
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const pct   = total ? (ctx.raw / total * 100).toFixed(1) : 0;
                        return ` ${ctx.raw.toLocaleString()} alumnos (${pct}%)`;
                    }
                }
            }
        },
    },
});

// ── 2. DONA: Alertas por estatus ───────────────────────────────
new Chart(document.getElementById('chartDonaEstatus'), {
    type: 'doughnut',
    data: {
        labels: <?= $labEstatus ?>,
        datasets: [{
            data: <?= $datEstatus ?>,
            backgroundColor: ['#3b82f6','#f59e0b','#ea580c','#16a34a','#94a3b8'],
            borderWidth: 2, borderColor: '#fff',
        }],
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const pct   = total ? (ctx.raw / total * 100).toFixed(1) : 0;
                        return ` ${ctx.raw.toLocaleString()} (${pct}%)`;
                    }
                }
            }
        },
    },
});

// ── 3. BARRAS: Riesgo por municipio ────────────────────────────
new Chart(document.getElementById('chartBarMunicipio'), {
    type: 'bar',
    data: {
        labels: <?= $labMun ?>,
        datasets: [
            { label: 'Medio',   data: <?= $datMedio ?>, backgroundColor: '#fde047', stack: 's' },
            { label: 'Alto',    data: <?= $datAlto  ?>, backgroundColor: '#f97316', stack: 's' },
            { label: 'Crítico', data: <?= $datCrit  ?>, backgroundColor: '#dc2626', stack: 's' },
        ],
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: { stacked: true, ticks: { maxRotation: 45, minRotation: 30 } },
            y: { stacked: true, beginAtZero: true,
                 ticks: { callback: v => v.toLocaleString() } },
        },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString()}`
                }
            }
        },
    },
});

// ── 4. LÍNEA: Evaluaciones por ciclo escolar ───────────────────
new Chart(document.getElementById('chartLineaCiclo'), {
    type: 'line',
    data: {
        labels: <?= $labCiclo ?>,
        datasets: [
            {
                label: 'Medio',
                data: <?= $datCicloM ?>,
                borderColor: '#ca8a04', backgroundColor: 'rgba(202,138,4,.12)',
                tension: 0.35, fill: true, pointRadius: 4,
            },
            {
                label: 'Alto',
                data: <?= $datCicloA ?>,
                borderColor: '#ea580c', backgroundColor: 'rgba(234,88,12,.10)',
                tension: 0.35, fill: true, pointRadius: 4,
            },
            {
                label: 'Crítico',
                data: <?= $datCicloC ?>,
                borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.10)',
                tension: 0.35, fill: true, pointRadius: 4,
            },
        ],
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: { grid: { color: '#f1f5f9' } },
            y: { beginAtZero: true,
                 ticks: { callback: v => v.toLocaleString() },
                 grid: { color: '#f1f5f9' } },
        },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString()}`
                }
            }
        },
    },
});
</script>
</body>
</html>
