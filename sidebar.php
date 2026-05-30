<?php
/**
 * sidebar.php
 * Barra lateral de navegación compartida. Incluir con require_once.
 * Variables necesarias: $_SESSION['usuario']['rol'] y opcionalmente $paginaActual
 */
$rol = $_SESSION['usuario']['rol'] ?? '';
$paginaActual = $paginaActual ?? basename($_SERVER['PHP_SELF']);

$menuAdmin = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Inicio'],
    ['url' => 'alumnos.php',      'ico' => '👤', 'txt' => 'Alumnos'],
    ['url' => 'escuelas.php',     'ico' => '🏫', 'txt' => 'Escuelas'],
    ['url' => 'grupos.php',       'ico' => '📚', 'txt' => 'Grupos'],
    ['url' => 'captura_sisat.php','ico' => '📋', 'txt' => 'Captura SISAT'],
    ['url' => 'alertas.php',      'ico' => '🔔', 'txt' => 'Alertas'],
    ['url' => 'seguimiento.php',  'ico' => '📝', 'txt' => 'Seguimiento'],
    ['url' => 'reportes.php',     'ico' => '📊', 'txt' => 'Reportes'],
    ['url' => 'usuarios.php',     'ico' => '👥', 'txt' => 'Usuarios'],
];

$menuSedu = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Inicio'],
    ['url' => 'alumnos.php',      'ico' => '👤', 'txt' => 'Alumnos'],
    ['url' => 'escuelas.php',     'ico' => '🏫', 'txt' => 'Escuelas'],
    ['url' => 'alertas.php',      'ico' => '🔔', 'txt' => 'Alertas'],
    ['url' => 'reportes.php',     'ico' => '📊', 'txt' => 'Reportes SEDU'],
];

$menuDirector = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Inicio'],
    ['url' => 'alumnos.php',      'ico' => '👤', 'txt' => 'Alumnos'],
    ['url' => 'grupos.php',       'ico' => '📚', 'txt' => 'Grupos'],
    ['url' => 'alertas.php',      'ico' => '🔔', 'txt' => 'Alertas'],
    ['url' => 'seguimiento.php',  'ico' => '📝', 'txt' => 'Seguimiento'],
    ['url' => 'reportes.php',     'ico' => '📊', 'txt' => 'Reportes'],
];

$menuDocente = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Inicio'],
    ['url' => 'alumnos.php',      'ico' => '👤', 'txt' => 'Mis alumnos'],
    ['url' => 'captura_sisat.php','ico' => '📋', 'txt' => 'Captura SISAT'],
    ['url' => 'alertas.php',      'ico' => '🔔', 'txt' => 'Alertas'],
];

$menuOrientador = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Inicio'],
    ['url' => 'alumnos.php',      'ico' => '👤', 'txt' => 'Alumnos'],
    ['url' => 'captura_sisat.php','ico' => '📋', 'txt' => 'Captura SISAT'],
    ['url' => 'alertas.php',      'ico' => '🔔', 'txt' => 'Alertas'],
    ['url' => 'seguimiento.php',  'ico' => '📝', 'txt' => 'Seguimiento'],
];

$menuAlumno = [
    ['url' => 'dashboard.php',    'ico' => '🏠', 'txt' => 'Mi panel'],
];

$menus = [
    'ADMIN'      => $menuAdmin,
    'SEDU'       => $menuSedu,
    'DIRECTOR'   => $menuDirector,
    'DOCENTE'    => $menuDocente,
    'ORIENTADOR' => $menuOrientador,
    'ALUMNO'     => $menuAlumno,
];

$menu = $menus[$rol] ?? $menuAlumno;
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-icon">🏫</span>
        <div>
            <div class="sidebar-title">SISAT</div>
            <div class="sidebar-sub">Alerta Temprana</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <a
                href="<?= h($item['url']) ?>"
                class="nav-item <?= ($paginaActual === $item['url']) ? 'activo' : '' ?>"
            >
                <span class="nav-ico"><?= $item['ico'] ?></span>
                <span><?= h($item['txt']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-name"><?= h($_SESSION['usuario']['nombre'] ?? '') ?></div>
            <div class="sidebar-user-rol"><?= h($rol) ?></div>
        </div>
        <a href="logout.php" class="btn-logout" title="Cerrar sesión">⏻</a>
    </div>
</aside>
