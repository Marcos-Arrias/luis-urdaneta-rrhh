<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    try {
        $pdo->beginTransaction();
        
        if ($a === 'crear') {
            // Inserta evento base
            $pdo->prepare("INSERT INTO cronograma_eventos (fecha, hora_inicio, hora_fin, titulo, descripcion, creador_id) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$_POST['fecha'], $_POST['hora_inicio'], $_POST['hora_fin'], $_POST['titulo'], $_POST['descripcion'], $_SESSION['admin_id'] ?? null]);
            
            $evento_id = $pdo->lastInsertId();
            
            // Insertar involucrados usando bucle (Tabla Relacional 1FN)
            if(!empty($_POST['involucrados'])) {
                $stmtPart = $pdo->prepare("INSERT INTO cronograma_participantes (evento_id, personal_id) VALUES (?, ?)");
                foreach($_POST['involucrados'] as $p_id) {
                    $stmtPart->execute([$evento_id, $p_id]);
                }
            }
            
            registrarHistorial($pdo, 'Cronograma', 'CREAR', "Creó actividad: {$_POST['titulo']}.");
            $pdo->commit(); header("Location: cronograma.php?msg=creado"); exit;
            
        } elseif ($a === 'eliminar') {
            // El DELETE CASCADE borrará a los participantes automáticamente
            $pdo->prepare("DELETE FROM cronograma_eventos WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Cronograma', 'ELIMINAR', "Eliminó actividad.");
            $pdo->commit(); header("Location: cronograma.php?msg=eliminado"); exit;
        }
    } catch (PDOException $e) { $pdo->rollBack(); die("Error DB: " . $e->getMessage()); }
}

$fechaFiltro = $_GET['fecha_filtro'] ?? date('Y-m-d');

// Consulta Máster: Trae el evento y usa GROUP_CONCAT para traer los nombres de la otra tabla como una lista
$actividades = $pdo->prepare("
    SELECT e.*, 
    (SELECT GROUP_CONCAT(CONCAT(p.apellidos, ' ', p.nombres) SEPARATOR '||') 
     FROM cronograma_participantes cp JOIN personal p ON cp.personal_id = p.id 
     WHERE cp.evento_id = e.id) as lista_nombres
    FROM cronograma_eventos e 
    WHERE e.fecha = ? ORDER BY e.hora_inicio ASC
");
$actividades->execute([$fechaFiltro]);
$datos = $actividades->fetchAll();

// Personal Activo para el Modal
$todo_personal = $pdo->query("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre FROM personal WHERE estado_laboral = 'Activo' ORDER BY apellidos ASC")->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>


<style>
.agenda-row { display: flex; margin-bottom: 1.5rem; position: relative; }
.agenda-time { width: 120px; text-align: right; padding-right: 1.5rem; border-right: 3px solid var(--bs-primary); font-weight: 800; color: var(--bs-primary); flex-shrink: 0; }
.agenda-time small { display: block; font-weight: 500; color: var(--bs-secondary); font-size: 0.8rem; }
.agenda-card { flex-grow: 1; margin-left: 1.5rem; background: var(--bs-body-tertiary); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid var(--bs-border-color); position: relative; transition: transform 0.2s; }
.agenda-card:hover { transform: scale(1.01); border-color: var(--bs-primary); }
.agenda-card::before { content: ''; position: absolute; left: -1.5rem; top: 1.2rem; width: 15px; height: 3px; background: var(--bs-primary); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-calendar2-event me-2 text-primary"></i> Agenda Diaria</h2>
        <p class="text-muted small mb-0">Eventos y actividades programadas para la fecha.</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro">
        <i class="bi bi-plus-lg me-1"></i> Nueva Actividad
    </button>
</div>

<form method="GET" class="mb-5 bg-body-tertiary p-3 rounded-pill shadow-sm d-inline-flex align-items-center border">
    <i class="bi bi-search ms-2 me-3 text-muted"></i>
    <input type="date" name="fecha_filtro" class="form-control border-0 bg-transparent shadow-none" value="<?= $fechaFiltro ?>" style="width: 150px;">
    <button class="btn btn-dark rounded-pill px-4 ms-2">Consultar</button>
</form>

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
                    <form action="" method="POST" onsubmit="return confirm('¿Borrar actividad?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm text-danger border-0 p-0 fs-5"><i class="bi bi-x-circle-fill"></i></button></form>
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
            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486747.png" width="100" class="mb-3">
            <h4>Día Despejado</h4>
            <p>No hay eventos registrados para esta fecha.</p>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalRegistro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title">Agregar Actividad</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="" method="POST"><div class="modal-body">
        <input type="hidden" name="accion" value="crear">
        <div class="mb-3"><label>Título</label><input type="text" name="titulo" class="form-control" required></div>
        <div class="row mb-3"><div class="col-md-6"><label>Inicio</label><input type="time" name="hora_inicio" class="form-control" required></div><div class="col-md-6"><label>Fin</label><input type="time" name="hora_fin" class="form-control" required></div></div>
        <div class="mb-3"><label>Fecha</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-3">
            <label>Vincular Personal</label>
            <select name="involucrados[]" class="form-select" multiple size="4">
                <?php foreach ($todo_personal as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label>Notas</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar Actividad</button></div></form>
</div></div></div>
<?php include '../Includes/footer.php'; ?>