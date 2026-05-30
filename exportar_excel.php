<?php
/**
 * exportar_excel.php
 * SISAT — Exporta reportes institucionales como Excel (formato XLS via HTML).
 *
 * Uso:
 *   exportar_excel.php?tipo=general
 *   exportar_excel.php?tipo=municipio[&municipio=Saltillo]
 *   exportar_excel.php?tipo=escuela[&municipio=X&nivel=SECUNDARIA]
 *   exportar_excel.php?tipo=alertas
 *   exportar_excel.php?tipo=criticos[&municipio=Saltillo]
 *
 * Nota: genera HTML con Content-Type vnd.ms-excel.
 * Excel y LibreOffice Calc lo abren directamente desde el navegador.
 */

session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN', 'SEDU', 'DIRECTOR']);

// ── Parámetros ────────────────────────────────────────────────
$tipo       = $_GET['tipo']      ?? 'general';
$filtroMun  = trim($_GET['municipio'] ?? '');
$filtroNiv  = trim($_GET['nivel']     ?? '');

$tiposValidos = ['general', 'municipio', 'escuela', 'alertas', 'criticos'];
if (!in_array($tipo, $tiposValidos, true)) $tipo = 'general';

// ── Helper de consulta segura ─────────────────────────────────
function xlsQuery(PDO $pdo, string $sql, array $p = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ── Obtener datos según tipo ──────────────────────────────────
$titulo  = 'Reporte SISAT';
$encab   = [];
$filas   = [];
$notas   = [];

switch ($tipo) {

    case 'general':
        $titulo = 'Resumen General SISAT';
        $row = xlsQuery($pdo,
            "SELECT * FROM resumen_dashboard_sisat ORDER BY FECHA_ACTUALIZACION DESC LIMIT 1"
        );
        if ($row) {
            $r = $row[0];
            $encab = ['Indicador', 'Valor'];
            $etiquetas = [
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
            foreach ($etiquetas as $col => $etiq) {
                $filas[] = [$etiq, $r[$col] ?? ''];
            }
        } else {
            $notas[] = 'Tabla resumen_dashboard_sisat vacía. Ejecuta actualizar_resumen_dashboard.sql.';
        }
        break;

    case 'municipio':
        $titulo = 'Riesgo por Municipio';
        if ($filtroMun) $titulo .= " — $filtroMun";
        $sql  = "SELECT * FROM resumen_riesgo_municipio";
        $pars = [];
        if ($filtroMun) { $sql .= " WHERE MUNICIPIO = ?"; $pars[] = $filtroMun; }
        $sql .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC";
        $rows = xlsQuery($pdo, $sql, $pars);
        $encab = ['Municipio', 'Total alumnos',
                  'Riesgo bajo', 'Riesgo medio', 'Riesgo alto', 'Riesgo crítico',
                  'Alertas abiertas', 'Alertas cerradas', '% en riesgo'];
        foreach ($rows as $r) {
            $tot = (int)$r['TOTAL_ALUMNOS'] ?: 1;
            $enR = (int)$r['RIESGO_MEDIO'] + (int)$r['RIESGO_ALTO'] + (int)$r['RIESGO_CRITICO'];
            $filas[] = [
                $r['MUNICIPIO'],
                $r['TOTAL_ALUMNOS'],
                $r['RIESGO_BAJO'],
                $r['RIESGO_MEDIO'],
                $r['RIESGO_ALTO'],
                $r['RIESGO_CRITICO'],
                $r['ALERTAS_ABIERTAS'],
                $r['ALERTAS_CERRADAS'],
                number_format($enR / $tot * 100, 1) . '%',
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'escuela':
        $titulo = 'Riesgo por Escuela';
        if ($filtroMun) $titulo .= " — $filtroMun";
        if ($filtroNiv) $titulo .= " ($filtroNiv)";
        $sql  = "SELECT * FROM resumen_riesgo_escuela WHERE 1=1";
        $pars = [];
        if ($filtroMun) { $sql .= " AND MUNICIPIO = ?"; $pars[] = $filtroMun; }
        if ($filtroNiv) { $sql .= " AND NIVEL = ?";     $pars[] = $filtroNiv; }
        $sql .= " ORDER BY (RIESGO_CRITICO + RIESGO_ALTO) DESC LIMIT 500";
        $rows = xlsQuery($pdo, $sql, $pars);
        $encab = ['Escuela', 'Municipio', 'Nivel', 'Zona escolar', 'Total alumnos',
                  'Riesgo bajo', 'Riesgo medio', 'Riesgo alto', 'Riesgo crítico',
                  'Total alertas', '% en riesgo'];
        foreach ($rows as $r) {
            $tot = (int)$r['TOTAL_ALUMNOS'] ?: 1;
            $enR = (int)$r['RIESGO_MEDIO'] + (int)$r['RIESGO_ALTO'] + (int)$r['RIESGO_CRITICO'];
            $filas[] = [
                $r['NOMBRE_ESCUELA'],
                $r['MUNICIPIO'],
                $r['NIVEL'],
                $r['ZONA_ESCOLAR'],
                $r['TOTAL_ALUMNOS'],
                $r['RIESGO_BAJO'],
                $r['RIESGO_MEDIO'],
                $r['RIESGO_ALTO'],
                $r['RIESGO_CRITICO'],
                $r['TOTAL_ALERTAS'],
                number_format($enR / $tot * 100, 1) . '%',
            ];
        }
        if (count($rows) === 500) $notas[] = 'Resultado limitado a 500 escuelas. Aplica filtros para ver un subconjunto específico.';
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'alertas':
        $titulo = 'Alertas por Estatus';
        $rows = xlsQuery($pdo,
            "SELECT * FROM resumen_alertas_estatus
             ORDER BY FIELD(ESTATUS,'NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA')"
        );
        $encab = ['Estatus', 'Total alertas', 'Porcentaje (%)'];
        $etiqEst = [
            'NUEVA' => 'Nueva', 'EN_REVISION' => 'En revisión',
            'EN_SEGUIMIENTO' => 'En seguimiento',
            'ATENDIDA' => 'Atendida', 'CERRADA' => 'Cerrada',
        ];
        foreach ($rows as $r) {
            $filas[] = [
                $etiqEst[$r['ESTATUS']] ?? $r['ESTATUS'],
                $r['TOTAL'],
                $r['PORCENTAJE'],
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;

    case 'criticos':
        $titulo = 'Top Escuelas con Casos Críticos';
        if ($filtroMun) $titulo .= " — $filtroMun";
        $sql  = "SELECT * FROM resumen_top_escuelas_criticas WHERE 1=1";
        $pars = [];
        if ($filtroMun) { $sql .= " AND MUNICIPIO = ?"; $pars[] = $filtroMun; }
        $sql .= " ORDER BY CASOS_CRITICOS DESC LIMIT 100";
        $rows = xlsQuery($pdo, $sql, $pars);
        $encab = ['#', 'Escuela', 'Municipio', 'Nivel',
                  'Casos críticos', 'Alertas abiertas'];
        foreach ($rows as $i => $r) {
            $filas[] = [
                $i + 1,
                $r['NOMBRE_ESCUELA'],
                $r['MUNICIPIO'],
                $r['NIVEL'],
                $r['CASOS_CRITICOS'],
                $r['ALERTAS_ABIERTAS'],
            ];
        }
        if (empty($rows)) $notas[] = 'Sin datos. Ejecuta actualizar_resumen_reportes.sql.';
        break;
}

// ── Nombre de archivo de descarga ─────────────────────────────
$fecha_arch = date('Y-m-d');
$nombre_arch = "SISAT_{$tipo}_{$fecha_arch}.xls";

// ── Headers HTTP para descarga Excel ─────────────────────────
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre_arch . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
// BOM UTF-8 para que Excel reconozca tildes
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]><xml>
 <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
  <x:Name><?= htmlspecialchars($titulo) ?></x:Name>
  <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
 </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
<style>
  body { font-family: Arial, sans-serif; font-size: 11pt; }
  .titulo  { font-size: 14pt; font-weight: bold; color: #061f45; }
  .subtit  { font-size: 10pt; color: #64748b; }
  table    { border-collapse: collapse; width: 100%; margin-top: 12px; }
  th       { background-color: #061f45; color: #ffffff; padding: 8px 10px;
             text-align: left; font-size: 10pt; border: 1px solid #e2e8f0; }
  td       { padding: 6px 10px; border: 1px solid #e2e8f0; font-size: 10pt; vertical-align: middle; }
  tr:nth-child(even) td { background-color: #f8fafc; }
  .nota    { color: #b45309; font-size: 10pt; font-style: italic; padding: 4px 0; }
  .pie     { font-size: 9pt; color: #94a3b8; margin-top: 16px; }
</style>
</head>
<body>

<p class="titulo">SISAT — <?= htmlspecialchars($titulo) ?></p>
<p class="subtit">
    Sistema de Alerta Temprana para Abandono Escolar &nbsp;|&nbsp;
    Generado: <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp;
    Usuario: <?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? '') ?>
    (<?= htmlspecialchars($_SESSION['usuario']['rol'] ?? '') ?>)
</p>

<?php if ($notas): ?>
    <?php foreach ($notas as $nota): ?>
        <p class="nota">⚠ <?= htmlspecialchars($nota) ?></p>
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
    <p class="nota">Sin registros para este reporte.</p>
<?php endif; ?>

<p class="pie">
    SISAT — Sistema de Alerta Temprana para Abandono Escolar<br>
    Este archivo fue generado automáticamente. Los datos provienen de tablas resumen actualizadas periódicamente.
</p>

</body>
</html>
