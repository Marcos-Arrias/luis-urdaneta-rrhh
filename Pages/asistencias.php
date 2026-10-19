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

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Control de Asistencias</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-plus-circle me-2"></i>Registrar Novedad</button>
</div>

<!-- Filtro de Fecha -->
<form method="GET" class="mb-4 d-flex align-items-center bg-body-tertiary p-3 rounded shadow-sm">
    <label class="me-3 fw-bold"><i class="bi bi-calendar3 me-2"></i>Ver registros del día:</label>
    <input type="date" name="fecha_filtro" class="form-control w-auto me-2" value="<?= $fechaFiltro ?>">
    <button type="submit" class="btn btn-secondary">Filtrar</button>
</form>

<div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-dark">
            <tr><th class="ps-3">Fecha</th><th>Categoría</th><th>Personal</th><th>Estado</th><th>Motivo / Razón</th><th class="text-center">Acción</th></tr>
        </thead>
        <tbody>
            <?php if (count($datos) > 0): ?>
                <?php foreach ($datos as $d): ?>
                <tr>
                    <td class="ps-3 fw-bold"><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($d['tipo_personal'] ?? 'Desconocido') ?></span></td>
                    <td><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></td>
                    <td>
                        <?php 
                        $color = $d['estado_asistencia'] == 'Ausente' ? 'danger' : ($d['estado_asistencia'] == 'Retardo' ? 'warning text-dark' : 'info text-dark');
                        echo "<span class='badge bg-$color'>{$d['estado_asistencia']}</span>";
                        ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($d['observaciones']) ?: 'Sin especificar' ?></td>
                    <td class="text-center">
                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Borrar registro?');">
                            <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Todos asistieron este día. No hay incidencias.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div></div></div>

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