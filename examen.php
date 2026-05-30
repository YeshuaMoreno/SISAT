<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["examen"])) {
    header("Location: menu.php");
    exit;
}

$examen = &$_SESSION["examen"];
$indice = $examen["indice"];
$total = $examen["total"];
$matricula = $_SESSION["usuario"]["matricula"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idPregunta = $examen["preguntas"][$indice];
    $idRespuesta = isset($_POST["respuesta"]) ? (int)$_POST["respuesta"] : 0;

    $texto = "Sin responder";

    if ($idRespuesta > 0) {

        $stmt = $pdo->prepare("
            SELECT OPCION, OK
            FROM respuestas
            WHERE ID_PREGUNTA = ? AND ID_RESPUESTA = ?
        ");

        $stmt->execute([$idPregunta, $idRespuesta]);

        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($r) {

            $texto = $r["OPCION"];

            if ($r["OK"] == 1) {
                $examen["correctas"]++;
            }
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO examen_estudiante
        (ID_PREGUNTA, MATRICULA, RESPUESTA)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $idPregunta,
        $matricula,
        $texto
    ]);

    $examen["indice"]++;

    if ($examen["indice"] >= $examen["total"]) {

        header("Location: resultado.php");
        exit;

    }

    header("Location: examen.php");
    exit;
}

$idPreguntaActual = $examen["preguntas"][$indice];

$stmt = $pdo->prepare("
    SELECT p.*, b.RUTA
    FROM preguntas p
    LEFT JOIN banco_imagenes b
    ON p.CODIGO_IMAGEN = b.CODIGO_IMAGEN
    WHERE p.ID_PREGUNTA = ?
");

$stmt->execute([$idPreguntaActual]);

$pregunta = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM respuestas
    WHERE ID_PREGUNTA = ?
");

$stmt->execute([$idPreguntaActual]);

$respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Examen</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:"Segoe UI", Arial, sans-serif;
}

body{
    background:#eef2f7;
    color:#0f172a;
}

.topbar{
    height:72px;
    background:#0b2a4a;
    color:white;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 30px;
}

.topbar h1{
    font-size:20px;
}

.usuario{
    font-weight:bold;
}

.layout{
    display:flex;
}

.sidebar{
    width:220px;
    background:white;
    padding:20px;
    border-right:1px solid #e5e7eb;
    min-height:calc(100vh - 72px);
}

.nav{
    display:block;
    padding:12px;
    margin-bottom:10px;
    border-radius:8px;
    text-decoration:none;
    color:#334155;
}

.nav.activo{
    background:#eaf2ff;
    color:#2563eb;
    font-weight:bold;
}

.main{
    flex:1;
    padding:25px;
}

.card{
    background:white;
    border-radius:14px;
    padding:25px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}

.exam-header{
    display:flex;
    justify-content:space-between;
    margin-bottom:20px;
}

.badge{
    font-weight:bold;
}

.tiempo{
    color:#16a34a;
    font-weight:bold;
}

.pregunta{
    text-align:center;
    font-size:24px;
    margin-bottom:25px;
}

.img{
    display:block;
    margin:0 auto 20px;
    max-width:450px;
    width:100%;
    border-radius:10px;
}

.opcion{
    display:flex;
    align-items:center;
    gap:12px;
    padding:16px;
    border:1px solid #d1d5db;
    border-radius:12px;
    margin-bottom:14px;
    cursor:pointer;
    background:#f8fafc;
    transition:.2s;
}

.opcion:hover{
    border-color:#2563eb;
    background:#f1f5ff;
}

.opcion input{
    width:18px;
    height:18px;
    accent-color:#2563eb;
}

.opcion:has(input:checked){
    border:2px solid #2563eb;
    background:#eef4ff;
}

.footer{
    display:flex;
    justify-content:flex-end;
    margin-top:20px;
}

.btn{
    padding:13px 22px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:bold;
}

.btn-main{
    background:#0b2a4a;
    color:white;
}

.btn-main:hover{
    background:#123d69;
}

</style>

<script>

let t = 60;

function timer(){

    document.getElementById("t").innerText =
    "00:" + String(t).padStart(2, "0");

    if(t <= 0){

        document.getElementById("form").submit();

    }else{

        t--;

        setTimeout(timer, 1000);

    }
}

window.onload = timer;

</script>

</head>

<body>

<header class="topbar">

    <h1>SIMULADOR DE MANEJO</h1>

    <div class="usuario">
        <?php echo htmlspecialchars($_SESSION["usuario"]["nombre"]); ?>
    </div>

</header>

<div class="layout">

<aside class="sidebar">

    <a class="nav activo">
        Examen
    </a>

    <a class="nav" href="dashboard.php">
        Dashboard
    </a>

    <a class="nav" href="menu.php">
        Menú
    </a>

</aside>

<main class="main">

<div class="card">

<div class="exam-header">

    <div class="badge">
        <?php echo strtoupper($examen["tipo"]); ?>
    </div>

    <div class="badge">
        <?php echo $indice + 1; ?> de <?php echo $total; ?>
    </div>

    <div class="tiempo" id="t">
        01:00
    </div>

</div>

<h2 class="pregunta">

    <?php echo htmlspecialchars($pregunta["REACTIVO"]); ?>

</h2>

<?php if (!empty($pregunta["RUTA"])): ?>

    <img class="img" src="<?php echo $pregunta["RUTA"]; ?>">

<?php endif; ?>

<form method="POST" id="form">

<?php foreach($respuestas as $r): ?>

<label class="opcion">

    <input 
        type="radio"
        name="respuesta"
        value="<?php echo $r["ID_RESPUESTA"]; ?>"
    >

    <?php echo htmlspecialchars($r["OPCION"]); ?>

</label>

<?php endforeach; ?>

<div class="footer">

    <button class="btn btn-main" type="submit">
        Siguiente →
    </button>

</div>

</form>

</div>

</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

document.getElementById("form").addEventListener("submit", function(e){

    let opcion = document.querySelector('input[name="respuesta"]:checked');

    if(!opcion){

        e.preventDefault();

        Swal.fire({
            icon: 'warning',
            title: '¡Atención!',
            text: 'Debes seleccionar una respuesta',
            confirmButtonText: 'DE ACUERDO',
            confirmButtonColor: '#6c5ce7'
        });

    }

});

</script>

</body>
</html>