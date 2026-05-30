<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';

// Si ya está autenticado, redirigir
if (isset($_SESSION['usuario'])) {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario']    ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        $mensaje = 'Completa todos los campos.';
    } else {
        $sql = "
            SELECT u.ID_USUARIO, u.USUARIO, u.CORREO, u.PWD, u.ACTIVO,
                   r.NOMBRE_ROL
            FROM usuario u
            INNER JOIN rol r ON u.ID_ROL = r.ID_ROL
            WHERE (u.USUARIO = ? OR u.CORREO = ?)
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario, $usuario]);
        $row = $stmt->fetch();

        if ($row && (int)$row['ACTIVO'] === 1 && password_verify($contrasena, $row['PWD'])) {
            $_SESSION['usuario'] = [
                'id'     => $row['ID_USUARIO'],
                'nombre' => $row['USUARIO'],
                'correo' => $row['CORREO'],
                'rol'    => $row['NOMBRE_ROL'],
            ];
            header('Location: dashboard.php');
            exit;
        } else {
            $mensaje = 'Usuario o contraseña incorrectos, o cuenta inactiva.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISAT — Iniciar sesión</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        body {
            background: linear-gradient(135deg, #061f45 0%, #0b4f8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            width: 100%;
            max-width: 420px;
            padding: 42px 38px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo h1 {
            font-size: 32px;
            font-weight: 900;
            color: #061f45;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .login-logo p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }
        .login-logo .escudo {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            transition: border-color .2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1e3a5f;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #061f45;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: background .2s;
        }
        .btn-login:hover { background: #0b3570; }
        .alerta-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
            text-align: center;
        }
        .pie {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 22px;
        }
    </style>
</head>
<body>

<div class="login-card">

    <div class="login-logo">
        <span class="escudo">🏫</span>
        <h1>SISAT</h1>
        <p>Sistema de Alerta Temprana<br>para Abandono Escolar</p>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alerta-error"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label for="usuario">Usuario o correo electrónico</label>
            <input
                type="text"
                id="usuario"
                name="usuario"
                placeholder="Escribe tu usuario o correo"
                value="<?= h($_POST['usuario'] ?? '') ?>"
                required
                autofocus
            >
        </div>

        <div class="form-group">
            <label for="contrasena">Contraseña</label>
            <input
                type="password"
                id="contrasena"
                name="contrasena"
                placeholder="Contraseña"
                required
            >
        </div>

        <button class="btn-login" type="submit">Iniciar sesión</button>
    </form>

    <p class="pie">SISAT &copy; <?= date('Y') ?> &mdash; Secretaría de Educación</p>
</div>

</body>
</html>
