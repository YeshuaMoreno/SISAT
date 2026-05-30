<?php
/**
 * hash_sisat.php
 * Genera los hashes reales de contraseñas para los usuarios de prueba.
 * EJECUTAR UNA SOLA VEZ y copiar los hashes al SQL.
 * ELIMINAR este archivo en producción.
 */
$contrasenas = [
    'admin'      => 'Admin123*',
    'sedu'       => 'Sedu123*',
    'director'   => 'Director123*',
    'docente'    => 'Docente123*',
    'orientador' => 'Orientador123*',
    'alumno01'   => 'Alumno123*',
    'alumno02'   => 'Alumno123*',
    'alumno03'   => 'Alumno123*',
    'alumno04'   => 'Alumno123*',
    'alumno05'   => 'Alumno123*',
];
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Hash SISAT</title>
<style>body{font-family:monospace;padding:30px;background:#f8fafc;}table{border-collapse:collapse;width:100%;}th,td{padding:10px;border:1px solid #ddd;text-align:left;}th{background:#061f45;color:#fff;}tr:nth-child(even){background:#f1f5f9;}</style>
</head>
<body>
<h2>Hashes de contraseñas SISAT</h2>
<p style="color:#dc2626;font-weight:bold;">⚠️ Eliminar este archivo después de importar los hashes a la base de datos.</p>
<table>
<thead><tr><th>Usuario</th><th>Contraseña</th><th>Hash (copiar al SQL)</th></tr></thead>
<tbody>
<?php foreach ($contrasenas as $user => $pwd): ?>
<tr>
    <td><?= htmlspecialchars($user) ?></td>
    <td><?= htmlspecialchars($pwd) ?></td>
    <td style="font-size:12px;word-break:break-all;"><?= password_hash($pwd, PASSWORD_DEFAULT) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h3 style="margin-top:30px;">SQL para actualizar contraseñas</h3>
<pre style="background:#1e293b;color:#e2e8f0;padding:20px;border-radius:8px;overflow-x:auto;">
<?php foreach ($contrasenas as $user => $pwd):
    $hash = password_hash($pwd, PASSWORD_DEFAULT); ?>
UPDATE usuario SET PWD = '<?= $hash ?>' WHERE USUARIO = '<?= $user ?>';
<?php endforeach; ?>
</pre>

<p style="margin-top:20px;color:#64748b;">Copia el SQL de arriba y ejecútalo en tu gestor de base de datos (phpMyAdmin, MySQL Workbench, etc.)</p>
</body>
</html>
