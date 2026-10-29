</div> <!-- Cierra el container-fluid -->
        </div> <!-- Cierra el page-content-wrapper -->
    </div> <!-- Cierra el wrapper principal -->

    <!-- Carga de JS de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Archivo JS Global con Cache Busting para evitar errores de navegador -->
    <script src="../JS/script.js?v=<?= time() ?>"></script>

    <!-- MODAL BOOTSTRAP PARA AGREGAR CARGO RÁPIDO (REPARADO: SIN tabindex="-1") -->
    <div class="modal fade shadow-lg" id="modalQuickAddCargo" data-bs-backdrop="static">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva Función / Cargo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-body">
                    <input type="hidden" id="quick_tipo_id">
                    <input type="hidden" id="quick_select_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nombre de la Función:</label>
                        <!-- Ahora sí podrás escribir aquí sin bloqueos -->
                        <input type="text" id="quick_nombre_cargo" class="form-control form-control-sm" placeholder="Ej. Robótica" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold">Descripción (Opcional):</label>
                        <textarea id="quick_descripcion_cargo" class="form-control form-control-sm" rows="3" placeholder="Detalles de la cátedra..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-2 border-0">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="btnGuardarQuick" onclick="guardarCargoModal()">Guardar en Catálogo</button>
                </div>
            </div>
        </div>
    </div>

