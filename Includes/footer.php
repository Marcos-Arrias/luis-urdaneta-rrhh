</div> <!-- Cierra el container-fluid -->
        </div> <!-- Cierra el page-content-wrapper -->
    </div> <!-- Cierra el wrapper principal -->

    <!-- Carga de JS de Bootstrap Local -->
    <script src="../CSS/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/script.js?v=<?= time() ?>"></script>

    <!-- ======================================================== -->
    <!-- MODAL DE BIENVENIDA / PREGUNTA DE TUTORIAL (1RA VEZ) -->
    <!-- ======================================================== -->
    <div class="modal fade" id="modalBienvenidaTutorial" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-center p-4 rounded-4">
                <div class="modal-body p-0">
                    <div class="mb-3">
                        <i class="bi bi-mortarboard-fill text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Portal RRHH Urdaneta</h3>
                    <p class="text-primary fw-bold small mb-3">U.E.N Luis Urdaneta</p>
                    <p class="text-muted small mb-4">¿Es tu primera vez administrando el sistema? Te invitamos a realizar un <strong>recorrido guiado completo</strong> para aprender a gestionar expedientes, organigramas, novedades, asistencias y personalizaciones.</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary rounded-pill fw-bold py-2 shadow-sm" onclick="iniciarRecorridoGuiado()">
                            <i class="bi bi-compass-fill me-1"></i> Iniciar Tour Maestro Completo
                        </button>
                        <button type="button" class="btn btn-light rounded-pill fw-bold py-2 border text-muted" onclick="omitirTutorial()">
                            Omitir e ir directo al sistema
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TARJETA FLOTANTE DEL TUTORIAL MAESTRO (12 PASOS) -->
    <!-- ======================================================== -->
    <div id="tour-card" class="card shadow-lg border-2 border-primary position-fixed rounded-4 d-none" style="width: 350px; z-index: 1090; bottom: 25px; right: 25px; transition: all 0.3s ease;">
        <!-- Barra de progreso superior -->
        <div class="progress rounded-top-4" style="height: 6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="tour-progress" style="width: 10%;"></div>
        </div>
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-primary text-white fw-bold px-2 py-1 rounded-pill" id="tour-step-badge">Paso 1 de 12</span>
                <button type="button" class="btn-close small" onclick="finalizarTutorial()" title="Cerrar Guía"></button>
            </div>
            <h6 class="fw-bold mb-1 text-primary" id="tour-title">Título</h6>
            <p class="small text-muted mb-3" id="tour-desc" style="line-height: 1.4;">Descripción...</p>
            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <button class="btn btn-sm btn-light border rounded-pill px-3 fw-bold" id="tour-prev-btn" onclick="moverPasoTutorial(-1)">Atrás</button>
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" id="tour-next-btn" onclick="moverPasoTutorial(1)">Siguiente</button>
            </div>
        </div>
    </div>

    <!-- SCRIPT DE APARIENCIA + MOTOR EXTENDIDO DEL TUTORIAL -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // --- 1. APARIENCIA ---
        const switchModo = document.getElementById('switchModoOscuro');
        if (localStorage.getItem('modoOscuro') === 'activado') { 
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if(switchModo) switchModo.checked = true;
        }

        const colorGuardado = localStorage.getItem('sidebarColor') || 'default';
        const colorCustom = localStorage.getItem('sidebarCustomColor') || '#1e3c72';
        const colorMenu = document.getElementById('selectMenuColor');
        const colorPicker = document.getElementById('customColorPicker');
        if(colorMenu) colorMenu.value = colorGuardado;
        if(colorPicker) colorPicker.value = colorCustom;
        aplicarColorSidebar(colorGuardado, colorCustom);

        const tableDensity = localStorage.getItem('tableDensity') || '2';
        const rangeTable = document.getElementById('rangeTableDensity');
        if(rangeTable) rangeTable.value = tableDensity;
        aplicarDensidadTabla(tableDensity);

        const fontSize = localStorage.getItem('fontSize') || '2';
        const rangeFont = document.getElementById('rangeFontSize');
        if(rangeFont) rangeFont.value = fontSize;
        aplicarFuenteGlobal(fontSize);

        const fontSaved = localStorage.getItem('fontFamily') || 'default';
        const fontSelect = document.getElementById('selectFontFamily');
        if(fontSelect) fontSelect.value = fontSaved;
        aplicarTipografia(fontSaved);

        // Cargar fondo si existe
        const bgSaved = localStorage.getItem('customWallpaper');
        if(bgSaved && bgSaved.trim() !== '') {
            aplicarFondo(bgSaved);
        }

        // --- 2. DETECCIÓN DE PRIMERA VEZ (TUTORIAL) ---
        if (!localStorage.getItem('tutorial_realizado')) {
            setTimeout(() => {
                let modalBienvenida = new bootstrap.Modal(document.getElementById('modalBienvenidaTutorial'));
                modalBienvenida.show();
            }, 800);
        }

        // --- 3. LISTENERS DE APARIENCIA EN TIEMPO REAL ---
        if(switchModo) switchModo.addEventListener('change', (e) => {
            let estado = e.target.checked ? 'activado' : 'desactivado';
            document.documentElement.setAttribute('data-bs-theme', estado === 'activado' ? 'dark' : 'light');
            localStorage.setItem('modoOscuro', estado);
        });

        if(colorMenu) colorMenu.addEventListener('change', (e) => { 
            localStorage.setItem('sidebarColor', e.target.value); 
            aplicarColorSidebar(e.target.value, colorPicker ? colorPicker.value : null); 
        });

        if(colorPicker) colorPicker.addEventListener('input', (e) => {
            localStorage.setItem('sidebarCustomColor', e.target.value);
            localStorage.setItem('sidebarColor', 'custom');
            if(colorMenu) colorMenu.value = 'custom';
            aplicarColorSidebar('custom', e.target.value);
        });

        if(rangeTable) rangeTable.addEventListener('input', (e) => { localStorage.setItem('tableDensity', e.target.value); aplicarDensidadTabla(e.target.value); });
        if(rangeFont) rangeFont.addEventListener('input', (e) => { localStorage.setItem('fontSize', e.target.value); aplicarFuenteGlobal(e.target.value); });
        
        if(fontSelect) fontSelect.addEventListener('change', (e) => {
            localStorage.setItem('fontFamily', e.target.value);
            aplicarTipografia(e.target.value);
        });

        // Carga de archivo de fondo
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
                        } catch(err) {
                            alert("¡La imagen es muy pesada para guardarla en memoria! Use una más ligera o pegue un enlace (URL).");
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Carga de URL de fondo
        const bgUrlInput = document.getElementById('bgUrlInput');
        if(bgUrlInput) {
            bgUrlInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                if(val) {
                    localStorage.setItem('customWallpaper', val);
                    aplicarFondo(val);
                }
            });
        }
    });

    // ========================================================
    // FUNCIÓN DE QUITAR FONDO (100% CORREGIDA)
    // ========================================================
    window.quitarFondoCompleto = function() {
        // 1. Borrar de localStorage
        localStorage.removeItem('customWallpaper');
        
        // 2. Limpiar estilos aplicados en el body
        document.body.style.backgroundImage = 'none';
        document.body.style.removeProperty('background-image');
        
        // 3. Limpiar inputs visuales
        const bgUrlInput = document.getElementById('bgUrlInput');
        const bgInput = document.getElementById('bgImageInput');
        if (bgUrlInput) bgUrlInput.value = '';
        if (bgInput) bgInput.value = '';
        
        // 4. Pequeña animación de confirmación
        let toast = document.createElement('div');
        toast.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg';
        toast.style.zIndex = '9999';
        toast.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Fondo eliminado. Se restableció el fondo del sistema.';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    };

    // ========================================================
    // TOUR MAESTRO EXTENDIDO (12 PASOS COMPLETOS)
    // ========================================================
    let pasoActual = 0;
    const pasosTutorial = [
        {
            titulo: "1. Bienvenido al Portal RRHH",
            desc: "Este sistema te permite gestionar todo el talento humano de la institución: expedientes, nómina, asistencias, horarios y jerarquías.",
            selector: ".sidebar-heading"
        },
        {
            titulo: "2. Menú de Navegación",
            desc: "Accede rápidamente a todos los módulos. Puedes colapsar este menú con el botón de hamburguesa superior para mayor espacio.",
            selector: "#sidebar-wrapper"
        },
        {
            titulo: "3. Panel Principal (Dashboard)",
            desc: "Monitorea en tiempo real los contadores globales divididos en Docentes, Administrativos y Obreros.",
            selector: "a[href='dashboard.php']"
        },
        {
            titulo: "4. Módulos de Personal",
            desc: "Cada categoría (Docente, Admin, Obrero) cuenta con su directorio detallado, contactos telefónicos y cargos oficiales.",
            selector: "a[href='personal.php?tipo=1']"
        },
        {
            titulo: "5. Tabla Interactiva y Selección Múltiple",
            desc: "Puedes ordenar alfabéticamente haciendo clic en cualquier columna, arrastrar columnas para reorganizarlas y seleccionar casillas para acciones masivas (Eliminar o Cambiar de Cargo).",
            selector: "th.sortable"
        },
        {
            titulo: "6. Expediente MPPE y Carga Múltiple",
            desc: "Al registrar o editar a una persona, puedes adjuntar múltiples documentos digitales a la vez (cédulas, títulos, certificados médicos y fotos carnet).",
            selector: "button[data-bs-target='#modalRegistro']"
        },
        {
            titulo: "7. Organigrama Visual de Jerarquías",
            desc: "Árbol interactivo en cuadrícula. Puedes hacer zoom con la rueda del ratón, arrastrar el lienzo, mover jefes y exportar el organigrama a PDF, PNG o SVG.",
            selector: "a[href='organigrama.php']"
        },
        {
            titulo: "8. Horario Dinámico por Colores",
            desc: "Organización visual de turnos semanales (Lunes a Domingo) clasificados por colores para distribuir clases, labores de oficina y guardias.",
            selector: "a[href='calendario.php']"
        },
        {
            titulo: "9. Control de Novedades y Asistencias",
            desc: "Semáforo interactivo de 7 días continuos. Registra ausencias, retardos, permisos justificados y asistencias regulares con navegación ágil día a día.",
            selector: "a[href='asistencias.php']"
        },
        {
            titulo: "10. Cronograma de Actividades",
            desc: "Agenda diaria con vinculación de múltiples participantes a asambleas, reuniones de consejo docente y jornadas institucionales.",
            selector: "a[href='cronograma.php']"
        },
        {
            titulo: "11. Auditoría y Trazabilidad",
            desc: "Bitácora de seguridad que guarda un registro inmutable de cada creación, actualización, eliminación o inicio de sesión en el sistema.",
            selector: "a[href='historial.php']"
        },
        {
            titulo: "12. Personalización y Exportación",
            desc: "Desde Ajustes puedes cambiar al Modo Oscuro, elegir colores libres con el círculo cromático, cambiar fuentes, subir tu propio fondo o emitir reportes en Excel, Word, PDF y PowerPoint.",
            selector: "a[href='configuracion.php']"
        }
    ];

    function iniciarRecorridoGuiado() {
        bootstrap.Modal.getInstance(document.getElementById('modalBienvenidaTutorial'))?.hide();
        pasoActual = 0;
        document.getElementById('tour-card').classList.remove('d-none');
        mostrarPasoTutorial();
    }

    function omitirTutorial() {
        bootstrap.Modal.getInstance(document.getElementById('modalBienvenidaTutorial'))?.hide();
        localStorage.setItem('tutorial_realizado', 'true');
    }

    function finalizarTutorial() {
        document.getElementById('tour-card').classList.add('d-none');
        localStorage.setItem('tutorial_realizado', 'true');
        quitarResaltados();
    }

    function moverPasoTutorial(direccion) {
        pasoActual += direccion;
        if (pasoActual < 0) pasoActual = 0;
        if (pasoActual >= pasosTutorial.length) {
            finalizarTutorial();
            return;
        }
        mostrarPasoTutorial();
    }

    function mostrarPasoTutorial() {
        quitarResaltados();
        let paso = pasosTutorial[pasoActual];
        
        // Actualizar barra de progreso
        let porcentaje = Math.round(((pasoActual + 1) / pasosTutorial.length) * 100);
        document.getElementById('tour-progress').style.width = porcentaje + '%';
        
        document.getElementById('tour-step-badge').innerText = `Paso ${pasoActual + 1} de ${pasosTutorial.length}`;
        document.getElementById('tour-title').innerText = paso.titulo;
        document.getElementById('tour-desc').innerText = paso.desc;
        document.getElementById('tour-prev-btn').disabled = (pasoActual === 0);
        document.getElementById('tour-next-btn').innerText = (pasoActual === pasosTutorial.length - 1) ? "Finalizar Tour" : "Siguiente";

        if (paso.selector) {
            let el = document.querySelector(paso.selector);
            if (el) {
                el.style.outline = "4px solid #ffca28";
                el.style.boxShadow = "0 0 20px rgba(255, 202, 40, 0.9)";
                el.style.borderRadius = "8px";
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    function quitarResaltados() {
        document.querySelectorAll('*').forEach(el => {
            if (el.style.outline && el.style.outline.includes('rgb(255, 202, 40)')) {
                el.style.outline = '';
                el.style.boxShadow = '';
            }
        });
    }

    // DISPARADOR MANUAL DESDE AJUSTES
    window.iniciarTutorialManual = function() {
        pasoActual = 0;
        document.getElementById('tour-card').classList.remove('d-none');
        mostrarPasoTutorial();
    };

    // FUNCIONES APLICADORAS VISUALES
    function aplicarColorSidebar(tema, customColor = null) {
        let elemento = document.getElementById('sidebar-wrapper');
        if(!elemento) return;
        elemento.style.background = ''; 
        if(tema === 'dark') elemento.style.background = 'linear-gradient(180deg, #212529 0%, #000000 100%)';
        else if(tema === 'success') elemento.style.background = 'linear-gradient(180deg, #198754 0%, #146c43 100%)';
        else if(tema === 'danger') elemento.style.background = 'linear-gradient(180deg, #dc3545 0%, #b02a37 100%)';
        else if(tema === 'custom' && customColor) elemento.style.background = customColor;
        else elemento.style.background = 'linear-gradient(180deg, #1e3c72 0%, #2a5298 100%)'; 
    }

    function aplicarTipografia(fuente) {
        if(fuente === 'default' || fuente === '') document.body.style.fontFamily = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        else document.body.style.fontFamily = fuente;
    }

    function aplicarFondo(urlBase64) {
        if (urlBase64 && urlBase64.trim() !== '') {
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
            if(valor === '1') { tabla.classList.add('table-sm'); tabla.style.fontSize = '0.85rem'; } 
            else if(valor === '3') { tabla.querySelectorAll('th, td').forEach(c => c.style.padding = '1.2rem 0.5rem'); }
        });
    }

    function aplicarFuenteGlobal(valor) {
        if(valor === '1') document.body.style.fontSize = '0.85rem';
        else if(valor === '3') document.body.style.fontSize = '1.1rem';
        else document.body.style.fontSize = '1rem';
    }
    </script>
</body>
</html>