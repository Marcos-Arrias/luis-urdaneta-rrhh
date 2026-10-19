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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-list-task me-2 text-primary"></i> Cronograma Diario</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-plus-circle me-2"></i>Nueva Actividad</button>
</div>

<form method="GET" class="mb-4 d-flex align-items-center bg-body-tertiary p-3 rounded shadow-sm border-start border-4 border-secondary">
    <label class="me-3 fw-bold">Ver del día:</label><input type="date" name="fecha_filtro" class="form-control w-auto me-2" value="<?= $fechaFiltro ?>"><button class="btn btn-secondary">Filtrar</button>
</form>

<div class="row">
    <?php if (count($datos) > 0): ?>
        <?php foreach ($datos as $d): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 border-top border-4 border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary fs-6 mb-2"><?= date('h:i A', strtotime($d['hora_inicio'])) ?> - <?= date('h:i A', strtotime($d['hora_fin'])) ?></span>
                        <form action="" method="POST" onsubmit="return confirm('¿Borrar?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash fs-5"></i></button></form>
                    </div>
                    <h5 class="fw-bold"><?= htmlspecialchars($d['titulo']) ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($d['descripcion']) ?></p>
                    
                    <h6 class="fw-bold mb-2 fs-6"><i class="bi bi-people-fill me-1"></i> Involucrados:</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <?php 
                        if ($d['lista_nombres']) {
                            $involucrados = explode('||', $d['lista_nombres']);
                            foreach ($involucrados as $inv) echo "<span class='badge bg-light text-dark border'>".htmlspecialchars($inv)."</span>";
                        } else { echo "<span class='text-muted small'>Nadie asignado</span>"; }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 alert alert-info text-center py-4">No hay actividades programadas.</div>
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