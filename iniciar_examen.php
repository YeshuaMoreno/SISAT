<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

$tipo = $_GET["tipo"] ?? "practica";

if ($tipo === "final") {
    $limite = 40;
    $valor = 2.5;
} else {
    $tipo = "practica";
    $limite = 20;
    $valor = 5;
}

// 🔥 Preguntas aleatorias SIN repetir
$sql = "SELECT ID_PREGUNTA FROM preguntas ORDER BY RAND() LIMIT $limite";
$preguntas = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

$_SESSION["examen"] = [
    "tipo" => $tipo,
    "preguntas" => $preguntas,
    "indice" => 0,
    "correctas" => 0,
    "total" => count($preguntas),
    "valor" => $valor
];

header("Location: examen.php");
exit;