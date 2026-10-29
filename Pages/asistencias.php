<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        if ($a === 'crear') {
            $hora_entrada = !empty($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
            $hora_salida = !empty($_POST['hora_salida']) ? $_POST['hora_salida'] : null;

            $pdo->prepare("INSERT INTO asistencias (personal_id, fecha, hora_entrada, hora_salida, estado_asistencia, observaciones) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$_POST['personal_id'], $_POST['fecha'], $hora_entrada, $hora_salida, $_POST['estado_asistencia'], $_POST['observaciones']]);
            
            registrarHistorial($pdo, 'Asistencias', 'CREAR', "Registró {$_POST['estado_asistencia']} el {$_POST['fecha']}.");
            header("Location: asistencias.php?fecha_filtro=" . $_POST['fecha'] . "&msg=creado"); exit;
            
        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM asistencias WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Asistencias', 'ELIMINAR', "Eliminó un registro de asistencia.");
            header("Location: asistencias.php?fecha_filtro=" . ($_GET['fecha_filtro'] ?? date('Y-m-d')) . "&msg=eliminado"); exit;
        }
    } catch (PDOException $e) { 
        if ($e->getCode() == 23000) { header("Location: asistencias.php?fecha_filtro=" . $_POST['fecha'] . "&msg=duplicado"); exit; }
        else { die("Error DB: " . $e->getMessage()); }
    }
}

// FECHAS RELATIVAS PARA NAVEGACIÓN
$fechaFiltro = $_GET['fecha_filtro'] ?? date('Y-m-d');
$ayer = date('Y-m-d', strtotime($fechaFiltro . ' -1 day'));
$manana = date('Y-m-d', strtotime($fechaFiltro . ' +1 day'));
$hoy = date('Y-m-d');

