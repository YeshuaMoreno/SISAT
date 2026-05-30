<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

$matricula = $_SESSION["usuario"]["matricula"];

/* Intentos simulador */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM historial_estudiante
    WHERE MATRICULA = ?
    AND TIPO_TEST = 'practica'
");
$stmt->execute([$matricula]);
$intentosPractica = $stmt->fetchColumn();

/* Intentos final */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM historial_estudiante
    WHERE MATRICULA = ?
    AND TIPO_TEST = 'final'
");
$stmt->execute([$matricula]);
$intentosFinal = $stmt->fetchColumn();

$restantesPractica = 6 - $intentosPractica;
$restantesFinal = 3 - $intentosFinal;
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <title>Menú</title>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:"Segoe UI", Arial, sans-serif;
        }

        body{
            background:#eef2f7;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
        }

        .contenedor{
            width:100%;
            max-width:500px;
            background:white;
            padding:35px;
            border-radius:16px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
        }

        h2{
            text-align:center;
            margin-bottom:25px;
            color:#0b2a4a;
        }

        .info{
            background:#f8fafc;
            border:1px solid #dbe3ef;
            border-radius:12px;
            padding:15px;
            margin-bottom:20px;
        }

        .info p{
            margin-bottom:10px;
            font-size:15px;
            color:#334155;
        }

        .info strong{
            color:#0b63ce;
        }

        .boton{
            display:block;
            width:100%;
            text-align:center;
            background:#0b2a4a;
            color:white;
            padding:14px;
            margin-bottom:14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:bold;
            transition:.2s;
        }

        .boton:hover{
            background:#123d69;
        }

        .boton-secundario{
            background:#2563eb;
        }

        .boton-secundario:hover{
            background:#1d4ed8;
        }

        .boton-salir{
            background:#dc2626;
        }

        .boton-salir:hover{
            background:#b91c1c;
        }

        .agotado{
            background:#94a3b8 !important;
            cursor:not-allowed;
            pointer-events:none;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <h2>
        Bienvenido <?php echo htmlspecialchars($_SESSION["usuario"]["nombre"]); ?>
    </h2>

    <div class="info">

        <p>
            Intentos simulador usados:
            <strong>
                <?php echo $intentosPractica; ?> / 6
            </strong>
        </p>

        <p>
            Intentos restantes:
            <strong>
                <?php echo $restantesPractica; ?>
            </strong>
        </p>

        <br>

        <p>
            Intentos examen final usados:
            <strong>
                <?php echo $intentosFinal; ?> / 3
            </strong>
        </p>

        <p>
            Intentos restantes:
            <strong>
                <?php echo $restantesFinal; ?>
            </strong>
        </p>

    </div>

    <?php if($restantesPractica > 0): ?>

        <a class="boton" href="iniciar_examen.php?tipo=practica">
            Simulador
        </a>

    <?php else: ?>

        <a class="boton agotado">
            Simulador agotado
        </a>

    <?php endif; ?>


    <?php if($restantesFinal > 0): ?>

        <a class="boton" href="iniciar_examen.php?tipo=final">
            Examen Final
        </a>

    <?php else: ?>

        <a class="boton agotado">
            Examen final agotado
        </a>

    <?php endif; ?>


    <a class="boton boton-secundario" href="dashboard.php">
        Dashboard
    </a>

    <a class="boton boton-salir" href="logout.php">
        Cerrar sesión
    </a>

</div>

</body>
</html>