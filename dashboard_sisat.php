<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN', 'SEDU', 'DIRECTOR']);

// ── Leer tarjetas desde tabla resumen (sin tocar evaluacion_sisat) ──
$resumen      = null;
$resumenError = null;

try {
    $stmt   = $pdo->query("SELECT * FROM resumen_dashboard_sisat ORDER BY FECHA_ACTUALIZACION DESC LIMIT 1");
    $resumen = $stmt->fetch();
    if ($resumen === false) {
        $resumenError = 'vacia';
        $resumen      = null;
    }
} catch (PDOException $e) {
    $resumenError = 'no_existe';
}

// Extraer valores del resumen (0 como fallback seguro)
$totalAlumnos    = (int)($resumen['TOTAL_ALUMNOS']      ?? 0);
$totalEscuelas   = (int)($resumen['TOTAL_ESCUELAS']     ?? 0);
$totalEvals      = (int)($resumen['TOTAL_EVALUACIONES'] ?? 0);
$totalAlertas    = (int)($resumen['TOTAL_ALERTAS']      ?? 0);
$alertasAbiertas = (int)($resumen['ALERTAS_ABIERTAS']   ?? 0);
$alertasCerradas = (int)($resumen['ALERTAS_CERRADAS']   ?? 0);
$dist = [
    'BAJO'    => (int)($resumen['RIESGO_BAJO']    ?? 0),
    'MEDIO'   => (int)($resumen['RIESGO_MEDIO']   ?? 0),
    'ALTO'    => (int)($resumen['RIESGO_ALTO']    ?? 0),
    'CRITICO' => (int)($resumen['RIESGO_CRITICO'] ?? 0),
];
$fechaResumen = $resumen['FECHA_ACTUALIZACION'] ?? null;

// ── Alertas por estatus (tabla ligera) ────────────────────────
$alertasEstatus = $pdo->query("
    SELECT ESTATUS, COUNT(*) AS total
    FROM alerta
    GROUP BY ESTATUS
    ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA')
")->fetchAll();

// ── Riesgo por municipio (agrupado, solo si hay datos) ────────
$porMunicipio = $pdo->query("
    SELECT
        esc.MUNICIPIO,
        COUNT(DISTINCT a.ID_ALUMNO)                                    AS total_alumnos,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'BAJO'    THEN 1 ELSE 0 END)  AS bajo,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'MEDIO'   THEN 1 ELSE 0 END)  AS medio,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'ALTO'    THEN 1 ELSE 0 END)  AS alto,
        SUM(CASE WHEN ev.NIVEL_RIESGO = 'CRITICO' THEN 1 ELSE 0 END)  AS critico
    FROM evaluacion_sisat ev
    INNER JOIN (
        SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima
        FROM evaluacion_sisat GROUP BY ID_ALUMNO
    ) ult ON ev.ID_ALUMNO = ult.ID_ALUMNO AND ev.ID_EVALUACION = ult.ultima
    INNER JOIN alumno  a   ON a.ID_ALUMNO    = ev.ID_ALUMNO AND a.ACTIVO = 1
    LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
    LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE esc.MUNICIPIO IS NOT NULL
    GROUP BY esc.MUNICIPIO
    ORDER BY (SUM(CASE WHEN ev.NIVEL_RIESGO='CRITICO' THEN 1 ELSE 0 END) +
              SUM(CASE WHEN ev.NIVEL_RIESGO='ALTO'    THEN 1 ELSE 0 END)) DESC
    LIMIT 20
")->fetchAll();

