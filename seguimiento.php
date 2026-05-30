<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireLogin();

$idAlerta = (int)($_GET['id_alerta'] ?? 0);
$mensaje  = '';
$tipo     = '';

// ── Registrar nuevo seguimiento ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_seguimiento'])) {
    $idAl   = (int)($_POST['id_alerta'] ?? 0);
    $accion = trim($_POST['accion_realizada'] ?? '');
    $result = trim($_POST['resultado']        ?? '');
    $prox   = trim($_POST['proxima_accion']   ?? '');
    $fecha  = $_POST['fecha_proxima_accion']  ?? '';
    $nuevoEst = $_POST['nuevo_estatus']       ?? '';

    if ($idAl === 0 || $accion === '') {
        $mensaje = 'La acción realizada es obligatoria.';
        $tipo    = 'error';
        $idAlerta = $idAl;
    } else {
        $pdo->prepare("
            INSERT INTO seguimiento (ID_ALERTA, ID_USUARIO, ACCION_REALIZADA, RESULTADO, PROXIMA_ACCION, FECHA_PROXIMA_ACCION)
            VALUES (?,?,?,?,?,?)
        ")->execute([
            $idAl,
            $_SESSION['usuario']['id'],
            $accion,
            $result ?: null,
            $prox   ?: null,
            $fecha  ?: null,
        ]);

        // Actualizar estatus de la alerta
        $estatusValidos = ['NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA'];
        if (in_array($nuevoEst, $estatusValidos)) {
            $cierre = in_array($nuevoEst, ['ATENDIDA','CERRADA']) ? ', FECHA_CIERRE = NOW()' : '';
            $pdo->prepare("UPDATE alerta SET ESTATUS = ? $cierre WHERE ID_ALERTA = ?")->execute([$nuevoEst, $idAl]);
        }

        $idAlerta = $idAl;
        $mensaje  = 'Seguimiento registrado correctamente.';
        $tipo     = 'exito';
    }
}

// ── Cargar datos de la alerta ──────────────────────────────────
$alerta = null;
$alumno = null;
$evaluacion = null;
$indicadoresEval = [];
$histSeguimiento = [];

