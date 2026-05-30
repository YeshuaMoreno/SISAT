<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireLogin();

$accion  = $_GET['accion'] ?? 'lista';
$id      = (int)($_GET['id'] ?? 0);
$mensaje = '';
$tipo    = '';

// ── Guardar ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'MATRICULA'         => trim($_POST['matricula'] ?? ''),
        'CURP'              => strtoupper(trim($_POST['curp'] ?? '')) ?: null,
        'NOMBRE'            => trim($_POST['nombre'] ?? ''),
        'APELLIDO_PATERNO'  => trim($_POST['apellido_paterno'] ?? ''),
        'APELLIDO_MATERNO'  => trim($_POST['apellido_materno'] ?? ''),
        'FECHA_NACIMIENTO'  => $_POST['fecha_nacimiento'] ?: null,
        'SEXO'              => $_POST['sexo'] ?? 'M',
        'TELEFONO'          => trim($_POST['telefono'] ?? '') ?: null,
        'CORREO'            => trim($_POST['correo'] ?? '') ?: null,
        'DIRECCION'         => trim($_POST['direccion'] ?? '') ?: null,
        'ID_GRUPO'          => (int)($_POST['id_grupo'] ?? 0) ?: null,
    ];

    if ($datos['MATRICULA'] === '' || $datos['NOMBRE'] === '' || $datos['APELLIDO_PATERNO'] === '') {
        $mensaje = 'Matrícula, nombre y apellido paterno son obligatorios.';
        $tipo    = 'error';
        $accion  = ($id > 0) ? 'editar' : 'nuevo';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE alumno SET MATRICULA=?, CURP=?, NOMBRE=?, APELLIDO_PATERNO=?, APELLIDO_MATERNO=?,
                FECHA_NACIMIENTO=?, SEXO=?, TELEFONO=?, CORREO=?, DIRECCION=?, ID_GRUPO=? WHERE ID_ALUMNO=?");
            $stmt->execute([...array_values($datos), $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO alumno (MATRICULA, CURP, NOMBRE, APELLIDO_PATERNO, APELLIDO_MATERNO,
                FECHA_NACIMIENTO, SEXO, TELEFONO, CORREO, DIRECCION, ID_GRUPO) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute(array_values($datos));
        }
        header('Location: alumnos.php?msg=' . ($id > 0 ? 'editado' : 'creado'));
        exit;
    }
}

// ── Eliminar (lógica) ──────────────────────────────────────────
if ($accion === 'eliminar' && $id > 0) {
    $pdo->prepare("UPDATE alumno SET ACTIVO = 0 WHERE ID_ALUMNO = ?")->execute([$id]);
    header('Location: alumnos.php?msg=eliminado');
    exit;
}

// ── Cargar para editar ─────────────────────────────────────────
$alumno = [];
if (($accion === 'editar' || $accion === 'ver') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM alumno WHERE ID_ALUMNO = ?");
    $stmt->execute([$id]);
    $alumno = $stmt->fetch() ?: [];
}

