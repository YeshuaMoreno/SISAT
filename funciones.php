<?php
/**
 * funciones.php
 * Helpers reutilizables del sistema SISAT
 */

// ──────────────────────────────────────────────────────────────
// Seguridad de sesión
// ──────────────────────────────────────────────────────────────

function requireLogin(): void
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRoles(array $rolesPermitidos): void
{
    requireLogin();
    $rolActual = $_SESSION['usuario']['rol'] ?? '';
    if (!in_array($rolActual, $rolesPermitidos, true)) {
        header('Location: dashboard.php?acceso=denegado');
        exit;
    }
}

function rolActual(): string
{
    return $_SESSION['usuario']['rol'] ?? '';
}

function esAdmin(): bool      { return rolActual() === 'ADMIN'; }
function esSedu(): bool       { return rolActual() === 'SEDU'; }
function esDirector(): bool   { return rolActual() === 'DIRECTOR'; }
function esDocente(): bool    { return rolActual() === 'DOCENTE'; }
function esOrientador(): bool { return rolActual() === 'ORIENTADOR'; }
function esAlumno(): bool     { return rolActual() === 'ALUMNO'; }

// ──────────────────────────────────────────────────────────────
// Cálculo de riesgo SISAT
// ──────────────────────────────────────────────────────────────

/**
 * Calcula el puntaje y nivel de riesgo de abandono escolar.
 *
 * @param float $asistencia  Porcentaje de asistencia (0-100)
 * @param float $promedio    Promedio general (0-10)
 * @param array $indicadores Array de indicadores con: ['peso' => int, 'valor' => bool]
 * @return array ['puntaje' => int, 'nivel' => string]
 */
function calcularRiesgoSisat(float $asistencia, float $promedio, array $indicadores): array
{
    $puntaje = 0;

    // Regla de asistencia
    if ($asistencia < 80) {
        $puntaje += 3;
    } elseif ($asistencia < 90) {
        $puntaje += 1;
    }

    // Regla de promedio
    if ($promedio < 6) {
        $puntaje += 3;
    } elseif ($promedio < 7) {
        $puntaje += 2;
    } elseif ($promedio < 8) {
        $puntaje += 1;
    }

    // Suma pesos de indicadores activos
    $indicadoresActivos = 0;
    foreach ($indicadores as $ind) {
        if (!empty($ind['valor'])) {
            $puntaje += (int)($ind['peso'] ?? 1);
            $indicadoresActivos++;
        }
    }

    // Si hay 3 o más indicadores activos, se suma un punto adicional
    if ($indicadoresActivos >= 3) {
        $puntaje += 1;
    }

    // Determinar nivel
    if ($puntaje <= 2) {
        $nivel = 'BAJO';
    } elseif ($puntaje <= 5) {
        $nivel = 'MEDIO';
    } elseif ($puntaje <= 8) {
        $nivel = 'ALTO';
    } else {
        $nivel = 'CRITICO';
    }

    return ['puntaje' => $puntaje, 'nivel' => $nivel];
}

// ──────────────────────────────────────────────────────────────
// Helpers de presentación
// ──────────────────────────────────────────────────────────────

function badgeRiesgo(string $nivel): string
{
    $clases = [
        'BAJO'    => 'badge-bajo',
        'MEDIO'   => 'badge-medio',
        'ALTO'    => 'badge-alto',
        'CRITICO' => 'badge-critico',
    ];
    $cls = $clases[$nivel] ?? 'badge-bajo';
    return '<span class="badge-riesgo ' . $cls . '">' . htmlspecialchars($nivel) . '</span>';
}

function badgeEstatus(string $estatus): string
{
    $etiquetas = [
        'NUEVA'          => ['cls' => 'est-nueva',    'txt' => 'Nueva'],
        'EN_REVISION'    => ['cls' => 'est-revision', 'txt' => 'En revisión'],
        'EN_SEGUIMIENTO' => ['cls' => 'est-seguimiento','txt' => 'En seguimiento'],
        'ATENDIDA'       => ['cls' => 'est-atendida', 'txt' => 'Atendida'],
        'CERRADA'        => ['cls' => 'est-cerrada',  'txt' => 'Cerrada'],
    ];
    $e = $etiquetas[$estatus] ?? ['cls' => '', 'txt' => $estatus];
    return '<span class="badge-estatus ' . $e['cls'] . '">' . htmlspecialchars($e['txt']) . '</span>';
}

function h(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

function fechaCorta(?string $fecha): string
{
    if (!$fecha) return '—';
    return date('d/m/Y', strtotime($fecha));
}

function fechaHora(?string $fecha): string
{
    if (!$fecha) return '—';
    return date('d/m/Y H:i', strtotime($fecha));
}

// ──────────────────────────────────────────────────────────────
// Hash de contraseñas
// ──────────────────────────────────────────────────────────────

function hashContrasena(string $pwd): string
{
    return password_hash($pwd, PASSWORD_DEFAULT);
}

function verificarContrasena(string $pwd, string $hash): bool
{
    return password_verify($pwd, $hash);
}
?>
