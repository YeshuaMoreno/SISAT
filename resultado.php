<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario"]) || !isset($_SESSION["examen"])) {
    header("Location: login.php");
    exit;
}

$examen = $_SESSION["examen"];
$matricula = $_SESSION["usuario"]["matricula"];

$correctas = $examen["correctas"];
$total = $examen["total"];
$valor = $examen["valor"];
$tipo = $examen["tipo"];

$calificacion = $correctas * $valor;
$porcentaje = ($correctas / $total) * 100;

$estado = ($porcentaje >= 75) ? "APROBADO" : "NO APROBADO";

// Guardar en historial
$stmt = $pdo->prepare("
    INSERT INTO historial_estudiante
    (MATRICULA, TIPO_TEST, FECHA_HORA_REALIZA, CALIFICACION)
    VALUES (?, ?, NOW(), ?)
");
$stmt->execute([$matricula, $tipo, $porcentaje]);

unset($_SESSION["examen"]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resultado</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<div class="contenedor contenedor-chico">
    <h2>Resultado</h2>

    <p>Correctas: <?php echo $correctas; ?> / <?php echo $total; ?></p>
    <p>Porcentaje: <?php echo number_format($porcentaje,2); ?>%</p>

    <p class="<?php echo ($estado=='APROBADO') ? 'resultado-aprobado' : 'resultado-no'; ?>">
        <?php echo $estado; ?>
    </p>

    <a class="boton" href="menu.php">Volver</a>
</div>

</body>
</html>