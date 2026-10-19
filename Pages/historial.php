<?php
require_once '../PHP/connection.php';
try {
    $historial = $pdo->query("SELECT h.*, u.username as nombre_usuario FROM historial_auditoria h LEFT JOIN usuarios_sistema u ON h.usuario_id = u.id ORDER BY h.fecha_evento DESC LIMIT 50")->fetchAll();
} catch (PDOException $e) { $historial = []; }

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clock-history me-2 text-primary"></i> Auditoría del Sistema</h2>
</div>
<div class="row">
    <?php if (count($historial) > 0): ?>
        <?php foreach ($historial as $h): 
            $color = 'primary'; $icono = 'bi-info-circle';
            if ($h['accion'] == 'CREAR') { $color = 'success'; $icono = 'bi-plus-circle'; }
            if ($h['accion'] == 'ACTUALIZAR') { $color = 'warning text-dark'; $icono = 'bi-pencil-square'; }
            if ($h['accion'] == 'ELIMINAR') { $color = 'danger'; $icono = 'bi-trash'; }
            if ($h['accion'] == 'ACCESO_CRITICO') { $color = 'danger bg-opacity-25 border-danger border-2'; $icono = 'bi-shield-exclamation'; }
        ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 border-start border-4 border-<?= str_replace(' text-dark','',$color) ?> h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-<?= $color ?> rounded-pill"><i class="bi <?= $icono ?> me-1"></i> <?= htmlspecialchars($h['accion']) ?></span>
                        <small class="text-muted" style="font-size: 0.8rem;"><i class="bi bi-calendar me-1"></i> <?= date('d/m/Y h:i A', strtotime($h['fecha_evento'])) ?></small>
                    </div>
                    <h6 class="fw-bold mb-1"><i class="bi bi-diagram-2 me-1"></i> Módulo: <?= htmlspecialchars($h['tabla_afectada']) ?></h6>
                    <p class="card-text text-muted small mb-3"><?= htmlspecialchars($h['descripcion_detallada']) ?></p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <small class="text-secondary fw-bold"><i class="bi bi-person-badge me-1"></i> Usuario: <?= strtoupper(htmlspecialchars($h['nombre_usuario'] ?? 'Sistema')) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info text-center py-4 w-100"><i class="bi bi-info-circle fs-3 d-block mb-2"></i>No hay registros en auditoría.</div>
    <?php endif; ?>
</div>
<?php include '../Includes/footer.php'; ?>