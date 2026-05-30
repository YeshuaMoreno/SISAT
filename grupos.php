<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN','SEDU','DIRECTOR','DOCENTE']);

$accion  = $_GET['accion'] ?? 'lista';
$id      = (int)($_GET['id'] ?? 0);
$mensaje = '';
$tipo    = '';

// ── Guardar ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEscuela  = (int)($_POST['id_escuela'] ?? 0);
    $grado      = trim($_POST['grado'] ?? '');
    $grupo      = strtoupper(trim($_POST['grupo'] ?? ''));
    $ciclo      = trim($_POST['ciclo_escolar'] ?? '');
    $idDocente  = (int)($_POST['id_docente'] ?? 0) ?: null;

    if (!$idEscuela || $grado === '' || $grupo === '' || $ciclo === '') {
        $mensaje = 'Escuela, grado, grupo y ciclo escolar son obligatorios.';
        $tipo    = 'error';
        $accion  = ($id > 0) ? 'editar' : 'nuevo';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE grupo SET ID_ESCUELA=?, GRADO=?, GRUPO=?, CICLO_ESCOLAR=?, ID_DOCENTE=? WHERE ID_GRUPO=?");
            $stmt->execute([$idEscuela, $grado, $grupo, $ciclo, $idDocente, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO grupo (ID_ESCUELA, GRADO, GRUPO, CICLO_ESCOLAR, ID_DOCENTE) VALUES (?,?,?,?,?)");
            $stmt->execute([$idEscuela, $grado, $grupo, $ciclo, $idDocente]);
        }
        header('Location: grupos.php?msg=' . ($id > 0 ? 'editado' : 'creado'));
        exit;
    }
}

// ── Eliminar ───────────────────────────────────────────────────
if ($accion === 'eliminar' && $id > 0) {
    $pdo->prepare("DELETE FROM grupo WHERE ID_GRUPO = ?")->execute([$id]);
    header('Location: grupos.php?msg=eliminado');
    exit;
}

// ── Cargar datos para editar ───────────────────────────────────
$grupo = [];
if (($accion === 'editar') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM grupo WHERE ID_GRUPO = ?");
    $stmt->execute([$id]);
    $grupo = $stmt->fetch() ?: [];
}

// ── Catálogos ──────────────────────────────────────────────────
$escuelas = $pdo->query("SELECT ID_ESCUELA, NOMBRE_ESCUELA FROM escuela WHERE ACTIVA=1 ORDER BY NOMBRE_ESCUELA")->fetchAll();
$docentes = $pdo->query("
    SELECT u.ID_USUARIO, u.USUARIO
    FROM usuario u
    INNER JOIN rol r ON u.ID_ROL = r.ID_ROL
    WHERE r.NOMBRE_ROL = 'DOCENTE' AND u.ACTIVO = 1
    ORDER BY u.USUARIO
")->fetchAll();

// ── Listado ────────────────────────────────────────────────────
$lista = [];
if ($accion === 'lista') {
    $lista = $pdo->query("
        SELECT g.*, esc.NOMBRE_ESCUELA, u.USUARIO AS docente_nombre,
               COUNT(a.ID_ALUMNO) AS total_alumnos
        FROM grupo g
        LEFT JOIN escuela esc ON esc.ID_ESCUELA = g.ID_ESCUELA
        LEFT JOIN usuario u   ON u.ID_USUARIO   = g.ID_DOCENTE
        LEFT JOIN alumno a    ON a.ID_GRUPO      = g.ID_GRUPO AND a.ACTIVO = 1
        GROUP BY g.ID_GRUPO
        ORDER BY esc.NOMBRE_ESCUELA, g.GRADO, g.GRUPO
    ")->fetchAll();
}

$msgs = ['creado' => 'Grupo registrado.', 'editado' => 'Grupo actualizado.', 'eliminado' => 'Grupo eliminado.'];
if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])) { $mensaje = $msgs[$_GET['msg']]; $tipo = 'exito'; }

$paginaActual = 'grupos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Grupos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">📚 Grupos</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <?php if ($accion === 'lista'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3>Grupos registrados</h3>
                        <?php if (in_array(rolActual(), ['ADMIN','DIRECTOR'])): ?>
                            <a class="btn btn-primario btn-sm" href="grupos.php?accion=nuevo">+ Nuevo grupo</a>
                        <?php endif; ?>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Escuela</th>
                                    <th>Grado</th>
                                    <th>Grupo</th>
                                    <th>Ciclo</th>
                                    <th>Docente</th>
                                    <th>Alumnos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($lista)): ?>
                                <tr><td colspan="7" class="texto-centro texto-gris" style="padding:22px;">No hay grupos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $g): ?>
                                <tr>
                                    <td><?= h($g['NOMBRE_ESCUELA'] ?? '—') ?></td>
                                    <td><?= h($g['GRADO']) ?>°</td>
                                    <td><strong><?= h($g['GRUPO']) ?></strong></td>
                                    <td><?= h($g['CICLO_ESCOLAR']) ?></td>
                                    <td><?= h($g['docente_nombre'] ?? '—') ?></td>
                                    <td><strong><?= $g['total_alumnos'] ?></strong></td>
                                    <td>
                                        <div class="flex-gap">
                                            <a class="btn btn-secundario btn-sm" href="grupos.php?accion=editar&id=<?= $g['ID_GRUPO'] ?>">Editar</a>
                                            <a class="btn btn-peligro btn-sm" href="grupos.php?accion=eliminar&id=<?= $g['ID_GRUPO'] ?>"
                                               onclick="return confirm('¿Eliminar este grupo?')">Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $accion === 'nuevo' ? 'Registrar nuevo grupo' : 'Editar grupo' ?></h3>
                        <a class="btn btn-secundario btn-sm" href="grupos.php">← Volver</a>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group full">
                                    <label>Escuela *</label>
                                    <select name="id_escuela" required>
                                        <option value="">Selecciona una escuela</option>
                                        <?php foreach ($escuelas as $e): ?>
                                            <option value="<?= $e['ID_ESCUELA'] ?>"
                                                <?= ($grupo['ID_ESCUELA'] ?? 0) == $e['ID_ESCUELA'] ? 'selected' : '' ?>>
                                                <?= h($e['NOMBRE_ESCUELA']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Grado *</label>
                                    <select name="grado" required>
                                        <?php foreach (['1','2','3','4','5','6'] as $gr): ?>
                                            <option value="<?= $gr ?>" <?= ($grupo['GRADO'] ?? '') === $gr ? 'selected' : '' ?>><?= $gr ?>°</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Grupo *</label>
                                    <input type="text" name="grupo" maxlength="5" required
                                           placeholder="Ej: A, B, C"
                                           value="<?= h($grupo['GRUPO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Ciclo escolar *</label>
                                    <input type="text" name="ciclo_escolar" maxlength="10" required
                                           placeholder="2024-2025"
                                           value="<?= h($grupo['CICLO_ESCOLAR'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Docente asignado</label>
                                    <select name="id_docente">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($docentes as $d): ?>
                                            <option value="<?= $d['ID_USUARIO'] ?>"
                                                <?= ($grupo['ID_DOCENTE'] ?? 0) == $d['ID_USUARIO'] ? 'selected' : '' ?>>
                                                <?= h($d['USUARIO']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex-gap mt-20">
                                <button class="btn btn-primario" type="submit">
                                    <?= $accion === 'nuevo' ? 'Registrar grupo' : 'Guardar cambios' ?>
                                </button>
                                <a class="btn btn-secundario" href="grupos.php">Cancelar</a>
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
