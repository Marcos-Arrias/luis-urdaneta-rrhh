<?php
require_once '../PHP/connection.php';

// 1. AUTO-CREAR TABLA SQL DEL ORGANIGRAMA
$pdo->exec("CREATE TABLE IF NOT EXISTS organigrama_institucional (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estructura_json LONGTEXT NOT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. PROCESAR GUARDADO AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jerarquia'])) {
    $jerarquia = $_POST['jerarquia'];
    $existe = $pdo->query("SELECT COUNT(*) FROM organigrama_institucional")->fetchColumn();
    
    if ($existe > 0) {
        $stmt = $pdo->prepare("UPDATE organigrama_institucional SET estructura_json = ?");
    } else {
        $stmt = $pdo->prepare("INSERT INTO organigrama_institucional (estructura_json) VALUES (?)");
    }
    
    $stmt->execute([$jerarquia]);
    registrarHistorial($pdo, 'Organigrama', 'ACTUALIZAR', "Actualizó la jerarquía del personal institucional.");
    echo json_encode(['success' => true]);
    exit;
}

// 3. CARGAR ÁRBOL ACTUAL (Si no hay nada, devuelve un array vacío [])
$jerarquia_actual = $pdo->query("SELECT estructura_json FROM organigrama_institucional LIMIT 1")->fetchColumn();
if (!$jerarquia_actual) { $jerarquia_actual = "[]"; }

