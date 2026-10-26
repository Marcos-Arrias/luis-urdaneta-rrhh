<?php
require_once '../PHP/connection.php';

// 1. AUTO-CREAR TABLA RELACIONAL DEL ORGANIGRAMA
$pdo->exec("CREATE TABLE IF NOT EXISTS organigrama_jerarquia (
    personal_id INT PRIMARY KEY,
    jefe_id INT NULL,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_id) REFERENCES personal(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. PROCESAR GUARDADO AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nodos'])) {
    // Evita el error E_NOTICE asegurándose que exista un buffer antes de limpiar
    if (ob_get_length()) { ob_clean(); } 
    header('Content-Type: application/json');
    
    try {
        $nodos = json_decode($_POST['nodos'], true);
        
        $pdo->beginTransaction();
        // Borramos la jerarquía vieja para insertar la nueva estructura
        $pdo->exec("DELETE FROM organigrama_jerarquia");
        
        if (is_array($nodos) && count($nodos) > 0) {
            $stmt = $pdo->prepare("INSERT INTO organigrama_jerarquia (personal_id, jefe_id) VALUES (?, ?)");
            foreach ($nodos as $n) {
                $emp_id = intval($n['id']);
                $jefe_id = !empty($n['pid']) ? intval($n['pid']) : null;
                
                // Evitamos que por error del sistema un empleado sea su propio jefe
                if ($emp_id !== $jefe_id) {
                    $stmt->execute([$emp_id, $jefe_id]);
                }
            }
        }
        $pdo->commit();
        
        registrarHistorial($pdo, 'Organigrama', 'ACTUALIZAR', "Actualizó la jerarquía del personal institucional.");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. CARGAR ÁRBOL ACTUAL
$queryArbol = $pdo->query("
    SELECT o.personal_id as id, o.jefe_id as pid, 
           p.nombres, p.apellidos, c.nombre as cargo,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as foto
    FROM organigrama_jerarquia o
    JOIN personal p ON o.personal_id = p.id
    LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
")->fetchAll(PDO::FETCH_ASSOC);

// Formateamos los datos
$data_tree = [];
foreach ($queryArbol as $row) {
    $img = $row['foto'] ? $row['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    $data_tree[] = [
        'id' => $row['id'],
        'pid' => $row['pid'],
        'name' => $row['apellidos'] . ', ' . $row['nombres'],
        'title' => $row['cargo'],
        'img' => $img
    ];
}
$jerarquia_actual = json_encode($data_tree);

// 4. CARGAR TODO EL PERSONAL ACTIVO (Para el buscador)
$empleados = $pdo->query("
    SELECT p.id, p.cedula, p.nombres, p.apellidos, c.nombre as cargo,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as foto
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    WHERE p.estado_laboral = 'Activo'
    ORDER BY p.apellidos ASC
")->fetchAll();

include '../Includes/header.php'; 
include '../Includes/sidebar.php';
?>

<!-- Librería Oficial OrgChart.js -->
<script src="https://balkan.app/js/OrgChart.js"></script>

<style>
    #tree { width: 100%; height: 650px; background-color: var(--bs-body-tertiary); border-radius: 1rem; border: 1px solid var(--bs-border-color); }
    [data-bs-theme="dark"] .boc-light { background-color: #212529 !important; }
    [data-bs-theme="dark"] .boc-light rect { fill: #343a40 !important; stroke: #495057 !important; }
    [data-bs-theme="dark"] .boc-light text { fill: #f8f9fa !important; }
    .boc-edit-button, .boc-add-button { display: none !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i> Organigrama Institucional</h2>
        <p class="text-muted small mb-0">Gestión de jerarquías y dependencias.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger rounded-pill fw-bold shadow-sm" onclick="limpiarOrganigrama()" title="Vaciar panel">
            <i class="bi bi-trash"></i>
        </button>
        <button class="btn btn-outline-secondary rounded-pill fw-bold shadow-sm" onclick="chart.exportPNG('Organigrama_Urdaneta')" title="Bajar Imagen">
            <i class="bi bi-file-image"></i> PDF / PNG
        </button>
        <button class="btn btn-success shadow-sm rounded-pill px-4 fw-bold" onclick="abrirModalAgregar()">
            <i class="bi bi-person-plus-fill me-1"></i> Añadir Personal
        </button>
        <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" onclick="guardarOrganigrama()" id="btnGuardar">
            <i class="bi bi-save me-1"></i> Guardar Cambios
        </button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 relative">
    <div class="card-body p-0 position-relative">
        
        <!-- LIENZO EN BLANCO ESTRICTO -->
        <div id="overlay-vacio" style="display: none; position: absolute; top:0; left:0; width:100%; height:100%; background-color: var(--bs-body-tertiary); z-index: 100; flex-direction: column; justify-content: center; align-items: center;">
            <div class="p-5 text-center bg-body shadow-sm border rounded-4">
                <i class="bi bi-diagram-3-fill text-primary opacity-50 d-block mb-3" style="font-size: 4rem;"></i>
                <h3 class="fw-bold text-body mb-2">Lienzo en Blanco</h3>
                <p class="text-muted mb-4">El organigrama no tiene trabajadores registrados aún.</p>
                <button class="btn btn-success rounded-pill px-4 fw-bold btn-lg shadow-sm" onclick="abrirModalAgregar()">
                    <i class="bi bi-person-plus me-1"></i> Comenzar a Armar Estructura
                </button>
            </div>
        </div>

        <div id="tree"></div>
    </div>
</div>

<!-- MODAL PARA AÑADIR -->
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
                        <?php foreach ($empleados as $e): 
                            $img = $e['foto'] ? $e['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                        ?>
                            <option value="<?= $e['id'] ?>" 
                                    data-name="<?= htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?>" 
                                    data-cargo="<?= htmlspecialchars($e['cargo']) ?>"
                                    data-img="<?= htmlspecialchars($img) ?>">
                                <?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?> - (<?= htmlspecialchars($e['cargo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-success">2. ¿A quién le reporta? (Jefe Directo):</label>
                    <select id="selectJefe" class="form-select shadow-sm border-success"></select>
                    <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Si elige "Ninguno", será el Director Principal.</small>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success w-100 fw-bold rounded-pill" onclick="confirmarAgregar()">Añadir al Árbol</button>
            </div>
        </div>
    </div>
</div>

<script>
    OrgChart.SEARCH_PLACEHOLDER = "Buscar empleado en el árbol...";

    let dataTree = <?= $jerarquia_actual ?>;

    let chart = new OrgChart(document.getElementById("tree"), {
        template: "luba",
        enableSearch: true,
        enableDragDrop: true,
        nodeBinding: { field_0: "name", field_1: "title", img_0: "img" },
        nodes: dataTree,
        nodeMenu: { remove: { text: "Quitar empleado del árbol" } }
    });

    chart.on('remove', function () { setTimeout(verificarVacio, 100); });

    function verificarVacio() {
        let count = Object.keys(chart.nodes).length;
        let overlay = document.getElementById('overlay-vacio');
        if (count === 0) {
            overlay.style.display = 'flex';
        } else {
            overlay.style.display = 'none';
        }
    }
    
    verificarVacio();

    // ABRIR MODAL DINÁMICO (CORREGIDO PARA CARGAR NOMBRES CORRECTAMENTE)
    function abrirModalAgregar() {
        let selectJefe = document.getElementById('selectJefe');
        selectJefe.innerHTML = '<option value="">Ninguno (Será Nodo Raíz / Director)</option>';
        
        // Obtener la lista segura usando chart.get()
        for (let nodeId in chart.nodes) {
            let nodeData = chart.get(nodeId);
            if (nodeData) {
                selectJefe.add(new Option(nodeData.name + ' (' + nodeData.title + ')', nodeData.id));
            }
        }

        document.getElementById('selectEmpleado').value = ""; 
        new bootstrap.Modal(document.getElementById('modalAddNode')).show();
    }

    // CONFIRMAR AGREGAR (VALIDACIÓN REPARADA)
    function confirmarAgregar() {
        let selectEmp = document.getElementById('selectEmpleado');
        let selectJefe = document.getElementById('selectJefe');
        
        if(!selectEmp.value) { alert('Seleccione un empleado.'); return; }
        
        let option = selectEmp.options[selectEmp.selectedIndex];
        let id = option.value;
        let name = option.getAttribute('data-name');
        let title = option.getAttribute('data-cargo');
        let img = option.getAttribute('data-img');
        let pid = selectJefe.value === "" ? null : selectJefe.value;

        // Comprobación segura de si el nodo ya existe
        if(chart.get(id)) {
            alert('¡' + name + ' ya está asignado en el organigrama!');
            return;
        }

        chart.addNode({ id: id, pid: pid, name: name, title: title, img: img });
        verificarVacio(); 
        
        bootstrap.Modal.getInstance(document.getElementById('modalAddNode')).hide();
    }

    function limpiarOrganigrama() {
        if(confirm('¿Vaciar organigrama? (Recuerde guardar después).')) {
            chart.load([]); 
            verificarVacio();
        }
    }

    // GUARDAR EN BD (LÓGICA REPARADA Y A PRUEBA DE DRAG & DROP)
    async function guardarOrganigrama() {
        let btn = document.getElementById('btnGuardar');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
        btn.disabled = true;

        try {
            let nodosExtraidos = [];
            
            // Extraer IDs y PIDs activos directamente de la matriz de la librería OrgChart
            for (let nodeId in chart.nodes) {
                let nodeInstance = chart.nodes[nodeId];
                if (nodeInstance && nodeInstance.id) {
                    nodosExtraidos.push({
                        id: nodeInstance.id,
                        pid: (nodeInstance.pid === "" || nodeInstance.pid === null || nodeInstance.pid === undefined) ? null : nodeInstance.pid
                    });
                }
            }

            let formData = new FormData();
            formData.append('nodos', JSON.stringify(nodosExtraidos));

            let response = await fetch('organigrama.php', { method: 'POST', body: formData });
            let result = await response.json();

            if(result.success) {
                btn.classList.replace('btn-primary', 'btn-success');
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardado Exitoso';
            } else {
                alert("Hubo un error guardando: " + result.error);
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
            }
        } catch (error) {
            console.error(error);
            alert("Fallo de conexión.");
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
        } finally {
            setTimeout(() => {
                btn.classList.replace('btn-success', 'btn-primary');
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Cambios';
                btn.disabled = false;
            }, 2000);
        }
    }
</script>

<?php include '../Includes/footer.php'; ?>