if ($idAlerta > 0) {
    $stmt = $pdo->prepare("
        SELECT al.*, ev.ASISTENCIA_PORCENTAJE, ev.PROMEDIO_GENERAL, ev.PUNTAJE_RIESGO,
               ev.OBSERVACIONES AS obs_eval, ev.FECHA_EVALUACION, ev.ID_EVALUACION,
               CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO,' ',a.APELLIDO_MATERNO) AS alumno_nombre,
               a.MATRICULA, a.ID_ALUMNO, a.CURP, a.FECHA_NACIMIENTO, a.SEXO, a.TELEFONO, a.CORREO,
               g.GRADO, g.GRUPO AS letra_grupo, g.CICLO_ESCOLAR,
               esc.NOMBRE_ESCUELA, esc.MUNICIPIO
        FROM alerta al
        INNER JOIN evaluacion_sisat ev ON ev.ID_EVALUACION = al.ID_EVALUACION
        INNER JOIN alumno a ON a.ID_ALUMNO = al.ID_ALUMNO
        LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
        LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
        WHERE al.ID_ALERTA = ?
    ");
    $stmt->execute([$idAlerta]);
    $alerta = $stmt->fetch();

    if ($alerta) {
        // Indicadores de esta evaluación
        $stmtInd = $pdo->prepare("
            SELECT ir.NOMBRE_INDICADOR, ir.PESO, ed.VALOR, ed.OBSERVACION
            FROM evaluacion_detalle ed
            INNER JOIN indicador_riesgo ir ON ir.ID_INDICADOR = ed.ID_INDICADOR
            WHERE ed.ID_EVALUACION = ?
            ORDER BY ed.VALOR DESC, ir.PESO DESC
        ");
        $stmtInd->execute([$alerta['ID_EVALUACION']]);
        $indicadoresEval = $stmtInd->fetchAll();

        // Historial de seguimientos
        $stmtSeg = $pdo->prepare("
            SELECT seg.*, u.USUARIO AS nombre_usuario
            FROM seguimiento seg
            INNER JOIN usuario u ON u.ID_USUARIO = seg.ID_USUARIO
            WHERE seg.ID_ALERTA = ?
            ORDER BY seg.FECHA_SEGUIMIENTO DESC
        ");
        $stmtSeg->execute([$idAlerta]);
        $histSeguimiento = $stmtSeg->fetchAll();
    }
}

// ── Listado de todas las alertas con seguimiento ───────────────
$listaAlertas = [];
if ($idAlerta === 0) {
    $listaAlertas = $pdo->query("
        SELECT al.ID_ALERTA, al.NIVEL_RIESGO, al.ESTATUS, al.FECHA_CREACION,
               CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO) AS alumno_nombre,
               a.MATRICULA,
               esc.NOMBRE_ESCUELA,
               COUNT(seg.ID_SEGUIMIENTO) AS total_seg
        FROM alerta al
        INNER JOIN alumno a ON a.ID_ALUMNO = al.ID_ALUMNO
        LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
        LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
        LEFT JOIN seguimiento seg ON seg.ID_ALERTA = al.ID_ALERTA
        WHERE al.ESTATUS NOT IN ('CERRADA')
        GROUP BY al.ID_ALERTA
        ORDER BY CASE al.NIVEL_RIESGO WHEN 'CRITICO' THEN 1 WHEN 'ALTO' THEN 2 WHEN 'MEDIO' THEN 3 ELSE 4 END,
                 al.FECHA_CREACION DESC
    ")->fetchAll();
}

$paginaActual = 'seguimiento.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Seguimiento de casos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">📝 Seguimiento de casos</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <?php if ($idAlerta === 0 || $alerta === null): ?>
                <!-- ═══ LISTA DE ALERTAS ACTIVAS ════════════════════════ -->
                <div class="panel">
                    <div class="panel-header"><h3>Alertas activas con seguimiento pendiente</h3></div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Escuela</th>
                                    <th>Nivel</th>
                                    <th>Estatus</th>
                                    <th>Seguimientos</th>
                                    <th>Fecha alerta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($listaAlertas)): ?>
                                <tr><td colspan="7" class="texto-centro texto-gris" style="padding:22px;">No hay alertas activas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaAlertas as $la): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($la['alumno_nombre']) ?></strong>
                                        <div class="texto-gris"><?= h($la['MATRICULA']) ?></div>
                                    </td>
                                    <td><?= h($la['NOMBRE_ESCUELA'] ?? '—') ?></td>
                                    <td><?= badgeRiesgo($la['NIVEL_RIESGO']) ?></td>
                                    <td><?= badgeEstatus($la['ESTATUS']) ?></td>
                                    <td style="text-align:center;"><?= $la['total_seg'] ?></td>
                                    <td class="texto-gris"><?= fechaCorta($la['FECHA_CREACION']) ?></td>
                                    <td>
                                        <a class="btn btn-primario btn-sm"
                                           href="seguimiento.php?id_alerta=<?= $la['ID_ALERTA'] ?>">
                                            Ver y registrar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- ═══ DETALLE DE ALERTA Y SEGUIMIENTO ════════════════ -->
                <div class="flex-entre mb-10">
                    <h2 style="color:#061f45;">Alerta #<?= $alerta['ID_ALERTA'] ?> — <?= h($alerta['alumno_nombre']) ?></h2>
                    <a class="btn btn-secundario btn-sm" href="seguimiento.php">← Volver</a>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;">
                    <!-- Datos del alumno -->
                    <div class="panel">
                        <div class="panel-header"><h3>👤 Datos del alumno</h3></div>
                        <div class="panel-body">
                            <table style="font-size:13px;">
                                <tr><td style="color:#64748b;width:38%;">Matrícula</td><td><strong><?= h($alerta['MATRICULA']) ?></strong></td></tr>
                                <tr><td style="color:#64748b;">Escuela</td><td><?= h($alerta['NOMBRE_ESCUELA'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Municipio</td><td><?= h($alerta['MUNICIPIO'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Grado/Grupo</td><td><?= h($alerta['GRADO'] ?? '—') ?>° <?= h($alerta['letra_grupo'] ?? '') ?></td></tr>
                                <tr><td style="color:#64748b;">Teléfono</td><td><?= h($alerta['TELEFONO'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Correo</td><td><?= h($alerta['CORREO'] ?? '—') ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Datos de la evaluación -->
                    <div class="panel">
                        <div class="panel-header"><h3>📋 Evaluación SISAT</h3></div>
                        <div class="panel-body">
                            <div style="display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap;">
                                <div style="text-align:center;">
                                    <div style="font-size:11px;color:#64748b;font-weight:600;">NIVEL DE RIESGO</div>
                                    <div style="margin-top:4px;"><?= badgeRiesgo($alerta['NIVEL_RIESGO']) ?></div>
                                </div>
                                <div style="text-align:center;">
                                    <div style="font-size:11px;color:#64748b;font-weight:600;">PUNTAJE</div>
                                    <div style="font-size:28px;font-weight:900;color:#061f45;"><?= $alerta['PUNTAJE_RIESGO'] ?></div>
                                </div>
                                <div style="text-align:center;">
                                    <div style="font-size:11px;color:#64748b;font-weight:600;">ASISTENCIA</div>
                                    <div style="font-size:22px;font-weight:700;"><?= number_format($alerta['ASISTENCIA_PORCENTAJE'], 1) ?>%</div>
                                </div>
                                <div style="text-align:center;">
                                    <div style="font-size:11px;color:#64748b;font-weight:600;">PROMEDIO</div>
                                    <div style="font-size:22px;font-weight:700;"><?= number_format($alerta['PROMEDIO_GENERAL'], 1) ?></div>
                                </div>
                            </div>
                            <div style="font-size:12px;margin-bottom:8px;font-weight:700;color:#334155;">Indicadores marcados:</div>
                            <?php foreach ($indicadoresEval as $ind): ?>
                                <?php if ($ind['VALOR']): ?>
                                    <div style="font-size:12px;padding:4px 0;color:#b91c1c;">
                                        ⚠️ <?= h($ind['NOMBRE_INDICADOR']) ?>
                                        <?php if ($ind['OBSERVACION']): ?>
                                            <span style="color:#64748b;"> — <?= h($ind['OBSERVACION']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($alerta['obs_eval']): ?>
                                <div style="margin-top:10px;font-size:12px;color:#64748b;">
                                    <strong>Observaciones:</strong> <?= h($alerta['obs_eval']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Historial de seguimientos -->
                <div class="panel mb-20">
                    <div class="panel-header"><h3>📋 Historial de seguimientos</h3></div>
                    <?php if (empty($histSeguimiento)): ?>
                        <div class="panel-body texto-gris">Sin seguimientos registrados aún.</div>
                    <?php else: ?>
                        <div class="panel-body" style="padding:0;">
                            <?php foreach ($histSeguimiento as $seg): ?>
                                <div style="padding:16px 22px;border-bottom:1px solid #f1f5f9;">
                                    <div class="flex-entre mb-10">
                                        <div>
                                            <span style="font-weight:700;color:#061f45;"><?= h($seg['nombre_usuario']) ?></span>
                                            <span class="texto-gris" style="margin-left:10px;"><?= fechaHora($seg['FECHA_SEGUIMIENTO']) ?></span>
                                        </div>
                                    </div>
                                    <div style="font-size:14px;margin-bottom:6px;">
                                        <strong>Acción realizada:</strong> <?= h($seg['ACCION_REALIZADA']) ?>
                                    </div>
                                    <?php if ($seg['RESULTADO']): ?>
                                        <div style="font-size:13px;color:#475569;margin-bottom:4px;">
                                            <strong>Resultado:</strong> <?= h($seg['RESULTADO']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($seg['PROXIMA_ACCION']): ?>
                                        <div style="font-size:13px;color:#475569;">
                                            <strong>Próxima acción:</strong> <?= h($seg['PROXIMA_ACCION']) ?>
                                            <?php if ($seg['FECHA_PROXIMA_ACCION']): ?>
                                                <span class="texto-gris">(<?= fechaCorta($seg['FECHA_PROXIMA_ACCION']) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Registrar nuevo seguimiento -->
                <?php if (!esAlumno()): ?>
                <div class="panel">
                    <div class="panel-header"><h3>➕ Registrar seguimiento</h3></div>
                    <div class="panel-body">
                        <form method="POST">
                            <input type="hidden" name="guardar_seguimiento" value="1">
                            <input type="hidden" name="id_alerta" value="<?= $idAlerta ?>">

                            <div class="form-group mb-20">
                                <label>Estatus de la alerta</label>
                                <select name="nuevo_estatus">
                                    <?php
                                    $estatusOpts = ['NUEVA','EN_REVISION','EN_SEGUIMIENTO','ATENDIDA','CERRADA'];
                                    $labels = ['Nueva','En revisión','En seguimiento','Atendida','Cerrada'];
                                    foreach ($estatusOpts as $i => $est): ?>
                                        <option value="<?= $est ?>" <?= $alerta['ESTATUS'] === $est ? 'selected' : '' ?>>
                                            <?= $labels[$i] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mb-20">
                                <label>Acción realizada *</label>
                                <textarea name="accion_realizada" rows="3" required
                                          placeholder="Describe la acción realizada..."></textarea>
                            </div>

                            <div class="form-group mb-20">
                                <label>Resultado obtenido</label>
                                <textarea name="resultado" rows="2"
                                          placeholder="¿Cuál fue el resultado de la acción?"></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Próxima acción</label>
                                    <textarea name="proxima_accion" rows="2"
                                              placeholder="¿Qué se hará en el próximo seguimiento?"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de próxima acción</label>
                                    <input type="date" name="fecha_proxima_accion">
                                </div>
                            </div>

                            <div class="flex-gap mt-20">
                                <button class="btn btn-primario" type="submit">Guardar seguimiento</button>
                                <a class="btn btn-secundario" href="seguimiento.php">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
