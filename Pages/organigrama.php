<?php
require_once '../PHP/connection.php';

// 1. AUTO-CREAR TABLA RELACIONAL SI NO EXISTE
$pdo->exec("CREATE TABLE IF NOT EXISTS organigrama_jerarquia (
    personal_id INT PRIMARY KEY,
    jefe_id INT NULL,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_id) REFERENCES personal(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. PROCESAMIENTO AJAX DEL GUARDADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nodos'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $nodos = json_decode($_POST['nodos'], true);
        if (!is_array($nodos)) throw new Exception("Formato de nodos inválido.");
        
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM organigrama_jerarquia"); 
        
        if (count($nodos) > 0) {
            $stmtCheck = $pdo->query("SELECT id FROM personal");
            $personalExistente = array_flip($stmtCheck->fetchAll(PDO::FETCH_COLUMN, 0));
            $stmt = $pdo->prepare("INSERT INTO organigrama_jerarquia (personal_id, jefe_id) VALUES (?, ?)");
            
            $guardados = 0;
            foreach ($nodos as $n) {
                $emp_id = intval($n['id'] ?? 0);
                $jefe_id = (!empty($n['pid'])) ? intval($n['pid']) : null;
                
                if (isset($personalExistente[$emp_id])) {
                    if ($jefe_id !== null && !isset($personalExistente[$jefe_id])) $jefe_id = null;
                    if ($emp_id === $jefe_id) $jefe_id = null;
                    
                    $stmt->execute([$emp_id, $jefe_id]);
                    $guardados++;
                }
            }
        }
        $pdo->commit();
        registrarHistorial($pdo, 'Organigrama', 'ACTUALIZAR', "Jerarquía guardada ($guardados trabajadores).");
        echo json_encode(['success' => true, 'total' => $guardados]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. CARGAR ÁRBOL ACTUAL
$queryArbol = $pdo->query("
    SELECT o.personal_id as id, o.jefe_id as pid, 
           p.cedula, p.nombres, p.apellidos, c.nombre as cargo, c.tipo_personal_id,
           pda.turno, pda.correo_electronico, p.fecha_ingreso, p.estado_laboral,
           pda.estado_civil, pda.nivel_academico, pda.tipo_sangre, pda.banco_nomina, pda.direccion_habitacion,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as foto
    FROM organigrama_jerarquia o
    JOIN personal p ON o.personal_id = p.id
    LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
    LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
")->fetchAll(PDO::FETCH_ASSOC);

$data_tree = [];
foreach ($queryArbol as $row) {
    $iniciales = mb_strtoupper(mb_substr($row['nombres'], 0, 1, 'UTF-8') . mb_substr($row['apellidos'], 0, 1, 'UTF-8'), 'UTF-8');
    $color = '0d6efd'; $textoColor = 'ffffff';
    if ($row['tipo_personal_id'] == 2) $color = '198754';
    if ($row['tipo_personal_id'] == 3) { $color = 'ffc107'; $textoColor = '000000'; }
    
    $svgAvatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23$color'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' fill='%23$textoColor' font-size='40' font-family='Arial' font-weight='bold'>$iniciales</text></svg>";
    $img = !empty($row['foto']) ? $row['foto'] : $svgAvatar;
    
    $data_tree[] = [
        'id' => (int)$row['id'],
        'pid' => $row['pid'] ? (int)$row['pid'] : null,
        'name' => htmlspecialchars($row['apellidos'] . ', ' . $row['nombres'], ENT_QUOTES, 'UTF-8'),
        'title' => htmlspecialchars($row['cargo'] ?? 'Sin cargo', ENT_QUOTES, 'UTF-8'),
        'img' => $img,
        'tipo_personal' => $row['tipo_personal_id'],
        'cedula' => 'V-' . $row['cedula'],
        'telefono' => htmlspecialchars($row['telefono'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'correo' => htmlspecialchars($row['correo_electronico'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'ingreso' => date('d/m/Y', strtotime($row['fecha_ingreso'])),
        'estado' => $row['estado_laboral'],
        'estado_civil' => htmlspecialchars($row['estado_civil'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'nivel_academico' => htmlspecialchars($row['nivel_academico'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'tipo_sangre' => htmlspecialchars($row['tipo_sangre'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'banco' => htmlspecialchars($row['banco_nomina'] ?? 'S/R', ENT_QUOTES, 'UTF-8'),
        'direccion' => htmlspecialchars($row['direccion_habitacion'] ?? 'S/R', ENT_QUOTES, 'UTF-8')
    ];
}
$jerarquia_actual = json_encode($data_tree, JSON_UNESCAPED_UNICODE);

// 4. CARGAR PERSONAL PARA AÑADIR
$empleados = $pdo->query("
    SELECT p.id, p.nombres, p.apellidos, c.nombre as cargo, c.tipo_personal_id,
           p.cedula, p.fecha_ingreso, p.estado_laboral,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as foto
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    WHERE p.estado_laboral = 'Activo'
    ORDER BY p.apellidos ASC
")->fetchAll(PDO::FETCH_ASSOC);
$personal_json = json_encode($empleados, JSON_UNESCAPED_UNICODE);

include '../Includes/header.php'; 
include '../Includes/sidebar.php';
?>

<!-- Librería Oficial OrgChart.js Local -->
<script src="../JS/OrgChart.js"></script>

<style>
    #tree-container { width: 100%; height: 750px; background-color: var(--bs-body-tertiary); border-radius: 1rem; border: 1px solid var(--bs-border-color); position: relative; overflow: hidden;}
    #tree { width: 100%; height: 100%; }
    
    /* Adaptación al modo oscuro */
    [data-bs-theme="dark"] .boc-light rect { fill: #212529 !important; stroke: #495057 !important; }
    [data-bs-theme="dark"] .boc-light text { fill: #ffffff !important; }
    [data-bs-theme="dark"] .node rect { fill: #212529 !important; }
    [data-bs-theme="dark"] .node text { fill: #ffffff !important; }
    [data-bs-theme="dark"] .boc-search { background-color: #212529 !important; border-color: #495057 !important; color: white !important; }
    [data-bs-theme="dark"] .boc-search input { color: white !important; }
    
    /* MODOS PERSONALIZADOS EN PANTALLA */
    #tree.hide-avatars image { display: none !important; }
    #tree.mono-mode .node rect { fill: #ffffff !important; stroke: #000000 !important; stroke-width: 2px !important; }
    #tree.mono-mode .node text { fill: #000000 !important; }
    #tree.mono-mode .link { stroke: #000000 !important; stroke-width: 2px !important; }

    /* CONTENEDOR EXCLUSIVO PARA IMPRESIÓN / PDF (BLINDADO) */
    #print-container { display: none; }
    @media print {
        #wrapper, .modal, .modal-backdrop, #bulk-toolbar { display: none !important; }
        #print-container {
            display: flex !important;
            justify-content: center;
            align-items: center;
            width: 100vw;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: white !important;
            z-index: 999999;
        }
        @page { size: landscape; margin: 5mm; }
    }

    .boc-edit-form, .edit-form, div[data-balkan-orgchart-edit-form], .boc-edit-button, .boc-add-button, .node-menu { display: none !important; }
    .modal-header { cursor: move; }

    /* ANIMACIÓN DEL BOTÓN DE GUARDAR */
    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 12px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .pulse-warning { animation: pulse-red 1.5s infinite; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i> Organigrama Institucional</h2>
        <p class="text-muted small mb-0">Rueda del ratón para Zoom. Arrastre para mover. Clic en empleado para opciones.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-outline-danger rounded-pill fw-bold shadow-sm" onclick="vaciarArbol()" title="Vaciar organigrama"><i class="bi bi-trash"></i></button>
        <button class="btn btn-outline-secondary rounded-pill fw-bold shadow-sm" onclick="abrirModalExportacion()"><i class="bi bi-sliders me-1"></i> Vista y Exportar</button>
        <button class="btn btn-success shadow-sm rounded-pill px-4 fw-bold" onclick="abrirModalAgregar()"><i class="bi bi-person-plus-fill me-1"></i> Añadir Personal</button>
        <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" onclick="guardarOrganigrama()" id="btnGuardar"><i class="bi bi-save me-1"></i> Guardar Cambios</button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-0">
        <div id="tree-container"><div id="tree"></div></div>
    </div>
</div>

<!-- MODAL: CONFIGURACIÓN VISUAL Y EXPORTACIÓN -->
<div class="modal fade" id="modalExportacion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Apariencia y Descarga</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary p-4">
                
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-palette-fill me-2"></i>1. Opciones Visuales</h6>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="form-check form-switch mb-3 fs-6">
                            <input class="form-check-input" type="checkbox" id="chkMonoMode" onchange="aplicarFiltrosVisuales()">
                            <label class="form-check-label fw-medium" for="chkMonoMode">Modo Monocromático (B/N)</label>
                        </div>
                        <div class="form-check form-switch fs-6">
                            <input class="form-check-input" type="checkbox" id="chkHideIcons" onchange="aplicarFiltrosVisuales()">
                            <label class="form-check-label fw-medium" for="chkHideIcons">Ocultar Fotos e Íconos</label>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-success mb-3"><i class="bi bi-distribute-vertical me-2"></i>2. Distribución</h6>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <select id="selectEstructura" class="form-select shadow-sm" onchange="cambiarEstructuraArbol()">
                            <option value="mixed">Mixto (Optimizado para ahorrar espacio)</option>
                            <option value="vertical">Vertical (Clásico, de arriba a abajo)</option>
                            <option value="horizontal">Horizontal (De izquierda a derecha)</option>
                        </select>
                    </div>
                </div>

                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-download me-2"></i>3. Guardar Documento</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary flex-fill fw-bold py-2" onclick="exportarPNG()"><i class="bi bi-filetype-png d-block fs-3 mb-1"></i> Imagen PNG</button>
                    <button class="btn btn-outline-danger flex-fill fw-bold py-2" onclick="exportarPDF()"><i class="bi bi-printer d-block fs-3 mb-1"></i> Imprimir / PDF</button>
                    <button class="btn btn-outline-warning text-dark flex-fill fw-bold py-2" onclick="exportarSVG()"><i class="bi bi-filetype-svg d-block fs-3 mb-1"></i> Vector SVG</button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL OPCIONES NODO -->
<div class="modal fade" id="modalOpcionesNodo" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border border-secondary shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">Opciones del Empleado</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="opcionesNodeId">
                <p class="text-center fw-bold mb-3" id="opcionesNodeName"></p>
                <div class="d-grid gap-2">
                    <button class="btn btn-info text-white fw-bold text-start" onclick="abrirDesdeMenu('detalles')"><i class="bi bi-eye-fill me-2"></i> Ver Detalles Completos</button>
                    <button class="btn btn-warning text-dark fw-bold text-start" onclick="abrirDesdeMenu('editar')"><i class="bi bi-diagram-2-fill me-2"></i> Cambiar de Jefe</button>
                    <button class="btn btn-success fw-bold text-start" onclick="abrirDesdeMenu('redirigir_crud')"><i class="bi bi-pencil-square me-2"></i> Editar en Personal</button>
                    <button class="btn btn-danger fw-bold text-start mt-2" onclick="abrirDesdeMenu('eliminar')"><i class="bi bi-trash-fill me-2"></i> Quitar del Organigrama</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VER DETALLES -->
<div class="modal fade" id="modalVerDetalles" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-info shadow-lg">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard me-2"></i>Ficha Detallada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-body-tertiary">
                <div class="d-flex align-items-center mb-3">
                    <img id="detImg" src="" class="rounded-circle shadow-sm me-3" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #0dcaf0;">
                    <div>
                        <h4 class="fw-bold mb-0" id="detName">Nombre</h4>
                        <span class="badge bg-info text-dark mt-1" id="detTitle">Cargo</span>
                        <span class="badge bg-success mt-1" id="detEstado">Estado</span>
                    </div>
                </div>
                <div class="accordion shadow-sm" id="accordionDetalles">
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button fw-bold py-2" type="button" data-bs-toggle="collapse" data-bs-target="#col1">Datos Principales</button></h2>
                        <div id="col1" class="accordion-collapse collapse show" data-bs-parent="#accordionDetalles">
                            <div class="accordion-body small bg-body">
                                <strong>Cédula:</strong> <span id="detCedula"></span><br>
                                <strong>Teléfono:</strong> <span id="detTelefono"></span><br>
                                <strong>Correo:</strong> <span id="detCorreo"></span><br>
                                <strong>Fecha Ingreso:</strong> <span id="detIngreso"></span>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed fw-bold py-2" type="button" data-bs-toggle="collapse" data-bs-target="#col2">Información Adicional</button></h2>
                        <div id="col2" class="accordion-collapse collapse" data-bs-parent="#accordionDetalles">
                            <div class="accordion-body small bg-body">
                                <strong>Estado Civil:</strong> <span id="detCivil"></span><br>
                                <strong>Académico:</strong> <span id="detAcademico"></span><br>
                                <strong>Dirección:</strong> <span id="detDir"></span><br>
                                <strong>Tipo Sangre:</strong> <span id="detSangre" class="text-danger fw-bold"></span><br>
                                <strong>Banco Nómina:</strong> <span id="detBanco"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-2 border-top">
                <button type="button" class="btn btn-secondary w-100 fw-bold" data-bs-dismiss="modal">Cerrar Ficha</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CAMBIAR JEFE -->
<div class="modal fade" id="modalEditPosition" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border border-warning shadow-lg">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Cambiar Jefe</h6>
                <button type="button" class="btn-close btn-close-modal" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" id="editNodeId">
                <p class="text-center fw-bold mb-3 text-primary" id="editNodeName"></p>
                <label class="form-label fw-bold small text-muted">Nuevo Supervisor:</label>
                <select id="selectNuevoJefe" class="form-select shadow-sm"></select>
            </div>
            <div class="modal-footer p-2 border-0">
                <button type="button" class="btn btn-warning w-100 fw-bold" onclick="confirmarEdicionPosicion()">Aplicar Cambio</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR NODO -->
<div class="modal fade" id="modalAddNode" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-success shadow-lg">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"></i>Agregar al Organigrama</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-primary">1. Seleccionar Trabajador:</label>
                    <select id="selectEmpleado" class="form-select border-primary shadow-sm">
                        <option value="">Seleccione a la persona...</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres'], ENT_QUOTES, 'UTF-8') ?> - (<?= htmlspecialchars($e['cargo'], ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-success">2. Jefe Inmediato:</label>
                    <select id="selectJefeAgregar" class="form-select shadow-sm border-success"></select>
                </div>
            </div>
            <div class="modal-footer p-2 border-0">
                <button type="button" class="btn btn-success w-100 fw-bold" onclick="confirmarAgregar()">Añadir al Árbol</button>
            </div>
        </div>
    </div>
</div>

<script>
    const dataTree = <?= $jerarquia_actual ?>;
    const todosEmpleados = <?= $personal_json ?>;

    OrgChart.SEARCH_PLACEHOLDER = "🔍 Buscar empleado (Nombre, Cargo o Cédula)...";

    let chart = new OrgChart(document.getElementById("tree"), {
        template: "luba",
        layout: OrgChart.mixed, 
        enableSearch: true,
        searchFields: ["name", "title", "cedula"],
        enableDragDrop: true,
        mouseScrool: OrgChart.action.zoom,
        toolbar: { zoom: true, fit: true },
        nodeMenu: null, 
        nodeBinding: { field_0: "name", field_1: "title", img_0: "img" },
        nodes: dataTree
    });

    // ==========================================
    // SISTEMA DE DETECCIÓN DE CAMBIOS
    // ==========================================
    let tieneCambiosSinGuardar = false;

    function marcarComoModificado() {
        tieneCambiosSinGuardar = true;
        let btn = document.getElementById('btnGuardar');
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-danger', 'pulse-warning');
        btn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> ¡Guardar Cambios!';
    }

    function marcarComoGuardado() {
        tieneCambiosSinGuardar = false;
        let btn = document.getElementById('btnGuardar');
        btn.classList.remove('btn-danger', 'pulse-warning');
        btn.classList.add('btn-primary');
        btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
    }

    chart.onDrop(function () { marcarComoModificado(); });

    window.addEventListener('beforeunload', function (e) {
        if (tieneCambiosSinGuardar) {
            e.preventDefault();
            e.returnValue = 'Tienes cambios en el organigrama sin guardar. ¿Seguro que quieres salir?';
        }
    });

    // ==========================================
    // EXPORTACIÓN 100% NATIVA Y OFFLINE (REPARADA)
    // ==========================================
    function abrirModalExportacion() {
        new bootstrap.Modal(document.getElementById('modalExportacion')).show();
    }

    function aplicarFiltrosVisuales() {
        let treeEl = document.getElementById('tree');
        treeEl.classList.toggle('mono-mode', document.getElementById('chkMonoMode').checked);
        treeEl.classList.toggle('hide-avatars', document.getElementById('chkHideIcons').checked);
    }

    function cambiarEstructuraArbol() {
        let formato = document.getElementById('selectEstructura').value;
        if (formato === 'vertical') {
            chart.config.layout = OrgChart.normal;
            chart.config.orientation = OrgChart.orientation.top;
        } else if (formato === 'horizontal') {
            chart.config.layout = OrgChart.normal;
            chart.config.orientation = OrgChart.orientation.left;
        } else {
            chart.config.layout = OrgChart.mixed;
            chart.config.orientation = OrgChart.orientation.top;
        }
        chart.draw();
    }

    // CLONA EL SVG E INYECTA LOS FILTROS EN SUS ATRIBUTOS REALES
    function obtenerSvgConFiltros() {
        let svgOriginal = document.querySelector("#tree svg");
        if (!svgOriginal) return null;
        let svgClone = svgOriginal.cloneNode(true);

        let isMono = document.getElementById('chkMonoMode').checked;
        let isHideIcons = document.getElementById('chkHideIcons').checked;

        // 1. Ocultar avatares
        if (isHideIcons) {
            svgClone.querySelectorAll('image').forEach(img => {
                img.setAttribute('display', 'none');
                img.style.display = 'none';
            });
        }

        // 2. Aplicar blanco y negro en atributos reales
        if (isMono) {
            svgClone.querySelectorAll('[data-n-id] rect, .node rect, rect').forEach(rect => {
                rect.setAttribute('fill', '#ffffff');
                rect.setAttribute('stroke', '#000000');
                rect.setAttribute('stroke-width', '2');
            });
            svgClone.querySelectorAll('[data-n-id] text, .node text, text').forEach(text => {
                text.setAttribute('fill', '#000000');
            });
            svgClone.querySelectorAll('path.link, path').forEach(path => {
                path.setAttribute('stroke', '#000000');
                path.setAttribute('stroke-width', '2');
            });
        }

        return svgClone;
    }

    // PROCESADOR A CANVAS
    function generarCanvasOrganigrama(callback) {
        chart.fit();
        setTimeout(() => {
            let svgClone = obtenerSvgConFiltros();
            if (!svgClone) return alert("Error al procesar el organigrama.");

            let svgData = new XMLSerializer().serializeToString(svgClone);
            if (!svgData.match(/^<svg[^>]+xmlns="http\:\/\/www\.w3\.org\/2000\/svg"/)) {
                svgData = svgData.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
            }

            let canvas = document.createElement("canvas");
            let ctx = canvas.getContext("2d");
            let img = new Image();

            let svgOrig = document.querySelector("#tree svg");
            let width = svgOrig.clientWidth || 1600;
            let height = svgOrig.clientHeight || 1000;

            canvas.width = width * 2;
            canvas.height = height * 2;
            ctx.scale(2, 2);

            let blob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
            let url = URL.createObjectURL(blob);

            img.onload = function () {
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);
                URL.revokeObjectURL(url);
                callback(canvas);
            };
            img.onerror = function() {
                URL.revokeObjectURL(url);
                alert("Error al convertir el organigrama en imagen.");
            };
            img.src = url;
        }, 300);
    }

    // 1. Exportar SVG con filtros aplicados
    function exportarSVG() {
        bootstrap.Modal.getInstance(document.getElementById('modalExportacion'))?.hide();
        chart.fit();
        setTimeout(() => {
            let svgClone = obtenerSvgConFiltros();
            if (!svgClone) return alert("Error capturando el vector.");
            let source = new XMLSerializer().serializeToString(svgClone);
            if (!source.match(/^<svg[^>]+xmlns="http\:\/\/www\.w3\.org\/2000\/svg"/)) {
                source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
            }
            let blob = new Blob([source], { type: "image/svg+xml;charset=utf-8" });
            let url = URL.createObjectURL(blob);
            let a = document.createElement("a");
            a.href = url;
            a.download = "Organigrama_Luis_Urdaneta.svg";
            a.click();
            URL.revokeObjectURL(url);
        }, 300);
    }

    // 2. Exportar PNG directo (con Monocromo y Sin Iconos)
    function exportarPNG() {
        bootstrap.Modal.getInstance(document.getElementById('modalExportacion'))?.hide();
        generarCanvasOrganigrama(function (canvas) {
            let a = document.createElement("a");
            a.download = "Organigrama_Luis_Urdaneta.png";
            a.href = canvas.toDataURL("image/png");
            a.click();
        });
    }

    // 3. Imprimir / Guardar como PDF oficial (Sin abrir pestañas raras)
    function exportarPDF() {
        bootstrap.Modal.getInstance(document.getElementById('modalExportacion'))?.hide();
        generarCanvasOrganigrama(function (canvas) {
            let imgData = canvas.toDataURL("image/png");
            let printContainer = document.getElementById('print-container');
            if (!printContainer) {
                printContainer = document.createElement('div');
                printContainer.id = 'print-container';
                document.body.appendChild(printContainer);
            }
            printContainer.innerHTML = `<img src="${imgData}" style="max-width: 100%; max-height: 95vh; object-fit: contain;">`;
            
            setTimeout(() => {
                window.print();
            }, 300);
        });
    }

    // ==========================================
    // RESTO DE FUNCIONES DEL CRUD
    // ==========================================
    chart.editUI.on('show', function (sender, nodeId) {
        let nodeData = chart.get(nodeId);
        document.getElementById('opcionesNodeId').value = nodeId;
        document.getElementById('opcionesNodeName').innerText = nodeData.name;
        new bootstrap.Modal(document.getElementById('modalOpcionesNodo')).show();
        return false; 
    });

    function abrirDesdeMenu(accion) {
        bootstrap.Modal.getInstance(document.getElementById('modalOpcionesNodo')).hide();
        let id = document.getElementById('opcionesNodeId').value;
        let node = chart.get(id);

        setTimeout(() => {
            if (accion === 'detalles') {
                document.getElementById('detImg').src = node.img;
                document.getElementById('detName').innerText = node.name;
                document.getElementById('detTitle').innerText = node.title;
                document.getElementById('detCedula').innerText = node.cedula;
                document.getElementById('detTelefono').innerText = node.telefono;
                document.getElementById('detCorreo').innerText = node.correo;
                document.getElementById('detIngreso').innerText = node.ingreso;
                document.getElementById('detEstado').innerText = node.estado;
                document.getElementById('detCivil').innerText = node.estado_civil || 'S/R';
                document.getElementById('detAcademico').innerText = node.nivel_academico || 'S/R';
                document.getElementById('detDir').innerText = node.direccion || 'S/R';
                document.getElementById('detSangre').innerText = node.tipo_sangre || 'S/R';
                document.getElementById('detBanco').innerText = node.banco || 'S/R';
                new bootstrap.Modal(document.getElementById('modalVerDetalles')).show();
            } 
            else if (accion === 'editar') {
                document.getElementById('editNodeId').value = node.id;
                document.getElementById('editNodeName').innerText = node.name;

                let selectJefe = document.getElementById('selectNuevoJefe');
                selectJefe.innerHTML = '<option value="">Ninguno (Máxima Autoridad)</option>';
                
                for (let j_id in chart.nodes) {
                    let n = chart.nodes[j_id];
                    if (n && n.id && n.id != id) {
                        let nodeObj = chart.get(n.id);
                        if (nodeObj) {
                            let option = new Option(nodeObj.name + ' (' + nodeObj.title + ')', nodeObj.id);
                            if (node.pid == nodeObj.id) option.selected = true;
                            selectJefe.add(option);
                        }
                    }
                }
                new bootstrap.Modal(document.getElementById('modalEditPosition')).show();
            } 
            else if (accion === 'redirigir_crud') {
                window.location.href = `personal.php?tipo=${node.tipo_personal}&auto_edit=${node.id}`;
            }
            else if (accion === 'eliminar') {
                if(confirm("¿Seguro que desea quitar a " + node.name + " del esquema gráfico?")) {
                    chart.removeNode(id);
                    marcarComoModificado();
                }
            }
        }, 350); 
    }

    function abrirModalAgregar() {
        let selectJefe = document.getElementById('selectJefeAgregar');
        selectJefe.innerHTML = '<option value="">Ninguno (Será Nodo Raíz / Director)</option>';
        for (let id in chart.nodes) {
            let n = chart.nodes[id];
            if (n && n.id) {
                let nodeObj = chart.get(n.id);
                if (nodeObj) selectJefe.add(new Option(nodeObj.name + ' (' + nodeObj.title + ')', nodeObj.id));
            }
        }
        document.getElementById('selectEmpleado').value = ""; 
        new bootstrap.Modal(document.getElementById('modalAddNode')).show();
    }

    function confirmarAgregar() {
        let empId = document.getElementById('selectEmpleado').value;
        let pid = document.getElementById('selectJefeAgregar').value;
        if(pid === "") pid = null;

        if(!empId) { alert('Seleccione un empleado.'); return; }
        if(chart.nodes[empId]) { alert('¡Esta persona ya está en el organigrama!'); return; }

        let empData = todosEmpleados.find(e => e.id == empId);
        if(empData) {
            let iniciales = (empData.nombres.charAt(0) + empData.apellidos.charAt(0)).toUpperCase();
            let color = '0d6efd'; let textoColor = 'ffffff';
            if (empData.tipo_personal_id == 2) color = '198754';
            if (empData.tipo_personal_id == 3) { color = 'ffc107'; textoColor = '000000'; }
            let svgAvatar = `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23${color}'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' fill='%23${textoColor}' font-size='40' font-family='Arial' font-weight='bold'>${iniciales}</text></svg>`;

            chart.addNode({
                id: parseInt(empData.id),
                pid: pid ? parseInt(pid) : null,
                name: empData.apellidos + ', ' + empData.nombres,
                title: empData.cargo,
                img: empData.foto ? empData.foto : svgAvatar,
                tipo_personal: empData.tipo_personal_id,
                cedula: 'V-' + empData.cedula,
                telefono: empData.telefono || 'S/R',
                correo: empData.correo_electronico || 'S/R',
                ingreso: empData.fecha_ingreso,
                estado: empData.estado_laboral
            });
            marcarComoModificado();
            bootstrap.Modal.getInstance(document.getElementById('modalAddNode')).hide();
        }
    }

    function confirmarEdicionPosicion() {
        let id = document.getElementById('editNodeId').value;
        let pid = document.getElementById('selectNuevoJefe').value;
        if (pid === "") pid = null;
        
        let nodeData = chart.get(id);
        nodeData.pid = pid ? parseInt(pid) : null;
        chart.updateNode(nodeData);
        
        marcarComoModificado();
        bootstrap.Modal.getInstance(document.getElementById('modalEditPosition')).hide();
    }

    function vaciarArbol() {
        if(confirm('¿Seguro que desea limpiar todo el esquema visual? (Recuerde guardar para sincronizar).')) {
            chart.load([]); 
            marcarComoModificado();
        }
    }

    async function guardarOrganigrama() {
        let btn = document.getElementById('btnGuardar');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        btn.disabled = true;

        try {
            let nodosExtraidos = [];
            for (let id in chart.nodes) {
                let n = chart.nodes[id];
                if (n && n.id !== undefined && n.id !== null) {
                    let empId = parseInt(n.id);
                    let jefeId = (n.pid !== undefined && n.pid !== null && n.pid !== "" && n.pid !== 0) ? parseInt(n.pid) : null;
                    if (!isNaN(empId)) nodosExtraidos.push({ id: empId, pid: (!isNaN(jefeId) && jefeId !== empId) ? jefeId : null });
                }
            }

            let formData = new FormData();
            formData.append('nodos', JSON.stringify(nodosExtraidos));

            let response = await fetch('organigrama.php', { method: 'POST', body: formData });
            let result = await response.json();

            if (result.success) {
                marcarComoGuardado();
            } else {
                alert("Error al guardar: " + result.error);
                btn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> ¡Guardar Cambios!';
            }
        } catch (error) {
            alert("Error de conexión al guardar los datos.");
            btn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> ¡Guardar Cambios!';
        } finally {
            btn.disabled = false;
        }
    }
</script>

<?php include '../Includes/footer.php'; ?>