<?php
session_start();
require_once 'conexion.php';
require_once 'funciones.php';
requireRoles(['ADMIN','DOCENTE','ORIENTADOR']);

$mensaje    = '';
$tipo       = '';
$resultado  = null;
$idAlumno   = (int)($_GET['id_alumno'] ?? 0);

// ── Cargar catálogos ───────────────────────────────────────────
$indicadores = $pdo->query("SELECT * FROM indicador_riesgo WHERE ACTIVO = 1 ORDER BY ID_INDICADOR")->fetchAll();
$alumnos     = $pdo->query("
    SELECT a.ID_ALUMNO,
           CONCAT(a.APELLIDO_PATERNO,' ',a.APELLIDO_MATERNO,' ',a.NOMBRE) AS nombre_completo,
           a.MATRICULA
    FROM alumno a
    WHERE a.ACTIVO = 1
    ORDER BY a.APELLIDO_PATERNO, a.NOMBRE
")->fetchAll();

// ── Cargar datos del alumno preseleccionado ────────────────────
$alumnoSel = null;
if ($idAlumno > 0) {
    $stmt = $pdo->prepare("SELECT * FROM alumno WHERE ID_ALUMNO = ? AND ACTIVO = 1");
    $stmt->execute([$idAlumno]);
    $alumnoSel = $stmt->fetch() ?: null;
}

// ── Procesar formulario ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAl       = (int)($_POST['id_alumno'] ?? 0);
    $asistencia = (float)($_POST['asistencia'] ?? 100);
    $promedio   = (float)($_POST['promedio'] ?? 10);
    $obs        = trim($_POST['observaciones'] ?? '');

    // Indicadores seleccionados
    $indSeleccionados = [];
    foreach ($indicadores as $ind) {
        $indSeleccionados[] = [
            'id'    => $ind['ID_INDICADOR'],
            'peso'  => $ind['PESO'],
            'valor' => isset($_POST['indicador_' . $ind['ID_INDICADOR']]),
            'obs'   => trim($_POST['obs_ind_' . $ind['ID_INDICADOR']] ?? ''),
        ];
    }

    if ($idAl === 0) {
        $mensaje = 'Debes seleccionar un alumno.';
        $tipo    = 'error';
    } elseif ($asistencia < 0 || $asistencia > 100) {
        $mensaje = 'El porcentaje de asistencia debe estar entre 0 y 100.';
        $tipo    = 'error';
    } elseif ($promedio < 0 || $promedio > 10) {
        $mensaje = 'El promedio debe estar entre 0 y 10.';
        $tipo    = 'error';
    } else {
        // Calcular riesgo
        $resultado = calcularRiesgoSisat($asistencia, $promedio, $indSeleccionados);

        // Insertar evaluacion_sisat
        $stmt = $pdo->prepare("
            INSERT INTO evaluacion_sisat
                (ID_ALUMNO, ID_USUARIO_CAPTURA, ASISTENCIA_PORCENTAJE, PROMEDIO_GENERAL, OBSERVACIONES, PUNTAJE_RIESGO, NIVEL_RIESGO)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $idAl,
            $_SESSION['usuario']['id'],
            $asistencia,
            $promedio,
            $obs,
            $resultado['puntaje'],
            $resultado['nivel'],
        ]);
        $idEval = $pdo->lastInsertId();

        // Insertar detalles de indicadores
        $stmtDet = $pdo->prepare("
            INSERT INTO evaluacion_detalle (ID_EVALUACION, ID_INDICADOR, VALOR, OBSERVACION)
            VALUES (?,?,?,?)
        ");
        foreach ($indSeleccionados as $ind) {
            $stmtDet->execute([$idEval, $ind['id'], $ind['valor'] ? 1 : 0, $ind['obs'] ?: null]);
        }

        // Crear alerta automática si riesgo >= MEDIO
        if (in_array($resultado['nivel'], ['MEDIO','ALTO','CRITICO'])) {
            $pdo->prepare("
                INSERT INTO alerta (ID_ALUMNO, ID_EVALUACION, NIVEL_RIESGO, ESTATUS, DESCRIPCION)
                VALUES (?,?,?,'NUEVA',?)
            ")->execute([
                $idAl,
                $idEval,
                $resultado['nivel'],
                "Alerta generada automáticamente. Puntaje: {$resultado['puntaje']}. Nivel: {$resultado['nivel']}.",
            ]);
        }

        $mensaje = "Evaluación registrada. Nivel de riesgo: {$resultado['nivel']} (puntaje: {$resultado['puntaje']}).";
        $tipo    = 'exito';
        // Conservar resultado en página
    }
}

