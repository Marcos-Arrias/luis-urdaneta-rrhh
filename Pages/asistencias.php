<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        if ($a === 'crear') {
            $hora_entrada = !empty($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
            $hora_salida = !empty($_POST['hora_salida']) ? $_POST['hora_salida'] : null;

            // Apuntando a la nueva tabla 'asistencias' y su columna 'estado_asistencia'
            $pdo->prepare("INSERT INTO asistencias (personal_id, fecha, hora_entrada, hora_salida, estado_asistencia, observaciones) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$_POST['personal_id'], $_POST['fecha'], $hora_entrada, $hora_salida, $_POST['estado_asistencia'], $_POST['observaciones']]);
            
            registrarHistorial($pdo, 'Asistencias', 'CREAR', "Registró {$_POST['estado_asistencia']} el {$_POST['fecha']}.");
            header("Location: asistencias.php?msg=creado"); exit;
            
        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM asistencias WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Asistencias', 'ELIMINAR', "Eliminó un registro de asistencia.");
            header("Location: asistencias.php?msg=eliminado"); exit;
        }
    } catch (PDOException $e) { 
        if ($e->getCode() == 23000) { header("Location: asistencias.php?msg=duplicado"); exit; }
        else { die("Error DB: " . $e->getMessage()); }
    }
}

$fechaFiltro = $_GET['fecha_filtro'] ?? date('Y-m-d');

// Consulta usando las nuevas tablas relacionadas (Fase 3 y 4 de la BD)
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

// Cargar TODO el personal agrupado por Tipo para el select dinámico
$todo_personal_raw = $pdo->query("
    SELECT p.id, CONCAT(p.apellidos, ', ', p.nombres) as nombre, t.nombre as tipo
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id 
    WHERE p.estado_laboral = 'Activo' 
    ORDER BY p.apellidos ASC
")->fetchAll();

// Agrupar en PHP para dárselo fácil a JavaScript
$personal_agrupado = [];
foreach ($todo_personal_raw as $p) {
    $personal_agrupado[$p['tipo']][] = ['id' => $p['id'], 'nombre' => $p['nombre']];
}

$kpi_ausentes = 0; $kpi_retardos = 0; $kpi_justificados = 0;
foreach($datos as $d) {
    if($d['estado_asistencia'] == 'Ausente') $kpi_ausentes++;
    if($d['estado_asistencia'] == 'Retardo') $kpi_retardos++;
    if($d['estado_asistencia'] == 'Falta Justificada') $kpi_justificados++;
}
include '../Includes/header.php'; include '../Includes/sidebar.php';
?>
<style>
.pulse-badge { animation: pulse 2s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
.pulse-warning { animation: pulse-w 2s infinite; }
@keyframes pulse-w { 0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-person-bounding-box me-2 text-primary"></i> Control de Novedades</h2>
        <p class="text-muted small mb-0">Gestión de inasistencias y llegadas tardías.</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-plus-lg me-1"></i> Reportar Novedad</button>
</div>

<!-- KPIs SUPERIORES -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 bg-danger-subtle text-danger shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div><h6 class="fw-bold mb-1">Ausencias Injustificadas</h6><h2 class="mb-0 fw-black"><?= $kpi_ausentes ?></h2></div><i class="bi bi-person-x-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-warning-subtle text-warning-emphasis shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div><h6 class="fw-bold mb-1">Llegadas con Retardo</h6><h2 class="mb-0 fw-black"><?= $kpi_retardos ?></h2></div><i class="bi bi-clock-history fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-info-subtle text-info-emphasis shadow-sm rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div><h6 class="fw-bold mb-1">Faltas Justificadas</h6><h2 class="mb-0 fw-black"><?= $kpi_justificados ?></h2></div><i class="bi bi-file-medical-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<form method="GET" class="mb-4 bg-body-tertiary p-2 rounded-pill shadow-sm d-inline-flex align-items-center border">
    <i class="bi bi-calendar2-check ms-3 me-2 text-primary"></i>
    <input type="date" name="fecha_filtro" class="form-control border-0 bg-transparent shadow-none" value="<?= $fechaFiltro ?>" style="width: 160px;">
    <button class="btn btn-dark rounded-pill px-4">Actualizar Vista</button>
</form>

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
                            else echo "<span class='badge bg-info text-dark rounded-pill px-3 py-2'><i class='bi bi-check-circle me-1'></i> JUSTIFICADO</span>";
                            ?>
                        </td>
                        <td class="text-muted small"><i class="bi bi-quote opacity-50 me-1"></i><?= htmlspecialchars($d['observaciones']) ?: 'Sin observaciones registradas.' ?></td>
                        <td class="text-center">
                            <form action="" method="POST" onsubmit="return confirm('¿Deshacer este registro?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-light text-danger shadow-sm rounded-circle"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-success py-5"><i class="bi bi-check2-all fs-1 d-block mb-3"></i><h5 class="fw-bold">Jornada Excelente</h5><p>Todo el personal asistió puntualmente este día.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Registro -->
<div class="modal fade" id="modalRegistro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title">Registrar Ausencia o Retardo</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" value="crear">
        
        <div class="mb-3"><label>Fecha</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-3">
            <label>Tipo de Personal</label>
            <select id="select_tipo" class="form-select" required onchange="cargarPersonal()">
                <option value="">Seleccione...</option>
                <?php foreach(array_keys($personal_agrupado) as $tipo): ?>
                    <option value="<?= $tipo ?>"><?= $tipo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Seleccionar Persona</label>
            <select name="personal_id" id="select_persona" class="form-select" required disabled>
                <option value="">Primero seleccione el tipo...</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="estado_asistencia" class="form-select" required>
                <option value="Ausente">Ausente (Injustificada)</option>
                <option value="Falta Justificada">Falta Justificada / Permiso</option>
                <option value="Retardo">Llegada con Retardo</option>
            </select>
        </div>
        <div class="mb-3"><label>Motivo</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar Registro</button></div></form>
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
            select.add(new Option(p.nombre, p.id)); // Ahora enviamos el ID limpio directamente
        });
    } else {
        select.disabled = true;
    }
}
</script>

<?php include '../Includes/footer.php'; ?>