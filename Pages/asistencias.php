<?php
require_once '../PHP/connection.php';

// Configurar zona horaria local para que el reloj interno coincida con tu país (Venezuela)
date_default_timezone_set('America/Caracas');

// Asegurar que la columna cierre_automatico exista (evita errores si no se ha corrido el SQL nuevo)
$pdo->exec("ALTER TABLE asistencias ADD COLUMN IF NOT EXISTS cierre_automatico TINYINT(1) NOT NULL DEFAULT 0");

// =====================================================================
// CONFIGURACIÓN DEL RELOJ INTERNO PARA CIERRE AUTOMÁTICO DEL DÍA
// =====================================================================
$hora_fin_jornada = '18:00:00'; // Hora en que termina el día laboral (6:00 PM)
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$fechaFiltro = $_GET['fecha_filtro'] ?? $hoy;

// Función PHP que sincroniza el cierre (Funciona aunque apaguen el PC antes de medianoche)
function procesarCierreAutomatico($pdo, $fechaEvaluar) {
    // Solo procesar días hábiles (Lunes=1 a Viernes=5)
    $diaSemana = date('N', strtotime($fechaEvaluar));
    if ($diaSemana > 5) return;

    // 1. Cerrar entradas sin salida
    $stmtUpd = $pdo->prepare("
        UPDATE asistencias 
        SET hora_salida = '18:00:00', 
            cierre_automatico = 1, 
            observaciones = TRIM(CONCAT(IFNULL(observaciones, ''), ' [Salida cerrada automáticamente]'))
        WHERE fecha = ? AND hora_entrada IS NOT NULL AND hora_salida IS NULL
    ");
    $stmtUpd->execute([$fechaEvaluar]);

    // 2. Insertar como Ausente a los Activos que ya habían ingresado a la institución y no tienen registro ese día
    $stmtIns = $pdo->prepare("
        INSERT IGNORE INTO asistencias (personal_id, fecha, estado_asistencia, cierre_automatico, observaciones)
        SELECT p.id, ?, 'Ausente', 1, '[Inasistencia automática al finalizar la jornada]'
        FROM personal p
        WHERE p.estado_laboral = 'Activo'
          AND p.fecha_ingreso <= ?
          AND NOT EXISTS (
              SELECT 1 FROM asistencias a WHERE a.personal_id = p.id AND a.fecha = ?
          )
    ");
    $stmtIns->execute([$fechaEvaluar, $fechaEvaluar, $fechaEvaluar]);
}

// Disparador automático según el reloj interno:
// Si la fecha consultada es anterior a hoy, O si es hoy y ya pasó la hora de cierre:
if ($fechaFiltro < $hoy || ($fechaFiltro === $hoy && $hora_actual >= $hora_fin_jornada)) {
    procesarCierreAutomatico($pdo, $fechaFiltro);
}

// =====================================================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        // -------------------------------------------------------------
        // NUEVO: REGISTRO RÁPIDO ERGONÓMICO POR CÉDULA
        // -------------------------------------------------------------
        if ($a === 'registro_rapido_cedula') {
            $fecha_reg = $_POST['fecha_rapida'] ?? $hoy;
            // Limpiamos puntos, letras y guiones para tolerancia a errores de escritura
            $cedula_limpia = preg_replace('/[^0-9]/', '', $_POST['cedula_rapida'] ?? '');

            if (empty($cedula_limpia)) {
                header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=cedula_vacia"); exit;
            }

            // Buscar trabajador activo por cédula normalizada
            $stmtP = $pdo->prepare("
                SELECT id, nombres, apellidos 
                FROM personal 
                WHERE REPLACE(REPLACE(REPLACE(UPPER(cedula), 'V', ''), '-', ''), '.', '') = ? 
                  AND estado_laboral = 'Activo' 
                LIMIT 1
            ");
            $stmtP->execute([$cedula_limpia]);
            $trabajador = $stmtP->fetch();

            if (!$trabajador) {
                header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=no_encontrado"); exit;
            }

            $pid = $trabajador['id'];
            $nombreComp = $trabajador['nombres'] . ' ' . $trabajador['apellidos'];

            // Verificar si ya tiene registro hoy
            $stmtAsist = $pdo->prepare("SELECT * FROM asistencias WHERE personal_id = ? AND fecha = ?");
            $stmtAsist->execute([$pid, $fecha_reg]);
            $existe = $stmtAsist->fetch();

            if (!$existe) {
                // PRIMERA MARCA DEL DÍA -> REGISTRAR ENTRADA COMO PRESENTE
                $pdo->prepare("INSERT INTO asistencias (personal_id, fecha, hora_entrada, estado_asistencia, observaciones) VALUES (?, ?, ?, 'Presente', 'Registro rápido por cédula')")
                    ->execute([$pid, $fecha_reg, $hora_actual]);
                
                registrarHistorial($pdo, 'Asistencias', 'CREAR', "Entrada rápida por cédula ($cedula_limpia - $nombreComp).");
                header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=rapido_entrada&emp=" . urlencode($nombreComp)); exit;
            } else {
                // SI YA ESTÁ REGISTRADO:
                if ($existe['estado_asistencia'] === 'Ausente' && $existe['cierre_automatico'] == 1) {
                    // Si el sistema lo había puesto ausente, pero llegó, lo pasamos a Presente
                    $pdo->prepare("UPDATE asistencias SET estado_asistencia = 'Presente', hora_entrada = ?, cierre_automatico = 0, observaciones = 'Presente (Actualizado por cédula)' WHERE id = ?")
                        ->execute([$hora_actual, $existe['id']]);
                    header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=rapido_entrada&emp=" . urlencode($nombreComp)); exit;
                } elseif (empty($existe['hora_salida']) && !empty($existe['hora_entrada'])) {
                    // SEGUNDA MARCA DEL DÍA -> REGISTRAR SALIDA
                    $pdo->prepare("UPDATE asistencias SET hora_salida = ?, cierre_automatico = 0 WHERE id = ?")
                        ->execute([$hora_actual, $existe['id']]);
                    
                    registrarHistorial($pdo, 'Asistencias', 'ACTUALIZAR', "Salida rápida por cédula ($cedula_limpia - $nombreComp).");
                    header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=rapido_salida&emp=" . urlencode($nombreComp)); exit;
                } else {
                    // Ya completó entrada y salida o tiene una falta justificada manual
                    header("Location: asistencias.php?fecha_filtro=$fecha_reg&msg=ya_completo&emp=" . urlencode($nombreComp)); exit;
                }
            }
        }
        // -------------------------------------------------------------
        // REGISTRO MANUAL DESDE MODAL (CON DETECCIÓN DE DUPLICADOS)
        // -------------------------------------------------------------
        elseif ($a === 'crear') {
            $hora_entrada = !empty($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
            $hora_salida = !empty($_POST['hora_salida']) ? $_POST['hora_salida'] : null;
            $personal_id = $_POST['personal_id'];
            $fecha = $_POST['fecha'];
            $estado = $_POST['estado_asistencia'];
            $obs = $_POST['observaciones'];

            $stmtCheck = $pdo->prepare("SELECT id FROM asistencias WHERE personal_id = ? AND fecha = ?");
            $stmtCheck->execute([$personal_id, $fecha]);
            $existeId = $stmtCheck->fetchColumn();

            if ($existeId) {
                $pdo->prepare("UPDATE asistencias SET hora_entrada=?, hora_salida=?, estado_asistencia=?, cierre_automatico=0, observaciones=? WHERE id=?")
                    ->execute([$hora_entrada, $hora_salida, $estado, $obs, $existeId]);
                registrarHistorial($pdo, 'Asistencias', 'ACTUALIZAR', "Actualizó novedad automáticamente el $fecha.");
                header("Location: asistencias.php?fecha_filtro=" . $fecha . "&msg=auto_editado"); exit;
            } else {
                $pdo->prepare("INSERT INTO asistencias (personal_id, fecha, hora_entrada, hora_salida, estado_asistencia, observaciones) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$personal_id, $fecha, $hora_entrada, $hora_salida, $estado, $obs]);
                registrarHistorial($pdo, 'Asistencias', 'CREAR', "Registró $estado el $fecha.");
                header("Location: asistencias.php?fecha_filtro=" . $fecha . "&msg=creado"); exit;
            }
            
        } elseif ($a === 'editar') {
            $id = $_POST['id'];
            $hora_entrada = !empty($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
            $hora_salida = !empty($_POST['hora_salida']) ? $_POST['hora_salida'] : null;

            $pdo->prepare("UPDATE asistencias SET personal_id=?, fecha=?, hora_entrada=?, hora_salida=?, estado_asistencia=?, cierre_automatico=0, observaciones=? WHERE id=?")
                ->execute([$_POST['personal_id'], $_POST['fecha'], $hora_entrada, $hora_salida, $_POST['estado_asistencia'], $_POST['observaciones'], $id]);

            registrarHistorial($pdo, 'Asistencias', 'ACTUALIZAR', "Actualizó novedad de {$_POST['estado_asistencia']}.");
            header("Location: asistencias.php?fecha_filtro=" . $_POST['fecha'] . "&msg=editado"); exit;

        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM asistencias WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Asistencias', 'ELIMINAR', "Eliminó un registro de asistencia.");
            header("Location: asistencias.php?fecha_filtro=" . ($_GET['fecha_filtro'] ?? $hoy) . "&msg=eliminado"); exit;
        }
    } catch (PDOException $e) { 
        die("Error DB: " . $e->getMessage()); 
    }
}

$ayer = date('Y-m-d', strtotime($fechaFiltro . ' -1 day'));
$manana = date('Y-m-d', strtotime($fechaFiltro . ' +1 day'));

// Consulta de asistencias del día seleccionado
$asistencias = $pdo->prepare("
    SELECT a.*, p.nombres, p.apellidos, p.cedula, t.nombre as tipo_personal, t.id as tipo_personal_id 
    FROM asistencias a 
    JOIN personal p ON a.personal_id = p.id
    LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
    LEFT JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id
    WHERE a.fecha = ? 
    ORDER BY a.id DESC
");
$asistencias->execute([$fechaFiltro]);
$datos = $asistencias->fetchAll();

// Cargar personal activo (para el modal y para el datalist ergonómico)
$todo_personal_raw = $pdo->query("
    SELECT p.id, p.cedula, CONCAT(p.apellidos, ', ', p.nombres) as nombre, t.nombre as tipo
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id 
    WHERE p.estado_laboral = 'Activo' 
    ORDER BY p.apellidos ASC
")->fetchAll();

$personal_agrupado = [];
foreach ($todo_personal_raw as $p) {
    $personal_agrupado[$p['tipo']][] = ['id' => $p['id'], 'nombre' => $p['nombre']];
}

$kpi_ausentes = 0; $kpi_retardos = 0; $kpi_justificados = 0; $kpi_presentes = 0;
foreach($datos as $d) {
    if($d['estado_asistencia'] == 'Ausente') $kpi_ausentes++;
    if($d['estado_asistencia'] == 'Retardo') $kpi_retardos++;
    if($d['estado_asistencia'] == 'Falta Justificada') $kpi_justificados++;
    if($d['estado_asistencia'] == 'Presente') $kpi_presentes++;
}

// Semáforo de días (-3 a +3 días)
$dia_inicio = date('Y-m-d', strtotime($fechaFiltro . ' -3 days'));
$dia_fin = date('Y-m-d', strtotime($fechaFiltro . ' +3 days'));

$stmtSem = $pdo->prepare("
    SELECT fecha,
           SUM(estado_asistencia = 'Ausente') as ausentes,
           SUM(estado_asistencia = 'Retardo') as retardos,
           SUM(estado_asistencia = 'Falta Justificada') as justificados,
           SUM(estado_asistencia = 'Presente') as presentes,
           COUNT(*) as total
    FROM asistencias
    WHERE fecha BETWEEN ? AND ?
    GROUP BY fecha
");
$stmtSem->execute([$dia_inicio, $dia_fin]);
$diasSemaforo = [];
foreach($stmtSem->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $diasSemaforo[$s['fecha']] = $s;
}

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>
<style>
.pulse-badge { animation: pulse 2s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
.pulse-warning { animation: pulse-w 2s infinite; }
@keyframes pulse-w { 0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } }
.day-card { transition: all 0.2s ease-in-out; border-radius: 12px; min-width: 105px; }
.day-card:hover { transform: translateY(-3px); }
.dot-indicator { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-person-bounding-box me-2 text-primary"></i> Control de Asistencias</h2>
        <p class="text-muted small mb-0">
            Cierre automático de jornada configurado a las <strong><?= date('h:i A', strtotime($hora_fin_jornada)) ?></strong> | 
            Reloj del Sistema: <span id="relojVivo" class="fw-bold text-primary"><?= date('h:i:s A') ?></span>
        </p>
    </div>
    <button class="btn btn-outline-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarModalAsistencia()">
        <i class="bi bi-clipboard-plus me-1"></i> Reportar Novedad Manual
    </button>
</div>

<!-- ========================================================= -->
<!-- BARRA ERGONÓMICA: REGISTRO RÁPIDO POR CÉDULA (1 CLIC/ENTER) -->
<!-- ========================================================= -->
<div class="card shadow-sm border-0 rounded-4 mb-4 border-start border-5 border-success bg-success-subtle">
    <div class="card-body p-3">
        <form action="" method="POST" class="row g-2 align-items-center">
            <input type="hidden" name="accion" value="registro_rapido_cedula">
            <input type="hidden" name="fecha_rapida" value="<?= $fechaFiltro ?>">
            
            <div class="col-md-4">
                <div class="d-flex align-items-center text-success-emphasis">
                    <i class="bi bi-lightning-charge-fill fs-2 me-2"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Asistencia Rápida por Cédula</h6>
                        <small class="opacity-75">Escribe la cédula y presiona <strong>Enter</strong> (Entrada / Salida).</small>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-body border-0 text-success fw-bold"><i class="bi bi-person-vcard"></i></span>
                    <input type="text" name="cedula_rapida" list="listaCedulasRapidas" class="form-control border-0 fw-bold" placeholder="Ej: 12345678..." autocomplete="off" autofocus required>
                    <datalist id="listaCedulasRapidas">
                        <?php foreach($todo_personal_raw as $pr): ?>
                            <option value="<?= htmlspecialchars($pr['cedula']) ?>"><?= htmlspecialchars($pr['nombre']) ?> (<?= $pr['tipo'] ?>)</option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm rounded-3">
                    <i class="bi bi-check2-circle me-1"></i> Marcar Presente
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SISTEMA DE ALERTAS CON FEEDBACK CLARO -->
<?php if(isset($_GET['msg'])): ?>
    <?php $empName = htmlspecialchars($_GET['emp'] ?? ''); ?>
    <?php if($_GET['msg'] == 'rapido_entrada'): ?>
        <div class="alert alert-success alert-dismissible shadow-sm fade show py-2"><i class="bi bi-box-arrow-in-right me-2 fs-5"></i> <strong>¡Entrada Registrada!</strong> Se marcó como <b>Presente</b> a <strong><?= $empName ?></strong>.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'rapido_salida'): ?>
        <div class="alert alert-primary alert-dismissible shadow-sm fade show py-2"><i class="bi bi-box-arrow-right me-2 fs-5"></i> <strong>¡Salida Registrada!</strong> Se cerró la jornada laboral de <strong><?= $empName ?></strong>.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'ya_completo'): ?>
        <div class="alert alert-info alert-dismissible shadow-sm fade show py-2"><i class="bi bi-info-circle-fill me-2"></i> <strong><?= $empName ?></strong> ya tiene registrada tanto su entrada como su salida (o un permiso) hoy.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'no_encontrado'): ?>
        <div class="alert alert-danger alert-dismissible shadow-sm fade show py-2"><i class="bi bi-person-x-fill me-2"></i> <strong>No encontrado:</strong> No existe ningún trabajador <b>Activo</b> con esa cédula.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'creado'): ?>
        <div class="alert alert-success alert-dismissible shadow-sm fade show py-2"><i class="bi bi-check-circle-fill me-2"></i> Asistencia registrada correctamente.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'editado'): ?>
        <div class="alert alert-info alert-dismissible shadow-sm fade show py-2"><i class="bi bi-pencil-fill me-2"></i> Registro actualizado.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'auto_editado'): ?>
        <div class="alert alert-warning alert-dismissible border-warning shadow-sm fade show py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Aviso:</strong> El trabajador ya tenía un registro en esta fecha; la información fue actualizada.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php elseif($_GET['msg'] == 'eliminado'): ?>
        <div class="alert alert-danger alert-dismissible shadow-sm fade show py-2"><i class="bi bi-trash-fill me-2"></i> Novedad eliminada.<button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
<?php endif; ?>

<!-- NAVEGACIÓN Y SEMÁFORO -->
<div class="card shadow-sm border-0 rounded-4 mb-4 bg-body-tertiary">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="btn-group shadow-sm">
                <a href="?fecha_filtro=<?= $ayer ?>" class="btn btn-outline-secondary btn-sm px-3 fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> Anterior
                </a>
                <a href="?fecha_filtro=<?= $hoy ?>" class="btn btn-outline-primary btn-sm px-3 fw-bold <?= ($fechaFiltro == $hoy) ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check me-1"></i> Hoy
                </a>
                <a href="?fecha_filtro=<?= $manana ?>" class="btn btn-outline-secondary btn-sm px-3 fw-bold">
                    Siguiente <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <form method="GET" class="d-flex align-items-center gap-2 m-0">
                <span class="small fw-bold text-muted"><i class="bi bi-calendar3 me-1"></i>Seleccionar Fecha:</span>
                <input type="date" name="fecha_filtro" class="form-control form-control-sm shadow-sm" value="<?= $fechaFiltro ?>" onchange="this.form.submit()" style="width: 155px;">
            </form>
        </div>

        <div class="d-flex overflow-auto gap-2 text-center pb-1">
            <?php 
            for ($i = -3; $i <= 3; $i++): 
                $diaActual = date('Y-m-d', strtotime($fechaFiltro . " $i day"));
                $esSeleccionado = ($diaActual == $fechaFiltro);
                $esHoy = ($diaActual == $hoy);
                $datosSemaforo = $diasSemaforo[$diaActual] ?? null;

                $diasEsp = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                $nombreDia = $diasEsp[date('w', strtotime($diaActual))];
                $fechaNum = date('d/m', strtotime($diaActual));
            ?>
            <a href="?fecha_filtro=<?= $diaActual ?>" class="day-card text-decoration-none flex-fill p-2 border <?= $esSeleccionado ? 'border-primary border-2 bg-primary text-white shadow' : 'bg-body border-secondary-subtle text-body' ?>">
                <span class="small fw-bold <?= $esSeleccionado ? 'text-white' : ($esHoy ? 'text-primary' : 'text-muted') ?>">
                    <?= $nombreDia ?> <?= $esHoy ? '★' : '' ?>
                </span>
                <h6 class="mb-2 fw-black <?= $esSeleccionado ? 'text-white' : '' ?>"><?= $fechaNum ?></h6>
                
                <div class="d-flex justify-content-center align-items-center gap-1" style="min-height: 14px;">
                    <?php if ($datosSemaforo && $datosSemaforo['total'] > 0): ?>
                        <?php if ($datosSemaforo['presentes'] > 0): ?><span class="dot-indicator bg-success" title="<?= $datosSemaforo['presentes'] ?> Presentes"></span><?php endif; ?>
                        <?php if ($datosSemaforo['retardos'] > 0): ?><span class="dot-indicator bg-warning" title="<?= $datosSemaforo['retardos'] ?> Retardos"></span><?php endif; ?>
                        <?php if ($datosSemaforo['justificados'] > 0): ?><span class="dot-indicator bg-info" title="<?= $datosSemaforo['justificados'] ?> Justificados"></span><?php endif; ?>
                        <?php if ($datosSemaforo['ausentes'] > 0): ?><span class="dot-indicator bg-danger" title="<?= $datosSemaforo['ausentes'] ?> Ausencias"></span><?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted" style="font-size: 0.65rem;">—</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- KPIs SUPERIORES -->
<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="card border-0 bg-success-subtle text-success shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><h6 class="fw-bold mb-1">Personal Presente</h6><h2 class="mb-0 fw-black"><?= $kpi_presentes ?></h2></div><i class="bi bi-person-check-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-0 bg-info-subtle text-info-emphasis shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><h6 class="fw-bold mb-1">Faltas Justificadas</h6><h2 class="mb-0 fw-black"><?= $kpi_justificados ?></h2></div><i class="bi bi-file-medical-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-0 bg-warning-subtle text-warning-emphasis shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><h6 class="fw-bold mb-1">Llegadas Tardías</h6><h2 class="mb-0 fw-black"><?= $kpi_retardos ?></h2></div><i class="bi bi-clock-history fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-0 bg-danger-subtle text-danger shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><h6 class="fw-bold mb-1">Ausencias / Inasist.</h6><h2 class="mb-0 fw-black"><?= $kpi_ausentes ?></h2></div><i class="bi bi-person-x-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- TABLA DE DETALLES -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light text-secondary">
                <tr>
                    <th class="ps-4">Cédula</th>
                    <th>Categoría</th>
                    <th>Trabajador Involucrado</th>
                    <th>Entrada / Salida</th>
                    <th>Condición</th>
                    <th>Observaciones</th>
                    <th class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($datos) > 0): ?>
                    <?php foreach ($datos as $d): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted">V-<?= htmlspecialchars($d['cedula']) ?></td>
                        <td><span class="badge bg-body-secondary text-body border rounded-pill"><?= htmlspecialchars($d['tipo_personal'] ?? 'N/A') ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></td>
                        <td class="small">
                            <?php if($d['hora_entrada'] || $d['hora_salida']): ?>
                                <span class="text-success fw-bold"><i class="bi bi-box-arrow-in-right"></i> <?= $d['hora_entrada'] ? date('h:i A', strtotime($d['hora_entrada'])) : '--' ?></span><br>
                                <span class="text-primary fw-bold"><i class="bi bi-box-arrow-right"></i> <?= $d['hora_salida'] ? date('h:i A', strtotime($d['hora_salida'])) : '<span class="text-warning">En turno</span>' ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if($d['estado_asistencia'] == 'Ausente') echo "<span class='badge bg-danger pulse-badge rounded-pill px-3 py-2'><i class='bi bi-x-circle me-1'></i> AUSENTE</span>";
                            elseif($d['estado_asistencia'] == 'Retardo') echo "<span class='badge bg-warning text-dark pulse-warning rounded-pill px-3 py-2'><i class='bi bi-clock me-1'></i> RETARDO</span>";
                            elseif($d['estado_asistencia'] == 'Falta Justificada') echo "<span class='badge bg-info text-dark rounded-pill px-3 py-2'><i class='bi bi-file-medical me-1'></i> JUSTIFICADO</span>";
                            elseif($d['estado_asistencia'] == 'Presente') echo "<span class='badge bg-success rounded-pill px-3 py-2'><i class='bi bi-check-circle me-1'></i> PRESENTE</span>";
                            
                            if(!empty($d['cierre_automatico']) && $d['cierre_automatico'] == 1) {
                                echo " <span class='badge bg-secondary-subtle text-secondary border ms-1' title='Procesado automáticamente al cierre de jornada'>Auto</span>";
                            }
                            ?>
                        </td>
                        <td class="text-muted small"><i class="bi bi-quote opacity-50 me-1"></i><?= htmlspecialchars($d['observaciones']) ?: 'Sin observaciones.' ?></td>
                        <td class="text-center text-nowrap">
                            <button class="btn btn-sm btn-light text-warning shadow-sm rounded-circle me-1" onclick='cargarEdicionAsistencia(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)' title="Editar Registro">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Deshacer este registro?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-light text-danger shadow-sm rounded-circle" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 opacity-25 d-block mb-3"></i><h5 class="fw-bold">Sin Registros aún</h5><p>Usa la barra superior de cédula para registrar llegadas del <?= date('d/m/Y', strtotime($fechaFiltro)) ?>.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL REGISTRO / EDICIÓN MANUAL -->
<div class="modal fade" id="modalRegistro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="asistModalTitulo">Registrar Control de Asistencia</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" id="asist_accion" value="crear">
        <input type="hidden" name="id" id="asist_id">
        
        <div id="alertaEdicion" class="alert alert-warning d-none py-2 px-3 small mb-3 border-warning shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-1 fs-6"></i> <strong>Aviso:</strong> Esta persona ya tiene un reporte hoy. Al guardar, se <b>actualizará (sobrescribirá)</b> el existente.
        </div>

        <div class="mb-3"><label class="form-label small fw-bold">Fecha</label><input type="date" name="fecha" id="asist_fecha" class="form-control" value="<?= $fechaFiltro ?>" required></div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Tipo de Personal</label>
            <select id="select_tipo" class="form-select" required onchange="cargarPersonal()">
                <option value="">Seleccione...</option>
                <?php foreach(array_keys($personal_agrupado) as $tipo): ?>
                    <option value="<?= $tipo ?>"><?= $tipo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Seleccionar Persona</label>
            <select name="personal_id" id="select_persona" class="form-select" required disabled>
                <option value="">Primero seleccione el tipo...</option>
            </select>
        </div>
        <div class="row mb-3">
            <div class="col-6"><label class="form-label small fw-bold">Hora Entrada (Opcional)</label><input type="time" name="hora_entrada" id="asist_entrada" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-bold">Hora Salida (Opcional)</label><input type="time" name="hora_salida" id="asist_salida" class="form-control"></div>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Estado Reportado</label>
            <select name="estado_asistencia" id="asist_estado" class="form-select" required>
                <option value="Presente">Presente (Asistencia Regular)</option>
                <option value="Ausente">Ausente (Injustificada)</option>
                <option value="Falta Justificada">Falta Justificada / Permiso</option>
                <option value="Retardo">Llegada con Retardo</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label small fw-bold">Motivo / Observaciones</label><textarea name="observaciones" id="asist_observaciones" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Registro</button></div></form>
</div></div></div>

<script>
const dataPersonal = <?= json_encode($personal_agrupado) ?>;
const asistenciasHoy = <?= json_encode($datos) ?>;

// Reloj interno en tiempo real en la pantalla
setInterval(() => {
    const ahora = new Date();
    document.getElementById('relojVivo').innerText = ahora.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}, 1000);

function cargarPersonal(selectedId = null) {
    const tipo = document.getElementById('select_tipo').value;
    const select = document.getElementById('select_persona');
    select.innerHTML = '<option value="">Seleccione a la persona...</option>';
    
    if(tipo && dataPersonal[tipo]) {
        select.disabled = false;
        dataPersonal[tipo].forEach(p => {
            let opt = new Option(p.nombre, p.id);
            if(selectedId && selectedId == p.id) opt.selected = true;
            select.add(opt); 
        });
    } else {
        select.disabled = true;
    }
    verificarDuplicado();
}

document.getElementById('select_persona').addEventListener('change', verificarDuplicado);
document.getElementById('asist_fecha').addEventListener('change', verificarDuplicado);

function verificarDuplicado() {
    let personalId = document.getElementById('select_persona').value;
    let fechaFiltroActual = '<?= $fechaFiltro ?>';
    let fechaInput = document.getElementById('asist_fecha').value;
    let alerta = document.getElementById('alertaEdicion');

    if(personalId && fechaInput === fechaFiltroActual) {
        let reg = asistenciasHoy.find(a => a.personal_id == personalId);
        if(reg) {
            alerta.classList.remove('d-none');
            document.getElementById('asist_estado').value = reg.estado_asistencia;
            document.getElementById('asist_entrada').value = reg.hora_entrada ? reg.hora_entrada.substring(0,5) : '';
            document.getElementById('asist_salida').value = reg.hora_salida ? reg.hora_salida.substring(0,5) : '';
            document.getElementById('asist_observaciones').value = reg.observaciones || '';
        } else {
            alerta.classList.add('d-none');
            document.getElementById('asist_estado').value = 'Presente';
            document.getElementById('asist_entrada').value = '';
            document.getElementById('asist_salida').value = '';
            document.getElementById('asist_observaciones').value = '';
        }
    } else {
        alerta.classList.add('d-none');
    }
}

function limpiarModalAsistencia() {
    document.getElementById('asistModalTitulo').innerText = 'Registrar Control de Asistencia';
    document.getElementById('asist_accion').value = 'crear';
    document.getElementById('asist_id').value = '';
    document.getElementById('asist_fecha').value = '<?= $fechaFiltro ?>';
    document.getElementById('select_tipo').value = '';
    document.getElementById('select_persona').innerHTML = '<option value="">Primero seleccione el tipo...</option>';
    document.getElementById('select_persona').disabled = true;
    document.getElementById('asist_estado').value = 'Presente';
    document.getElementById('asist_entrada').value = '';
    document.getElementById('asist_salida').value = '';
    document.getElementById('asist_observaciones').value = '';
    document.getElementById('alertaEdicion').classList.add('d-none');
}

function cargarEdicionAsistencia(d) {
    document.getElementById('asistModalTitulo').innerText = 'Editar Control de Asistencia';
    document.getElementById('asist_accion').value = 'editar';
    document.getElementById('asist_id').value = d.id;
    document.getElementById('asist_fecha').value = d.fecha;
    document.getElementById('asist_estado').value = d.estado_asistencia;
    document.getElementById('asist_entrada').value = d.hora_entrada ? d.hora_entrada.substring(0,5) : '';
    document.getElementById('asist_salida').value = d.hora_salida ? d.hora_salida.substring(0,5) : '';
    document.getElementById('asist_observaciones').value = d.observaciones || '';
    document.getElementById('alertaEdicion').classList.add('d-none');
    
    document.getElementById('select_tipo').value = d.tipo_personal;
    cargarPersonal(d.personal_id);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRegistro')).show();
}
</script>

<?php include '../Includes/footer.php'; ?>