<!-- SCRIPT DE AJUSTES GLOBALES DE APARIENCIA AVANZADA -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        
        // 1. MODO OSCURO
        const switchModo = document.getElementById('switchModoOscuro');
        if (localStorage.getItem('modoOscuro') === 'activado') { 
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if(switchModo) switchModo.checked = true;
        }

        // 2. COLOR DEL MENÚ LATERAL (Predefinido y Custom)
        const colorGuardado = localStorage.getItem('sidebarColor') || 'default';
        const colorCustom = localStorage.getItem('sidebarCustomColor') || '#1e3c72';
        const colorMenu = document.getElementById('selectMenuColor');
        const colorPicker = document.getElementById('customColorPicker');
        
        if(colorMenu) colorMenu.value = colorGuardado;
        if(colorPicker) colorPicker.value = colorCustom;
        aplicarColorSidebar(colorGuardado, colorCustom);

        // 3. TABLAS Y FUENTES (Sliders)
        const tableDensity = localStorage.getItem('tableDensity') || '2';
        const rangeTable = document.getElementById('rangeTableDensity');
        if(rangeTable) rangeTable.value = tableDensity;
        aplicarDensidadTabla(tableDensity);

        const fontSize = localStorage.getItem('fontSize') || '2';
        const rangeFont = document.getElementById('rangeFontSize');
        if(rangeFont) rangeFont.value = fontSize;
        aplicarFuenteGlobal(fontSize);

        // 4. ESTILO DE TARJETAS
        const cardStyle = localStorage.getItem('cardStyle') || 'shadow';
        const radiosCard = document.getElementsByName('cardStyle');
        if(radiosCard.length > 0) {
            let selectedRadio = document.getElementById(cardStyle === 'shadow' ? 'styleShadow' : 'styleFlat');
            if(selectedRadio) selectedRadio.checked = true;
        }
        aplicarEstiloTarjetas(cardStyle);

        // 5. TIPOGRAFÍA (Fuente del sistema)
        const fontSaved = localStorage.getItem('fontFamily') || 'default';
        const fontSelect = document.getElementById('selectFontFamily');
        if(fontSelect) fontSelect.value = fontSaved;
        aplicarTipografia(fontSaved);

        // 6. FONDO DE PANTALLA (Wallpaper)
        const bgSaved = localStorage.getItem('customWallpaper');
        const bgUrlInput = document.getElementById('bgUrlInput');
        if(bgSaved) aplicarFondo(bgSaved);

        // ==========================================
        // LISTENERS (GUARDAR CAMBIOS EN TIEMPO REAL)
        // ==========================================
        
        if(switchModo) switchModo.addEventListener('change', (e) => {
            let estado = e.target.checked ? 'activado' : 'desactivado';
            document.documentElement.setAttribute('data-bs-theme', estado === 'activado' ? 'dark' : 'light');
            localStorage.setItem('modoOscuro', estado);
        });

        // Cambio de selectores predefinidos
        if(colorMenu) colorMenu.addEventListener('change', (e) => { 
            localStorage.setItem('sidebarColor', e.target.value); 
            aplicarColorSidebar(e.target.value, colorPicker ? colorPicker.value : null); 
        });

        // Cambio desde el selector de color libre (círculo cromático)
        if(colorPicker) colorPicker.addEventListener('input', (e) => {
            localStorage.setItem('sidebarCustomColor', e.target.value);
            localStorage.setItem('sidebarColor', 'custom'); // Forzar modo custom
            if(colorMenu) colorMenu.value = 'custom';
            aplicarColorSidebar('custom', e.target.value);
        });

        if(rangeTable) rangeTable.addEventListener('input', (e) => { localStorage.setItem('tableDensity', e.target.value); aplicarDensidadTabla(e.target.value); });
        if(rangeFont) rangeFont.addEventListener('input', (e) => { localStorage.setItem('fontSize', e.target.value); aplicarFuenteGlobal(e.target.value); });
        
        radiosCard.forEach(radio => {
            radio.addEventListener('change', (e) => { localStorage.setItem('cardStyle', e.target.value); aplicarEstiloTarjetas(e.target.value); });
        });

        if(fontSelect) fontSelect.addEventListener('change', (e) => {
            localStorage.setItem('fontFamily', e.target.value);
            aplicarTipografia(e.target.value);
        });

        // Lógica de Subir Imagen de Fondo
        const bgInput = document.getElementById('bgImageInput');
        if(bgInput) {
            bgInput.addEventListener('change', function() {
                const file = this.files[0];
                if(file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            localStorage.setItem('customWallpaper', e.target.result);
                            aplicarFondo(e.target.result);
                            if(bgUrlInput) bgUrlInput.value = '';
                        } catch(err) {
                            alert("¡La imagen es muy pesada para guardarla en memoria! Intente con una más ligera o pegue un Enlace (URL).");
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Lógica de URL de Fondo
        if(bgUrlInput) {
            bgUrlInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                localStorage.setItem('customWallpaper', val);
                aplicarFondo(val);
            });
        }

        // Quitar Fondo
        const resetBgBtn = document.getElementById('resetBgBtn');
        if(resetBgBtn) {
            resetBgBtn.addEventListener('click', () => {
                localStorage.removeItem('customWallpaper');
                if(bgUrlInput) bgUrlInput.value = '';
                if(bgInput) bgInput.value = '';
                aplicarFondo('');
            });
        }
    });

    // ==========================================
    // FUNCIONES APLICADORAS (Render visual)
    // ==========================================
    
    function aplicarColorSidebar(tema, customColor = null) {
        let elemento = document.getElementById('sidebar-wrapper');
        if(!elemento) return;
        elemento.style.background = ''; 
        if(tema === 'dark') elemento.style.background = 'linear-gradient(180deg, #212529 0%, #000000 100%)';
        else if(tema === 'success') elemento.style.background = 'linear-gradient(180deg, #198754 0%, #146c43 100%)';
        else if(tema === 'danger') elemento.style.background = 'linear-gradient(180deg, #dc3545 0%, #b02a37 100%)';
        else if(tema === 'custom' && customColor) {
            // Aplica el color libre que elijas
            elemento.style.background = customColor;
        }
        else elemento.style.background = 'linear-gradient(180deg, #1e3c72 0%, #2a5298 100%)'; 
    }

    function aplicarTipografia(fuente) {
        if(fuente === 'default' || fuente === '') {
            document.body.style.fontFamily = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        } else {
            document.body.style.fontFamily = fuente;
        }
    }

    function aplicarFondo(urlBase64) {
        if (urlBase64) {
            // Coloca la imagen y le da un ligero oscurecimiento para que las letras no se pierdan
            document.body.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('${urlBase64}')`;
        } else {
            document.body.style.backgroundImage = 'none';
        }
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
            // Ignoramos la tarjeta del login para que no se dañe visualmente
            if(card.classList.contains('login-card')) return; 

            if(estilo === 'flat') { card.classList.remove('shadow-sm', 'shadow', 'shadow-lg'); card.classList.add('border'); } 
            else { card.classList.add('shadow-sm'); card.classList.remove('border'); }
        });
    }
    </script>
</body>
</html>