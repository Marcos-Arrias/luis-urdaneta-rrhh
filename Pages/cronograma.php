<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        $pdo->beginTransaction();
        
        if ($a === 'crear') {
            $pdo->prepare("INSERT INTO cronograma_eventos (fecha, hora_inicio, hora_fin, titulo, descripcion, creador_id) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$_POST['fecha'], $_POST['hora_inicio'], $_POST['hora_fin'], $_POST['titulo'], $_POST['descripcion'], $_SESSION['admin_id'] ?? null]);
            
            $evento_id = $pdo->lastInsertId();
            
            if(!empty($_POST['involucrados'])) {
                $stmtPart = $pdo->prepare("INSERT INTO cronograma_participantes (evento_id, personal_id) VALUES (?, ?)");
                foreach($_POST['involucrados'] as $p_id) {
                    $stmtPart->execute([$evento_id, $p_id]);
                }
            }
            
            registrarHistorial($pdo, 'Cronograma', 'CREAR', "Creó actividad: {$_POST['titulo']}.");
            $pdo->commit(); header("Location: cronograma.php?fecha_filtro=" . $_POST['fecha'] . "&msg=creado"); exit;
            
        } elseif ($a === 'editar') {
            $id = $_POST['id'];
            $pdo->prepare("UPDATE cronograma_eventos SET fecha=?, hora_inicio=?, hora_fin=?, titulo=?, descripcion=? WHERE id=?")
                ->execute([$_POST['fecha'], $_POST['hora_inicio'], $_POST['hora_fin'], $_POST['titulo'], $_POST['descripcion'], $id]);

            // Actualizar lista de involucrados
            $pdo->prepare("DELETE FROM cronograma_participantes WHERE evento_id=?")->execute([$id]);
            if (!empty($_POST['involucrados'])) {
                $stmtPart = $pdo->prepare("INSERT INTO cronograma_participantes (evento_id, personal_id) VALUES (?, ?)");
                foreach($_POST['involucrados'] as $p_id) {
                    $stmtPart->execute([$id, $p_id]);
                }
            }

            registrarHistorial($pdo, 'Cronograma', 'ACTUALIZAR', "Actualizó actividad: {$_POST['titulo']}.");
            $pdo->commit(); header("Location: cronograma.php?fecha_filtro=" . $_POST['fecha'] . "&msg=editado"); exit;

        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM cronograma_eventos WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Cronograma', 'ELIMINAR', "Eliminó actividad.");
            $pdo->commit(); header("Location: cronograma.php?fecha_filtro=" . ($_GET['fecha_filtro'] ?? date('Y-m-d')) . "&msg=eliminado"); exit;
        }
    } catch (PDOException $e) { $pdo->rollBack(); die("Error DB: " . $e->getMessage()); }
}

$fechaFiltro = $_GET['fecha_filtro'] ?? date('Y-m-d');
$ayer = date('Y-m-d', strtotime($fechaFiltro . ' -1 day'));
$manana = date('Y-m-d', strtotime($fechaFiltro . ' +1 day'));
$hoy = date('Y-m-d');

