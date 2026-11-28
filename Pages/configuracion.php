<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_cargo') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo_id = intval($_POST['tipo_personal_id'] ?? 0);
        $desc = trim($_POST['descripcion'] ?? 'Cátedra / Función institucional.');

        if (!empty($nombre) && $tipo_id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES (?, ?, ?)");
                $stmt->execute([$tipo_id, $nombre, $desc]);
                registrarHistorial($pdo, 'Catálogos', 'CREAR', "Creó el cargo: $nombre", $pdo->lastInsertId());
                $pdo->commit();
                header("Location: configuracion.php?msg=cargo_creado#cargos"); exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error DB: " . $e->getMessage());
            }
        }
    }

    if ($_POST['accion'] === 'eliminar_cargo') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM cat_cargos_areas WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Catálogos', 'ELIMINAR', 'Se eliminó un cargo del catálogo.', $_POST['id']);
            $pdo->commit();
            header("Location: configuracion.php?msg=cargo_eliminado#cargos"); exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == 23000) { header("Location: configuracion.php?msg=cargo_en_uso#cargos"); exit; }
            else { die("Error: " . $e->getMessage()); }
        }
    }
}

$cargos = $pdo->query("
    SELECT c.*, t.nombre as tipo,
           (SELECT COUNT(*) FROM personal_cargos pc WHERE pc.cargo_area_id = c.id AND pc.estado_cargo = 'Vigente') as total_asignados
    FROM cat_cargos_areas c 
    JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id 
    ORDER BY t.id ASC, c.nombre ASC
")->fetchAll();

$tipos_personal = $pdo->query("SELECT * FROM cat_tipos_personal ORDER BY id ASC")->fetchAll();
$personal_lista = $pdo->query("SELECT id, cedula, CONCAT(apellidos, ', ', nombres) as nombre FROM personal WHERE estado_laboral='Activo' ORDER BY apellidos ASC")->fetchAll();

include '../Includes/header.php'; 
include '../Includes/sidebar.php';
?>

<style>
.collapse-toggle-btn { cursor: pointer; user-select: none; transition: background-color 0.2s ease; }
.collapse-toggle-btn:hover { background-color: rgba(var(--bs-primary-rgb), 0.05); }
.chevron-rot { transition: transform 0.25s ease; }
.collapse-toggle-btn[aria-expanded="true"] .chevron-rot { transform: rotate(180deg); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-gear-fill me-2 text-primary"></i> Ajustes y Mantenimiento</h2>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php 
    $alertas = [
        'cargo_creado'    => ['class' => 'alert-success', 'text' => 'Cargo registrado correctamente.'],
        'cargo_eliminado' => ['class' => 'alert-success', 'text' => 'Cargo eliminado con éxito.'],
        'cargo_en_uso'    => ['class' => 'alert-danger',  'text' => 'No se puede eliminar: Personal asignado activo.'],
        'import_csv_ok'   => ['class' => 'alert-success', 'text' => 'Nómina importada masivamente desde Excel (.csv).'],
        'import_doc_ok'   => ['class' => 'alert-success', 'text' => 'Archivo guardado en el expediente del trabajador.'],
        'import_json_ok'  => ['class' => 'alert-success', 'text' => 'Copia de seguridad restaurada correctamente.'],
        'import_err'      => ['class' => 'alert-danger',  'text' => 'Error al procesar el archivo. Verifique la estructura del documento.']
    ];
    $m = $_GET['msg'];
    if (isset($alertas[$m])): ?>
        <div class="alert <?= $alertas[$m]['class'] ?> alert-dismissible fade show shadow-sm">
            <i class="bi bi-info-circle-fill me-2"></i> <?= $alertas[$m]['text'] ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<ul class="nav nav-pills mb-4 gap-2" id="ajustesTab" role="tablist">
    <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#apariencia"><i class="bi bi-palette me-1"></i> Apariencia</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#cargos"><i class="bi bi-tags me-1"></i> Cargos y Materias</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#exportar"><i class="bi bi-cloud-arrow-down me-1"></i> Exportar</button></li>
    <li class="nav-item"><button class="nav-link fw-bold text-success" data-bs-toggle="pill" data-bs-target="#importar"><i class="bi bi-cloud-arrow-up me-1"></i> Importar y Respaldos</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#atajos"><i class="bi bi-keyboard me-1"></i> Atajos</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tutorial"><i class="bi bi-compass me-1"></i> Tutorial</button></li>
</ul>

<div class="tab-content" id="ajustesTabContent">
    
    <!-- PESTAÑA 1: APARIENCIA COMPLETA -->
    <div class="tab-pane fade show active" id="apariencia">
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 border-start border-4 border-primary overflow-hidden">
                    <div class="card-header bg-transparent border-0 p-3 d-flex justify-content-between align-items-center collapse-toggle-btn" data-bs-toggle="collapse" data-bs-target="#collapsePresets" aria-expanded="false">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-stars fs-5 text-primary"></i>
                            <div><span class="fw-bold d-block lh-1">Temas Rápidos (1 Clic)</span><small class="text-muted" style="font-size: 0.74rem;">Perfiles visuales listos para usar</small></div>
                        </div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-primary-subtle text-primary rounded-pill">5 temas</span><i class="bi bi-chevron-down chevron-rot text-muted"></i></div>
                    </div>
                    <div id="collapsePresets" class="collapse">
                        <div class="card-body pt-0 pb-3 px-3 border-top">
                            <div class="d-flex flex-wrap gap-2 pt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3" onclick="aplicarTemaPreset('clasico')">🏛️ Institucional Clásico</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold rounded-pill px-3" onclick="aplicarTemaPreset('noche')">🌌 Noche Ejecutiva</button>
                                <button type="button" class="btn btn-sm btn-outline-success fw-bold rounded-pill px-3" onclick="aplicarTemaPreset('esmeralda')">🌿 Bosque Esmeralda</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold rounded-pill px-3" onclick="aplicarTemaPreset('papel')">📄 Minimalista 2D</button>
                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3" onclick="aplicarTemaPreset('carmesi')">🎓 Carmesí Académico</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 border-start border-4 border-info overflow-hidden">
                    <div class="card-header bg-transparent border-0 p-3 d-flex justify-content-between align-items-center collapse-toggle-btn" data-bs-toggle="collapse" data-bs-target="#collapseWallpaper" aria-expanded="false">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-image fs-5 text-info"></i>
                            <div><span class="fw-bold d-block lh-1">Fondo de Pantalla</span><small class="text-muted" style="font-size: 0.74rem;">Imagen desde tu computadora</small></div>
                        </div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-info-subtle text-info-emphasis rounded-pill d-none" id="badgeWallpaperActivo">Activo</span><i class="bi bi-chevron-down chevron-rot text-muted"></i></div>
                    </div>
                    <div id="collapseWallpaper" class="collapse">
                        <div class="card-body pt-0 pb-3 px-3 border-top">
                            <div class="row g-2 align-items-end pt-3 mb-3">
                                <div class="col-sm-9">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-upload me-1"></i>Elegir imagen desde esta computadora:</label>
                                    <input type="file" id="bgImageInput" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="col-sm-3">
                                    <button id="resetBgBtn" class="btn btn-outline-danger btn-sm w-100 fw-bold" onclick="quitarFondoCompleto()"><i class="bi bi-trash me-1"></i>Quitar</button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3 pt-2 border-top">
                                <label class="small fw-bold text-muted text-nowrap mb-0"><i class="bi bi-brightness-low me-1"></i>Oscurecimiento:</label>
                                <input type="range" class="form-range flex-grow-1" id="rangeWallpaperDim" min="0" max="80" step="5" value="35">
                                <span class="badge bg-secondary-subtle text-body border" id="lblWallpaperDim">35%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- TEMA VISUAL -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-info">
                    <div class="card-body text-center py-4 d-flex flex-column justify-content-center align-items-center">
                        <div class="theme-icon-stage mb-2"><i class="bi bi-sun-fill theme-icon-sun"></i><i class="bi bi-moon-stars-fill theme-icon-moon"></i></div>
                        <h5 class="fw-bold mb-1">Tema Visual</h5>
                        <small class="text-muted mb-3">Transición Claro / Oscuro</small>
                        <div class="form-check form-switch d-flex justify-content-center fs-5">
                            <input class="form-check-input me-2" type="checkbox" role="switch" id="switchModoOscuro" style="cursor:pointer;">
                            <label class="form-check-label fw-medium" for="switchModoOscuro" id="lblModoOscuroEstado">Modo Claro</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLOR DEL MENÚ -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-primary">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-palette-fill fs-1 mb-2 text-primary"></i>
                        <h5 class="fw-bold mb-2">Color del Menú</h5>
                        <select id="selectMenuColor" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold mb-3">
                            <option value="default">🔵 Azul Corporativo</option><option value="dark">⚫ Negro Carbón</option>
                            <option value="success">🟢 Verde Esmeralda</option><option value="danger">🔴 Rojo Carmesí</option>
                            <option value="custom">🎨 Color Personalizado...</option>
                        </select>
                        <div class="d-flex align-items-center justify-content-center">
                            <label class="small text-muted me-2 fw-bold">Selector Libre:</label>
                            <input type="color" id="customColorPicker" class="form-control form-control-color p-1 shadow-sm" style="height: 35px; width: 60px; cursor: pointer;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLOR DE ACENTO -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-info">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-droplet-half fs-1 mb-2 text-info"></i>
                        <h5 class="fw-bold mb-2">Color de Acento</h5>
                        <select id="selectAccentColor" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold mb-3">
                            <option value="default">🔵 Azul Institucional</option><option value="indigo">🟣 Índigo Ejecutivo</option>
                            <option value="emerald">🟢 Verde Esmeralda</option><option value="violet">🟪 Violeta Moderno</option>
                            <option value="amber">🟠 Ámbar / Dorado</option><option value="crimson">🔴 Rojo Carmesí</option>
                            <option value="teal">🩵 Turquesa / Teal</option><option value="custom">🎨 Color Personalizado...</option>
                        </select>
                        <div class="d-flex align-items-center justify-content-center">
                            <label class="small text-muted me-2 fw-bold">Selector Libre:</label>
                            <input type="color" id="customAccentPicker" class="form-control form-control-color p-1 shadow-sm" value="#0d6efd" style="height: 35px; width: 60px; cursor: pointer;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ESTILO DE CONTENEDORES -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-warning">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-layers-fill fs-1 mb-2 text-warning"></i>
                        <h5 class="fw-bold mb-1">Estilo de Contenedores</h5>
                        <small class="text-muted d-block mb-3">Profundidad de las tarjetas</small>
                        <div class="btn-group w-100 shadow-sm" role="group">
                            <input type="radio" class="btn-check" name="cardStyle" id="styleShadow" value="shadow" autocomplete="off"><label class="btn btn-outline-secondary btn-sm fw-bold" for="styleShadow">Relieve 3D</label>
                            <input type="radio" class="btn-check" name="cardStyle" id="styleFlat" value="flat" autocomplete="off"><label class="btn btn-outline-secondary btn-sm fw-bold" for="styleFlat">Plano 2D</label>
                            <input type="radio" class="btn-check" name="cardStyle" id="styleGlass" value="glass" autocomplete="off"><label class="btn btn-outline-secondary btn-sm fw-bold" for="styleGlass">Cristal</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FONDO AMBIENTAL -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-primary">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-grid-3x3-gap-fill fs-1 mb-2 text-primary"></i>
                        <h5 class="fw-bold mb-1">Fondo Ambiental</h5>
                        <small class="text-muted d-block mb-3">Fondo decorativo del sistema</small>
                        <select id="selectBgPattern" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="solid">Sólido Estándar</option><option value="gradient">Degradado Suave</option>
                            <option value="dots">Malla de Puntos</option><option value="grid">Cuadrícula Suave</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- FORMA DE BOTONES -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-success">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-toggle-on fs-1 mb-2 text-success"></i>
                        <h5 class="fw-bold mb-1">Forma de Botones</h5>
                        <small class="text-muted d-block mb-3">Estilo global de botones</small>
                        <select id="selectBtnStyle" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="default">Estándar (Mixto)</option><option value="pill">Cápsula (Muy Redondeados)</option><option value="square">Ejecutivo (Cuadrados)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- BARRA SUPERIOR -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-info">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-window-dock fs-1 mb-2 text-info"></i>
                        <h5 class="fw-bold mb-1">Barra Superior</h5>
                        <small class="text-muted d-block mb-3">Estilo de la barra superior</small>
                        <select id="selectNavbarStyle" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="solid">Sólida Estándar</option><option value="accent">Teñida con Acento</option><option value="glass">Cristal Difuminado</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- INDICADOR DEL MENÚ -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-warning">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-layout-sidebar-inset fs-1 mb-2 text-warning"></i>
                        <h5 class="fw-bold mb-1">Indicador del Menú</h5>
                        <small class="text-muted d-block mb-3">Resaltado de sección actual</small>
                        <select id="selectSidebarLinkStyle" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="pill">Pastilla Lateral (Clásico)</option><option value="block">Bloque Completo</option><option value="minimal">Borde Minimalista</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- FUENTE DEL SISTEMA -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-success">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-type fs-1 mb-2 text-success"></i>
                        <h5 class="fw-bold mb-1">Fuente del Sistema</h5>
                        <small class="text-muted d-block mb-3">Tipo de letra</small>
                        <select id="selectFontFamily" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="default">Estándar</option><option value="Georgia, serif">Clásica (Georgia)</option>
                            <option value="Tahoma, sans-serif">Compacta (Tahoma)</option><option value="'Courier New', Courier, monospace">Terminal (Courier)</option>
                            <option value="'Trebuchet MS', sans-serif">Moderna (Trebuchet)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- REDONDEADO DE BORDES (RESTAURADO) -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-secondary">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-bounding-box-circles fs-2 me-3 text-secondary"></i>
                            <div><h6 class="fw-bold mb-0">Redondeado de Bordes</h6><small class="text-muted">Curvatura de tarjetas.</small></div>
                        </div>
                        <input type="range" class="form-range" id="rangeBorderRadius" min="1" max="3" step="1">
                        <div class="d-flex justify-content-between small fw-bold text-muted mt-1">
                            <span>Cuadrado</span><span>Estándar</span><span>Muy Suave</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DENSIDAD DE TABLAS (RESTAURADO) -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-secondary">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-table fs-3 me-3 text-secondary"></i>
                            <div><h6 class="fw-bold mb-0">Densidad de Tablas</h6><small class="text-muted">Espacio entre filas.</small></div>
                        </div>
                        <input type="range" class="form-range" id="rangeTableDensity" min="1" max="3" step="1">
                        <div class="d-flex justify-content-between small fw-bold text-muted mt-1">
                            <span>Compacta</span><span>Normal</span><span>Relajada</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAMAÑO DE FUENTE (RESTAURADO) -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-danger">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-fonts fs-3 me-3 text-danger"></i>
                            <div><h6 class="fw-bold mb-0">Tamaño de Fuente</h6><small class="text-muted">Ajusta la lectura global.</small></div>
                        </div>
                        <input type="range" class="form-range" id="rangeFontSize" min="1" max="3" step="1">
                        <div class="d-flex justify-content-between small fw-bold text-muted mt-1">
                            <span>Pequeña</span><span>Estándar</span><span>Grande</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACCESIBILIDAD Y RESTABLECER -->
            <div class="col-12">
                <div class="card shadow-sm border-0 border-start border-4 border-primary bg-body-tertiary">
                    <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <span class="fw-bold text-primary"><i class="bi bi-universal-access me-2"></i>Accesibilidad y Lectura:</span>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="switchReduceMotion" style="cursor:pointer;"><label class="form-check-label small fw-medium" for="switchReduceMotion">Reducir animaciones</label></div>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="switchHighContrast" style="cursor:pointer;"><label class="form-check-label small fw-medium" for="switchHighContrast">Bordes de alto contraste</label></div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold" onclick="restablecerApariencia()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restablecer Apariencia
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PESTAÑA 2: CARGOS -->
    <div class="tab-pane fade" id="cargos">
        <div class="card shadow-sm border-0 mb-4 bg-body-tertiary">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4"><input type="text" id="buscarCargo" class="form-control" placeholder="Buscar por nombre..."></div>
                    <div class="col-md-5">
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="filtrarTipoCargo('todos')">Todos</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Docente')">Docentes</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Administrativo')">Admins</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Obrero')">Obreros</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearCargo">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Cargo
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" id="tablaCargos">
                        <thead class="table-light text-secondary">
                            <tr><th class="ps-4">Cargo / Materia</th><th>Descripción</th><th>Categoría</th><th class="text-center">Personal Asignado</th><th class="text-center">Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cargos as $c): ?>
                            <tr data-categoria="<?= htmlspecialchars($c['tipo']) ?>">
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($c['descripcion'] ?? 'Sin descripción') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($c['tipo']) ?></span></td>
                                <td class="text-center"><span class="badge rounded-pill bg-body-secondary text-body border px-3"><i class="bi bi-people me-1"></i> <?= $c['total_asignados'] ?></span></td>
                                <td class="text-center">
                                    <form action="" method="POST" onsubmit="return confirm('¿Seguro que desea eliminar?');">
                                        <input type="hidden" name="accion" value="eliminar_cargo"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" <?= $c['total_asignados'] > 0 ? 'title="En uso (Bloqueado)"' : '' ?>><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 3: EXPORTAR COMPLETA -->
    <div class="tab-pane fade" id="exportar">
        <div class="card shadow-sm border-0 mb-4 border-top border-4 border-primary">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-cloud-arrow-down me-2 text-primary"></i>Generador de Reportes Oficiales</h5>
                <p class="text-muted small mb-4">Seleccione el grupo de trabajadores y el formato en el que desea descargar el listado.</p>
                
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">1. Filtrar Personal:</label>
                        <select id="filtroExportPersonal" class="form-select form-select-lg shadow-sm">
                            <option value="todos">📋 Todo el Personal (Directorio General)</option>
                            <option value="1">👨‍🏫 Solo Personal Docente</option>
                            <option value="2">🏢 Solo Personal Administrativo</option>
                            <option value="3">🛠️ Solo Personal Obrero</option>
                        </select>
                    </div>
                </div>

                <label class="form-label fw-bold mb-3">2. Selecciona el Formato de Salida:</label>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-success shadow-sm">
                            <i class="bi bi-file-earmark-excel-fill text-success fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Excel (.XLS)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Hoja de cálculo organizada con todos los datos.</p>
                            <button onclick="descargarReporte('excel')" class="btn btn-success btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-danger shadow-sm">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">PDF Imprimible</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Documento formal listo para imprimir con membrete.</p>
                            <button onclick="descargarReporte('pdf')" class="btn btn-danger btn-sm w-100 mt-auto"><i class="bi bi-printer"></i> Abrir PDF</button>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-primary shadow-sm">
                            <i class="bi bi-file-earmark-word-fill text-primary fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Word (.DOC)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Fichas editables para memorándums y trámites.</p>
                            <button onclick="descargarReporte('word')" class="btn btn-primary btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-info shadow-sm">
                            <i class="bi bi-file-earmark-image-fill text-info fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Imagen (JPG)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Imagen lista para enviar o compartir fácilmente.</p>
                            <button onclick="descargarReporte('jpg')" class="btn btn-info btn-sm w-100 mt-auto text-white"><i class="bi bi-camera"></i> Generar JPG</button>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="card text-center p-3 h-100 border-secondary shadow-sm">
                            <i class="bi bi-file-earmark-spreadsheet-fill text-secondary fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Listado Compatible (.CSV)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Archivo ligero compatible con Excel y otros programas.</p>
                            <button onclick="descargarReporte('csv')" class="btn btn-secondary btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="card text-center p-3 h-100 border-warning shadow-sm">
                            <i class="bi bi-filetype-json text-warning fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Copia de Seguridad (.JSON)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Respaldo completo del personal para restaurar datos.</p>
                            <button onclick="descargarReporte('json')" class="btn btn-warning btn-sm w-100 mt-auto fw-bold text-dark"><i class="bi bi-download"></i> Descargar Copia</button>
                        </div>
                    </div>
                    
                    <div class="col-md-12 col-lg-4">
                        <div class="card text-center p-3 h-100 border-secondary shadow-sm">
                            <i class="bi bi-diagram-3-fill fs-1 mb-2 text-primary"></i>
                            <h6 class="fw-bold mb-1">Organigrama</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Estructura jerárquica visual de la institución.</p>
                            <button onclick="window.location.href='organigrama.php'" class="btn btn-outline-primary btn-sm w-100 mt-auto fw-bold"><i class="bi bi-eye"></i> Ir al Editor</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 4: IMPORTAR Y RESPALDOS COMPLETA -->
    <div class="tab-pane fade" id="importar">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100 border-top border-4 border-success">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success"><i class="bi bi-filetype-csv me-2"></i>Importar Listado desde Excel (.CSV)</h5>
                        <p class="small text-muted mb-3">Ideal para cargar listados extensos de trabajadores sin ingresarlos uno a uno.</p>
                        <div class="alert alert-success bg-success-subtle text-success-emphasis border-0 small mb-3">
                            <strong><i class="bi bi-info-circle-fill me-1"></i> Especificaciones del Archivo:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <li>Formato: Guardado como <code>CSV (delimitado por comas)</code>.</li>
                                <li>Orden de Columnas: <code>Cédula, Nombres, Apellidos, ID_Cargo, Fecha_Ingreso</code></li>
                                <li>Ejemplo: <code>31664538, Marcus, Vinicius, 1, 2024-09-15</code></li>
                            </ul>
                        </div>
                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tipo_importacion" value="csv_personal">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Seleccionar Archivo (.csv):</label>
                                <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Cargar Listado</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100 border-top border-4 border-primary">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-primary"><i class="bi bi-folder-plus me-2"></i>Subir Documento a un Trabajador</h5>
                        <p class="small text-muted mb-3">Guarda fotos o archivos escaneados directamente en el expediente de una persona.</p>
                        <div class="alert alert-primary bg-primary-subtle text-primary-emphasis border-0 small mb-3">
                            <strong><i class="bi bi-shield-check me-1"></i> Guardado Organizado:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <li>Formatos permitidos: <code>JPG, PNG, PDF, WEBP</code>.</li>
                                <li>Se almacena en la carpeta <code>documentos/</code> y aparece automáticamente en el Archivo Digital.</li>
                            </ul>
                        </div>
                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tipo_importacion" value="documento_personal">
                            <div class="row g-2 mb-2">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold">Trabajador Destino:</label>
                                    <select name="personal_id" class="form-select form-select-sm" required>
                                        <option value="">Seleccione personal...</option>
                                        <?php foreach ($personal_lista as $p): ?><option value="<?= $p['id'] ?>">V-<?= $p['cedula'] ?> - <?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Tipo de Documento:</label>
                                    <select name="tipo_documento" class="form-select form-select-sm" required>
                                        <option value="CED">Cédula</option><option value="FOT">Foto Carnet</option>
                                        <option value="TIT">Título</option><option value="MED">Cert. Médico</option>
                                        <option value="SOP">Reposo</option><option value="OTR">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Seleccionar Archivo:</label>
                                <input type="file" name="archivo_adjunto" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold btn-sm"><i class="bi bi-upload me-1"></i> Guardar Archivo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm border-0 border-top border-4 border-warning bg-body-tertiary">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1 text-warning-emphasis"><i class="bi bi-database-fill-up me-2 text-warning"></i>Restaurar Copia de Seguridad (.JSON)</h5>
                        <p class="small text-muted mb-3">Recuperación completa de trabajadores junto con todos sus datos del expediente.</p>
                        <div class="alert alert-warning bg-warning-subtle text-warning-emphasis border-0 small mb-3">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Información de Restauración:</strong>
                            Al subir un archivo de respaldo, el sistema restaurará los datos principales del personal, sus cargos, contactos, tallas, cuentas bancarias, turno y datos médicos.
                        </div>
                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
                            <input type="hidden" name="tipo_importacion" value="json_restore">
                            <input type="file" name="archivo_json" class="form-control flex-grow-1" accept=".json" required>
                            <button type="submit" class="btn btn-warning fw-bold text-dark text-nowrap"><i class="bi bi-arrow-repeat me-1"></i> Iniciar Restauración</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 5: ATAJOS -->
    <div class="tab-pane fade" id="atajos">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div><h5 class="fw-bold mb-1"><i class="bi bi-keyboard-fill me-2 text-primary"></i>Asignación de Atajos de Teclado</h5><p class="small text-muted mb-0">Personaliza tus teclas rápidas para navegar y abrir ventanas al instante.</p></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="switchAtajosActivos" checked style="cursor:pointer;"><label class="form-check-label small fw-bold" for="switchAtajosActivos">Atajos Habilitados</label></div>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" onclick="restablecerAtajosDefault()"><i class="bi bi-arrow-counterclockwise me-1"></i> Restablecer</button>
                    </div>
                </div>
                <div class="alert alert-info bg-info-subtle text-info-emphasis border-0 small mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Haz clic en <strong>"Cambiar"</strong> y presiona la combinación deseada (ej: <kbd>Alt</kbd>+<kbd>A</kbd>, <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>D</kbd> o <kbd>F2</kbd>–<kbd>F10</kbd>).
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary"><tr><th>Acción del Sistema</th><th>Descripción</th><th class="text-center">Atajo Actual</th><th class="text-end">Personalizar</th></tr></thead>
                        <tbody id="tablaConfigAtajos"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 6: MANUAL Y TUTORIAL -->
    <div class="tab-pane fade" id="tutorial">
        <?php include '../Includes/manual_usuario.php'; ?>
    </div>