// Consulta de asistencias del día seleccionado
$asistencias = $pdo->prepare("
    SELECT a.*, p.nombres, p.apellidos, t.nombre as tipo_personal 
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

// Cargar personal activo para el modal
$todo_personal_raw = $pdo->query("
    SELECT p.id, CONCAT(p.apellidos, ', ', p.nombres) as nombre, t.nombre as tipo
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

// KPI del día seleccionado
$kpi_ausentes = 0; $kpi_retardos = 0; $kpi_justificados = 0; $kpi_presentes = 0;
foreach($datos as $d) {
    if($d['estado_asistencia'] == 'Ausente') $kpi_ausentes++;
    if($d['estado_asistencia'] == 'Retardo') $kpi_retardos++;
    if($d['estado_asistencia'] == 'Falta Justificada') $kpi_justificados++;
    if($d['estado_asistencia'] == 'Presente') $kpi_presentes++;
}

// CONSULTA PARA EL SEMÁFORO DE LOS 7 DÍAS CERCANOS (-3 a +3 días)
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-person-bounding-box me-2 text-primary"></i> Control de Novedades</h2>
        <p class="text-muted small mb-0">Gestión de inasistencias y llegadas tardías.</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-plus-lg me-1"></i> Reportar Novedad</button>
</div>

<!-- ======================================================== -->
<!-- NUEVA BARRA NAVEGADORA DE DÍAS Y SEMÁFORO DE ESTADOS -->
<!-- ======================================================== -->
<div class="card shadow-sm border-0 rounded-4 mb-4 bg-body-tertiary">
    <div class="card-body p-3">
        
        <!-- Botones de Salto Rápido -->
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

            <!-- Selector de Fecha con Auto-Submit -->
            <form method="GET" class="d-flex align-items-center gap-2 m-0">
                <span class="small fw-bold text-muted"><i class="bi bi-calendar3 me-1"></i>Seleccionar Fecha:</span>
                <input type="date" name="fecha_filtro" class="form-control form-control-sm shadow-sm" value="<?= $fechaFiltro ?>" onchange="this.form.submit()" style="width: 155px;">
            </form>
        </div>

        <!-- Tira Interactiva de 7 Días con Código de Colores -->
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
                
                <!-- Indicadores de Color -->
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

        <div class="d-flex justify-content-center gap-3 mt-2 pt-2 border-top small text-muted" style="font-size: 0.75rem;">
            <span><span class="dot-indicator bg-success me-1"></span>Presente</span>
            <span><span class="dot-indicator bg-danger me-1"></span>Ausente</span>
            <span><span class="dot-indicator bg-warning me-1"></span>Retardo</span>
            <span><span class="dot-indicator bg-info me-1"></span>Justificado</span>
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
                <div><h6 class="fw-bold mb-1">Ausencias Injust.</h6><h2 class="mb-0 fw-black"><?= $kpi_ausentes ?></h2></div><i class="bi bi-person-x-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- TABLA DE DETALLES -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light text-secondary">
                <tr><th class="ps-4">Fecha</th><th>Categoría</th><th>Trabajador Involucrado</th><th>Condición Reportada</th><th>Motivo / Razón</th><th class="text-center">Acción</th></tr>
            </thead>
            <tbody>
                <?php if (count($datos) > 0): ?>
                    <?php foreach ($datos as $d): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted"><?= date('d/M/Y', strtotime($d['fecha'])) ?></td>
                        <td><span class="badge bg-body-secondary text-body border rounded-pill"><?= htmlspecialchars($d['tipo_personal'] ?? 'Desconocido') ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></td>
                        <td>
                            <?php 
                            if($d['estado_asistencia'] == 'Ausente') echo "<span class='badge bg-danger pulse-badge rounded-pill px-3 py-2'><i class='bi bi-x-circle me-1'></i> AUSENTE</span>";
                            elseif($d['estado_asistencia'] == 'Retardo') echo "<span class='badge bg-warning text-dark pulse-warning rounded-pill px-3 py-2'><i class='bi bi-clock me-1'></i> RETARDO</span>";
                            elseif($d['estado_asistencia'] == 'Falta Justificada') echo "<span class='badge bg-info text-dark rounded-pill px-3 py-2'><i class='bi bi-file-medical me-1'></i> JUSTIFICADO</span>";
                            elseif($d['estado_asistencia'] == 'Presente') echo "<span class='badge bg-success rounded-pill px-3 py-2'><i class='bi bi-check-circle me-1'></i> PRESENTE</span>";
                            ?>
                        </td>
                        <td class="text-muted small"><i class="bi bi-quote opacity-50 me-1"></i><?= htmlspecialchars($d['observaciones']) ?: 'Sin observaciones registradas.' ?></td>
                        <td class="text-center">
                            <form action="" method="POST" onsubmit="return confirm('¿Deshacer este registro?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-light text-danger shadow-sm rounded-circle"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 opacity-25 d-block mb-3"></i><h5 class="fw-bold">Sin Novedades Registradas</h5><p>No hay ausencias ni novedades guardadas para el <?= date('d/m/Y', strtotime($fechaFiltro)) ?>.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Registro -->
<div class="modal fade" id="modalRegistro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title">Registrar Control de Asistencia</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" value="crear">
        
        <div class="mb-3"><label class="form-label small fw-bold">Fecha</label><input type="date" name="fecha" class="form-control" value="<?= $fechaFiltro ?>" required></div>
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
        <div class="mb-3">
            <label class="form-label small fw-bold">Estado Reportado</label>
            <select name="estado_asistencia" class="form-select" required>
                <option value="Presente">Presente (Asistencia Regular)</option>
                <option value="Ausente">Ausente (Injustificada)</option>
                <option value="Falta Justificada">Falta Justificada / Permiso</option>
                <option value="Retardo">Llegada con Retardo</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label small fw-bold">Motivo / Observaciones</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Registro</button></div></form>
</div></div></div>

<script>
const dataPersonal = <?= json_encode($personal_agrupado) ?>;

function cargarPersonal() {
    const tipo = document.getElementById('select_tipo').value;
    const select = document.getElementById('select_persona');
    select.innerHTML = '<option value="">Seleccione a la persona...</option>';
    
    if(tipo && dataPersonal[tipo]) {
        select.disabled = false;
        dataPersonal[tipo].forEach(p => {
            select.add(new Option(p.nombre, p.id)); 
        });
    } else {
        select.disabled = true;
    }
}
</script>

<?php include '../Includes/footer.php'; ?>