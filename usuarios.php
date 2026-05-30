<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN']);

$accion  = $_GET['accion'] ?? 'lista';
$id      = (int)($_GET['id'] ?? 0);
$mensaje = '';
$tipo    = '';

// ── Guardar nuevo / editar ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']   ?? '');
    $correo  = trim($_POST['correo']    ?? '');
    $idRol   = (int)($_POST['id_rol']  ?? 0);
    $activo  = isset($_POST['activo']) ? 1 : 0;
    $pwd     = trim($_POST['pwd']      ?? '');

    if ($usuario === '' || $correo === '' || $idRol === 0) {
        $mensaje = 'Usuario, correo y rol son obligatorios.';
        $tipo    = 'error';
        $accion  = ($id > 0) ? 'editar' : 'nuevo';
    } else {
        if ($id > 0) {
            if ($pwd !== '') {
                $stmt = $pdo->prepare("UPDATE usuario SET USUARIO=?, CORREO=?, ID_ROL=?, ACTIVO=?, PWD=? WHERE ID_USUARIO=?");
                $stmt->execute([$usuario, $correo, $idRol, $activo, hashContrasena($pwd), $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuario SET USUARIO=?, CORREO=?, ID_ROL=?, ACTIVO=? WHERE ID_USUARIO=?");
                $stmt->execute([$usuario, $correo, $idRol, $activo, $id]);
            }
        } else {
            if ($pwd === '') {
                $mensaje = 'La contraseña es obligatoria para nuevos usuarios.';
                $tipo    = 'error';
                $accion  = 'nuevo';
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuario (USUARIO, CORREO, PWD, ID_ROL, ACTIVO) VALUES (?,?,?,?,?)");
                $stmt->execute([$usuario, $correo, hashContrasena($pwd), $idRol, $activo]);
            }
        }
        if ($tipo !== 'error') {
            header('Location: usuarios.php?msg=' . ($id > 0 ? 'editado' : 'creado'));
            exit;
        }
    }
}

// ── Activar / Desactivar ───────────────────────────────────────
if ($accion === 'toggle' && $id > 0) {
    $pdo->prepare("UPDATE usuario SET ACTIVO = 1 - ACTIVO WHERE ID_USUARIO = ?")->execute([$id]);
    header('Location: usuarios.php?msg=actualizado');
    exit;
}

// ── Cargar para editar ─────────────────────────────────────────
$usuarioEdit = [];
if (($accion === 'editar') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE ID_USUARIO = ?");
    $stmt->execute([$id]);
    $usuarioEdit = $stmt->fetch() ?: [];
}

// ── Catálogos ──────────────────────────────────────────────────
$roles = $pdo->query("SELECT * FROM rol ORDER BY ID_ROL")->fetchAll();

// ── Listado ────────────────────────────────────────────────────
$lista = [];
if ($accion === 'lista') {
    $lista = $pdo->query("
        SELECT u.*, r.NOMBRE_ROL
        FROM usuario u
        INNER JOIN rol r ON u.ID_ROL = r.ID_ROL
        ORDER BY u.ACTIVO DESC, r.ID_ROL, u.USUARIO
    ")->fetchAll();
}

$msgs = ['creado' => 'Usuario creado.', 'editado' => 'Usuario actualizado.', 'actualizado' => 'Estado actualizado.'];
if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])) { $mensaje = $msgs[$_GET['msg']]; $tipo = 'exito'; }

$paginaActual = 'usuarios.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Usuarios</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">👥 Gestión de usuarios</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <?php if ($accion === 'lista'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3>Usuarios del sistema</h3>
                        <a class="btn btn-primario btn-sm" href="usuarios.php?accion=nuevo">+ Nuevo usuario</a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($lista)): ?>
                                <tr><td colspan="7" class="texto-centro texto-gris" style="padding:22px;">Sin usuarios.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $u): ?>
                                <tr>
                                    <td class="texto-gris"><?= $u['ID_USUARIO'] ?></td>
                                    <td><strong><?= h($u['USUARIO']) ?></strong></td>
                                    <td><?= h($u['CORREO']) ?></td>
                                    <td>
                                        <span class="badge-estatus est-revision"><?= h($u['NOMBRE_ROL']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['ACTIVO']): ?>
                                            <span class="badge-estatus est-atendida">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-estatus est-cerrada">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="texto-gris"><?= fechaCorta($u['FECHA_CREACION']) ?></td>
                                    <td>
                                        <div class="flex-gap">
                                            <a class="btn btn-secundario btn-sm" href="usuarios.php?accion=editar&id=<?= $u['ID_USUARIO'] ?>">Editar</a>
                                            <?php if ($u['ID_USUARIO'] != $_SESSION['usuario']['id']): ?>
                                                <a class="btn btn-<?= $u['ACTIVO'] ? 'advertencia' : 'exito' ?> btn-sm"
                                                   href="usuarios.php?accion=toggle&id=<?= $u['ID_USUARIO'] ?>"
                                                   onclick="return confirm('¿Cambiar estado del usuario?')">
                                                    <?= $u['ACTIVO'] ? 'Desactivar' : 'Activar' ?>
                                                </a>
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

            <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $accion === 'nuevo' ? 'Crear nuevo usuario' : 'Editar usuario' ?></h3>
                        <a class="btn btn-secundario btn-sm" href="usuarios.php">← Volver</a>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nombre de usuario *</label>
                                    <input type="text" name="usuario" maxlength="60" required
                                           value="<?= h($usuarioEdit['USUARIO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Correo electrónico *</label>
                                    <input type="email" name="correo" maxlength="120" required
                                           value="<?= h($usuarioEdit['CORREO'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Rol *</label>
                                    <select name="id_rol" required>
                                        <option value="">Selecciona un rol</option>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= $r['ID_ROL'] ?>"
                                                <?= ($usuarioEdit['ID_ROL'] ?? 0) == $r['ID_ROL'] ? 'selected' : '' ?>>
                                                <?= h($r['NOMBRE_ROL']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Contraseña <?= $accion === 'editar' ? '(dejar vacío para no cambiar)' : '*' ?></label>
                                    <input type="password" name="pwd" maxlength="100"
                                           placeholder="<?= $accion === 'editar' ? 'Sin cambios si está vacío' : 'Mínimo 8 caracteres' ?>"
                                           <?= $accion === 'nuevo' ? 'required' : '' ?>>
                                </div>
                                <?php if ($accion === 'editar'): ?>
                                <div class="form-group" style="justify-content:flex-end;flex-direction:row;align-items:center;gap:10px;">
                                    <input type="checkbox" name="activo" id="activo" style="width:auto;"
                                           <?= ($usuarioEdit['ACTIVO'] ?? 1) ? 'checked' : '' ?>>
                                    <label for="activo">Usuario activo</label>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-gap mt-20">
                                <button class="btn btn-primario" type="submit">
                                    <?= $accion === 'nuevo' ? 'Crear usuario' : 'Guardar cambios' ?>
                                </button>
                                <a class="btn btn-secundario" href="usuarios.php">Cancelar</a>
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