// ── Catálogos ──────────────────────────────────────────────────
$grupos = $pdo->query("
    SELECT g.ID_GRUPO, CONCAT(esc.NOMBRE_ESCUELA,' — ',g.GRADO,'° ',g.GRUPO,' (',g.CICLO_ESCOLAR,')') AS descripcion
    FROM grupo g
    LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
    ORDER BY esc.NOMBRE_ESCUELA, g.GRADO, g.GRUPO
")->fetchAll();

// ── Filtros y listado ──────────────────────────────────────────
$lista = [];
$filtroGrupo = (int)($_GET['grupo'] ?? 0);
$filtroNivel = trim($_GET['nivel'] ?? '');
$filtroNombre = trim($_GET['q'] ?? '');

if ($accion === 'lista') {
    $where  = ['a.ACTIVO = 1'];
    $params = [];

    if ($filtroGrupo > 0) { $where[] = 'a.ID_GRUPO = ?'; $params[] = $filtroGrupo; }
    if ($filtroNombre !== '') {
        $where[] = "(a.NOMBRE LIKE ? OR a.APELLIDO_PATERNO LIKE ? OR a.MATRICULA LIKE ?)";
        $like = "%$filtroNombre%";
        $params = array_merge($params, [$like, $like, $like]);
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(a.NOMBRE,' ',a.APELLIDO_PATERNO,' ',a.APELLIDO_MATERNO) AS nombre_completo,
               g.GRADO, g.GRUPO AS letra_grupo, g.CICLO_ESCOLAR,
               esc.NOMBRE_ESCUELA,
               ev.NIVEL_RIESGO, ev.PUNTAJE_RIESGO, ev.FECHA_EVALUACION
        FROM alumno a
        LEFT JOIN grupo g ON g.ID_GRUPO = a.ID_GRUPO
        LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
        LEFT JOIN (
            SELECT ev1.*
            FROM evaluacion_sisat ev1
            INNER JOIN (
                SELECT ID_ALUMNO, MAX(ID_EVALUACION) AS ultima FROM evaluacion_sisat GROUP BY ID_ALUMNO
            ) ult ON ev1.ID_ALUMNO = ult.ID_ALUMNO AND ev1.ID_EVALUACION = ult.ultima
        ) ev ON ev.ID_ALUMNO = a.ID_ALUMNO
        WHERE $whereStr
        ORDER BY a.APELLIDO_PATERNO, a.NOMBRE
    ");
    $stmt->execute($params);
    $lista = $stmt->fetchAll();
}

// ── Ver perfil de alumno ───────────────────────────────────────
$evaluaciones = [];
$alertasAlumno = [];
if ($accion === 'ver' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT ev.*, u.USUARIO AS capturado_por
        FROM evaluacion_sisat ev
        INNER JOIN usuario u ON u.ID_USUARIO = ev.ID_USUARIO_CAPTURA
        WHERE ev.ID_ALUMNO = ?
        ORDER BY ev.FECHA_EVALUACION DESC
    ");
    $stmt->execute([$id]);
    $evaluaciones = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT al.*, COUNT(seg.ID_SEGUIMIENTO) AS total_seguimientos
        FROM alerta al
        LEFT JOIN seguimiento seg ON seg.ID_ALERTA = al.ID_ALERTA
        WHERE al.ID_ALUMNO = ?
        GROUP BY al.ID_ALERTA
        ORDER BY al.FECHA_CREACION DESC
    ");
    $stmt->execute([$id]);
    $alertasAlumno = $stmt->fetchAll();
}

$msgs = ['creado' => 'Alumno registrado.', 'editado' => 'Datos actualizados.', 'eliminado' => 'Alumno dado de baja.'];
if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])) { $mensaje = $msgs[$_GET['msg']]; $tipo = 'exito'; }

