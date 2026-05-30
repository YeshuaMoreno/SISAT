<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireLogin();

$mensaje = '';
$tipo    = '';

// ── Cambiar estatus ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estatus'])) {
    $idAlerta  = (int)($_POST['id_alerta'] ?? 0);
    $nuevoEst  = $_POST['nuevo_estatus'] ?? '';
    $estatusValidos = ['NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA'];
    if ($idAlerta > 0 && in_array($nuevoEst, $estatusValidos)) {
        $cierre = in_array($nuevoEst, ['ATENDIDA','CERRADA']) ? ', FECHA_CIERRE = NOW()' : '';
        $pdo->prepare("UPDATE alerta SET ESTATUS = ? $cierre WHERE ID_ALERTA = ?")->execute([$nuevoEst, $idAlerta]);
        $mensaje = 'Estatus de alerta actualizado.';
        $tipo    = 'exito';
    }
}

// ── Filtros ────────────────────────────────────────────────────
$filtroEscuela = (int)($_GET['escuela'] ?? 0);
$filtroGrupo   = (int)($_GET['grupo']   ?? 0);
$filtroNivel   = trim($_GET['nivel']    ?? '');
$filtroEstatus = trim($_GET['estatus']  ?? '');
$filtroFechaD  = trim($_GET['fecha_d']  ?? '');
$filtroFechaH  = trim($_GET['fecha_h']  ?? '');
$filtroNombre  = trim($_GET['q']        ?? '');

$where  = ['1=1'];
$params = [];

if ($filtroEscuela > 0) { $where[] = 'g.ID_ESCUELA = ?'; $params[] = $filtroEscuela; }
if ($filtroGrupo   > 0) { $where[] = 'a.ID_GRUPO = ?';   $params[] = $filtroGrupo; }
if ($filtroNivel   !== '') { $where[] = 'al.NIVEL_RIESGO = ?'; $params[] = $filtroNivel; }
if ($filtroEstatus !== '') { $where[] = 'al.ESTATUS = ?';      $params[] = $filtroEstatus; }
if ($filtroFechaD  !== '') { $where[] = 'DATE(al.FECHA_CREACION) >= ?'; $params[] = $filtroFechaD; }
if ($filtroFechaH  !== '') { $where[] = 'DATE(al.FECHA_CREACION) <= ?'; $params[] = $filtroFechaH; }
if ($filtroNombre  !== '') {
    $where[] = "(a.NOMBRE LIKE ? OR a.APELLIDO_PATERNO LIKE ? OR a.MATRICULA LIKE ?)";
    $like = "%$filtroNombre%";
    $params = array_merge($params, [$like, $like, $like]);
}

$whereStr = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT al.ID_ALERTA, al.NIVEL_RIESGO, al.ESTATUS, al.FECHA_CREACION, al.DESCRIPCION,
           CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO) AS alumno_nombre,
           a.MATRICULA, a.ID_ALUMNO,
           g.GRADO, g.GRUPO AS letra_grupo,
           esc.NOMBRE_ESCUELA,
           ev.ASISTENCIA_PORCENTAJE, ev.PROMEDIO_GENERAL, ev.PUNTAJE_RIESGO,
           (SELECT COUNT(*) FROM seguimiento seg WHERE seg.ID_ALERTA = al.ID_ALERTA) AS total_seguimientos
    FROM alerta al
    INNER JOIN alumno a ON a.ID_ALUMNO = al.ID_ALUMNO
    INNER JOIN evaluacion_sisat ev ON ev.ID_EVALUACION = al.ID_EVALUACION
    LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    WHERE $whereStr
    ORDER BY
        CASE al.NIVEL_RIESGO WHEN 'CRITICO' THEN 1 WHEN 'ALTO' THEN 2 WHEN 'MEDIO' THEN 3 ELSE 4 END,
        al.FECHA_CREACION DESC
");
$stmt->execute($params);
$alertas = $stmt->fetchAll();