</div>

<!-- MODAL CREAR NUEVO CARGO -->
<div class="modal fade" id="modalCrearCargo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Cargo / Materia</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form action="" method="POST">
                <div class="modal-body bg-body-tertiary">
                    <input type="hidden" name="accion" value="crear_cargo">
                    <div class="mb-3"><label class="form-label small fw-bold">Categoría de Personal</label><select name="tipo_personal_id" class="form-select" required><option value="">Seleccione categoría...</option><?php foreach ($tipos_personal as $tp): ?><option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nombre']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Nombre del Cargo o Materia</label><input type="text" name="nombre" class="form-control" placeholder="Ej: Química General" required></div>
                    <div class="mb-2"><label class="form-label small fw-bold">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success w-100 fw-bold">Guardar en Catálogo</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('buscarCargo')?.addEventListener('input', function(e) {
    let term = e.target.value.toLowerCase();
    document.querySelectorAll('#tablaCargos tbody tr').forEach(tr => { tr.style.display = tr.innerText.toLowerCase().includes(term) ? '' : 'none'; });
});
function filtrarTipoCargo(tipo) {
    document.querySelectorAll('#tablaCargos tbody tr').forEach(tr => { tr.style.display = (tipo === 'todos' || tr.getAttribute('data-categoria') === tipo) ? '' : 'none'; });
}
function descargarReporte(formato) {
    let tipo = document.getElementById('filtroExportPersonal').value;
    let url = `../PHP/exportar.php?formato=${formato}&tipo=${tipo}`;
    if (formato === 'pdf' || formato === 'jpg') window.open(url, '_blank');
    else window.location.href = url;
}
document.addEventListener("DOMContentLoaded", () => {
    const bgActivo = localStorage.getItem('customWallpaper');
    const badgeWp = document.getElementById('badgeWallpaperActivo');
    if (badgeWp && bgActivo && bgActivo.trim() !== '') badgeWp.classList.remove('d-none');

    if (window.location.hash) {
        const trigger = document.querySelector(`button[data-bs-target="${window.location.hash}"]`);
        if (trigger && typeof bootstrap !== 'undefined') new bootstrap.Tab(trigger).show();
    }
    if (typeof renderizarTablaAtajos === 'function') renderizarTablaAtajos();
});
</script>

<?php include '../Includes/footer.php'; ?>