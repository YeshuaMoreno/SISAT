<?php
set_time_limit(300);
require_once "conexion.php";

$nombres = [
    "Juan","Luis","Ana","Maria","Carlos","Fernanda","Diego",
    "Sofia","Pedro","Jorge","Miguel","Daniel","Andrea",
    "Camila","Valeria","Jose","Emiliano","Ricardo"
];

$apellidos = [
    "Lopez","Martinez","Garcia","Hernandez","Sanchez",
    "Torres","Flores","Ramirez","Gonzalez","Morales"
];

for ($i = 1; $i <= 5000; $i++)  {

    $matricula = 2026000000 + $i;

    $nombre = $nombres[array_rand($nombres)];
    $paterno = $apellidos[array_rand($apellidos)];
    $materno = $apellidos[array_rand($apellidos)];
    $email = strtolower($nombre . $matricula . "@correo.com");
    $telefono = "844" . rand(1000000, 9999999);
    $password = password_hash("123456", PASSWORD_DEFAULT);

    try {

        $stmt = $pdo->prepare("
            INSERT INTO estudiante
            (MATRICULA, NOMBRE, PATERNO, MATERNO, EMAIL, TELEFONO, CONTRASENA)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $matricula,
            $nombre,
            $paterno,
            $materno,
            $email,
            $telefono,
            $password
        ]);

        $intentosPractica = rand(1, 6);
        $intentosFinal = rand(0, 3);

        for ($j = 1; $j <= $intentosPractica; $j++) {
            $calificacion = rand(40, 100);
            $fecha = date("Y-m-d H:i:s", strtotime("-" . rand(1, 365) . " days"));

            $stmt2 = $pdo->prepare("
                INSERT INTO historial_estudiante
                (MATRICULA, TIPO_TEST, FECHA_HORA_REALIZA, CALIFICACION)
                VALUES (?, 'practica', ?, ?)
            ");

            $stmt2->execute([$matricula, $fecha, $calificacion]);
        }

        for ($j = 1; $j <= $intentosFinal; $j++) {
            $calificacion = rand(40, 100);
            $fecha = date("Y-m-d H:i:s", strtotime("-" . rand(1, 365) . " days"));

            $stmt3 = $pdo->prepare("
                INSERT INTO historial_estudiante
                (MATRICULA, TIPO_TEST, FECHA_HORA_REALIZA, CALIFICACION)
                VALUES (?, 'final', ?, ?)
            ");

            $stmt3->execute([$matricula, $fecha, $calificacion]);
        }

    } catch (Exception $e) {
        // Si ya existe, lo brinca y sigue
        continue;
    }
}

echo "Datos simulados generados correctamente.";
?>