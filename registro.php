<?php
session_start();
require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $matricula = trim($_POST["matricula"]);
    $nombre = trim($_POST["nombre"]);
    $paterno = trim($_POST["paterno"]);
    $materno = trim($_POST["materno"]);
    $email = trim($_POST["email"]);
    $telefono = trim($_POST["telefono"]);
    $contrasena = trim($_POST["contrasena"]);

    //$regexPassword = "/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.#_-])[A-Za-z\d@$!%*?&.#_-]{8,}$/";
    $regexPassword = "/^(?=.*[A-Z])(?=.*\d)(?=.*[$])[A-Za-z\d$]{8,}$/";

    if (
        $matricula != "" &&
        $nombre != "" &&
        $email != "" &&
        $contrasena != "" &&
        preg_match($regexPassword, $contrasena)
    ) {

        try {

            $sql = "INSERT INTO estudiante 
            (MATRICULA, NOMBRE, PATERNO, MATERNO, EMAIL, TELEFONO, CONTRASENA)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $matricula,
                $nombre,
                $paterno,
                $materno,
                $email,
                $telefono,
                password_hash($contrasena, PASSWORD_DEFAULT)
            ]);

            $mensaje = "Registrado correctamente. 
            <a href='login.php'>Iniciar sesión aquí</a>";

        } catch (Exception $e) {

            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La matrícula o correo ya existen',
                    confirmButtonColor: '#dc2626'
                });
            </script>
            ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <title>Registro</title>

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
            position:relative;
        }

        .volver{
            position:absolute;
            top:25px;
            left:25px;
            background:white;
            color:#0b2a4a;
            padding:12px 18px;
            border-radius:10px;
            text-decoration:none;
            font-weight:bold;
            box-shadow:0 4px 12px rgba(0,0,0,.08);
            transition:.2s;
        }

        .volver:hover{
            background:#e2e8f0;
        }

        .contenedor{
            width:100%;
            max-width:450px;
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

        form{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        input{
            padding:14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:15px;
            outline:none;
        }

        input:focus{
            border-color:#2563eb;
        }

        .info-pass{
            color:#64748b;
            font-size:13px;
            margin-top:-6px;
            margin-bottom:6px;
            line-height:1.4;
        }

        button{
            background:#0b2a4a;
            color:white;
            padding:14px;
            border:none;
            border-radius:10px;
            cursor:pointer;
            font-size:15px;
            font-weight:bold;
            transition:.2s;
        }

        button:hover{
            background:#123d69;
        }

        .mensaje{
            margin-top:18px;
            text-align:center;
            font-weight:600;
        }

        .mensaje a{
            color:#2563eb;
            text-decoration:none;
            font-weight:bold;
        }

        .mensaje a:hover{
            text-decoration:underline;
        }

    </style>

</head>

<body>

<a class="volver" href="login.php">
    ← Volver al login
</a>

<div class="contenedor">

    <h2>Registro de estudiante</h2>

    <form method="POST" id="formRegistro">

        <input type="text" name="matricula" placeholder="Matrícula">

        <input type="text" name="nombre" placeholder="Nombre">

        <input type="text" name="paterno" placeholder="Apellido paterno">

        <input type="text" name="materno" placeholder="Apellido materno">

        <input type="email" name="email" placeholder="Correo electrónico">

        <input type="text" name="telefono" placeholder="Teléfono">

        <input 
            type="password" 
            name="contrasena" 
            id="contrasena" 
            placeholder="Contraseña"
        >

        <input 
            type="password" 
            name="confirmar_contrasena" 
            id="confirmar_contrasena"
            placeholder="Confirmar contraseña"
        >

        <small class="info-pass">
            La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.
        </small>

        <button type="submit">
            Registrarse
        </button>

    </form>

    <p class="mensaje">
        <?php echo $mensaje; ?>
    </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

document.getElementById("formRegistro").addEventListener("submit", function(e){

    let matricula = document.querySelector("[name='matricula']").value.trim();
    let nombre = document.querySelector("[name='nombre']").value.trim();
    let email = document.querySelector("[name='email']").value.trim();
    let contrasena = document.querySelector("[name='contrasena']").value.trim();
    let confirmar = document.querySelector("[name='confirmar_contrasena']").value.trim();

    let regexPassword =
    /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.#_-])[A-Za-z\d@$!%*?&.#_-]{8,}$/;

    if(
        matricula === "" ||
        nombre === "" ||
        email === "" ||
        contrasena === "" ||
        confirmar === ""
    ){

        e.preventDefault();

        Swal.fire({
            icon: 'warning',
            title: '¡Atención!',
            text: 'Necesitas completar los campos obligatorios',
            confirmButtonText: 'DE ACUERDO',
            confirmButtonColor: '#6c5ce7'
        });

        return;
    }

    if(contrasena !== confirmar){

        e.preventDefault();

        Swal.fire({
            icon: 'error',
            title: 'Contraseñas diferentes',
            text: 'Las contraseñas no coinciden',
            confirmButtonText: 'DE ACUERDO',
            confirmButtonColor: '#dc2626'
        });

        return;
    }

    if(!regexPassword.test(contrasena)){

        e.preventDefault();

        Swal.fire({
            icon: 'warning',
            title: 'Contraseña insegura',
            text: 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.',
            confirmButtonText: 'DE ACUERDO',
            confirmButtonColor: '#6c5ce7'
        });

        return;
    }

});

</script>

</body>
</html>