<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN', 'SEDU', 'DIRECTOR']);

// ── Filtros GET ───────────────────────────────────────────────
$filtroMun   = trim($_GET['municipio'] ?? '');
$filtroNivel = trim($_GET['nivel']     ?? '');
$filtroLimit = (int)($_GET['limite']   ?? 50);
if (!in_array($filtroLimit, [25, 50, 100, 200])) $filtroLimit = 50;

// ── Helper: detectar tabla resumen ───────────────────────────
function leerResumen(PDO $pdo, string $sql, array $params = []): array|false
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (PDOException $e) {
        return false;
    }
}
function leerUno(PDO $pdo, string $sql, array $params = []): array|false
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

// ── 1. Tarjetas generales — desde resumen_dashboard_sisat ────
$resumenGeneral = leerUno($pdo,
    "SELECT * FROM resumen_dashboard_sisat ORDER BY FECHA_ACTUALIZACION DESC LIMIT 1"
);
$sinResumenGeneral = ($resumenGeneral === false);

// ── 2. Por municipio ──────────────────────────────────────────
$sqlMun  = "SELECT * FROM resumen_riesgo_municipio";
$parMun  = [];
if ($filtroMun !== '') { $sqlMun .= " WHERE MUNICIPIO = ?"; $parMun[] = $filtroMun; }
$sqlMun .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC";
$porMunicipio   = leerResumen($pdo, $sqlMun, $parMun);
$sinMunicipio   = ($porMunicipio === false);

// ── 3. Por escuela ────────────────────────────────────────────
$sqlEsc  = "SELECT * FROM resumen_riesgo_escuela WHERE 1=1";
$parEsc  = [];
if ($filtroMun   !== '') { $sqlEsc .= " AND MUNICIPIO = ?"; $parEsc[] = $filtroMun; }
if ($filtroNivel !== '') { $sqlEsc .= " AND NIVEL = ?";     $parEsc[] = $filtroNivel; }
$sqlEsc .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC LIMIT ?";
$parEsc[] = $filtroLimit;
$porEscuela   = leerResumen($pdo, $sqlEsc, $parEsc);
$sinEscuela   = ($porEscuela === false);

// ── 4. Alertas por estatus ────────────────────────────────────
$alertasEstatus = leerResumen($pdo,
    "SELECT * FROM resumen_alertas_estatus
     ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA')"
);
$sinAlertas = ($alertasEstatus === false);

// ── 5. Top escuelas críticas ──────────────────────────────────
$sqlTop = "SELECT * FROM resumen_top_escuelas_criticas WHERE 1=1";
$parTop = [];
if ($filtroMun !== '') { $sqlTop .= " AND MUNICIPIO = ?"; $parTop[] = $filtroMun; }
$sqlTop .= " ORDER BY CASOS_CRITICOS DESC LIMIT 20";
$topCriticas   = leerResumen($pdo, $sqlTop, $parTop);
$sinTopCriticas = ($topCriticas === false);

// ── 6. Lista de municipios para el filtro ─────────────────────
$listaMunicipios = leerResumen($pdo,
    "SELECT MUNICIPIO FROM resumen_riesgo_municipio ORDER BY MUNICIPIO"
);
if ($listaMunicipios === false) {
    // fallback: desde escuela
    $listaMunicipios = leerResumen($pdo,
        "SELECT DISTINCT MUNICIPIO FROM escuela WHERE ACTIVA=1 ORDER BY MUNICIPIO"
    ) ?: [];
}

// ── Hay alguna tabla resumen faltante? ────────────────────────
$faltanResumenes = $sinMunicipio || $sinEscuela || $sinAlertas || $sinTopCriticas;

// ── Construir query string de filtros activos para links export
$qExport = http_build_query(array_filter([
    'municipio' => $filtroMun,
    'nivel'     => $filtroNivel,
]));
$qExport = $qExport ? '&' . $qExport : '';