// ── Evaluaciones por ciclo (inferido desde fecha) ─────────────
$porCiclo = $pdo->query("
    SELECT
        CASE
            WHEN MONTH(FECHA_EVALUACION) >= 9
                THEN CONCAT(YEAR(FECHA_EVALUACION),'-',YEAR(FECHA_EVALUACION)+1)
            ELSE CONCAT(YEAR(FECHA_EVALUACION)-1,'-',YEAR(FECHA_EVALUACION))
        END                                                AS ciclo,
        COUNT(*)                                           AS total,
        SUM(CASE WHEN NIVEL_RIESGO='MEDIO'   THEN 1 ELSE 0 END) AS medio,
        SUM(CASE WHEN NIVEL_RIESGO='ALTO'    THEN 1 ELSE 0 END) AS alto,
        SUM(CASE WHEN NIVEL_RIESGO='CRITICO' THEN 1 ELSE 0 END) AS critico
    FROM evaluacion_sisat
    GROUP BY ciclo
    ORDER BY ciclo
")->fetchAll();

// ── Top 10 escuelas críticas (tabla alerta, sin escanear eval) ─
$top10 = $pdo->query("
    SELECT
        esc.NOMBRE_ESCUELA, esc.MUNICIPIO,
        COUNT(al.ID_ALERTA) AS criticos
    FROM alerta al
    INNER JOIN alumno  a   ON a.ID_ALUMNO    = al.ID_ALUMNO
    LEFT  JOIN grupo   g   ON g.ID_GRUPO     = a.ID_GRUPO
    LEFT  JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE al.NIVEL_RIESGO = 'CRITICO'
      AND al.ESTATUS NOT IN ('ATENDIDA','CERRADA')
    GROUP BY esc.ID_ESCUELA
    ORDER BY criticos DESC
    LIMIT 10
")->fetchAll();

// ── Datos para Chart.js ───────────────────────────────────────
$labMun   = json_encode(array_column($porMunicipio, 'MUNICIPIO'));
$datMedio = json_encode(array_column($porMunicipio, 'medio'));
$datAlto  = json_encode(array_column($porMunicipio, 'alto'));
$datCrit  = json_encode(array_column($porMunicipio, 'critico'));

$labEstatus = json_encode(array_map(function ($r) {
    return ['NUEVA'=>'Nueva','EN_REVISION'=>'En revisión',
            'EN_SEGUIMIENTO'=>'En seguimiento','ATENDIDA'=>'Atendida',
            'CERRADA'=>'Cerrada'][$r['ESTATUS']] ?? $r['ESTATUS'];
}, $alertasEstatus));
$datEstatus = json_encode(array_column($alertasEstatus, 'total'));

$labCiclo  = json_encode(array_column($porCiclo, 'ciclo'));
$datCicloM = json_encode(array_column($porCiclo, 'medio'));
$datCicloA = json_encode(array_column($porCiclo, 'alto'));
$datCicloC = json_encode(array_column($porCiclo, 'critico'));

// Dona de riesgo usa directamente el resumen
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
        /* ── Grid de tarjetas: responsive, sin overflow ─────────── */
        .grid-metricas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .card-metrica {
            min-width: 0;          /* evita que flexbox/grid expanda */
            max-width: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }

        /* Número grande: se achica cuando el espacio es poco */
        .card-metrica .valor {
            font-size: clamp(22px, 2.4vw, 40px);
            font-weight: 900;
            color: #061f45;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Contenedor principal: sin scroll horizontal */
        .pagina,
        .main-content {
            overflow-x: hidden;
        }

        /* ── Charts ──────────────────────────────────────────────── */
        .chart-container {
            background: #fff;
            border-radius: 13px;
            border: 1px solid #dce3ef;
            box-shadow: 0 3px 10px rgba(0,0,0,.05);
            padding: 20px 22px;
            margin-bottom: 22px;
            min-width: 0;
            overflow: hidden;
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
        @media (max-width: 900px) {
            .charts-2col { grid-template-columns: 1fr; }
        }

        /* ── Badge TESIS ─────────────────────────────────────────── */
        .badge-sim {
            display: inline-block;
            background: linear-gradient(135deg, #1e3a5f, #0b63ce);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .5px;
            vertical-align: middle;
        }

        /* ── Banner de aviso resumen ─────────────────────────────── */
        .aviso-resumen {
            background: #fefce8;
            border: 1.5px solid #fde047;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .aviso-resumen .aviso-ico { font-size: 26px; line-height: 1; }
        .aviso-resumen h4 { color: #713f12; font-size: 15px; margin-bottom: 4px; }
        .aviso-resumen p  { color: #78350f; font-size: 13px; line-height: 1.6; margin: 0; }
        .aviso-resumen code {
            display: inline-block;
            background: #1e293b;
            color: #fde047;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-family: monospace;
        }

        /* ── Fecha de actualización ──────────────────────────────── */
        .resumen-meta {
            font-size: 12px;
            color: #94a3b8;
            text-align: right;
            margin-top: -14px;
            margin-bottom: 18px;
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

            <?php if ($resumenError !== null): ?>
            <!-- ── Aviso: tabla resumen no disponible ─────────────── -->
            <div class="aviso-resumen">
                <div class="aviso-ico">⚠️</div>
                <div>
                    <h4>
                        <?= $resumenError === 'no_existe'
                            ? 'La tabla resumen_dashboard_sisat no existe'
                            : 'La tabla resumen_dashboard_sisat está vacía' ?>
                    </h4>
                    <p>
                        Las tarjetas de totales no pueden mostrarse porque la tabla de resumen
                        <?= $resumenError === 'no_existe' ? 'no ha sido creada' : 'no tiene datos' ?>.
                        Las gráficas siguen funcionando directamente desde la base de datos.<br><br>
                        Para generar el resumen, ejecuta en MySQL Workbench o phpMyAdmin:<br>
                        <code>actualizar_resumen_dashboard.sql</code>
                        &nbsp;—&nbsp; o corre el script desde la terminal:
                        <code>mysql -u root -p sisat &lt; actualizar_resumen_dashboard.sql</code>
                    </p>
                </div>
            </div>
            <?php elseif ($fechaResumen): ?>
            <div class="resumen-meta">
                📅 Datos actualizados al: <strong><?= h(fechaHora($fechaResumen)) ?></strong>
                &nbsp;·&nbsp;
                <a href="?refrescar=1" style="color:#0b63ce;font-size:12px;">Forzar recarga de gráficas</a>
            </div>
            <?php endif; ?>

            <!-- ── Fila 1: Totales generales ──────────────────────── -->
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

            <!-- ── Fila 2: Distribución de riesgo ────────────────── -->
            <div class="grid-metricas">
                <div class="card-metrica verde">
                    <div class="label">Riesgo bajo</div>
                    <div class="valor"><?= number_format($dist['BAJO']) ?></div>
                    <div class="ico">🟢</div>
                </div>
                <div class="card-metrica amarillo">
                    <div class="label">Riesgo medio</div>
                    <div class="valor"><?= number_format($dist['MEDIO']) ?></div>
                    <div class="ico">🟡</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Riesgo alto</div>
                    <div class="valor"><?= number_format($dist['ALTO']) ?></div>
                    <div class="ico">🟠</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Riesgo crítico</div>
                    <div class="valor"><?= number_format($dist['CRITICO']) ?></div>
                    <div class="ico">🔴</div>
                </div>
            </div>

            <!-- ── Gráficas: dona riesgo + dona estatus ───────────── -->
            <div class="charts-2col">
                <div class="chart-container">
                    <h3>🍩 Distribución de nivel de riesgo</h3>
                    <canvas id="chartDonaRiesgo" height="260"></canvas>
                </div>
                <div class="chart-container">
                    <h3>🔔 Alertas por estatus</h3>
                    <canvas id="chartDonaEstatus" height="260"></canvas>
                </div>
            </div>

            <!-- ── Barras: riesgo por municipio ──────────────────── -->
            <div class="chart-container">
                <h3>📍 Nivel de riesgo por municipio (top 20)</h3>
                <canvas id="chartBarMunicipio" height="120"></canvas>
            </div>

            <!-- ── Línea: historial por ciclo escolar ────────────── -->
            <div class="chart-container">
                <h3>📈 Evaluaciones por nivel de riesgo — serie por ciclo escolar</h3>
                <canvas id="chartLineaCiclo" height="100"></canvas>
            </div>

            <!-- ── Top 10 escuelas críticas ───────────────────────── -->
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
                            <tr>
                                <td colspan="5" class="texto-centro texto-gris" style="padding:20px;">
                                    Sin casos críticos abiertos.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $maxCrit = max(array_column($top10, 'criticos') ?: [1]); ?>
                            <?php foreach ($top10 as $i => $t): ?>
                            <tr>
                                <td style="color:#64748b;font-weight:700;"><?= $i + 1 ?></td>
                                <td><strong><?= h($t['NOMBRE_ESCUELA'] ?? '—') ?></strong></td>
                                <td><?= h($t['MUNICIPIO'] ?? '—') ?></td>
                                <td style="font-size:20px;font-weight:900;color:#dc2626;">
                                    <?= number_format($t['criticos']) ?>
                                </td>
                                <td style="min-width:120px;">
                                    <div style="height:12px;background:#fee2e2;border-radius:6px;overflow:hidden;">
                                        <div style="height:100%;width:<?= round($t['criticos'] / $maxCrit * 100) ?>%;background:#dc2626;border-radius:6px;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Detalle por municipio ──────────────────────────── -->
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
                            <tr>
                                <td colspan="7" class="texto-centro texto-gris" style="padding:20px;">
                                    Sin datos. Importa la simulación primero.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($porMunicipio as $m): ?>
                            <?php
                                $tot      = (int)$m['total_alumnos'] ?: 1;
                                $enRiesgo = (int)$m['medio'] + (int)$m['alto'] + (int)$m['critico'];
                                $pct      = round($enRiesgo / $tot * 100, 1);
                                $barColor = $pct > 50 ? '#dc2626' : ($pct > 25 ? '#ea580c' : '#16a34a');
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
                                            <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= $barColor ?>;"></div>
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

            <!-- ── Panel informativo ──────────────────────────────── -->
            <div class="panel">
                <div class="panel-header"><h3>ℹ️ Acerca de este dashboard</h3></div>
                <div class="panel-body">
                    <p style="font-size:14px;color:#475569;line-height:1.8;">
                        Las <strong>tarjetas de totales y riesgo</strong> se leen desde
                        <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-size:13px;">resumen_dashboard_sisat</code>
                        para garantizar velocidad con millones de registros.<br>
                        Las <strong>gráficas</strong> se calculan en tiempo real desde las tablas operativas.
                    </p>
                    <p style="font-size:13px;color:#64748b;margin-top:8px;">
                        Para recalcular los totales después de importar datos nuevos, ejecuta:
                    </p>
                    <pre style="background:#1e293b;color:#e2e8f0;padding:14px 18px;border-radius:9px;font-size:13px;margin-top:10px;overflow-x:auto;white-space:pre-wrap;">mysql -u root -p sisat &lt; actualizar_resumen_dashboard.sql</pre>
                    <div class="flex-gap mt-20">
                        <a class="btn btn-primario"   href="reportes.php">📊 Reportes SEDU</a>
                        <a class="btn btn-secundario" href="alertas.php">🔔 Ver alertas</a>
                        <a class="btn btn-secundario" href="alumnos.php">👤 Ver alumnos</a>
                    </div>
                </div>
            </div>

        </div><!-- /pagina -->
    </div><!-- /main-content -->
</div><!-- /layout -->

<script>
Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#475569';

// ── 1. DONA: Distribución de riesgo (desde resumen) ────────────
new Chart(document.getElementById('chartDonaRiesgo'), {
    type: 'doughnut',
    data: {
        labels: ['Bajo', 'Medio', 'Alto', 'Crítico'],
        datasets: [{
            data: <?= $datRiesgo ?>,
            backgroundColor: ['#16a34a', '#ca8a04', '#ea580c', '#dc2626'],
            borderWidth: 2,
            borderColor: '#fff',
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
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct   = total ? (ctx.raw / total * 100).toFixed(1) : 0;
                        return ` ${ctx.raw.toLocaleString()} alumnos (${pct}%)`;
                    },
                },
            },
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
            backgroundColor: ['#3b82f6', '#f59e0b', '#ea580c', '#16a34a', '#94a3b8'],
            borderWidth: 2,
            borderColor: '#fff',
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
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct   = total ? (ctx.raw / total * 100).toFixed(1) : 0;
                        return ` ${ctx.raw.toLocaleString()} (${pct}%)`;
                    },
                },
            },
        },
    },
});

// ── 3. BARRAS APILADAS: riesgo por municipio ───────────────────
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
            tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString()}` } },
        },
    },
});

// ── 4. LÍNEA: historial por ciclo escolar ──────────────────────
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
            tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString()}` } },
        },
    },
});
</script>
</body>
</html>
