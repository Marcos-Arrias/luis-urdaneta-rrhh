</div> <!-- Cierra el container-fluid de tu página -->
        </div> <!-- Cierra el page-content-wrapper -->
    </div> <!-- Cierra el wrapper principal -->

    <!-- Carga de JS de Bootstrap -->
    <script src="../CSS/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/script.js?v=<?= time() ?>"></script>

<!-- MODAL BOOTSTRAP PARA AGREGAR CARGO RÁPIDO -->
    <div class="modal fade" id="modalQuickAddCargo" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Cargo/Materia</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-body">
                    <input type="hidden" id="quick_tipo_id">
                    <input type="hidden" id="quick_select_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nombre de la Función:</label>
                        <input type="text" id="quick_nombre_cargo" class="form-control form-control-sm" placeholder="Ej. Robótica" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold">Descripción (Opcional):</label>
                        <textarea id="quick_descripcion_cargo" class="form-control form-control-sm" rows="3" placeholder="Detalles para mostrar al pasar el cursor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-2 border-0">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="btnGuardarQuick" onclick="guardarCargoModal()">Guardar y Seleccionar</button>
                </div>
           </div> <!-- CIERRA EL container-fluid (CRÍTICO PARA LAS PESTAÑAS) -->
        </div> <!-- CIERRA EL page-content-wrapper (CRÍTICO) -->
    </div> <!-- CIERRA EL wrapper principal (CRÍTICO) -->

    <!-- Carga de JS de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/script.js?v=<?= time() ?>"></script>

    <!-- MODAL BOOTSTRAP PARA AGREGAR CARGO RÁPIDO -->
    <div class="modal fade" id="modalQuickAddCargo" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva Función / Cargo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-body">
                    <input type="hidden" id="quick_tipo_id">
                    <input type="hidden" id="quick_select_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nombre de la Función:</label>
                        <input type="text" id="quick_nombre_cargo" class="form-control form-control-sm" placeholder="Ej. Robótica" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold">Descripción (Opcional):</label>
                        <textarea id="quick_descripcion_cargo" class="form-control form-control-sm" rows="3" placeholder="Detalles para mostrar al pasar el cursor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-2 border-0">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="btnGuardarQuick" onclick="guardarCargoModal()">Guardar en Catálogo</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT DE AJUSTES GLOBALES DE APARIENCIA Y MODALES -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const switchModo = document.getElementById('switchModoOscuro');
        if (localStorage.getItem('modoOscuro') === 'activado') { 
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if(switchModo) switchModo.checked = true;
        }

        const colorGuardado = localStorage.getItem('sidebarColor') || 'default';
        const colorMenu = document.getElementById('selectMenuColor');
        if(colorMenu) colorMenu.value = colorGuardado;
        aplicarColorSidebar(colorGuardado);

        const tableDensity = localStorage.getItem('tableDensity') || '2';
        const rangeTable = document.getElementById('rangeTableDensity');
        if(rangeTable) rangeTable.value = tableDensity;
        aplicarDensidadTabla(tableDensity);

        const fontSize = localStorage.getItem('fontSize') || '2';
        const rangeFont = document.getElementById('rangeFontSize');
        if(rangeFont) rangeFont.value = fontSize;
        aplicarFuenteGlobal(fontSize);

        const cardStyle = localStorage.getItem('cardStyle') || 'shadow';
        const radiosCard = document.getElementsByName('cardStyle');
        if(radiosCard.length > 0) {
            let selectedRadio = document.getElementById(cardStyle === 'shadow' ? 'styleShadow' : 'styleFlat');
            if(selectedRadio) selectedRadio.checked = true;
        }
        aplicarEstiloTarjetas(cardStyle);

        // LISTENERS
        if(switchModo) switchModo.addEventListener('change', (e) => {
            let estado = e.target.checked ? 'activado' : 'desactivado';
            document.documentElement.setAttribute('data-bs-theme', estado === 'activado' ? 'dark' : 'light');
            localStorage.setItem('modoOscuro', estado);
        });

        if(colorMenu) colorMenu.addEventListener('change', (e) => { localStorage.setItem('sidebarColor', e.target.value); aplicarColorSidebar(e.target.value); });
        if(rangeTable) rangeTable.addEventListener('input', (e) => { localStorage.setItem('tableDensity', e.target.value); aplicarDensidadTabla(e.target.value); });
        if(rangeFont) rangeFont.addEventListener('input', (e) => { localStorage.setItem('fontSize', e.target.value); aplicarFuenteGlobal(e.target.value); });
        
        radiosCard.forEach(radio => {
            radio.addEventListener('change', (e) => { localStorage.setItem('cardStyle', e.target.value); aplicarEstiloTarjetas(e.target.value); });
        });
    });

    function aplicarColorSidebar(tema) {
        let elemento = document.getElementById('sidebar-wrapper');
        if(!elemento) return;
        elemento.style.background = ''; 
        if(tema === 'dark') elemento.style.background = 'linear-gradient(180deg, #212529 0%, #000000 100%)';
        else if(tema === 'success') elemento.style.background = 'linear-gradient(180deg, #198754 0%, #146c43 100%)';
        else if(tema === 'danger') elemento.style.background = 'linear-gradient(180deg, #dc3545 0%, #b02a37 100%)';
        else elemento.style.background = 'linear-gradient(180deg, #1e3c72 0%, #2a5298 100%)'; 
    }

    function aplicarDensidadTabla(valor) {
        document.querySelectorAll('.table').forEach(tabla => {
            tabla.classList.remove('table-sm');
            tabla.style.fontSize = '';
            tabla.querySelectorAll('th, td').forEach(c => c.style.padding = '');

            if(valor === '1') { 
                tabla.classList.add('table-sm'); 
                tabla.style.fontSize = '0.85rem'; 
            } else if(valor === '3') {
                tabla.querySelectorAll('th, td').forEach(c => c.style.padding = '1.2rem 0.5rem');
            }
        });
    }

    function aplicarFuenteGlobal(valor) {
        if(valor === '1') document.body.style.fontSize = '0.85rem';
        else if(valor === '3') document.body.style.fontSize = '1.1rem';
        else document.body.style.fontSize = '1rem';
    }

    function aplicarEstiloTarjetas(estilo) {
        document.querySelectorAll('.card').forEach(card => {
            if(estilo === 'flat') { card.classList.remove('shadow-sm', 'shadow', 'shadow-lg'); card.classList.add('border'); } 
            else { card.classList.add('shadow-sm'); card.classList.remove('border'); }
        });
    }

    function agregarCargoRapido(tipoPersonalId, selectId) {
        document.getElementById('quick_tipo_id').value = tipoPersonalId;
        document.getElementById('quick_select_id').value = selectId;
        document.getElementById('quick_nombre_cargo').value = ''; 
        document.getElementById('quick_descripcion_cargo').value = ''; 
        new bootstrap.Modal(document.getElementById('modalQuickAddCargo')).show();
    }

    async function guardarCargoModal() {
        let nombre = document.getElementById('quick_nombre_cargo').value.trim();
        let descripcion = document.getElementById('quick_descripcion_cargo').value.trim();
        let tipoId = document.getElementById('quick_tipo_id').value;
        let selectId = document.getElementById('quick_select_id').value;
        let btn = document.getElementById('btnGuardarQuick');

        if (nombre === "") { alert("Por favor escriba un nombre."); return; }
        btn.disabled = true; btn.innerHTML = 'Guardando...';

        try {
            let formData = new FormData(); formData.append('nombre', nombre); formData.append('descripcion', descripcion); formData.append('tipo_personal_id', tipoId);
            let response = await fetch('../PHP/ajax_agregar_cargo.php', { method: 'POST', body: formData });
            let data = await response.json();

            if (data.success) {
                let select = document.getElementById(selectId);
                select.add(new Option(data.nombre, data.id));
                select.value = data.id; 
                bootstrap.Modal.getInstance(document.getElementById('modalQuickAddCargo')).hide();
            } else { alert("Error: " + data.error); }
        } catch (error) { alert("Ocurrió un error de conexión."); }
        btn.disabled = false; btn.innerHTML = 'Guardar y Seleccionar';
    }
    </script>
</body>
</html>