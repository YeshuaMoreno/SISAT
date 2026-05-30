<?php
/**
 * exportar_pdf.php
 * SISAT — Exporta reportes institucionales como vista imprimible PDF.
 *
 * Detecta si Dompdf está instalado vía Composer.
 * Si no, genera HTML imprimible con CSS @print y botón de impresión.
 *
 * Uso:
 *   exportar_pdf.php?tipo=general
 *   exportar_pdf.php?tipo=municipio[&municipio=Saltillo]
 *   exportar_pdf.php?tipo=escuela[&municipio=X&nivel=SECUNDARIA]
 *   exportar_pdf.php?tipo=alertas
 *   exportar_pdf.php?tipo=criticos[&municipio=Saltillo]
 */

session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN', 'SEDU', 'DIRECTOR']);

// ── Parámetros ────────────────────────────────────────────────
$tipo      = $_GET['tipo']      ?? 'general';
$filtroMun = trim($_GET['municipio'] ?? '');
$filtroNiv = trim($_GET['nivel']     ?? '');

$tiposValidos = ['general', 'municipio', 'escuela', 'alertas', 'criticos'];
if (!in_array($tipo, $tiposValidos, true)) $tipo = 'general';

// ── Helper de consulta segura ─────────────────────────────────
function pdfQuery(PDO $pdo, string $sql, array $p = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ── Detectar Dompdf ───────────────────────────────────────────
$dompdfPath = __DIR__ . '/vendor/autoload.php';
$usaDompdf  = file_exists($dompdfPath);
// Si Dompdf existe se activa; de lo contrario vista imprimible.
// Para forzar vista imprimible: ?print=1
if (isset($_GET['print'])) $usaDompdf = false;

// ── Obtener datos según tipo ──────────────────────────────────
$titulo  = 'Reporte SISAT';
$subtipo = '';
$encab   = [];
$filas   = [];
$notas   = [];

switch ($tipo) {

    case 'general':
        $titulo  = 'Resumen General';
        $subtipo = 'Indicadores institucionales consolidados';
        $row = pdfQuery($pdo,
            "SELECT * FROM resumen_dashboard_sisat ORDER BY FECHA_ACTUALIZACION DESC LIMIT 1"
        );
        if ($row) {
            $r = $row[0];
            $encab = ['Indicador', 'Valor'];
            $mapa = [
                'TOTAL_ALUMNOS'      => 'Total de alumnos',
                'TOTAL_ESCUELAS'     => 'Escuelas activas',
                'TOTAL_EVALUACIONES' => 'Total de evaluaciones',
                'TOTAL_ALERTAS'      => 'Total de alertas',
                'ALERTAS_ABIERTAS'   => 'Alertas abiertas',
                'ALERTAS_CERRADAS'   => 'Alertas cerradas',
                'RIESGO_BAJO'        => 'Alumnos — Riesgo bajo',
                'RIESGO_MEDIO'       => 'Alumnos — Riesgo medio',
                'RIESGO_ALTO'        => 'Alumnos — Riesgo alto',
                'RIESGO_CRITICO'     => 'Alumnos — Riesgo crítico',
                'FECHA_ACTUALIZACION'=> 'Última actualización',
            ];
            foreach ($mapa as $col => $etiq) {
                $val = isset($r[$col]) ? number_format((float)$r[$col]) : '—';
                if ($col === 'FECHA_ACTUALIZACION') $val = $r[$col] ?? '—';
                $filas[] = [$etiq, $val];
            }
        } else {
            $notas[] = 'Tabla resumen_dashboard_sisat vacía o inexistente.';
        }
        break;

    case 'municipio':
        $titulo  = 'Riesgo por Municipio';
        $subtipo = $filtroMun ?: 'Todos los municipios';
        $sql  = "SELECT * FROM resumen_riesgo_municipio";
        $pars = [];
        if ($filtroMun) { $sql .= " WHERE MUNICIPIO = ?"; $pars[] = $filtroMun; }
        $sql .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC";
        $rows = pdfQuery($pdo, $sql, $pars);
        $encab = ['Municipio', 'Alumnos', 'Bajo', 'Medio', 'Alto', 'Crítico',
                  'Alert. abiertas', 'Alert. cerradas', '% riesgo'];
        foreach ($rows as $r) {
            $tot = (int)$r['TOTAL_ALUMNOS'] ?: 1;
            $enR = (int)$r['RIESGO_MEDIO'] + (int)$r['RIESGO_ALTO'] + (int)$r['RIESGO_CRITICO'];
            $filas[] = [
                $r['MUNICIPIO'],
                number_format($r['TOTAL_ALUMNOS']),
                number_format($r['RIESGO_BAJO']),
                number_format($r['RIESGO_MEDIO']),
                number_format($r['RIESGO_ALTO']),
                number_format($r['RIESGO_CRITICO']),
                number_format($r['ALERTAS_ABIERTAS']),
                number_format($r['ALERTAS_CERRADAS']),
                number_format($enR / $tot * 100, 1) . '%',
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'escuela':
        $titulo  = 'Riesgo por Escuela';
        $partes  = array_filter([$filtroMun, $filtroNiv]);
        $subtipo = $partes ? implode(' — ', $partes) : 'Top 100 escuelas en riesgo';
        $sql  = "SELECT * FROM resumen_riesgo_escuela WHERE 1=1";
        $pars = [];
        if ($filtroMun) { $sql .= " AND MUNICIPIO = ?"; $pars[] = $filtroMun; }
        if ($filtroNiv) { $sql .= " AND NIVEL = ?";     $pars[] = $filtroNiv; }
        $sql .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC LIMIT 100";
        $rows = pdfQuery($pdo, $sql, $pars);
        $encab = ['Escuela', 'Municipio', 'Nivel', 'Alumnos',
                  'Bajo', 'Medio', 'Alto', 'Crítico', 'Alertas', '% riesgo'];
        foreach ($rows as $r) {
            $tot = (int)$r['TOTAL_ALUMNOS'] ?: 1;
            $enR = (int)$r['RIESGO_MEDIO'] + (int)$r['RIESGO_ALTO'] + (int)$r['RIESGO_CRITICO'];
            $filas[] = [
                $r['NOMBRE_ESCUELA'],
                $r['MUNICIPIO'],
                $r['NIVEL'],
                number_format($r['TOTAL_ALUMNOS']),
                number_format($r['RIESGO_BAJO']),
                number_format($r['RIESGO_MEDIO']),
                number_format($r['RIESGO_ALTO']),
                number_format($r['RIESGO_CRITICO']),
                number_format($r['TOTAL_ALERTAS']),
                number_format($enR / $tot * 100, 1) . '%',
            ];
        }
        if (count($rows) === 100) $notas[] = 'Muestra limitada a 100 escuelas. Usa filtros para ver un subconjunto.';
        if (empty($rows))         $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'alertas':
        $titulo  = 'Alertas por Estatus';
        $subtipo = 'Distribución de alertas en el sistema';
        $rows = pdfQuery($pdo,
            "SELECT * FROM resumen_alertas_estatus
             ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA')"
        );
        $encab = ['Estatus', 'Total alertas', 'Porcentaje'];
        $etiqEst = [
            'NUEVA' => 'Nueva', 'EN_REVISION' => 'En revisión',
            'EN_SEGUIMIENTO' => 'En seguimiento',
            'ATENDIDA' => 'Atendida', 'CERRADA' => 'Cerrada',
        ];
        foreach ($rows as $r) {
            $filas[] = [
                $etiqEst[$r['ESTATUS']] ?? $r['ESTATUS'],
                number_format($r['TOTAL']),
                $r['PORCENTAJE'] . '%',
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'criticos':
        $titulo  = 'Top Escuelas con Casos Críticos';
        $subtipo = $filtroMun ?: 'Todos los municipios — Top 50';
        $sql  = "SELECT * FROM resumen_top_escuelas_criticas WHERE 1=1";
        $pars = [];
        if ($filtroMun) { $sql .= " AND MUNICIPIO = ?"; $pars[] = $filtroMun; }
        $sql .= " ORDER BY CASOS_CRITICOS DESC LIMIT 50";
        $rows = pdfQuery($pdo, $sql, $pars);
        $encab = ['#', 'Escuela', 'Municipio', 'Nivel', 'Casos críticos', 'Alertas abiertas'];
        foreach ($rows as $i => $r) {
            $filas[] = [
                $i + 1,
                $r['NOMBRE_ESCUELA'],
                $r['MUNICIPIO'],
                $r['NIVEL'],
                number_format($r['CASOS_CRITICOS']),
                number_format($r['ALERTAS_ABIERTAS']),
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;
}

// ── Si Dompdf disponible: PDF real ────────────────────────────
if ($usaDompdf) {
    require_once $dompdfPath;
   

    $html = _buildPdfHtml($titulo, $subtipo, $encab, $filas, $notas, $filtroMun, $filtroNiv, true);

    $options = new \Dompdf\Options();
    $dompdf = new \Dompdf\Dompdf($options);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("SISAT_{$tipo}_" . date('Y-m-d') . ".pdf",
        ['Attachment' => true]);
    exit;
}

// ── Vista imprimible (sin Dompdf) ─────────────────────────────
function _buildPdfHtml(string $titulo, string $subtipo, array $encab,
                       array $filas, array $notas,
                       string $filtroMun, string $filtroNiv,
                       bool $soloTabla = false): string
{
    $fecha = date('d/m/Y H:i');
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SISAT — <?= htmlspecialchars($titulo) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #fff;
            padding: 24px;
        }

        /* ── Cabecera ── */
        .hdr {
            border-bottom: 3px solid #061f45;
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .hdr-left h1 { font-size: 18px; color: #061f45; font-weight: 900; }
        .hdr-left h2 { font-size: 12px; color: #475569; font-weight: 400; margin-top: 4px; }
        .hdr-left .subtipo { font-size: 11px; color: #64748b; margin-top: 2px; }
        .hdr-right { text-align: right; font-size: 10px; color: #94a3b8; }
        .hdr-right strong { display: block; font-size: 12px; color: #475569; }

        /* ── Tabla ── */
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th {
            background: #061f45;
            color: #fff;
            padding: 7px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            vertical-align: top;
        }
        tr:nth-child(even) td { background: #f8fafc; }

        /* ── Notas ── */
        .nota {
            background: #fefce8;
            border-left: 4px solid #f59e0b;
            padding: 8px 12px;
            font-size: 10px;
            color: #78350f;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        /* ── Pie ── */
        .pie {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        /* ── Botón flotante (solo en pantalla) ── */
        .btn-imprimir {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #061f45;
            color: #fff;
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0,0,0,.25);
            z-index: 1000;
        }
        .btn-imprimir:hover { background: #0b3570; }
        .btn-volver {
            position: fixed;
            top: 60px;
            right: 16px;
            background: #e2e8f0;
            color: #475569;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            z-index: 1000;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        /* ── CSS de impresión ── */
        @media print {
            .btn-imprimir, .btn-volver { display: none !important; }
            body { padding: 0; font-size: 10px; }
            table { page-break-inside: auto; }
            tr    { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }

        @page {
            size: A4 landscape;
            margin: 15mm 12mm;
        }
    </style>
</head>
<body>

<?php if (!$soloTabla): ?>
<button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
<a class="btn-volver" href="reportes.php">← Volver</a>
<?php endif; ?>

<div class="hdr">
    <div class="hdr-left">
        <h1>SISAT — <?= htmlspecialchars($titulo) ?></h1>
        <h2>Sistema de Alerta Temprana para Abandono Escolar</h2>
        <?php if ($subtipo): ?>
            <div class="subtipo"><?= htmlspecialchars($subtipo) ?></div>
        <?php endif; ?>
    </div>
    <div class="hdr-right">
        <strong>Fecha de generación</strong>
        <?= $fecha ?><br>
        <?php if ($filtroMun): ?>Municipio: <?= htmlspecialchars($filtroMun) ?><br><?php endif; ?>
        <?php if ($filtroNiv): ?>Nivel: <?= htmlspecialchars($filtroNiv) ?><br><?php endif; ?>
    </div>
</div>

<?php if ($notas): ?>
    <?php foreach ($notas as $nota): ?>
        <div class="nota">⚠ <?= htmlspecialchars($nota) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($filas)): ?>
<table>
    <?php if ($encab): ?>
    <thead>
        <tr>
            <?php foreach ($encab as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php endif; ?>
    <tbody>
        <?php foreach ($filas as $fila): ?>
        <tr>
            <?php foreach ($fila as $celda): ?>
                <td><?= htmlspecialchars((string)$celda) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php elseif (empty($notas)): ?>
    <div class="nota">Sin registros para este reporte.</div>
<?php endif; ?>

<div class="pie">
    <span>SISAT — Sistema de Alerta Temprana para Abandono Escolar</span>
    <span>Los datos provienen de tablas resumen. Para actualizar: ejecuta actualizar_resumen_reportes.sql</span>
</div>

<?php if (!$soloTabla): ?>
<script>
    // Auto-imprimir si viene de un botón con ?autoprint=1
    <?php if (!empty($_GET['autoprint'])): ?>
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
    <?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
<?php
    return ob_get_clean();
}

// ── Salida de la vista imprimible ─────────────────────────────
echo _buildPdfHtml($titulo, $subtipo, $encab, $filas, $notas, $filtroMun, $filtroNiv, false);
