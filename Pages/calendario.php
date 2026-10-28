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
.schedule-grid { display: flex; overflow-x: auto; gap: 1rem; padding-bottom: 1rem; }
.day-col { flex: 1; min-width: 250px; background: var(--bs-body-tertiary); border-radius: 1.2rem; padding: 1rem; border: 1px solid var(--bs-border-color); }
.day-header { text-align: center; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px dashed var(--bs-border-color); }
.shift-card { background: var(--bs-body-bg); border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; border-left: 5px solid; box-shadow: 0 4px 15px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); position: relative; overflow: hidden; color: #fff;}
.shift-card.bg-warning { color: #000 !important; } /* Fuerza negro en tarjetas amarillas */
.shift-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
.shift-time { font-size: 0.8rem; font-weight: 700; opacity: 0.9; display: flex; align-items: center; margin-bottom: 0.5rem; }
.shift-title { font-weight: 800; font-size: 1.1rem; margin-bottom: 0.2rem; }
.shift-count { position: absolute; right: 1rem; top: 1rem; background: rgba(0,0,0,0.2); color: inherit; font-weight: bold; border-radius: 50px; padding: 0.2rem 0.6rem; font-size: 0.8rem; }
.badge-member { background: rgba(0,0,0,0.15); color: inherit; border: 1px solid rgba(255,255,255,0.2); font-size:0.75rem; font-weight: 600; padding: 3px 8px;}
.bg-primary { border-left-color: #0d6efd !important; } .bg-success { border-left-color: #198754 !important; } .bg-danger { border-left-color: #dc3545 !important; } .bg-warning { border-left-color: #ffc107 !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-calendar-week me-2 text-primary"></i> Horario Dinámico</h2>
        <p class="text-muted small mb-0">Organización visual de turnos semanales.</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTurno" onclick="limpiarFormularioTurno()">
        <i class="bi bi-plus-lg me-1"></i> Asignar Turno
    </button>
</div>

<div class="schedule-grid">
    <?php foreach($dias_semana as $dia): ?>
    <div class="day-col shadow-sm">
        <div class="day-header <?= ($dia=='Sábado'||$dia=='Domingo')?'text-danger':'text-primary' ?>"><?= $dia ?></div>
        
        <?php 
        $hay_turnos = false;
        foreach ($intervalos_unicos as $hora): 
            if (isset($matriz[$hora][$dia])): 
                $hay_turnos = true;
                $empleados = $matriz[$hora][$dia]; 
                $total = count($empleados);
                $color = $empleados[0]['color_fondo']; 
        ?>
            <div class="shift-card <?= $color ?>" onclick='abrirModalGestion(<?= json_encode($empleados) ?>, "<?= $dia ?>", "<?= $hora ?>")'>
                <div class="shift-count"><i class="bi bi-people-fill"></i> <?= $total ?></div>
                <div class="shift-time"><i class="bi bi-clock-fill me-1"></i> <?= str_replace("-", "<i class='bi bi-arrow-right mx-1'></i>", $hora) ?></div>
                <div class="shift-title"><?= htmlspecialchars($empleados[0]['actividad']) ?></div>
                
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <?php 
                    $mostrados = 0;
                    foreach($empleados as $e) {
                        if($mostrados < 3) echo '<span class="badge badge-member rounded-pill">'.htmlspecialchars(explode(" ", $e['nombres'])[0], ENT_QUOTES, 'UTF-8').'</span>';
                        $mostrados++;
                    }
                    if($total > 3) echo '<span class="badge bg-light text-dark border rounded-pill">+'.($total-3).'</span>';
                    ?>
                </div>
            </div>
        <?php 
            endif;
        endforeach; 
        if(!$hay_turnos): 
        ?>
            <div class="text-center p-4 opacity-50">
                <i class="bi bi-cup-hot fs-1 d-block mb-2"></i>
                <small class="fw-bold d-block">Día Libre</small>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
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