// Catálogos para filtros
$escuelas = $pdo->query("SELECT ID_ESCUELA, NOMBRE_ESCUELA FROM escuela WHERE ACTIVA=1 ORDER BY NOMBRE_ESCUELA")->fetchAll();
$grupos   = $pdo->query("SELECT g.ID_GRUPO, CONCAT(g.GRADO,'° ',g.GRUPO,' — ',COALESCE(esc.NOMBRE_ESCUELA,'')) AS desc_grupo
    FROM grupo g LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA ORDER BY esc.NOMBRE_ESCUELA, g.GRADO")->fetchAll();

$paginaActual = 'alertas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Alertas tempranas</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">🔔 Alertas tempranas</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="panel mb-20">
                <div class="panel-header"><h3>Filtros</h3></div>
                <div class="panel-body">
                    <form method="GET" class="filtros">
                        <input type="text" name="q" placeholder="Nombre o matrícula" value="<?= h($filtroNombre) ?>">
                        <select name="escuela">
                            <option value="">Todas las escuelas</option>
                            <?php foreach ($escuelas as $e): ?>
                                <option value="<?= $e['ID_ESCUELA'] ?>" <?= $filtroEscuela == $e['ID_ESCUELA'] ? 'selected' : '' ?>>
                                    <?= h($e['NOMBRE_ESCUELA']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="nivel">
                            <option value="">Todos los niveles</option>
                            <?php foreach (['BAJO','MEDIO','ALTO','CRITICO'] as $n): ?>
                                <option value="<?= $n ?>" <?= $filtroNivel === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="estatus">
                            <option value="">Todos los estatus</option>
                            <option value="NUEVA"          <?= $filtroEstatus === 'NUEVA' ? 'selected' : '' ?>>Nueva</option>
                            <option value="EN_REVISION"    <?= $filtroEstatus === 'EN_REVISION' ? 'selected' : '' ?>>En revisión</option>
                            <option value="EN_SEGUIMIENTO" <?= $filtroEstatus === 'EN_SEGUIMIENTO' ? 'selected' : '' ?>>En seguimiento</option>
                            <option value="ATENDIDA"       <?= $filtroEstatus === 'ATENDIDA' ? 'selected' : '' ?>>Atendida</option>
                            <option value="CERRADA"        <?= $filtroEstatus === 'CERRADA' ? 'selected' : '' ?>>Cerrada</option>
                        </select>
                        <input type="date" name="fecha_d" title="Desde" value="<?= h($filtroFechaD) ?>">
                        <input type="date" name="fecha_h" title="Hasta" value="<?= h($filtroFechaH) ?>">
                        <button class="btn btn-primario btn-sm" type="submit">Filtrar</button>
                        <a class="btn btn-secundario btn-sm" href="alertas.php">Limpiar</a>
                    </form>
                </div>
            </div>

            <!-- Listado -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Alertas — <?= count($alertas) ?> resultado(s)</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Alumno</th>
                                <th>Escuela / Grupo</th>
                                <th>Nivel</th>
                                <th>Estatus</th>
                                <th>Asist.</th>
                                <th>Prom.</th>
                                <th>Puntaje</th>
                                <th>Fecha</th>
                                <th>Seguim.</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($alertas)): ?>
                            <tr><td colspan="11" class="texto-centro texto-gris" style="padding:24px;">No se encontraron alertas con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($alertas as $al): ?>
                            <tr>
                                <td style="color:#64748b;font-size:12px;"><?= $al['ID_ALERTA'] ?></td>
                                <td>
                                    <a href="alumnos.php?accion=ver&id=<?= $al['ID_ALUMNO'] ?>" style="color:#061f45;font-weight:700;text-decoration:none;">
                                        <?= h($al['alumno_nombre']) ?>
                                    </a>
                                    <div class="texto-gris"><?= h($al['MATRICULA']) ?></div>
                                </td>
                                <td>
                                    <?= h($al['NOMBRE_ESCUELA'] ?? '—') ?>
                                    <?php if ($al['GRADO']): ?>
                                        <div class="texto-gris"><?= h($al['GRADO']) ?>° <?= h($al['letra_grupo']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= badgeRiesgo($al['NIVEL_RIESGO']) ?></td>
                                <td><?= badgeEstatus($al['ESTATUS']) ?></td>
                                <td><?= number_format($al['ASISTENCIA_PORCENTAJE'], 1) ?>%</td>
                                <td><?= number_format($al['PROMEDIO_GENERAL'], 1) ?></td>
                                <td style="font-weight:700;"><?= $al['PUNTAJE_RIESGO'] ?></td>
                                <td class="texto-gris"><?= fechaCorta($al['FECHA_CREACION']) ?></td>
                                <td style="text-align:center;"><?= $al['total_seguimientos'] ?></td>
                                <td>
                                    <div class="flex-gap">
                                        <a class="btn btn-primario btn-sm"
                                           href="seguimiento.php?id_alerta=<?= $al['ID_ALERTA'] ?>">Ver / Seguir</a>

                                        <?php if (!esAlumno()): ?>
                                        <button class="btn btn-secundario btn-sm"
                                            onclick="mostrarCambioEstatus(<?= $al['ID_ALERTA'] ?>, '<?= $al['ESTATUS'] ?>')">
                                            Cambiar estatus
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal cambio de estatus -->
<div id="modal-estatus" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:30px;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <h3 style="margin-bottom:18px;color:#061f45;">Cambiar estatus de alerta</h3>
        <form method="POST">
            <input type="hidden" name="cambiar_estatus" value="1">
            <input type="hidden" name="id_alerta" id="modal_id_alerta">
            <div class="form-group mb-20">
                <label>Nuevo estatus</label>
                <select name="nuevo_estatus" id="modal_estatus_sel">
                    <option value="NUEVA">Nueva</option>
                    <option value="EN_REVISION">En revisión</option>
                    <option value="EN_SEGUIMIENTO">En seguimiento</option>
                    <option value="ATENDIDA">Atendida</option>
                    <option value="CERRADA">Cerrada</option>
                </select>
            </div>
            <div class="flex-gap">
                <button class="btn btn-primario" type="submit">Guardar</button>
                <button class="btn btn-secundario" type="button" onclick="cerrarModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarCambioEstatus(id, estatusActual) {
    document.getElementById('modal_id_alerta').value = id;
    document.getElementById('modal_estatus_sel').value = estatusActual;
    document.getElementById('modal-estatus').style.display = 'flex';
}
function cerrarModal() {
    document.getElementById('modal-estatus').style.display = 'none';
}
</script>
</body>
</html>
