<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once '../PHP/connection.php';

// Asegurarnos de que el campo color exista (Para no romper tu UI)
$pdo->exec("ALTER TABLE horarios_laborales ADD COLUMN IF NOT EXISTS color_fondo VARCHAR(50) DEFAULT 'bg-primary'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        if ($a === 'crear') {
            $pdo->prepare("INSERT INTO horarios_laborales (personal_id, dia_semana, hora_inicio, hora_fin, actividad, color_fondo) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$_POST['personal_id'], $_POST['dia_semana'], $_POST['hora_inicio'], $_POST['hora_fin'], $_POST['actividad'], $_POST['color_fondo']]);
            registrarHistorial($pdo, 'Horarios', 'CREAR', "Turno agregado el {$_POST['dia_semana']}.");
            header("Location: calendario.php?msg=creado"); exit;
            
        } elseif ($a === 'editar') {
            $pdo->prepare("UPDATE horarios_laborales SET personal_id=?, dia_semana=?, hora_inicio=?, hora_fin=?, actividad=?, color_fondo=? WHERE id=?")
                ->execute([$_POST['personal_id'], $_POST['dia_semana'], $_POST['hora_inicio'], $_POST['hora_fin'], $_POST['actividad'], $_POST['color_fondo'], $_POST['id']]);
            registrarHistorial($pdo, 'Horarios', 'ACTUALIZAR', "Turno modificado.");
            header("Location: calendario.php?msg=editado"); exit;
            
        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM horarios_laborales WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Horarios', 'ELIMINAR', "Turno eliminado.");
            header("Location: calendario.php?msg=eliminado"); exit;
        }
    } catch (PDOException $e) { die("Error DB: " . $e->getMessage()); }
}