$paginaActual = 'alumnos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Alumnos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">👤 Alumnos</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <!-- ═══ LISTADO ═══════════════════════════════════════════ -->
            <?php if ($accion === 'lista'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3>Alumnos registrados</h3>
                        <?php if (!esAlumno()): ?>
                            <a class="btn btn-primario btn-sm" href="alumnos.php?accion=nuevo">+ Nuevo alumno</a>
                        <?php endif; ?>
                    </div>
                    <div class="panel-body">
                        <!-- Filtros -->
                        <form method="GET" class="filtros">
                            <input type="hidden" name="accion" value="lista">
                            <input type="text" name="q" placeholder="Nombre o matrícula" value="<?= h($filtroNombre) ?>" style="max-width:220px;">
                            <select name="grupo">
                                <option value="">Todos los grupos</option>
                                <?php foreach ($grupos as $gr): ?>
                                    <option value="<?= $gr['ID_GRUPO'] ?>" <?= $filtroGrupo == $gr['ID_GRUPO'] ? 'selected' : '' ?>>
                                        <?= h($gr['descripcion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-secundario btn-sm" type="submit">Filtrar</button>
                            <a class="btn btn-secundario btn-sm" href="alumnos.php">Limpiar</a>
                        </form>
                    </div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Nombre completo</th>
                                    <th>Grupo</th>
                                    <th>Escuela</th>
                                    <th>Riesgo</th>
                                    <th>Última eval.</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($lista)): ?>
                                <tr><td colspan="7" class="texto-centro texto-gris" style="padding:22px;">No se encontraron alumnos.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $a): ?>
                                <tr>
                                    <td><strong><?= h($a['MATRICULA']) ?></strong></td>
                                    <td><?= h($a['nombre_completo']) ?></td>
                                    <td>
                                        <?php if ($a['GRADO']): ?>
                                            <?= h($a['GRADO']) ?>° <?= h($a['letra_grupo']) ?>
                                        <?php else: ?>
                                            <span class="texto-gris">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($a['NOMBRE_ESCUELA'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($a['NIVEL_RIESGO']): ?>
                                            <?= badgeRiesgo($a['NIVEL_RIESGO']) ?>
                                        <?php else: ?>
                                            <span class="texto-gris">Sin eval.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="texto-gris"><?= fechaCorta($a['FECHA_EVALUACION']) ?></td>
                                    <td>
                                        <div class="flex-gap">
                                            <a class="btn btn-secundario btn-sm" href="alumnos.php?accion=ver&id=<?= $a['ID_ALUMNO'] ?>">Ver</a>
                                            <?php if (!esAlumno()): ?>
                                                <a class="btn btn-secundario btn-sm" href="alumnos.php?accion=editar&id=<?= $a['ID_ALUMNO'] ?>">Editar</a>
                                                <a class="btn btn-primario btn-sm"   href="captura_sisat.php?id_alumno=<?= $a['ID_ALUMNO'] ?>">📋 Evaluar</a>
                                            <?php endif; ?>
                                            <?php if (esAdmin()): ?>
                                                <a class="btn btn-peligro btn-sm" href="alumnos.php?accion=eliminar&id=<?= $a['ID_ALUMNO'] ?>"
                                                   onclick="return confirm('¿Dar de baja a este alumno?')">Baja</a>
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

            <!-- ═══ VER PERFIL ════════════════════════════════════════ -->
            <?php elseif ($accion === 'ver' && !empty($alumno)): ?>
                <div class="flex-entre mb-10">
                    <h2 style="color:#061f45;">
                        <?= h($alumno['NOMBRE'].' '.$alumno['APELLIDO_PATERNO'].' '.$alumno['APELLIDO_MATERNO']) ?>
                    </h2>
                    <div class="flex-gap">
                        <a class="btn btn-primario btn-sm" href="captura_sisat.php?id_alumno=<?= $id ?>">📋 Nueva evaluación</a>
                        <a class="btn btn-secundario btn-sm" href="alumnos.php?accion=editar&id=<?= $id ?>">Editar datos</a>
                        <a class="btn btn-secundario btn-sm" href="alumnos.php">← Volver</a>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px;">
                    <div class="panel">
                        <div class="panel-header"><h3>Datos personales</h3></div>
                        <div class="panel-body">
                            <table style="font-size:14px;">
                                <tr><td style="color:#64748b;width:40%;">Matrícula</td><td><strong><?= h($alumno['MATRICULA']) ?></strong></td></tr>
                                <tr><td style="color:#64748b;">CURP</td><td><?= h($alumno['CURP'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Fecha de nac.</td><td><?= fechaCorta($alumno['FECHA_NACIMIENTO']) ?></td></tr>
                                <tr><td style="color:#64748b;">Sexo</td><td><?= h($alumno['SEXO']) ?></td></tr>
                                <tr><td style="color:#64748b;">Teléfono</td><td><?= h($alumno['TELEFONO'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Correo</td><td><?= h($alumno['CORREO'] ?? '—') ?></td></tr>
                                <tr><td style="color:#64748b;">Dirección</td><td><?= h($alumno['DIRECCION'] ?? '—') ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-header"><h3>Alertas activas</h3></div>
                        <div class="panel-body" style="padding:0;">
                            <table>
                                <thead><tr><th>Nivel</th><th>Estatus</th><th>Fecha</th><th></th></tr></thead>
                                <tbody>
                                <?php if (empty($alertasAlumno)): ?>
                                    <tr><td colspan="4" class="texto-centro texto-gris" style="padding:16px;">Sin alertas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($alertasAlumno as $al): ?>
                                    <tr>
                                        <td><?= badgeRiesgo($al['NIVEL_RIESGO']) ?></td>
                                        <td><?= badgeEstatus($al['ESTATUS']) ?></td>
                                        <td class="texto-gris"><?= fechaCorta($al['FECHA_CREACION']) ?></td>
                                        <td><a class="btn btn-secundario btn-sm" href="seguimiento.php?id_alerta=<?= $al['ID_ALERTA'] ?>">Ver</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h3>Historial de evaluaciones SISAT</h3></div>
                    <div class="panel-body" style="padding:0;">
                        <table>
                            <thead><tr><th>Fecha</th><th>Asistencia</th><th>Promedio</th><th>Puntaje</th><th>Nivel</th><th>Capturó</th></tr></thead>
                            <tbody>
                            <?php if (empty($evaluaciones)): ?>
                                <tr><td colspan="6" class="texto-centro texto-gris" style="padding:16px;">Sin evaluaciones.</td></tr>
                            <?php else: ?>
                                <?php foreach ($evaluaciones as $ev): ?>
                                <tr>
                                    <td><?= fechaHora($ev['FECHA_EVALUACION']) ?></td>
                                    <td><?= number_format($ev['ASISTENCIA_PORCENTAJE'], 1) ?>%</td>
                                    <td><?= number_format($ev['PROMEDIO_GENERAL'], 1) ?></td>
                                    <td><strong><?= $ev['PUNTAJE_RIESGO'] ?></strong></td>
                                    <td><?= badgeRiesgo($ev['NIVEL_RIESGO']) ?></td>
                                    <td class="texto-gris"><?= h($ev['capturado_por']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- ═══ FORMULARIO NUEVO / EDITAR ════════════════════════ -->
            <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $accion === 'nuevo' ? 'Registrar nuevo alumno' : 'Editar alumno' ?></h3>
                        <a class="btn btn-secundario btn-sm" href="alumnos.php">← Volver</a>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label>Matrícula *</label>
                                    <input type="text" name="matricula" maxlength="20" required
                                           value="<?= h($alumno['MATRICULA'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>CURP</label>
                                    <input type="text" name="curp" maxlength="18"
                                           value="<?= h($alumno['CURP'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Sexo</label>
                                    <select name="sexo">
                                        <option value="M" <?= ($alumno['SEXO'] ?? 'M') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="F" <?= ($alumno['SEXO'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                                        <option value="OTRO" <?= ($alumno['SEXO'] ?? '') === 'OTRO' ? 'selected' : '' ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Nombre(s) *</label>
                                    <input type="text" name="nombre" maxlength="80" required
                                           value="<?= h($alumno['NOMBRE'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Apellido paterno *</label>
                                    <input type="text" name="apellido_paterno" maxlength="60" required
                                           value="<?= h($alumno['APELLIDO_PATERNO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Apellido materno</label>
                                    <input type="text" name="apellido_materno" maxlength="60"
                                           value="<?= h($alumno['APELLIDO_MATERNO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento</label>
                                    <input type="date" name="fecha_nacimiento"
                                           value="<?= h($alumno['FECHA_NACIMIENTO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" maxlength="20"
                                           value="<?= h($alumno['TELEFONO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Correo</label>
                                    <input type="email" name="correo" maxlength="120"
                                           value="<?= h($alumno['CORREO'] ?? '') ?>">
                                </div>
                                <div class="form-group full">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" maxlength="200"
                                           value="<?= h($alumno['DIRECCION'] ?? '') ?>">
                                </div>
                                <div class="form-group full">
                                    <label>Grupo</label>
                                    <select name="id_grupo">
                                        <option value="">Sin grupo asignado</option>
                                        <?php foreach ($grupos as $gr): ?>
                                            <option value="<?= $gr['ID_GRUPO'] ?>"
                                                <?= ($alumno['ID_GRUPO'] ?? 0) == $gr['ID_GRUPO'] ? 'selected' : '' ?>>
                                                <?= h($gr['descripcion']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex-gap mt-20">
                                <button class="btn btn-primario" type="submit">
                                    <?= $accion === 'nuevo' ? 'Registrar alumno' : 'Guardar cambios' ?>
                                </button>
                                <a class="btn btn-secundario" href="alumnos.php">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
