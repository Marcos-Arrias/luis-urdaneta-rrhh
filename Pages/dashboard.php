<?php 
require_once '../PHP/connection.php';
date_default_timezone_set('America/Caracas');

$hoy = date('Y-m-d');

// 1. ESTADÍSTICAS DE PERSONAL ACTIVO POR CATEGORÍA
$catsRaw = $pdo->query("
    SELECT t.id, t.nombre, COUNT(DISTINCT p.id) as total
    FROM cat_tipos_personal t
    LEFT JOIN cat_cargos_areas c ON t.id = c.tipo_personal_id
    LEFT JOIN personal_cargos pc ON c.id = pc.cargo_area_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN personal p ON pc.personal_id = p.id AND p.estado_laboral = 'Activo'
    GROUP BY t.id
")->fetchAll(PDO::FETCH_ASSOC);

$qDocentes = 0; $qAdmins = 0; $qObreros = 0;
foreach ($catsRaw as $c) {
    if ($c['id'] == 1) $qDocentes = (int)$c['total'];
    if ($c['id'] == 2) $qAdmins   = (int)$c['total'];
    if ($c['id'] == 3) $qObreros  = (int)$c['total'];
}
$totalActivos = $qDocentes + $qAdmins + $qObreros;

// Porcentajes para el Gráfico Donut SVG (evitar división por cero)
$baseCalc = max($totalActivos, 1);
$pctDoc = round(($qDocentes / $baseCalc) * 100, 1);
$pctAdm = round(($qAdmins   / $baseCalc) * 100, 1);
$pctObr = round(($qObreros  / $baseCalc) * 100, 1);

// 2. DESGLOSE POR ESTADO LABORAL
$estadosRaw = $pdo->query("SELECT estado_laboral, COUNT(*) as total FROM personal GROUP BY estado_laboral")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalPlantel = array_sum($estadosRaw);

// 3. ASISTENCIA DE HOY
$asistRaw = $pdo->prepare("SELECT estado_asistencia, COUNT(*) as total FROM asistencias WHERE fecha = ? GROUP BY estado_asistencia");
$asistRaw->execute([$hoy]);
$asistHoy = $asistRaw->fetchAll(PDO::FETCH_KEY_PAIR);
$hoyPresentes   = (int)($asistHoy['Presente'] ?? 0);
$hoyRetardos    = (int)($asistHoy['Retardo'] ?? 0);
$hoyJustif      = (int)($asistHoy['Falta Justificada'] ?? 0);
$hoyAusentes    = (int)($asistHoy['Ausente'] ?? 0);
$hoyRegistrados = $hoyPresentes + $hoyRetardos + $hoyJustif + $hoyAusentes;

// 4. DOCUMENTOS EN REPOSITORIO
$docStats = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(tamano_bytes), 0) as bytes FROM documentos")->fetch();
$totalDocs = (int)($docStats['total'] ?? 0);
$docsMB    = number_format(($docStats['bytes'] ?? 0) / 1048576, 2);