$paginaActual = 'reportes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Reportes institucionales</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Tarjetas sin overflow */
        .grid-metricas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-metrica { min-width: 0; overflow: hidden; }
        .card-metrica .valor {
            font-size: clamp(20px, 2vw, 36px);
            font-weight: 900;
            color: #061f45;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pagina, .main-content { overflow-x: hidden; }

        /* Barra de porcentaje inline */
        .barra-pct {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .barra-pct-track {
            flex: 1;
            background: #f1f5f9;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }
        .barra-pct-fill { height: 100%; border-radius: 4px; }

        /* Aviso resumen faltante */
        .aviso-resumen {
            background: #fefce8;
            border: 1.5px solid #fde047;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 22px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .aviso-resumen .ico { font-size: 24px; }
        .aviso-resumen h4   { color: #713f12; margin-bottom: 4px; }
        .aviso-resumen p    { color: #78350f; font-size: 13px; line-height: 1.6; margin: 0; }
        .aviso-resumen code {
            background: #1e293b; color: #fde047;
            padding: 2px 7px; border-radius: 4px;
            font-size: 12px; font-family: monospace;
        }

        /* Etiqueta resumen vacío dentro de tabla */
        .sin-datos-resumen {
            padding: 16px;
            color: #64748b;
            font-size: 13px;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">

        <div class="topbar">
            <div class="topbar-titulo">📊 Reportes Institucionales SISAT</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>

        <div class="pagina">

            <?php if ($faltanResumenes): ?>
            <!-- ── Aviso: ejecutar script de resumen ──────────── -->
            <div class="aviso-resumen">
                <div class="ico">⚠️</div>
                <div>
                    <h4>Tablas de resumen no generadas</h4>
                    <p>
                        Los reportes por municipio, escuela y top críticas usan tablas resumen que
                        aún no existen o están vacías. Ejecuta el script en la terminal:<br><br>
                        <code>"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root &lt; actualizar_resumen_reportes.sql</code><br><br>
                        O abre <code>actualizar_resumen_reportes.sql</code> en phpMyAdmin y ejecútalo.
                        Las métricas generales se siguen mostrando mientras exista <code>resumen_dashboard_sisat</code>.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Filtros ─────────────────────────────────────── -->
            <div class="panel" style="margin-bottom:22px;">
                <div class="panel-header"><h3>🔍 Filtros de reporte</h3></div>
                <div class="panel-body">
                    <form method="GET" class="filtros">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Municipio</label>
                            <select name="municipio" style="min-width:180px;">
                                <option value="">Todos</option>
                                <?php foreach ($listaMunicipios as $m): ?>
                                    <option value="<?= h($m['MUNICIPIO']) ?>"
                                        <?= $filtroMun === $m['MUNICIPIO'] ? 'selected' : '' ?>>
                                        <?= h($m['MUNICIPIO']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Nivel educativo</label>
                            <select name="nivel" style="min-width:160px;">
                                <option value="">Todos</option>
                                <?php foreach (['SECUNDARIA','BACHILLERATO','PRIMARIA','OTRO'] as $nv): ?>
                                    <option value="<?= $nv ?>" <?= $filtroNivel === $nv ? 'selected' : '' ?>><?= $nv ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Mostrar escuelas</label>
                            <select name="limite" style="min-width:100px;">
                                <?php foreach ([25,50,100,200] as $lim): ?>
                                    <option value="<?= $lim ?>" <?= $filtroLimit === $lim ? 'selected' : '' ?>>Top <?= $lim ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:flex;gap:8px;align-items:flex-end;">
                            <button class="btn btn-primario btn-sm" type="submit">Aplicar</button>
                            <a class="btn btn-secundario btn-sm" href="reportes.php">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Botones de exportación general ────────────── -->
            <div class="panel" style="margin-bottom:22px;">
                <div class="panel-header">
                    <h3>📥 Exportar reportes</h3>
                    <?php if ($filtroMun || $filtroNivel): ?>
                        <span style="font-size:12px;color:#64748b;">
                            Filtros activos:
                            <?= $filtroMun ? h($filtroMun) : '' ?>
                            <?= $filtroNivel ? '· ' . h($filtroNivel) : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <div class="flex-gap" style="flex-wrap:wrap;gap:10px;">
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Resumen general</div>
                            <div class="flex-gap">
                                <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=general">📥 Excel</a>
                                <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=general">🖨️ PDF</a>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Por municipio</div>
                            <div class="flex-gap">
                                <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=municipio<?= $qExport ?>">📥 Excel</a>
                                <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=municipio<?= $qExport ?>">🖨️ PDF</a>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Por escuela</div>
                            <div class="flex-gap">
                                <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=escuela<?= $qExport ?>">📥 Excel</a>
                                <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=escuela<?= $qExport ?>">🖨️ PDF</a>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Alertas por estatus</div>
                            <div class="flex-gap">
                                <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=alertas">📥 Excel</a>
                                <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=alertas">🖨️ PDF</a>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Top escuelas críticas</div>
                            <div class="flex-gap">
                                <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=criticos<?= $qExport ?>">📥 Excel</a>
                                <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=criticos<?= $qExport ?>">🖨️ PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Tarjetas: Resumen general ──────────────────── -->
            <?php if (!$sinResumenGeneral && $resumenGeneral): ?>
            <div class="grid-metricas">
                <div class="card-metrica azul">
                    <div class="label">Total alumnos</div>
                    <div class="valor"><?= number_format($resumenGeneral['TOTAL_ALUMNOS']) ?></div>
                    <div class="ico">👤</div>
                </div>
                <div class="card-metrica gris">
                    <div class="label">Escuelas activas</div>
                    <div class="valor"><?= number_format($resumenGeneral['TOTAL_ESCUELAS']) ?></div>
                    <div class="ico">🏫</div>
                </div>
                <div class="card-metrica azul">
                    <div class="label">Total evaluaciones</div>
                    <div class="valor"><?= number_format($resumenGeneral['TOTAL_EVALUACIONES']) ?></div>
                    <div class="ico">📋</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Total alertas</div>
                    <div class="valor"><?= number_format($resumenGeneral['TOTAL_ALERTAS']) ?></div>
                    <div class="ico">🔔</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Alertas abiertas</div>
                    <div class="valor"><?= number_format($resumenGeneral['ALERTAS_ABIERTAS']) ?></div>
                    <div class="ico">⚠️</div>
                </div>
                <div class="card-metrica verde">
                    <div class="label">Alertas cerradas</div>
                    <div class="valor"><?= number_format($resumenGeneral['ALERTAS_CERRADAS']) ?></div>
                    <div class="ico">✅</div>
                </div>
            </div>
            <div class="grid-metricas">
                <div class="card-metrica verde">
                    <div class="label">Riesgo bajo</div>
                    <div class="valor"><?= number_format($resumenGeneral['RIESGO_BAJO']) ?></div>
                    <div class="ico">🟢</div>
                </div>
                <div class="card-metrica amarillo">
                    <div class="label">Riesgo medio</div>
                    <div class="valor"><?= number_format($resumenGeneral['RIESGO_MEDIO']) ?></div>
                    <div class="ico">🟡</div>
                </div>
                <div class="card-metrica naranja">
                    <div class="label">Riesgo alto</div>
                    <div class="valor"><?= number_format($resumenGeneral['RIESGO_ALTO']) ?></div>
                    <div class="ico">🟠</div>
                </div>
                <div class="card-metrica rojo">
                    <div class="label">Riesgo crítico</div>
                    <div class="valor"><?= number_format($resumenGeneral['RIESGO_CRITICO']) ?></div>
                    <div class="ico">🔴</div>
                </div>
            </div>
            <?php elseif ($sinResumenGeneral): ?>
            <div class="alerta alerta-aviso mb-20">
                Resumen general no disponible. Ejecuta <strong>actualizar_resumen_dashboard.sql</strong>.
            </div>
            <?php endif; ?>

            <!-- ── Alertas por estatus ────────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🔔 Alertas por estatus</h3>
                    <div class="flex-gap">
                        <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=alertas">📥 Excel</a>
                        <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=alertas">🖨️ PDF</a>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <?php if ($sinAlertas): ?>
                        <p class="sin-datos-resumen">Tabla resumen no generada. Ejecuta actualizar_resumen_reportes.sql.</p>
                    <?php elseif (empty($alertasEstatus)): ?>
                        <p class="sin-datos-resumen">Sin alertas registradas.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Estatus</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Porcentaje</th>
                                <th>Distribución</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $etiqEstatus = [
                            'NUEVA' => 'Nueva', 'EN_REVISION' => 'En revisión',
                            'EN_SEGUIMIENTO' => 'En seguimiento',
                            'ATENDIDA' => 'Atendida', 'CERRADA' => 'Cerrada',
                        ];
                        $colorEstatus = [
                            'NUEVA' => '#3b82f6', 'EN_REVISION' => '#f59e0b',
                            'EN_SEGUIMIENTO' => '#ea580c',
                            'ATENDIDA' => '#16a34a', 'CERRADA' => '#94a3b8',
                        ];
                        foreach ($alertasEstatus as $ae):
                            $color = $colorEstatus[$ae['ESTATUS']] ?? '#94a3b8';
                        ?>
                        <tr>
                            <td>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $color ?>;margin-right:6px;"></span>
                                <strong><?= h($etiqEstatus[$ae['ESTATUS']] ?? $ae['ESTATUS']) ?></strong>
                            </td>
                            <td style="text-align:right;font-weight:700;"><?= number_format($ae['TOTAL']) ?></td>
                            <td style="text-align:right;"><?= number_format($ae['PORCENTAJE'], 1) ?>%</td>
                            <td style="min-width:150px;">
                                <div class="barra-pct">
                                    <div class="barra-pct-track">
                                        <div class="barra-pct-fill" style="width:<?= $ae['PORCENTAJE'] ?>%;background:<?= $color ?>;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Por municipio ───────────────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📍 Riesgo por municipio
                        <?= $filtroMun ? '<span style="font-size:13px;color:#64748b;"> — '.h($filtroMun).'</span>' : '' ?>
                    </h3>
                    <div class="flex-gap">
                        <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=municipio<?= $qExport ?>">📥 Excel</a>
                        <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=municipio<?= $qExport ?>">🖨️ PDF</a>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <?php if ($sinMunicipio): ?>
                        <p class="sin-datos-resumen">Tabla resumen no generada. Ejecuta actualizar_resumen_reportes.sql.</p>
                    <?php elseif (empty($porMunicipio)): ?>
                        <p class="sin-datos-resumen">Sin datos con los filtros actuales.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Municipio</th>
                                <th style="text-align:right;">Alumnos</th>
                                <th style="text-align:right;">🟢 Bajo</th>
                                <th style="text-align:right;">🟡 Medio</th>
                                <th style="text-align:right;">🟠 Alto</th>
                                <th style="text-align:right;">🔴 Crítico</th>
                                <th style="text-align:right;">⚠️ Alertas abiertas</th>
                                <th style="text-align:right;">✅ Alertas cerradas</th>
                                <th>% en riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porMunicipio as $m):
                            $tot = (int)$m['TOTAL_ALUMNOS'] ?: 1;
                            $enR = (int)$m['RIESGO_MEDIO'] + (int)$m['RIESGO_ALTO'] + (int)$m['RIESGO_CRITICO'];
                            $pct = round($enR / $tot * 100, 1);
                            $col = $pct > 50 ? '#dc2626' : ($pct > 25 ? '#ea580c' : '#16a34a');
                        ?>
                        <tr>
                            <td><strong><?= h($m['MUNICIPIO']) ?></strong></td>
                            <td style="text-align:right;"><?= number_format($m['TOTAL_ALUMNOS']) ?></td>
                            <td style="text-align:right;color:#166534;font-weight:700;"><?= number_format($m['RIESGO_BAJO']) ?></td>
                            <td style="text-align:right;color:#854d0e;font-weight:700;"><?= number_format($m['RIESGO_MEDIO']) ?></td>
                            <td style="text-align:right;color:#9a3412;font-weight:700;"><?= number_format($m['RIESGO_ALTO']) ?></td>
                            <td style="text-align:right;color:#991b1b;font-weight:700;"><?= number_format($m['RIESGO_CRITICO']) ?></td>
                            <td style="text-align:right;"><?= number_format($m['ALERTAS_ABIERTAS']) ?></td>
                            <td style="text-align:right;"><?= number_format($m['ALERTAS_CERRADAS']) ?></td>
                            <td>
                                <div class="barra-pct">
                                    <div class="barra-pct-track">
                                        <div class="barra-pct-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                                    </div>
                                    <span style="font-size:12px;font-weight:700;white-space:nowrap;"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Por escuela ─────────────────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🏫 Riesgo por escuela
                        <span style="font-size:13px;color:#64748b;">
                            — Top <?= $filtroLimit ?>
                            <?= $filtroMun   ? ' · '.h($filtroMun)   : '' ?>
                            <?= $filtroNivel ? ' · '.h($filtroNivel) : '' ?>
                        </span>
                    </h3>
                    <div class="flex-gap">
                        <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=escuela<?= $qExport ?>">📥 Excel</a>
                        <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=escuela<?= $qExport ?>">🖨️ PDF</a>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <?php if ($sinEscuela): ?>
                        <p class="sin-datos-resumen">Tabla resumen no generada. Ejecuta actualizar_resumen_reportes.sql.</p>
                    <?php elseif (empty($porEscuela)): ?>
                        <p class="sin-datos-resumen">Sin datos con los filtros actuales.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Escuela</th>
                                <th>Municipio</th>
                                <th>Nivel</th>
                                <th style="text-align:right;">Alumnos</th>
                                <th style="text-align:right;">🟢</th>
                                <th style="text-align:right;">🟡</th>
                                <th style="text-align:right;">🟠</th>
                                <th style="text-align:right;">🔴</th>
                                <th style="text-align:right;">Alertas</th>
                                <th>% riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porEscuela as $e):
                            $tot = (int)$e['TOTAL_ALUMNOS'] ?: 1;
                            $enR = (int)$e['RIESGO_MEDIO'] + (int)$e['RIESGO_ALTO'] + (int)$e['RIESGO_CRITICO'];
                            $pct = round($enR / $tot * 100, 1);
                            $col = $pct > 50 ? '#dc2626' : ($pct > 25 ? '#ea580c' : '#16a34a');
                        ?>
                        <tr>
                            <td><strong><?= h($e['NOMBRE_ESCUELA']) ?></strong></td>
                            <td><?= h($e['MUNICIPIO']) ?></td>
                            <td><?= h($e['NIVEL']) ?></td>
                            <td style="text-align:right;"><?= number_format($e['TOTAL_ALUMNOS']) ?></td>
                            <td style="text-align:right;color:#166534;font-weight:700;"><?= number_format($e['RIESGO_BAJO']) ?></td>
                            <td style="text-align:right;color:#854d0e;font-weight:700;"><?= number_format($e['RIESGO_MEDIO']) ?></td>
                            <td style="text-align:right;color:#9a3412;font-weight:700;"><?= number_format($e['RIESGO_ALTO']) ?></td>
                            <td style="text-align:right;color:#991b1b;font-weight:700;"><?= number_format($e['RIESGO_CRITICO']) ?></td>
                            <td style="text-align:right;"><?= number_format($e['TOTAL_ALERTAS']) ?></td>
                            <td>
                                <div class="barra-pct">
                                    <div class="barra-pct-track">
                                        <div class="barra-pct-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                                    </div>
                                    <span style="font-size:12px;font-weight:700;white-space:nowrap;"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Top escuelas críticas ───────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🚨 Top escuelas con más casos críticos</h3>
                    <div class="flex-gap">
                        <a class="btn btn-exito btn-sm"     href="exportar_excel.php?tipo=criticos<?= $qExport ?>">📥 Excel</a>
                        <a class="btn btn-secundario btn-sm" href="exportar_pdf.php?tipo=criticos<?= $qExport ?>">🖨️ PDF</a>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <?php if ($sinTopCriticas): ?>
                        <p class="sin-datos-resumen">Tabla resumen no generada. Ejecuta actualizar_resumen_reportes.sql.</p>
                    <?php elseif (empty($topCriticas)): ?>
                        <p class="sin-datos-resumen">Sin casos críticos con los filtros actuales.</p>
                    <?php else:
                        $maxCrit = max(array_column($topCriticas, 'CASOS_CRITICOS') ?: [1]);
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Escuela</th>
                                <th>Municipio</th>
                                <th>Nivel</th>
                                <th style="text-align:right;">Casos críticos</th>
                                <th style="text-align:right;">Alertas abiertas</th>
                                <th>Barra</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topCriticas as $i => $tc): ?>
                        <tr>
                            <td style="color:#64748b;font-weight:700;"><?= $i + 1 ?></td>
                            <td><strong><?= h($tc['NOMBRE_ESCUELA']) ?></strong></td>
                            <td><?= h($tc['MUNICIPIO']) ?></td>
                            <td><?= h($tc['NIVEL']) ?></td>
                            <td style="text-align:right;font-size:18px;font-weight:900;color:#dc2626;"><?= number_format($tc['CASOS_CRITICOS']) ?></td>
                            <td style="text-align:right;font-weight:700;"><?= number_format($tc['ALERTAS_ABIERTAS']) ?></td>
                            <td style="min-width:120px;">
                                <div style="height:10px;background:#fee2e2;border-radius:5px;overflow:hidden;">
                                    <div style="height:100%;width:<?= round($tc['CASOS_CRITICOS']/$maxCrit*100) ?>%;background:#dc2626;border-radius:5px;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Pie de página ──────────────────────────────── -->
            <?php
            $fechaRef = false;
            if (!$sinMunicipio && !empty($porMunicipio)) {
                $fr = leerUno($pdo, "SELECT MAX(FECHA_ACTUALIZACION) AS f FROM resumen_riesgo_municipio");
                if ($fr) $fechaRef = $fr['f'];
            }
            ?>
            <?php if ($fechaRef): ?>
            <p class="texto-gris texto-centro" style="margin-top:8px;font-size:12px;">
                Datos calculados al <?= h(fechaHora($fechaRef)) ?> —
                Para actualizar ejecuta <strong>actualizar_resumen_reportes.sql</strong>
            </p>
            <?php endif; ?>

        </div><!-- /pagina -->
    </div><!-- /main-content -->
</div><!-- /layout -->
</body>
</html>