// 4. CARGAR PERSONAL ACTIVO PARA EL SELECT DEL MODAL
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
    /* Estilos del contenedor del organigrama */
    #tree { width: 100%; height: 600px; background-color: var(--bs-body-tertiary); border-radius: 1rem; border: 1px solid var(--bs-border-color); }
    
    /* Adaptación nativa a Modo Oscuro y Estética Juicy */
    [data-bs-theme="dark"] .boc-light { background-color: #212529 !important; }
    [data-bs-theme="dark"] .boc-light rect { fill: #343a40 !important; stroke: #495057 !important; }
    [data-bs-theme="dark"] .boc-light text { fill: #f8f9fa !important; }
    
    /* Ocultar botones feos nativos de la librería para usar los nuestros */
    .boc-edit-button, .boc-add-button { display: none !important; }

    /* Estilo del Overlay de Vacío (Para tapar el texto en inglés) */
    #overlay-vacio {
        background-color: var(--bs-body-tertiary); /* Se adapta al modo oscuro */
        border-radius: 1rem;
        z-index: 100;
        transition: opacity 0.3s ease;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i> Organigrama Dinámico</h2>
        <p class="text-muted small mb-0">Agrega personal real. Arrastra las tarjetas para reordenar dependencias.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger rounded-pill fw-bold shadow-sm" onclick="limpiarOrganigrama()" title="Vaciar todo el panel">
            <i class="bi bi-trash"></i>
        </button>
        <button class="btn btn-outline-secondary rounded-pill fw-bold shadow-sm" onclick="chart.exportPNG('Organigrama_Institucional')" title="Descargar como Imagen">
            <i class="bi bi-file-image"></i> Bajar PNG
        </button>
        <button class="btn btn-success shadow-sm rounded-pill px-4 fw-bold" onclick="abrirModalAgregar()">
            <i class="bi bi-person-plus-fill me-1"></i> Vincular Personal
        </button>
        <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" onclick="guardarOrganigrama()" id="btnGuardar">
            <i class="bi bi-save me-1"></i> Guardar Árbol
        </button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0 position-relative">
        
        <!-- OVERLAY ELEGANTE EN ESPAÑOL (OCULTA EL INGLÉS) -->
        <div id="overlay-vacio" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center">
            <div class="p-5 text-center bg-body shadow-sm border rounded-4">
                <i class="bi bi-diagram-3-fill text-primary opacity-50 d-block mb-3" style="font-size: 4rem;"></i>
                <h3 class="fw-bold text-body mb-2">Lienzo en Blanco</h3>
                <p class="text-muted mb-4">El organigrama no tiene trabajadores registrados aún.</p>
                <button class="btn btn-success rounded-pill px-4 fw-bold btn-lg shadow-sm" onclick="abrirModalAgregar()">
                    <i class="bi bi-person-plus-fill me-1"></i> Comenzar a Vincular Personal
                </button>
            </div>
        </div>

        <div id="tree"></div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL PARA AGREGAR PERSONAL AL ORGANIGRAMA -->
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
                    <select id="selectJefe" class="form-select shadow-sm border-success">
                        <!-- Se llena dinámicamente con JS -->
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Si elige "Ninguno", será el Director Principal (Nodo Raíz).</small>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success w-100 fw-bold rounded-pill" onclick="confirmarAgregar()">Añadir al Árbol</button>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. TRADUCIR EL BUSCADOR DE ORGCHART
    OrgChart.SEARCH_PLACEHOLDER = "Buscar empleado en el árbol...";

    // 2. INICIALIZAR ORGANIGRAMA
    let dataTree = <?= $jerarquia_actual ?>;

    let chart = new OrgChart(document.getElementById("tree"), {
        template: "luba", // Estilo moderno con foto circular
        enableSearch: true,
        enableDragDrop: true, // Permite arrastrar para cambiar dependencias
        nodeBinding: {
            field_0: "name", // Mapea el Nombre
            field_1: "title", // Mapea el Cargo
            img_0: "img"     // Mapea la Foto
        },
        nodes: dataTree,
        nodeMenu: {
            remove: { text: "Desvincular (Quitar del árbol)" }
        }
    });

    // 3. FUNCIÓN PARA MOSTRAR/OCULTAR EL OVERLAY DE VACÍO
    function verificarVacio() {
        let overlay = document.getElementById('overlay-vacio');
        // Si no hay nodos en el gráfico, mostramos la pantalla en español bonita
        if (Object.keys(chart.nodes).length === 0) {
            overlay.style.display = 'flex';
        } else {
            overlay.style.display = 'none';
        }
    }

    // Comprobamos al cargar la página
    setTimeout(verificarVacio, 100);

    // 4. ABRIR MODAL DINÁMICO
    function abrirModalAgregar() {
        let selectJefe = document.getElementById('selectJefe');
        selectJefe.innerHTML = '<option value="">Ninguno (Será Nodo Raíz / Director)</option>';
        
        let nodosActivos = Object.values(chart.nodes).map(n => n.data);
        nodosActivos.forEach(n => {
            selectJefe.add(new Option(n.name + ' (' + n.title + ')', n.id));
        });

        document.getElementById('selectEmpleado').value = ""; 
        new bootstrap.Modal(document.getElementById('modalAddNode')).show();
    }

    // 5. AÑADIR EL NODO VISUALMENTE
    function confirmarAgregar() {
        let selectEmp = document.getElementById('selectEmpleado');
        let selectJefe = document.getElementById('selectJefe');
        
        if(!selectEmp.value) { alert('Por favor, seleccione un empleado de la lista.'); return; }
        
        let option = selectEmp.options[selectEmp.selectedIndex];
        let id = option.value;
        let name = option.getAttribute('data-name');
        let title = option.getAttribute('data-cargo');
        let img = option.getAttribute('data-img');
        let pid = selectJefe.value || null;

        if(chart.nodes[id]) {
            alert('¡' + name + ' ya está asignado en el organigrama!');
            return;
        }

        // Agregar al árbol y ocultar el cartel de vacío
        chart.addNode({ id: id, pid: pid, name: name, title: title, img: img });
        verificarVacio(); 
        
        bootstrap.Modal.getInstance(document.getElementById('modalAddNode')).hide();
    }

    // 6. VACIAR EL ÁRBOL
    function limpiarOrganigrama() {
        if(confirm('¿Borrar toda la estructura actual? (Aún deberá hacer click en "Guardar" para registrar el cambio).')) {
            chart.load([]); 
            verificarVacio(); // Volver a mostrar el cartel en español
        }
    }

    // 7. GUARDAR EN MYSQL
    async function guardarOrganigrama() {
        let btn = document.getElementById('btnGuardar');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        btn.disabled = true;

        let nodosActivos = [];
        for (let id in chart.nodes) {
            nodosActivos.push(chart.nodes[id].data);
        }

        try {
            let formData = new FormData();
            formData.append('jerarquia', JSON.stringify(nodosActivos));

            let response = await fetch('organigrama.php', { method: 'POST', body: formData });
            let result = await response.json();

            if(result.success) {
                btn.classList.replace('btn-primary', 'btn-success');
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Árbol Guardado';
                setTimeout(() => {
                    btn.classList.replace('btn-success', 'btn-primary');
                    btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Árbol';
                    btn.disabled = false;
                }, 2500);
            }
        } catch (error) {
            alert("Error de conexión. No se pudo guardar.");
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar Árbol';
            btn.disabled = false;
        }
    }
</script>

<?php include '../Includes/footer.php'; ?>