$paginaActual = 'captura_sisat.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SISAT — Captura de evaluación</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="layout">
    <?php require_once 'sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-titulo">📋 Captura de evaluación SISAT</div>
            <span class="topbar-rol"><?= h(rolActual()) ?></span>
        </div>
        <div class="pagina">

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= $mensaje ?></div>
            <?php endif; ?>

            <?php if ($resultado !== null && $tipo === 'exito'): ?>
                <!-- Resultado de riesgo calculado -->
                <div class="panel mb-20" style="border-left: 6px solid
                    <?php
                        $colores = ['BAJO' => '#16a34a', 'MEDIO' => '#ca8a04', 'ALTO' => '#ea580c', 'CRITICO' => '#dc2626'];
                        echo $colores[$resultado['nivel']] ?? '#64748b';
                    ?>;">
                    <div class="panel-header"><h3>Resultado de la evaluación</h3></div>
                    <div class="panel-body">
                        <div class="riesgo-resultado">
                            <div class="puntaje-num"><?= $resultado['puntaje'] ?></div>
                            <div>
                                <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Nivel de riesgo</div>
                                <div class="nivel-txt" style="color:<?= $colores[$resultado['nivel']] ?>;"><?= h($resultado['nivel']) ?></div>
                                <?php if (in_array($resultado['nivel'], ['MEDIO','ALTO','CRITICO'])): ?>
                                    <div style="margin-top:8px;" class="alerta-aviso alerta" style="margin-top:8px;">
                                        ⚠️ Se generó una alerta automática de nivel <?= h($resultado['nivel']) ?>.
                                    </div>
                                <?php else: ?>
                                    <div class="alerta alerta-exito" style="margin-top:8px;">✅ Riesgo bajo. No se genera alerta automática.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-gap mt-20">
                            <a class="btn btn-primario"   href="alertas.php">Ver alertas</a>
                            <a class="btn btn-secundario" href="captura_sisat.php">Nueva evaluación</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3>Formulario de captura SISAT</h3>
                    <a class="btn btn-secundario btn-sm" href="alumnos.php">← Volver a alumnos</a>
                </div>
                <div class="panel-body">
                    <form method="POST" id="frmSisat">

                        <!-- 1. Selección de alumno -->
                        <fieldset style="border:1px solid #dce3ef;border-radius:10px;padding:18px;margin-bottom:22px;">
                            <legend style="font-weight:700;color:#061f45;padding:0 10px;">1. Alumno</legend>
                            <div class="form-group">
                                <label>Alumno *</label>
                                <select name="id_alumno" id="sel_alumno" required>
                                    <option value="">Selecciona un alumno</option>
                                    <?php foreach ($alumnos as $a): ?>
                                        <option value="<?= $a['ID_ALUMNO'] ?>"
                                            <?= ($idAlumno === $a['ID_ALUMNO']) ? 'selected' : '' ?>>
                                            <?= h($a['MATRICULA']) ?> — <?= h($a['nombre_completo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </fieldset>

                        <!-- 2. Datos cuantitativos -->
                        <fieldset style="border:1px solid #dce3ef;border-radius:10px;padding:18px;margin-bottom:22px;">
                            <legend style="font-weight:700;color:#061f45;padding:0 10px;">2. Datos cuantitativos</legend>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Asistencia (%) *</label>
                                    <input type="number" name="asistencia" id="inp_asistencia"
                                           min="0" max="100" step="0.01" required
                                           placeholder="Ej: 85.5"
                                           value="<?= h($_POST['asistencia'] ?? '') ?>">
                                    <small style="color:#64748b;">Menos del 80% suma riesgo alto.</small>
                                </div>
                                <div class="form-group">
                                    <label>Promedio general (0–10) *</label>
                                    <input type="number" name="promedio" id="inp_promedio"
                                           min="0" max="10" step="0.01" required
                                           placeholder="Ej: 6.8"
                                           value="<?= h($_POST['promedio'] ?? '') ?>">
                                    <small style="color:#64748b;">Menos de 6 suma riesgo alto.</small>
                                </div>
                            </div>
                        </fieldset>

                        <!-- 3. Indicadores de riesgo -->
                        <fieldset style="border:1px solid #dce3ef;border-radius:10px;padding:18px;margin-bottom:22px;">
                            <legend style="font-weight:700;color:#061f45;padding:0 10px;">3. Indicadores de riesgo</legend>
                            <p class="texto-gris mb-10">Marca los indicadores que aplican a este alumno:</p>
                            <div class="indicadores-grid">
                                <?php foreach ($indicadores as $ind): ?>
                                    <?php $checked = isset($_POST['indicador_' . $ind['ID_INDICADOR']]); ?>
                                    <label class="indicador-item">
                                        <input type="checkbox"
                                               name="indicador_<?= $ind['ID_INDICADOR'] ?>"
                                               value="1"
                                               <?= $checked ? 'checked' : '' ?>
                                               onchange="calcularPreview()">
                                        <div>
                                            <div class="ind-nombre"><?= h($ind['NOMBRE_INDICADOR']) ?></div>
                                            <div class="ind-desc"><?= h($ind['DESCRIPCION']) ?> <em>(Peso: <?= $ind['PESO'] ?>)</em></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <!-- 4. Observaciones -->
                        <fieldset style="border:1px solid #dce3ef;border-radius:10px;padding:18px;margin-bottom:22px;">
                            <legend style="font-weight:700;color:#061f45;padding:0 10px;">4. Observaciones generales</legend>
                            <div class="form-group">
                                <textarea name="observaciones" rows="4" placeholder="Observaciones adicionales sobre el alumno..."><?= h($_POST['observaciones'] ?? '') ?></textarea>
                            </div>
                        </fieldset>

                        <!-- Preview del riesgo en tiempo real -->
                        <div class="panel" style="background:#f8fafc;" id="preview_riesgo">
                            <div class="panel-header"><h3>Vista previa del riesgo</h3></div>
                            <div class="panel-body">
                                <div class="riesgo-resultado">
                                    <div class="puntaje-num" id="pv_puntaje">—</div>
                                    <div>
                                        <div style="font-size:12px;color:#64748b;font-weight:600;">Nivel estimado</div>
                                        <div class="nivel-txt" id="pv_nivel" style="color:#64748b;">Sin calcular</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-gap mt-20">
                            <button class="btn btn-primario" type="submit">Guardar evaluación SISAT</button>
                            <a class="btn btn-secundario" href="alumnos.php">Cancelar</a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Datos de indicadores con pesos
const indicadores = <?= json_encode(array_map(fn($i) => ['id' => $i['ID_INDICADOR'], 'peso' => $i['PESO']], $indicadores)) ?>;

function calcularPreview() {
    const asistencia = parseFloat(document.getElementById('inp_asistencia').value) || 100;
    const promedio   = parseFloat(document.getElementById('inp_promedio').value) || 10;

    let puntaje = 0;

    if (asistencia < 80) puntaje += 3;
    else if (asistencia < 90) puntaje += 1;

    if (promedio < 6) puntaje += 3;
    else if (promedio < 7) puntaje += 2;
    else if (promedio < 8) puntaje += 1;

    let activos = 0;
    indicadores.forEach(ind => {
        const cb = document.querySelector(`input[name="indicador_${ind.id}"]`);
        if (cb && cb.checked) {
            puntaje += ind.peso;
            activos++;
        }
    });

    if (activos >= 3) puntaje += 1;

    let nivel, color;
    if (puntaje <= 2)      { nivel = 'BAJO';    color = '#16a34a'; }
    else if (puntaje <= 5) { nivel = 'MEDIO';   color = '#ca8a04'; }
    else if (puntaje <= 8) { nivel = 'ALTO';    color = '#ea580c'; }
    else                   { nivel = 'CRÍTICO'; color = '#dc2626'; }

    document.getElementById('pv_puntaje').textContent = puntaje;
    const el = document.getElementById('pv_nivel');
    el.textContent = nivel;
    el.style.color = color;
}

document.getElementById('inp_asistencia').addEventListener('input', calcularPreview);
document.getElementById('inp_promedio').addEventListener('input', calcularPreview);
calcularPreview();
</script>
</body>
</html>