// Consulta de actividades del día (trayendo nombres e IDs para el select de participantes)
$actividades = $pdo->prepare("
    SELECT e.*, 
    (SELECT GROUP_CONCAT(CONCAT(p.apellidos, ' ', p.nombres) SEPARATOR '||') 
     FROM cronograma_participantes cp JOIN personal p ON cp.personal_id = p.id 
     WHERE cp.evento_id = e.id) as lista_nombres,
    (SELECT GROUP_CONCAT(cp.personal_id) FROM cronograma_participantes cp WHERE cp.evento_id = e.id) as lista_ids
    FROM cronograma_eventos e 
    WHERE e.fecha = ? ORDER BY e.hora_inicio ASC
");
$actividades->execute([$fechaFiltro]);
$datos = $actividades->fetchAll();

// Personal Activo para el Modal
$todo_personal = $pdo->query("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre FROM personal WHERE estado_laboral = 'Activo' ORDER BY apellidos ASC")->fetchAll();

// Consulta para el semáforo de eventos cercanos (-3 a +3 días)
$dia_inicio = date('Y-m-d', strtotime($fechaFiltro . ' -3 days'));
$dia_fin = date('Y-m-d', strtotime($fechaFiltro . ' +3 days'));

$stmtSemCron = $pdo->prepare("
    SELECT fecha, COUNT(*) as total_eventos
    FROM cronograma_eventos
    WHERE fecha BETWEEN ? AND ?
    GROUP BY fecha
");
$stmtSemCron->execute([$dia_inicio, $dia_fin]);
$eventosSemaforo = [];
foreach($stmtSemCron->fetchAll(PDO::FETCH_ASSOC) as $ec) {
    $eventosSemaforo[$ec['fecha']] = $ec['total_eventos'];
}

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<style>
.agenda-row { display: flex; margin-bottom: 1.5rem; position: relative; }
.agenda-time { width: 120px; text-align: right; padding-right: 1.5rem; border-right: 3px solid var(--bs-primary); font-weight: 800; color: var(--bs-primary); flex-shrink: 0; }
.agenda-time small { display: block; font-weight: 500; color: var(--bs-secondary); font-size: 0.8rem; }
.agenda-card { flex-grow: 1; margin-left: 1.5rem; background: var(--bs-body-tertiary); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid var(--bs-border-color); position: relative; transition: transform 0.2s; }
.agenda-card:hover { transform: scale(1.01); border-color: var(--bs-primary); }
.agenda-card::before { content: ''; position: absolute; left: -1.5rem; top: 1.2rem; width: 15px; height: 3px; background: var(--bs-primary); }
.day-card { transition: all 0.2s ease-in-out; border-radius: 12px; min-width: 105px; }
.day-card:hover { transform: translateY(-3px); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-calendar2-event me-2 text-primary"></i> Agenda Diaria</h2>
        <p class="text-muted small mb-0">Eventos y actividades programadas para la fecha.</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarModalCronograma()">
        <i class="bi bi-plus-lg me-1"></i> Nueva Actividad
    </button>
</div>

<!-- BARRA NAVEGADORA DE DÍAS -->
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
                <span class="small fw-bold text-muted"><i class="bi bi-calendar3 me-1"></i>Ir a fecha:</span>
                <input type="date" name="fecha_filtro" class="form-control form-control-sm shadow-sm" value="<?= $fechaFiltro ?>" onchange="this.form.submit()" style="width: 155px;">
            </form>
        </div>

        <!-- Tira de 7 días -->
        <div class="d-flex overflow-auto gap-2 text-center pb-1">
            <?php 
            for ($i = -3; $i <= 3; $i++): 
                $diaActual = date('Y-m-d', strtotime($fechaFiltro . " $i day"));
                $esSeleccionado = ($diaActual == $fechaFiltro);
                $esHoy = ($diaActual == $hoy);
                $cantEventos = $eventosSemaforo[$diaActual] ?? 0;

                $diasEsp = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                $nombreDia = $diasEsp[date('w', strtotime($diaActual))];
                $fechaNum = date('d/m', strtotime($diaActual));
            ?>
            <a href="?fecha_filtro=<?= $diaActual ?>" class="day-card text-decoration-none flex-fill p-2 border <?= $esSeleccionado ? 'border-primary border-2 bg-primary text-white shadow' : 'bg-body border-secondary-subtle text-body' ?>">
                <span class="small fw-bold <?= $esSeleccionado ? 'text-white' : ($esHoy ? 'text-primary' : 'text-muted') ?>">
                    <?= $nombreDia ?> <?= $esHoy ? '★' : '' ?>
                </span>
                <h6 class="mb-2 fw-black <?= $esSeleccionado ? 'text-white' : '' ?>"><?= $fechaNum ?></h6>
                
                <div class="d-flex justify-content-center align-items-center" style="min-height: 14px;">
                    <?php if ($cantEventos > 0): ?>
                        <span class="badge <?= $esSeleccionado ? 'bg-white text-primary' : 'bg-primary' ?> rounded-pill px-2" style="font-size:0.65rem;">
                            <?= $cantEventos ?> <?= $cantEventos === 1 ? 'actividad' : 'actividades' ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size: 0.65rem;">Libre</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- LISTADO DE EVENTOS DEL DÍA -->
<div class="container-fluid px-0 mt-3">
    <?php if (count($datos) > 0): ?>
        <?php foreach ($datos as $d): ?>
        <div class="agenda-row">
            <div class="agenda-time">
                <?= date('h:i A', strtotime($d['hora_inicio'])) ?>
                <small>Hasta <?= date('h:i A', strtotime($d['hora_fin'])) ?></small>
            </div>
            <div class="agenda-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4 class="fw-bold mb-2 text-body"><?= htmlspecialchars($d['titulo']) ?></h4>
                    <div class="d-flex align-items-center">
                        <!-- BOTÓN EDITAR ACTIVIDAD -->
                        <button type="button" class="btn btn-sm text-warning border-0 p-0 fs-5 me-2" onclick='cargarEdicionCronograma(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)' title="Editar actividad">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <!-- BOTÓN BORRAR ACTIVIDAD -->
                        <form action="" method="POST" onsubmit="return confirm('¿Borrar actividad?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button class="btn btn-sm text-danger border-0 p-0 fs-5" title="Eliminar"><i class="bi bi-x-circle-fill"></i></button>
                        </form>
                    </div>
                </div>
                <p class="text-muted mb-3"><?= htmlspecialchars($d['descripcion']) ?: 'Sin descripción detallada.' ?></p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <i class="bi bi-people text-secondary"></i>
                    <?php 
                    if ($d['lista_nombres']) {
                        foreach (explode('||', $d['lista_nombres']) as $inv) echo "<span class='badge bg-primary-subtle text-primary border rounded-pill'>$inv</span>";
                    } else { echo "<span class='text-muted small'>Público General</span>"; }
                    ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5 opacity-50">
            <i class="bi bi-calendar2-check fs-1 mb-2 d-block text-muted"></i>
            <h4>Día Despejado</h4>
            <p>No hay eventos registrados para el <?= date('d/m/Y', strtotime($fechaFiltro)) ?>.</p>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL REGISTRO / EDICIÓN -->
<div class="modal fade" id="modalRegistro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="cronModalTitulo">Agregar Actividad</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" id="cron_accion" value="crear">
        <input type="hidden" name="id" id="cron_id">
        
        <div class="mb-3"><label class="form-label small fw-bold">Título</label><input type="text" name="titulo" id="cron_titulo" class="form-control" required></div>
        <div class="row mb-3"><div class="col-md-6"><label class="form-label small fw-bold">Inicio</label><input type="time" name="hora_inicio" id="cron_inicio" class="form-control" required></div><div class="col-md-6"><label class="form-label small fw-bold">Fin</label><input type="time" name="hora_fin" id="cron_fin" class="form-control" required></div></div>
        <div class="mb-3"><label class="form-label small fw-bold">Fecha</label><input type="date" name="fecha" id="cron_fecha" class="form-control" value="<?= $fechaFiltro ?>" required></div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Vincular Personal</label>
            <select name="involucrados[]" id="cron_involucrados" class="form-select" multiple size="4">
                <?php foreach ($todo_personal as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-1">Mantenga presionada la tecla Ctrl para seleccionar varios.</small>
        </div>
        <div class="mb-3"><label class="form-label small fw-bold">Notas</label><textarea name="descripcion" id="cron_descripcion" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Actividad</button></div></form>
</div></div></div>

<script>
function limpiarModalCronograma() {
    document.getElementById('cronModalTitulo').innerText = 'Agregar Actividad';
    document.getElementById('cron_accion').value = 'crear';
    document.getElementById('cron_id').value = '';
    document.getElementById('cron_titulo').value = '';
    document.getElementById('cron_inicio').value = '';
    document.getElementById('cron_fin').value = '';
    document.getElementById('cron_fecha').value = '<?= $fechaFiltro ?>';
    document.getElementById('cron_descripcion').value = '';
    
    let select = document.getElementById('cron_involucrados');
    Array.from(select.options).forEach(opt => opt.selected = false);
}

function cargarEdicionCronograma(d) {
    document.getElementById('cronModalTitulo').innerText = 'Editar Actividad';
    document.getElementById('cron_accion').value = 'editar';
    document.getElementById('cron_id').value = d.id;
    document.getElementById('cron_titulo').value = d.titulo;
    document.getElementById('cron_inicio').value = d.hora_inicio;
    document.getElementById('cron_fin').value = d.hora_fin;
    document.getElementById('cron_fecha').value = d.fecha;
    document.getElementById('cron_descripcion').value = d.descripcion || '';
    
    // Pre-seleccionar a los involucrados
    let ids = d.lista_ids ? d.lista_ids.split(',').map(Number) : [];
    let select = document.getElementById('cron_involucrados');
    Array.from(select.options).forEach(opt => {
        opt.selected = ids.includes(Number(opt.value));
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRegistro')).show();
}
</script>

<?php include '../Includes/footer.php'; ?>