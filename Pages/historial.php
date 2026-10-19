<?php
require_once '../PHP/connection.php';
try {
    $historial = $pdo->query("SELECT h.*, u.username as nombre_usuario FROM historial_auditoria h LEFT JOIN usuarios_sistema u ON h.usuario_id = u.id ORDER BY h.fecha_evento DESC LIMIT 50")->fetchAll();
} catch (PDOException $e) { $historial = []; }

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<style>
.timeline { position: relative; padding: 20px 0; list-style: none; }
.timeline:before { content: ''; position: absolute; top: 0; bottom: 0; left: 40px; width: 4px; background: var(--bs-border-color); border-radius: 4px; }
.timeline-item { position: relative; margin-bottom: 30px; padding-left: 100px; padding-right: 20px; }
.timeline-icon { position: absolute; left: 24px; top: 0; width: 36px; height: 36px; border-radius: 50%; text-align: center; line-height: 36px; font-size: 1.2rem; color: white; box-shadow: 0 0 0 6px var(--bs-body-bg); z-index: 10; }
.timeline-content { background: var(--bs-body-tertiary); padding: 1.5rem; border-radius: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid var(--bs-border-color); transition: transform 0.2s; }
.timeline-content:hover { transform: translateX(5px); border-color: var(--bs-primary); }
.timeline-date { font-weight: bold; color: var(--bs-secondary); font-size: 0.85rem; letter-spacing: 0.5px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-activity me-2 text-primary"></i> Auditoría y Trazabilidad</h2>
        <p class="text-muted small mb-0">Registro histórico de las últimas 50 operaciones del sistema.</p>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <?php if (count($historial) > 0): ?>
            <ul class="timeline">
                <?php foreach ($historial as $h): 
                    $bg = 'bg-primary'; $icon = 'bi-info-lg';
                    if ($h['accion'] == 'CREAR') { $bg = 'bg-success'; $icon = 'bi-plus-lg'; }
                    if ($h['accion'] == 'ACTUALIZAR') { $bg = 'bg-warning text-dark'; $icon = 'bi-pencil-fill'; }
                    if ($h['accion'] == 'ELIMINAR') { $bg = 'bg-danger'; $icon = 'bi-trash-fill'; }
                    if ($h['accion'] == 'ACCESO_CRITICO') { $bg = 'bg-dark'; $icon = 'bi-shield-lock-fill'; }
                    if ($h['accion'] == 'LOGIN') { $bg = 'bg-info text-dark'; $icon = 'bi-door-open-fill'; }
                ?>
                <li class="timeline-item">
                    <div class="timeline-icon <?= $bg ?> shadow-sm"><i class="bi <?= $icon ?>"></i></div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge <?= $bg ?>-subtle text-<?= str_replace(' text-dark','',$bg) ?> rounded-pill px-3 py-2 fw-bold">
                                <?= $h['accion'] ?> EN <?= strtoupper($h['tabla_afectada']) ?>
                            </span>
                            <span class="timeline-date"><i class="bi bi-clock me-1"></i> <?= date('d M, h:i A', strtotime($h['fecha_evento'])) ?></span>
                        </div>
                        <p class="mb-3 fs-6"><?= htmlspecialchars($h['descripcion_detallada']) ?></p>
                        <div class="d-flex align-items-center">
                            <div class="avatar-thumb bg-secondary text-white d-flex align-items-center justify-content-center rounded-circle me-2" style="width:25px;height:25px;font-size:0.7rem;">
                                <?= strtoupper(substr($h['nombre_usuario'] ?? 'S', 0, 1)) ?>
                            </div>
                            <small class="fw-bold text-muted">Ejecutado por: <?= htmlspecialchars($h['nombre_usuario'] ?? 'Sistema') ?></small>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-center py-5"><i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i><h5>No hay registros</h5></div>
        <?php endif; ?>
    </div>
</div>
<?php include '../Includes/footer.php'; ?>