// 5. CUMPLEAÑEROS DEL MES
$cumpleaneros = $pdo->query("
    SELECT p.id, p.nombres, p.apellidos, p.fecha_nacimiento, DAY(p.fecha_nacimiento) as dia,
           c.nombre as cargo, c.tipo_personal_id
    FROM personal p
    LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
    WHERE p.fecha_nacimiento IS NOT NULL 
      AND MONTH(p.fecha_nacimiento) = MONTH(CURDATE())
      AND p.estado_laboral = 'Activo'
    GROUP BY p.id
    ORDER BY DAY(p.fecha_nacimiento) ASC
")->fetchAll();

// 6. PRÓXIMOS EVENTOS DEL CRONOGRAMA
$proximosEventos = $pdo->query("
    SELECT * FROM cronograma_eventos 
    WHERE fecha >= CURDATE() 
    ORDER BY fecha ASC, hora_inicio ASC 
    LIMIT 4
")->fetchAll();

// 7. ÚLTIMOS MOVIMIENTOS DE AUDITORÍA
$ultimasAuditorias = $pdo->query("
    SELECT h.*, u.username 
    FROM historial_auditoria h 
    LEFT JOIN usuarios_sistema u ON h.usuario_id = u.id 
    ORDER BY h.fecha_evento DESC 
    LIMIT 5
")->fetchAll();

$perfil = obtenerPerfilSeguridad($pdo, $_SESSION['admin_id'] ?? 0);

include '../Includes/header.php'; 
include '../Includes/sidebar.php'; 
?>

<style>
.dash-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.dash-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important; }
.quick-btn { transition: all 0.2s ease; }
.quick-btn:hover { transform: translateY(-2px); }
</style>

<!-- CABECERA DE BIENVENIDA -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-speedometer2 me-2 text-primary"></i>Panel Ejecutivo RRHH
        </h2>
        <p class="text-muted small mb-0">
            Bienvenido, <strong><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Administrador') ?></strong>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($perfil['rol_nombre'] ?? 'SuperAdministrador') ?> (ReBAC)
            </span>
            · Hoy es <?= date('d/m/Y') ?>
        </p>
    </div>

    <!-- BARRA DE ACCIONES RÁPIDAS -->
    <div class="d-flex flex-wrap gap-2">
        <a href="asistencias.php" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm quick-btn">
            <i class="bi bi-lightning-charge-fill me-1"></i> Marcar Asistencia
        </a>
        <a href="personal.php?tipo=1" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm quick-btn">
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Personal
        </a>
        <a href="documentos.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold shadow-sm quick-btn">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Subir Archivo
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold shadow-sm quick-btn" onclick="abrirModalBusquedaAvanzada()">
            <i class="bi bi-search me-1"></i> Buscar (Ctrl+K)
        </button>
    </div>
</div>

<!-- 1. KPIs PRINCIPALES (4 TARJETAS) -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 dash-card border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Personal Activo</span>
                        <h2 class="fw-black mb-1 mt-1"><?= $totalActivos ?></h2>
                    </div>
                    <div class="icon-circle bg-primary-subtle text-primary"><i class="bi bi-people-fill fs-4"></i></div>
                </div>
                <div class="small text-muted mt-2">
                    <span class="badge bg-primary-subtle text-primary">Doc: <?= $qDocentes ?></span>
                    <span class="badge bg-success-subtle text-success">Adm: <?= $qAdmins ?></span>
                    <span class="badge bg-warning-subtle text-warning-emphasis">Obr: <?= $qObreros ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 dash-card border-start border-4 border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Asistencia de Hoy</span>
                        <h2 class="fw-black mb-1 mt-1"><?= $hoyPresentes + $hoyRetardos ?> <small class="fs-6 text-muted fw-normal">/ <?= $totalActivos ?></small></h2>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-3"><i class="bi bi-person-check-fill fs-4"></i></div>
                </div>
                <div class="small text-muted mt-2">
                    <span class="text-success fw-bold"><?= $hoyPresentes ?> Presentes</span> · 
                    <span class="text-warning fw-bold"><?= $hoyRetardos ?> Retardos</span> · 
                    <span class="text-danger fw-bold"><?= $hoyAusentes ?> Aus.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 dash-card border-start border-4 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Archivo Digital</span>
                        <h2 class="fw-black mb-1 mt-1"><?= $totalDocs ?></h2>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-3"><i class="bi bi-folder2-open fs-4"></i></div>
                </div>
                <div class="small text-muted mt-2">
                    <i class="bi bi-hdd me-1"></i> <?= $docsMB ?> MB verificados con SHA-256
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 dash-card border-start border-4 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Próximos Eventos</span>
                        <h2 class="fw-black mb-1 mt-1"><?= count($proximosEventos) ?></h2>
                    </div>
                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-3"><i class="bi bi-calendar-event-fill fs-4"></i></div>
                </div>
                <div class="small text-muted mt-2">
                    <a href="cronograma.php" class="text-decoration-none fw-bold">Ver agenda institucional <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. GRÁFICA ESTADÍSTICA OFFLINE + DESGLOSE DE ESTADOS -->
<div class="row g-3 mb-4">
    <!-- Gráfico Donut SVG Puro (Sin Internet) -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Distribución del Personal Activo</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-around flex-wrap gap-3">
                <?php
                // Cálculo matemático exacto para anillo SVG (circunferencia = 100)
                $offDoc = 25;
                $offAdm = (125 - $pctDoc) % 100;
                $offObr = (125 - ($pctDoc + $pctAdm)) % 100;
                ?>
                <div class="position-relative" style="width: 170px; height: 170px;">
                    <svg viewBox="0 0 42 42" class="w-100 h-100">
                        <circle cx="21" cy="21" r="15.9154943" fill="transparent" stroke="var(--bs-secondary-bg)" stroke-width="5"></circle>
                        <?php if ($totalActivos > 0): ?>
                            <circle cx="21" cy="21" r="15.9154943" fill="transparent" stroke="#0d6efd" stroke-width="5" stroke-dasharray="<?= $pctDoc ?> <?= 100 - $pctDoc ?>" stroke-dashoffset="<?= $offDoc ?>"></circle>
                            <circle cx="21" cy="21" r="15.9154943" fill="transparent" stroke="#198754" stroke-width="5" stroke-dasharray="<?= $pctAdm ?> <?= 100 - $pctAdm ?>" stroke-dashoffset="<?= $offAdm ?>"></circle>
                            <circle cx="21" cy="21" r="15.9154943" fill="transparent" stroke="#ffc107" stroke-width="5" stroke-dasharray="<?= $pctObr ?> <?= 100 - $pctObr ?>" stroke-dashoffset="<?= $offObr ?>"></circle>
                        <?php endif; ?>
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="fw-black fs-3 lh-1"><?= $totalActivos ?></div>
                        <small class="text-muted" style="font-size: 0.7rem;">Activos</small>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2" style="min-width: 170px;">
                    <a href="personal.php?tipo=1" class="text-decoration-none text-body d-flex justify-content-between align-items-center p-2 rounded-3 bg-body-tertiary">
                        <span><span class="badge bg-primary me-2">&nbsp;</span>Docentes</span>
                        <strong><?= $qDocentes ?> (<?= $pctDoc ?>%)</strong>
                    </a>
                    <a href="personal.php?tipo=2" class="text-decoration-none text-body d-flex justify-content-between align-items-center p-2 rounded-3 bg-body-tertiary">
                        <span><span class="badge bg-success me-2">&nbsp;</span>Administrativos</span>
                        <strong><?= $qAdmins ?> (<?= $pctAdm ?>%)</strong>
                    </a>
                    <a href="personal.php?tipo=3" class="text-decoration-none text-body d-flex justify-content-between align-items-center p-2 rounded-3 bg-body-tertiary">
                        <span><span class="badge bg-warning me-2">&nbsp;</span>Obreros</span>
                        <strong><?= $qObreros ?> (<?= $pctObr ?>%)</strong>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Situación Laboral General -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Situación Laboral de la Nómina (Total: <?= $totalPlantel ?>)</h6>
                <a href="organigrama.php" class="small text-decoration-none fw-bold">Ver Organigrama <i class="bi bi-diagram-3"></i></a>
            </div>
            <div class="card-body d-flex flex-column justify-content-center gap-3">
                <?php
                $listaEst = [
                    'Activo'   => ['color' => 'bg-success', 'icono' => 'bi-check-circle-fill'],
                    'Permiso'  => ['color' => 'bg-info',    'icono' => 'bi-calendar-check'],
                    'Reposo'   => ['color' => 'bg-warning', 'icono' => 'bi-heart-pulse'],
                    'Jubilado' => ['color' => 'bg-secondary','icono' => 'bi-award'],
                    'Inactivo' => ['color' => 'bg-danger',  'icono' => 'bi-x-circle']
                ];
                $basePlantel = max($totalPlantel, 1);
                foreach ($listaEst as $nomEst => $cfg):
                    $cant = (int)($estadosRaw[$nomEst] ?? 0);
                    $porc = round(($cant / $basePlantel) * 100);
                ?>
                <div>
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span><i class="bi <?= $cfg['icono'] ?> me-1"></i> <?= $nomEst ?></span>
                        <span><?= $cant ?> trabajadores (<?= $porc ?>%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $cfg['color'] ?> rounded-pill" style="width: <?= $porc ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. CUMPLEAÑEROS DEL MES + PRÓXIMOS EVENTOS + AUDITORÍA -->
<div class="row g-3">
    <!-- Cumpleañeros -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-cake2-fill me-2 text-danger"></i>Cumpleañeros del Mes</h6>
            </div>
            <div class="card-body pt-0" style="max-height: 260px; overflow-y: auto;">
                <?php if (count($cumpleaneros) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($cumpleaneros as $cp): 
                            $esHoy = ((int)$cp['dia'] === (int)date('j'));
                        ?>
                        <a href="personal.php?tipo=<?= $cp['tipo_personal_id'] ?? 1 ?>&auto_edit=<?= $cp['id'] ?>" class="list-group-item list-group-item-action px-2 py-2 d-flex justify-content-between align-items-center border-0 rounded-3 mb-1 <?= $esHoy ? 'bg-danger-subtle' : '' ?>">
                            <div>
                                <div class="fw-bold small"><?= htmlspecialchars($cp['nombres'] . ' ' . $cp['apellidos']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($cp['cargo'] ?? 'Personal') ?></small>
                            </div>
                            <span class="badge <?= $esHoy ? 'bg-danger' : 'bg-body-secondary text-body border' ?> rounded-pill">
                                <?= $esHoy ? '🎂 ¡Hoy!' : 'Día ' . $cp['dia'] ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-balloon fs-2 opacity-25 d-block mb-1"></i>No hay cumpleaños registrados este mes.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Próximos Eventos -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-2 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-calendar2-week-fill me-2 text-warning"></i>Agenda Próxima</h6>
                <a href="cronograma.php" class="small text-decoration-none">Ver todo</a>
            </div>
            <div class="card-body pt-0">
                <?php if (count($proximosEventos) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($proximosEventos as $ev): ?>
                        <a href="cronograma.php?fecha_filtro=<?= $ev['fecha'] ?>" class="list-group-item list-group-item-action px-2 py-2 border-0 rounded-3 mb-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="small text-truncate me-2"><?= htmlspecialchars($ev['titulo']) ?></strong>
                                <span class="badge bg-warning-subtle text-warning-emphasis border"><?= date('d/M', strtotime($ev['fecha'])) ?></span>
                            </div>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($ev['hora_inicio'])) ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-calendar-check fs-2 opacity-25 d-block mb-1"></i>Agenda despejada para los próximos días.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bitácora Reciente -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-2 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-activity me-2 text-info"></i>Actividad Reciente</h6>
                <a href="historial.php" class="small text-decoration-none">Auditoría</a>
            </div>
            <div class="card-body pt-0">
                <?php if (count($ultimasAuditorias) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ultimasAuditorias as $aud): ?>
                        <div class="list-group-item px-1 py-2 border-bottom border-light-subtle bg-transparent">
                            <div class="d-flex justify-content-between small">
                                <span class="fw-bold text-primary"><?= htmlspecialchars($aud['accion']) ?> · <?= htmlspecialchars($aud['tabla_afectada']) ?></span>
                                <small class="text-muted"><?= date('d/m H:i', strtotime($aud['fecha_evento'])) ?></small>
                            </div>
                            <div class="small text-muted text-truncate" title="<?= htmlspecialchars($aud['descripcion_detallada']) ?>">
                                <?= htmlspecialchars($aud['descripcion_detallada']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4 small">Sin actividad registrada.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../Includes/footer.php'; ?>