// OBTENER TODOS LOS TURNOS (Con la nueva BD relacional)
$turnos_raw = $pdo->query("
    SELECT h.*, p.nombres, p.apellidos, c.nombre as cargo 
    FROM horarios_laborales h 
    INNER JOIN personal p ON h.personal_id = p.id 
    LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    GROUP BY h.id ORDER BY h.hora_inicio ASC
")->fetchAll();

$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$matriz = []; $intervalos_unicos = []; $ids_procesados = []; 

foreach ($turnos_raw as $t) {
    if (in_array($t['id'], $ids_procesados)) continue; 
    $ids_procesados[] = $t['id'];

    $intervalo = date('h:i A', strtotime($t['hora_inicio'])) . ' - ' . date('h:i A', strtotime($t['hora_fin']));
    if (!in_array($intervalo, $intervalos_unicos)) $intervalos_unicos[] = $intervalo;
    
    $matriz[$intervalo][$t['dia_semana']][] = $t;
}
sort($intervalos_unicos);

// Cargar personal activo para el modal
$personal_lista = $pdo->query("SELECT p.id, CONCAT(p.apellidos, ', ', p.nombres) as nombre FROM personal p WHERE p.estado_laboral='Activo' ORDER BY p.apellidos ASC")->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<!-- Se mantiene todo tu CSS original del calendario aquí -->
<style>
    .timetable { background-color: #fff; border-radius: 10px; overflow: hidden; border-collapse: separate; border-spacing: 0; }
    .timetable th { background-color: #1e3c72; color: white; text-align: center; padding: 12px; font-size: 0.85rem; border: 1px solid #152b52; }
    .timetable td { border: 1px solid #e9ecef; vertical-align: top; padding: 8px; min-width: 130px; }
    .time-col { background-color: #f8f9fa; font-weight: bold; text-align: center; vertical-align: middle !important; min-width: 110px; font-size: 0.85rem; }
    .event-block { border-radius: 6px; color: white; padding: 8px; cursor: pointer; position: relative; transition: all 0.3s ease; margin-bottom: 5px; }
    .event-summary { text-align: center; font-weight: bold; font-size: 0.85rem; }
    .event-details-hover { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; font-size: 0.75rem; opacity: 0.9; }
    .event-block:hover { transform: scale(1.02); z-index: 10; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    .event-block:hover .event-details-hover { max-height: 200px; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 8px; }
    .truncate-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-calendar3-week me-2 text-primary"></i> Horario de Trabajo</h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTurno" onclick="limpiarFormularioTurno()"><i class="bi bi-plus-circle me-1"></i> Asignar Turno</button>
</div>

<div class="card shadow-lg border-0 mb-5">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 timetable">
                <thead><tr><th>Intervalo</th><?php foreach($dias_semana as $d) echo "<th>$d</th>"; ?></tr></thead>
                <tbody>
                    <?php if (count($intervalos_unicos) > 0): foreach ($intervalos_unicos as $hora): ?>
                        <tr>
                            <td class="time-col"><?= str_replace(" - ", "<br>a<br>", $hora) ?></td>
                            <?php foreach ($dias_semana as $dia): ?>
                            <td>
                                <?php if (isset($matriz[$hora][$dia])): $empleados = $matriz[$hora][$dia]; $total = count($empleados); ?>
                                    <div class="event-block <?= $empleados[0]['color_fondo'] ?>" onclick='abrirModalGestion(<?= json_encode($empleados) ?>, "<?= $dia ?>", "<?= $hora ?>")'>
                                        <div class="event-summary"><i class="bi bi-people-fill fs-5 d-block"></i><?= $total ?> Asignados</div>
                                        <div class="event-details-hover">
                                            <?php foreach ($empleados as $emp): ?>
                                                <span class="truncate-text mb-1"><strong><?= htmlspecialchars($emp['apellidos']) ?></strong>: <?= htmlspecialchars($emp['actividad']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">El calendario está vacío.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Principal para Crear/Editar -->
<div class="modal fade" id="modalTurno" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title">Asignar Turno</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" id="accion" value="crear"><input type="hidden" name="id" id="turno_id">
        <div class="mb-3"><label>Trabajador</label><select name="personal_id" id="personal_id" class="form-select" required><option value="">Seleccione...</option>
            <?php foreach ($personal_lista as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label>Día</label><select name="dia_semana" id="dia_semana" class="form-select" required>
            <?php foreach($dias_semana as $d) echo "<option value='$d'>$d</option>"; ?>
        </select></div>
        <div class="row mb-3"><div class="col-6"><label>Entrada</label><input type="time" name="hora_inicio" id="hora_inicio" class="form-control" required></div><div class="col-6"><label>Salida</label><input type="time" name="hora_fin" id="hora_fin" class="form-control" required></div></div>
        <div class="mb-3"><label>Actividad</label><input type="text" name="actividad" id="actividad" class="form-control" required></div>
        <div class="mb-3"><label>Color</label><select name="color_fondo" id="color_fondo" class="form-select"><option value="bg-primary">Azul</option><option value="bg-success">Verde</option><option value="bg-danger">Rojo</option><option value="bg-warning text-dark">Amarillo</option><option value="bg-secondary">Gris</option></select></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar Turno</button></div></form>
</div></div></div>

<!-- Modal para ver grupo y tus scripts de Javascript idénticos... -->
<div class="modal fade" id="modalGestionGrupo" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Personal Asignado</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light"><div class="alert alert-secondary text-center"><strong id="grupoDia"></strong> | <span id="grupoHora"></span></div><table class="table bg-white"><thead><tr><th>Empleado</th><th>Cargo</th><th>Actividad</th><th>Acciones</th></tr></thead><tbody id="tablaEmpleadosGrupo"></tbody></table></div></div></div></div>
<form id="formEliminar" action="" method="POST" style="display:none;"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="delete_id"></form>

<script>
function limpiarFormularioTurno() { document.getElementById("accion").value = "crear"; document.getElementById("turno_id").value = ""; document.getElementById("personal_id").value = ""; }
function cargarEdicion(datos) { bootstrap.Modal.getInstance(document.getElementById('modalGestionGrupo'))?.hide(); document.getElementById("accion").value = "editar"; document.getElementById("turno_id").value = datos.id; document.getElementById("personal_id").value = datos.personal_id; document.getElementById("dia_semana").value = datos.dia_semana; document.getElementById("hora_inicio").value = datos.hora_inicio; document.getElementById("hora_fin").value = datos.hora_fin; document.getElementById("actividad").value = datos.actividad; document.getElementById("color_fondo").value = datos.color_fondo; new bootstrap.Modal(document.getElementById('modalTurno')).show(); }
function eliminarTurno(id) { if(confirm('¿Eliminar turno?')) { document.getElementById('delete_id').value = id; document.getElementById('formEliminar').submit(); } }
function abrirModalGestion(empleados, dia, hora) { document.getElementById('grupoDia').textContent = dia; document.getElementById('grupoHora').textContent = hora; let tbody = document.getElementById('tablaEmpleadosGrupo'); tbody.innerHTML = ''; empleados.forEach(emp => { let tr = document.createElement('tr'); let jsonEmp = JSON.stringify(emp).replace(/'/g, "&apos;").replace(/"/g, "&quot;"); tr.innerHTML = `<td>${emp.apellidos}, ${emp.nombres}</td><td>${emp.cargo}</td><td>${emp.actividad}</td><td><button class="btn btn-sm btn-warning me-1" onclick="cargarEdicion(${jsonEmp})"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-danger" onclick="eliminarTurno(${emp.id})"><i class="bi bi-trash"></i></button></td>`; tbody.appendChild(tr); }); new bootstrap.Modal(document.getElementById('modalGestionGrupo')).show(); }
</script>
<?php include '../Includes/footer.php'; ?>