<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN','SEDU','DIRECTOR']);

$accion  = $_GET['accion'] ?? 'lista';
$id      = (int)($_GET['id'] ?? 0);
$mensaje = '';
$tipo    = '';

// ── Eliminar (lógica) ──────────────────────────────────────────
if ($accion === 'eliminar' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE escuela SET ACTIVA = 0 WHERE ID_ESCUELA = ?");
    $stmt->execute([$id]);
    header('Location: escuelas.php?msg=eliminada');
    exit;
}

// ── Guardar nuevo / editar ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'CCT'            => strtoupper(trim($_POST['cct'] ?? '')),
        'NOMBRE_ESCUELA' => trim($_POST['nombre_escuela'] ?? ''),
        'MUNICIPIO'      => trim($_POST['municipio'] ?? ''),
        'NIVEL'          => $_POST['nivel'] ?? 'SECUNDARIA',
        'ZONA_ESCOLAR'   => strtoupper(trim($_POST['zona_escolar'] ?? '')),
        'ACTIVA'         => isset($_POST['activa']) ? 1 : 0,
    ];

    if ($datos['CCT'] === '' || $datos['NOMBRE_ESCUELA'] === '' || $datos['MUNICIPIO'] === '') {
        $mensaje = 'CCT, nombre de escuela y municipio son obligatorios.';
        $tipo    = 'error';
        $accion  = ($id > 0) ? 'editar' : 'nueva';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE escuela SET CCT=?, NOMBRE_ESCUELA=?, MUNICIPIO=?, NIVEL=?, ZONA_ESCOLAR=?, ACTIVA=? WHERE ID_ESCUELA=?");
            $stmt->execute(array_values($datos) + [$id]);
            $stmt->execute([...array_values($datos), $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO escuela (CCT, NOMBRE_ESCUELA, MUNICIPIO, NIVEL, ZONA_ESCOLAR, ACTIVA) VALUES (?,?,?,?,?,?)");
            $stmt->execute(array_values($datos));
        }
        header('Location: escuelas.php?msg=' . ($id > 0 ? 'editada' : 'creada'));
        exit;
    }
}

// ── Cargar escuela para editar ─────────────────────────────────
$escuela = [];
if (($accion === 'editar' || $accion === 'ver') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM escuela WHERE ID_ESCUELA = ?");
    $stmt->execute([$id]);
    $escuela = $stmt->fetch() ?: [];
}

// ── Listado ────────────────────────────────────────────────────
$lista = [];
if ($accion === 'lista') {
    $lista = $pdo->query("SELECT * FROM escuela ORDER BY NOMBRE_ESCUELA")->fetchAll();
}

$paginaActual = 'escuelas.php';
$msgs = ['creada' => 'Escuela registrada correctamente.', 'editada' => 'Escuela actualizada.', 'eliminada' => 'Escuela desactivada.'];
if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])) {
    $mensaje = $msgs[$_GET['msg']];
    $tipo    = 'exito';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Escuelas</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">🏫 Escuelas</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <?php if ($accion === 'lista'): ?>
                <!-- LISTADO -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>Escuelas registradas</h3>
                        <?php if (esAdmin() || esSedu()): ?>
                            <a class="btn btn-primario btn-sm" href="escuelas.php?accion=nueva">+ Nueva escuela</a>
                        <?php endif; ?>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>CCT</th>
                                    <th>Nombre</th>
                                    <th>Municipio</th>
                                    <th>Nivel</th>
                                    <th>Zona</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($lista)): ?>
                                <tr><td colspan="7" class="texto-centro texto-gris" style="padding:22px;">No hay escuelas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $e): ?>
                                <tr>
                                    <td><strong><?= h($e['CCT']) ?></strong></td>
                                    <td><?= h($e['NOMBRE_ESCUELA']) ?></td>
                                    <td><?= h($e['MUNICIPIO']) ?></td>
                                    <td><?= h($e['NIVEL']) ?></td>
                                    <td><?= h($e['ZONA_ESCOLAR']) ?></td>
                                    <td>
                                        <?php if ($e['ACTIVA']): ?>
                                            <span class="badge-estatus est-atendida">Activa</span>
                                        <?php else: ?>
                                            <span class="badge-estatus est-cerrada">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex-gap">
                                            <a class="btn btn-secundario btn-sm" href="escuelas.php?accion=editar&id=<?= $e['ID_ESCUELA'] ?>">Editar</a>
                                            <?php if (esAdmin()): ?>
                                                <a class="btn btn-peligro btn-sm" href="escuelas.php?accion=eliminar&id=<?= $e['ID_ESCUELA'] ?>"
                                                   onclick="return confirm('¿Desactivar esta escuela?')">Desactivar</a>
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

            <?php elseif ($accion === 'nueva' || $accion === 'editar'): ?>
                <!-- FORMULARIO -->
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $accion === 'nueva' ? 'Registrar nueva escuela' : 'Editar escuela' ?></h3>
                        <a class="btn btn-secundario btn-sm" href="escuelas.php">← Volver</a>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>CCT *</label>
                                    <input type="text" name="cct" maxlength="15" required
                                           value="<?= h($escuela['CCT'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Zona escolar *</label>
                                    <input type="text" name="zona_escolar" maxlength="30" required
                                           value="<?= h($escuela['ZONA_ESCOLAR'] ?? '') ?>">
                                </div>
                                <div class="form-group full">
                                    <label>Nombre de la escuela *</label>
                                    <input type="text" name="nombre_escuela" maxlength="150" required
                                           value="<?= h($escuela['NOMBRE_ESCUELA'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Municipio *</label>
                                    <input type="text" name="municipio" maxlength="80" required
                                           value="<?= h($escuela['MUNICIPIO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Nivel educativo</label>
                                    <select name="nivel">
                                        <?php foreach (['PREESCOLAR','PRIMARIA','SECUNDARIA','BACHILLERATO','OTRO'] as $n): ?>
                                            <option value="<?= $n ?>" <?= ($escuela['NIVEL'] ?? 'SECUNDARIA') === $n ? 'selected' : '' ?>><?= $n ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="justify-content:flex-end;flex-direction:row;align-items:center;gap:10px;">
                                    <input type="checkbox" name="activa" id="activa" style="width:auto;"
                                           <?= ($escuela['ACTIVA'] ?? 1) ? 'checked' : '' ?>>
                                    <label for="activa">Escuela activa</label>
                                </div>
                            </div>
                            <div class="flex-gap mt-20">
                                <button class="btn btn-primario" type="submit">
                                    <?= $accion === 'nueva' ? 'Registrar escuela' : 'Guardar cambios' ?>
                                </button>
                                <a class="btn btn-secundario" href="escuelas.php">Cancelar</a>
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
