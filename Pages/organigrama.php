<?php
require_once '../PHP/connection.php';

// 1. AUTO-CREAR TABLA RELACIONAL
$pdo->exec("CREATE TABLE IF NOT EXISTS organigrama_jerarquia (
    personal_id INT PRIMARY KEY,
    jefe_id INT NULL,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_id) REFERENCES personal(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. PROCESAR GUARDADO AJAX DEL ÁRBOL COMPLETO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nodos'])) {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $nodos = json_decode($_POST['nodos'], true);
        
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM organigrama_jerarquia"); 
        
        if (is_array($nodos) && count($nodos) > 0) {
            $stmt = $pdo->prepare("INSERT INTO organigrama_jerarquia (personal_id, jefe_id) VALUES (?, ?)");
            foreach ($nodos as $n) {
                $emp_id = intval($n['id']);
                $jefe_id = !empty($n['pid']) ? intval($n['pid']) : null;
                $stmt->execute([$emp_id, $jefe_id]);
            }
        }
        $pdo->commit();
        
        registrarHistorial($pdo, 'Organigrama', 'ACTUALIZAR', "Actualizó gráficamente la jerarquía institucional.");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. CARGAR ÁRBOL ACTUAL (Con datos extra para la vista de "Detalles")
$queryArbol = $pdo->query("
    SELECT o.personal_id as id, o.jefe_id as pid, 
           p.cedula, p.nombres, p.apellidos, c.nombre as cargo,
           pda.turno, pda.correo_electronico, p.fecha_ingreso, p.estado_laboral,
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
    $img = $row['foto'] ? $row['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    $data_tree[] = [
        'id' => $row['id'],
        'pid' => $row['pid'],
        'name' => $row['apellidos'] . ', ' . $row['nombres'],
        'title' => $row['cargo'],
        'img' => $img,
        // Datos extra para el modal de lectura
        'cedula' => $row['cedula'],
        'telefono' => $row['telefono'] ?? 'S/R',
        'correo' => $row['correo_electronico'] ?? 'S/R',
        'ingreso' => date('d/m/Y', strtotime($row['fecha_ingreso'])),
        'estado' => $row['estado_laboral']
    ];
}
$jerarquia_actual = json_encode($data_tree);

// 4. CARGAR PERSONAL ACTIVO PARA AÑADIR NUEVOS
$empleados = $pdo->query("
    SELECT p.id, p.nombres, p.apellidos, c.nombre as cargo,
           p.cedula, p.fecha_ingreso, p.estado_laboral,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as foto,
           pda.correo_electronico
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
    WHERE p.estado_laboral = 'Activo'
    ORDER BY p.apellidos ASC
")->fetchAll(PDO::FETCH_ASSOC);

$personal_json = json_encode($empleados);

include '../Includes/header.php'; 
include '../Includes/sidebar.php';
?>

<!-- Librería Oficial OrgChart.js -->
<script src="https://balkan.app/js/OrgChart.js"></script>

<style>
    /* Contenedor del árbol */
    #tree-container { 
        width: 100%; 
        height: 700px; 
        background-color: var(--bs-body-tertiary); 
        border-radius: 1rem; 
        border: 1px solid var(--bs-border-color);
        position: relative;
        overflow: hidden;
    }
    #tree { width: 100%; height: 100%; }
    
    /* Adaptación al modo oscuro */
    [data-bs-theme="dark"] .boc-light rect { fill: #2b3035 !important; stroke: #495057 !important; }
    [data-bs-theme="dark"] .boc-light text { fill: #f8f9fa !important; }
    
    /* OCULTAR TODOS LOS BOTONES DEFAULT (INGLÉS) DE LA LIBRERÍA */
    .boc-edit-button, .boc-add-button, .node-menu { display: none !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i> Organigrama Institucional</h2>
        <p class="text-muted small mb-0">Use los botones inferiores (+ / -) para Zoom. Arrastre para mover.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger rounded-pill fw-bold shadow-sm" onclick="vaciarArbol()" title="Vaciar panel">
            <i class="bi bi-trash"></i>
        </button>
        <button class="btn btn-success shadow-sm rounded-pill px-4 fw-bold" onclick="abrirModalAgregar()">
            <i class="bi bi-person-plus-fill me-1"></i> Añadir Personal
        </button>
        <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" onclick="guardarOrganigrama()" id="btnGuardar">
            <i class="bi bi-save me-1"></i> Guardar Cambios
        </button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-0">
        <div id="tree-container">
            <div id="tree"></div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 1. MODAL "MENÚ DE OPCIONES" AL HACER CLIC -->
<!-- ========================================== -->
<div class="modal fade" id="modalOpcionesNodo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title fw-bold" id="opcionesNodeName">Opciones</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="opcionesNodeId">
                <div class="d-grid gap-2">
                    <button class="btn btn-info text-white fw-bold text-start" onclick="abrirDesdeMenu('detalles')">
                        <i class="bi bi-eye-fill me-2"></i> Ver Detalles
                    </button>
                    <button class="btn btn-warning text-dark fw-bold text-start" onclick="abrirDesdeMenu('editar')">
                        <i class="bi bi-diagram-2-fill me-2"></i> Cambiar Posición (Jefe)
                    </button>
                    <button class="btn btn-danger fw-bold text-start" onclick="abrirDesdeMenu('eliminar')">
                        <i class="bi bi-trash-fill me-2"></i> Quitar del Organigrama
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. MODAL: AGREGAR AL ÁRBOL -->
<!-- ========================================== -->
<div class="modal fade" id="modalAddNode" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"></i>Agregar al Organigrama</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-primary">1. Seleccionar Trabajador:</label>
                    <select id="selectEmpleado" class="form-select border-primary shadow-sm">
                        <option value="">Seleccione a la persona...</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?> - (<?= htmlspecialchars($e['cargo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-success">2. ¿A quién le reporta? (Jefe Directo):</label>
                    <select id="selectJefeAgregar" class="form-select shadow-sm border-success"></select>
                    <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Si elige "Ninguno", será el Director Principal.</small>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success w-100 fw-bold rounded-pill" onclick="confirmarAgregar()">Añadir al Árbol</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 3. MODAL: EDITAR POSICIÓN EN EL ÁRBOL -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditPosition" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Cambiar Jefe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" id="editNodeId">
                <p class="text-center fw-bold mb-3" id="editNodeName">Nombre Empleado</p>
                
                <label class="form-label fw-bold small text-muted">Nuevo Supervisor Directo:</label>
                <select id="selectNuevoJefe" class="form-select shadow-sm"></select>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-warning w-100 fw-bold rounded-pill" onclick="confirmarEdicionPosicion()">Aplicar Cambio</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. MODAL: VER DETALLES (SOLO LECTURA) -->
<!-- ========================================== -->
<div class="modal fade" id="modalVerDetalles" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-dark border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position: absolute; right: 15px; top: 15px;"></button>
            </div>
            <div class="modal-body bg-info text-center pt-0 rounded-bottom">
                <img id="detImg" src="" class="rounded-circle border border-3 border-white shadow-sm mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                <h5 class="fw-bold mb-0" id="detName">Nombre Apellido</h5>
                <p class="mb-3 text-dark opacity-75 fw-bold" id="detTitle">Cargo</p>
            </div>
            <div class="modal-body p-4 bg-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person-vcard me-2"></i>Cédula:</span>
                        <strong id="detCedula"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-telephone me-2"></i>Teléfono:</span>
                        <strong id="detTelefono"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-envelope me-2"></i>Correo:</span>
                        <strong id="detCorreo"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-check me-2"></i>Ingreso:</span>
                        <strong id="detIngreso"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between border-0">
                        <span class="text-muted"><i class="bi bi-activity me-2"></i>Estado:</span>
                        <strong id="detEstado" class="text-success"></strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    const dataTree = <?= $jerarquia_actual ?>;
    const todosEmpleados = <?= $personal_json ?>;

    // INICIALIZACIÓN DEL GRÁFICO (REPARADO Y CON ZOOM)
    let chart = new OrgChart(document.getElementById("tree"), {
        template: "luba",
        enableDragDrop: true,
        
        // 1. SOLUCIÓN DEL ZOOM (Doble configuración para asegurar compatibilidad)
        mouseScrool: OrgChart.action.zoom,
        mouseScroll: OrgChart.action.zoom, 
        
        // 2. HERRAMIENTAS VISUALES DE ZOOM (Botones en pantalla)
        toolbar: {
            zoom: true,
            fit: true
        },
        
        // 3. APAGAR EL MENÚ DEFAULT EN INGLÉS
        nodeMenu: null, 

        nodeBinding: { 
            field_0: "name", 
            field_1: "title", 
            img_0: "img" 
        },
        nodes: dataTree
    });

    // ========================================================
    // 4. ELIMINAR EL OFFCANVAS EN INGLÉS Y ABRIR NUESTRO MENÚ
    // ========================================================
    chart.on('click', function (sender, args) {
        let id = args.node.id;
        let nodeData = chart.get(id);
        
        // Llenar el modal del menú
        document.getElementById('opcionesNodeId').value = id;
        document.getElementById('opcionesNodeName').innerText = nodeData.name;
        
        // Mostrar menú personalizado
        new bootstrap.Modal(document.getElementById('modalOpcionesNodo')).show();
        
        // RETORNAR FALSE ES LO QUE BLOQUEA EL PANEL EN INGLÉS DE ORGCHART.JS
        return false; 
    });


    // ========================================================
    // GESTIÓN DE LAS 3 OPCIONES DEL MENÚ
    // ========================================================
    function abrirDesdeMenu(accion) {
        // Cerrar el menú actual
        bootstrap.Modal.getInstance(document.getElementById('modalOpcionesNodo')).hide();
        
        let id = document.getElementById('opcionesNodeId').value;
        let node = chart.get(id);

        if (accion === 'detalles') {
            document.getElementById('detImg').src = node.img;
            document.getElementById('detName').innerText = node.name;
            document.getElementById('detTitle').innerText = node.title;
            document.getElementById('detCedula').innerText = node.cedula || 'N/A';
            document.getElementById('detTelefono').innerText = node.telefono || 'N/A';
            document.getElementById('detCorreo').innerText = node.correo || 'N/A';
            document.getElementById('detIngreso').innerText = node.ingreso || 'N/A';
            document.getElementById('detEstado').innerText = node.estado || 'N/A';
            new bootstrap.Modal(document.getElementById('modalVerDetalles')).show();
        } 
        else if (accion === 'editar') {
            document.getElementById('editNodeId').value = node.id;
            document.getElementById('editNodeName').innerText = node.name;

            let selectJefe = document.getElementById('selectNuevoJefe');
            selectJefe.innerHTML = '<option value="">Ninguno (Máxima Autoridad)</option>';
            
            for (let j_id in chart.nodes) {
                if (j_id != id) { // No puede ser su propio jefe
                    let n = chart.nodes[j_id];
                    let option = new Option(n.name + ' (' + n.title + ')', n.id);
                    if (node.pid == n.id) option.selected = true;
                    selectJefe.add(option);
                }
            }
            new bootstrap.Modal(document.getElementById('modalEditPosition')).show();
        } 
        else if (accion === 'eliminar') {
            if(confirm("¿Seguro que desea quitar a " + node.name + " del esquema gráfico?")) {
                chart.removeNode(id);
            }
        }
    }

    // ========================================================
    // FUNCIONES RESTANTES (Agregar, Confirmar Edición, Guardar)
    // ========================================================
    function abrirModalAgregar() {
        let selectJefe = document.getElementById('selectJefeAgregar');
        selectJefe.innerHTML = '<option value="">Ninguno (Será Nodo Raíz / Director)</option>';
        for (let id in chart.nodes) {
            let n = chart.nodes[id];
            selectJefe.add(new Option(n.name + ' (' + n.title + ')', n.id));
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
            chart.addNode({
                id: empData.id, pid: pid,
                name: empData.apellidos + ', ' + empData.nombres,
                title: empData.cargo,
                img: empData.foto ? empData.foto : 'https://cdn-icons-png.flaticon.com/512/149/149071.png',
                cedula: empData.cedula, telefono: empData.telefono || 'S/R',
                correo: empData.correo_electronico || 'S/R', ingreso: empData.fecha_ingreso, estado: empData.estado_laboral
            });
            bootstrap.Modal.getInstance(document.getElementById('modalAddNode')).hide();
        }
    }

    function confirmarEdicionPosicion() {
        let id = document.getElementById('editNodeId').value;
        let pid = document.getElementById('selectNuevoJefe').value;
        if (pid === "") pid = null;

        let nodeData = chart.get(id);
        nodeData.pid = pid;
        chart.updateNode(nodeData);

        bootstrap.Modal.getInstance(document.getElementById('modalEditPosition')).hide();
    }

    function vaciarArbol() {
        if(confirm('¿Seguro que desea limpiar todo el esquema visual? (Recuerde Guardar Cambios al final)')) {
            chart.load([]); 
        }
    }

    async function guardarOrganigrama() {
        let btn = document.getElementById('btnGuardar');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
        btn.disabled = true;

        try {
            let nodosExtraidos = [];
            for (let key in chart.nodes) {
                let n = chart.nodes[key];
                nodosExtraidos.push({
                    id: n.id,
                    pid: (n.pid === undefined || n.pid === null || n.pid === "") ? null : n.pid
                });
            }

            let formData = new FormData();
            formData.append('nodos', JSON.stringify(nodosExtraidos));

            let response = await fetch('organigrama.php', { method: 'POST', body: formData });
            let result = await response.json();

            if(result.success) {
                btn.classList.replace('btn-primary', 'btn-success');
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardado Exitoso';
            } else {
                alert("Error al guardar: " + result.error);
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
            }
        } catch (error) {
            alert("Error de red.");
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
        } finally {
            setTimeout(() => {
                btn.classList.replace('btn-success', 'btn-primary');
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
                btn.disabled = false;
            }, 2500);
        }
    }
</script>

<?php include '../Includes/footer.php'; ?>