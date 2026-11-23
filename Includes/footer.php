           </div> <!-- Cierra el container-fluid -->
        </div> <!-- Cierra el page-content-wrapper -->
    </div> <!-- Cierra el wrapper principal -->

    <!-- Carga de JS de Bootstrap Local -->
    <script src="../CSS/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/script.js?v=<?= time() ?>"></script>

    <!-- ======================================================== -->
    <!-- MODAL DE BIENVENIDA / PREGUNTA DE TUTORIAL (1RA VEZ)     -->
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
                        <button type="button" class="btn btn-icon-theme rounded-pill fw-bold py-2" onclick="omitirTutorial()">
                            Omitir e ir directo al sistema
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TARJETA FLOTANTE DEL TUTORIAL MAESTRO (12 PASOS)         -->
    <!-- ======================================================== -->
    <div id="tour-card" class="card shadow-lg border-2 border-primary position-fixed rounded-4 d-none" style="width: 350px; z-index: 1090; bottom: 25px; right: 25px; transition: all 0.3s ease;">
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
                <button class="btn btn-sm btn-icon-theme rounded-pill px-3 fw-bold" id="tour-prev-btn" onclick="moverPasoTutorial(-1)">Atrás</button>
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" id="tour-next-btn" onclick="moverPasoTutorial(1)">Siguiente</button>
            </div>
        </div>
    </div>

    <!-- SCRIPT GLOBAL DE APARIENCIA, SIDEBAR Y TUTORIAL -->
<script>
    const ACCENT_PRESETS = {
        'default': '#0d6efd',
        'indigo':  '#6610f2',
        'emerald': '#10b981',
        'violet':  '#8b5cf6',
        'amber':   '#d97706',
        'crimson': '#e11d48',
        'teal':    '#0d9488'
    };

    function hexToRgbTuple(hex) {
        hex = (hex || '#0d6efd').replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const num = parseInt(hex, 16) || 0x0d6efd;
        const r = (num >> 16) & 255, g = (num >> 8) & 255, b = num & 255;
        return { r, g, b, rgb: `${r}, ${g}, ${b}` };
    }

    function darkenHex(hex, factor = 0.84) {
        const { r, g, b } = hexToRgbTuple(hex);
        const dr = Math.max(0, Math.round(r * factor));
        const dg = Math.max(0, Math.round(g * factor));
        const db = Math.max(0, Math.round(b * factor));
        return '#' + [dr, dg, db].map(x => x.toString(16).padStart(2, '0')).join('');
    }

    document.addEventListener("DOMContentLoaded", () => {
        // --- 1. SIDEBAR COLAPSABLE ---
        const wrapper = document.getElementById('wrapper');
        const menuToggle = document.getElementById('menu-toggle');
        if (wrapper && localStorage.getItem('sidebarCollapsed') === 'true') wrapper.classList.add('sidebar-collapsed');
        if (menuToggle && wrapper) {
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                wrapper.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', wrapper.classList.contains('sidebar-collapsed'));
            });
        }

        // --- 2. MODO OSCURO SINCRONIZADO + SOL/LUNA ---
        const switchModo = document.getElementById('switchModoOscuro');
        const quickThemeBtn = document.getElementById('quickThemeToggle');

        window.sincronizarTemaUI = function(esOscuro) {
            document.documentElement.setAttribute('data-bs-theme', esOscuro ? 'dark' : 'light');
            localStorage.setItem('modoOscuro', esOscuro ? 'activado' : 'desactivado');
            if (switchModo) switchModo.checked = esOscuro;
            const lblCard = document.getElementById('lblModoOscuroEstado');
            if (lblCard) lblCard.innerText = esOscuro ? 'Modo Oscuro' : 'Modo Claro';
            const lbl = document.getElementById('quickThemeLabel');
            const bdg = document.getElementById('quickThemeBadge');
            if (lbl) lbl.innerText = esOscuro ? 'Modo Claro' : 'Modo Oscuro';
            if (bdg) bdg.innerText = esOscuro ? 'Dark' : 'Light';
        };

        sincronizarTemaUI(localStorage.getItem('modoOscuro') === 'activado');
        if (switchModo) switchModo.addEventListener('change', (e) => sincronizarTemaUI(e.target.checked));
        if (quickThemeBtn) {
            quickThemeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sincronizarTemaUI(document.documentElement.getAttribute('data-bs-theme') !== 'dark');
            });
        }

        // --- 3. COLOR DE ACENTO (DROPDOWN + SELECTOR LIBRE) ---
        const accentSelect = document.getElementById('selectAccentColor');
        const accentPicker = document.getElementById('customAccentPicker');
        const savedAccent = localStorage.getItem('accentColor') || 'default';
        const savedCustomAccent = localStorage.getItem('accentCustomColor') || '#0d6efd';

        if (accentSelect) accentSelect.value = savedAccent;
        if (accentPicker) accentPicker.value = savedAccent === 'custom' ? savedCustomAccent : (ACCENT_PRESETS[savedAccent] || '#0d6efd');
        aplicarColorAcento(savedAccent, savedCustomAccent);

        if (accentSelect) {
            accentSelect.addEventListener('change', (e) => {
                const val = e.target.value;
                localStorage.setItem('accentColor', val);
                if (val !== 'custom' && accentPicker) {
                    accentPicker.value = ACCENT_PRESETS[val] || '#0d6efd';
                }
                aplicarColorAcento(val, accentPicker ? accentPicker.value : savedCustomAccent);
            });
        }
        if (accentPicker) {
            accentPicker.addEventListener('input', (e) => {
                localStorage.setItem('accentCustomColor', e.target.value);
                localStorage.setItem('accentColor', 'custom');
                if (accentSelect) accentSelect.value = 'custom';
                aplicarColorAcento('custom', e.target.value);
            });
        }

        // --- 4. CONTENEDORES, TEXTURAS, BOTONES, NAVBAR E INDICADOR DE MENÚ ---
        const savedCardStyle = localStorage.getItem('cardStyle') || 'shadow';
        aplicarEstiloContenedores(savedCardStyle);
        document.querySelectorAll('input[name="cardStyle"]').forEach(radio => {
            if (radio.value === savedCardStyle) radio.checked = true;
            radio.addEventListener('change', (e) => { localStorage.setItem('cardStyle', e.target.value); aplicarEstiloContenedores(e.target.value); });
        });

        const initSelectPref = (id, key, fn, defVal) => {
            const el = document.getElementById(id);
            const val = localStorage.getItem(key) || defVal;
            if (el) {
                el.value = val;
                el.addEventListener('change', (e) => { localStorage.setItem(key, e.target.value); fn(e.target.value); });
            }
            fn(val);
        };

        initSelectPref('selectBgPattern', 'bgPattern', aplicarBgPattern, 'solid');
        initSelectPref('selectBtnStyle', 'btnStyle', aplicarBtnStyle, 'default');
        initSelectPref('selectNavbarStyle', 'navbarStyle', aplicarNavbarStyle, 'solid');
        initSelectPref('selectSidebarLinkStyle', 'sidebarLinkStyle', aplicarSidebarLinkStyle, 'pill');

        // --- 5. REDONDEADO Y ACCESIBILIDAD ---
        const savedRadius = localStorage.getItem('borderRadiusLevel') || '2';
        const rangeRadius = document.getElementById('rangeBorderRadius');
        if (rangeRadius) {
            rangeRadius.value = savedRadius;
            rangeRadius.addEventListener('input', (e) => { localStorage.setItem('borderRadiusLevel', e.target.value); aplicarRedondeado(e.target.value); });
        }
        aplicarRedondeado(savedRadius);

        const swMotion = document.getElementById('switchReduceMotion');
        const isReduce = localStorage.getItem('reduceMotion') === 'true';
        document.body.classList.toggle('reduce-motion', isReduce);
        if (swMotion) {
            swMotion.checked = isReduce;
            swMotion.addEventListener('change', (e) => { localStorage.setItem('reduceMotion', e.target.checked); document.body.classList.toggle('reduce-motion', e.target.checked); });
        }

        const swContrast = document.getElementById('switchHighContrast');
        const isContrast = localStorage.getItem('highContrast') === 'true';
        document.body.classList.toggle('high-contrast', isContrast);
        if (swContrast) {
            swContrast.checked = isContrast;
            swContrast.addEventListener('change', (e) => { localStorage.setItem('highContrast', e.target.checked); document.body.classList.toggle('high-contrast', e.target.checked); });
        }

        // --- 6. SIDEBAR COLOR, TABLAS, FUENTE Y WALLPAPER + DIMMER ---
        const colorGuardado = localStorage.getItem('sidebarColor') || 'default';
        const colorCustom = localStorage.getItem('sidebarCustomColor') || '#1e3c72';
        const colorMenu = document.getElementById('selectMenuColor');
        const colorPicker = document.getElementById('customColorPicker');
        if (colorMenu) colorMenu.value = colorGuardado;
        if (colorPicker) colorPicker.value = colorCustom;
        aplicarColorSidebar(colorGuardado, colorCustom);

        const tableDensity = localStorage.getItem('tableDensity') || '2';
        const rangeTable = document.getElementById('rangeTableDensity');
        if (rangeTable) rangeTable.value = tableDensity;
        aplicarDensidadTabla(tableDensity);

        const fontSize = localStorage.getItem('fontSize') || '2';
        const rangeFont = document.getElementById('rangeFontSize');
        if (rangeFont) rangeFont.value = fontSize;
        aplicarFuenteGlobal(fontSize);

        const fontSaved = localStorage.getItem('fontFamily') || 'default';
        const fontSelect = document.getElementById('selectFontFamily');
        if (fontSelect) fontSelect.value = fontSaved;
        aplicarTipografia(fontSaved);

        const dimSaved = localStorage.getItem('wallpaperDim') || '35';
        const rangeDim = document.getElementById('rangeWallpaperDim');
        const lblDim = document.getElementById('lblWallpaperDim');
        if (rangeDim) rangeDim.value = dimSaved;
        if (lblDim) lblDim.innerText = dimSaved + '%';
        if (rangeDim) {
            rangeDim.addEventListener('input', (e) => {
                localStorage.setItem('wallpaperDim', e.target.value);
                if (lblDim) lblDim.innerText = e.target.value + '%';
                const bgActual = localStorage.getItem('customWallpaper');
                if (bgActual) aplicarFondo(bgActual);
            });
        }

        const bgSaved = localStorage.getItem('customWallpaper');
        if (bgSaved && bgSaved.trim() !== '') aplicarFondo(bgSaved);

        if (!localStorage.getItem('tutorial_realizado')) {
            setTimeout(() => {
                let modalDom = document.getElementById('modalBienvenidaTutorial');
                if (modalDom && typeof bootstrap !== 'undefined') new bootstrap.Modal(modalDom).show();
            }, 800);
        }

        if (colorMenu) colorMenu.addEventListener('change', (e) => { localStorage.setItem('sidebarColor', e.target.value); aplicarColorSidebar(e.target.value, colorPicker ? colorPicker.value : null); });
        if (colorPicker) colorPicker.addEventListener('input', (e) => {
            localStorage.setItem('sidebarCustomColor', e.target.value);
            localStorage.setItem('sidebarColor', 'custom');
            if (colorMenu) colorMenu.value = 'custom';
            aplicarColorSidebar('custom', e.target.value);
        });
        if (rangeTable) rangeTable.addEventListener('input', (e) => { localStorage.setItem('tableDensity', e.target.value); aplicarDensidadTabla(e.target.value); });
        if (rangeFont) rangeFont.addEventListener('input', (e) => { localStorage.setItem('fontSize', e.target.value); aplicarFuenteGlobal(e.target.value); });
        if (fontSelect) fontSelect.addEventListener('change', (e) => { localStorage.setItem('fontFamily', e.target.value); aplicarTipografia(e.target.value); });

        const bgInput = document.getElementById('bgImageInput');
        if (bgInput) {
            bgInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try { localStorage.setItem('customWallpaper', e.target.result); aplicarFondo(e.target.result); }
                        catch (err) { alert("Imagen muy pesada para memoria local. Use una más ligera o una URL."); }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        const bgUrlInput = document.getElementById('bgUrlInput');
        if (bgUrlInput) {
            bgUrlInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                if (val) { localStorage.setItem('customWallpaper', val); aplicarFondo(val); }
            });
        }
    });

    function aplicarEstiloContenedores(estilo) {
        document.body.classList.remove('cards-shadow', 'cards-flat', 'cards-glass');
        if (estilo === 'flat') document.body.classList.add('cards-flat');
        else if (estilo === 'glass') document.body.classList.add('cards-glass');
        else document.body.classList.add('cards-shadow');
    }

    function aplicarColorAcento(clave, customHex = '#0d6efd') {
        if (clave === 'default') {
            document.body.classList.remove('custom-accent');
            document.documentElement.style.removeProperty('--accent-color');
            document.documentElement.style.removeProperty('--accent-rgb');
            document.documentElement.style.removeProperty('--accent-hover');
            return;
        }
        const hex = (clave === 'custom') ? customHex : (ACCENT_PRESETS[clave] || '#0d6efd');
        const { rgb } = hexToRgbTuple(hex);
        const hoverHex = darkenHex(hex, 0.84);

        document.body.classList.add('custom-accent');
        document.documentElement.style.setProperty('--accent-color', hex);
        document.documentElement.style.setProperty('--accent-rgb', rgb);
        document.documentElement.style.setProperty('--accent-hover', hoverHex);
    }

    function aplicarBgPattern(pat) {
        document.body.classList.remove('bg-pattern-gradient', 'bg-pattern-dots', 'bg-pattern-grid');
        if (['gradient', 'dots', 'grid'].includes(pat)) document.body.classList.add('bg-pattern-' + pat);
    }

    function aplicarBtnStyle(st) {
        document.body.classList.remove('btn-style-pill', 'btn-style-square');
        if (st === 'pill' || st === 'square') document.body.classList.add('btn-style-' + st);
    }

    function aplicarNavbarStyle(st) {
        document.body.classList.remove('navbar-style-accent', 'navbar-style-glass');
        if (st === 'accent' || st === 'glass') document.body.classList.add('navbar-style-' + st);
    }

    function aplicarSidebarLinkStyle(st) {
        document.body.classList.remove('sidebar-style-block', 'sidebar-style-minimal');
        if (st === 'block' || st === 'minimal') document.body.classList.add('sidebar-style-' + st);
    }

    function aplicarRedondeado(nivel) {
        let radio = '1rem';
        if (nivel === '1') radio = '0.3rem';
        else if (nivel === '3') radio = '1.65rem';
        document.documentElement.style.setProperty('--radio', radio);
    }

    function aplicarFondo(urlBase64) {
        if (urlBase64 && urlBase64.trim() !== '') {
            const dim = (parseInt(localStorage.getItem('wallpaperDim') || '35', 10) / 100).toFixed(2);
            document.body.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, ${dim}), rgba(0, 0, 0, ${dim})), url('${urlBase64}')`;
            document.body.classList.add('has-wallpaper');
        } else {
            document.body.style.removeProperty('background-image');
            document.body.classList.remove('has-wallpaper');
        }
    }

    function aplicarTemaPreset(nombre) {
        const presets = {
            clasico:   { modo: 'desactivado', menu: 'default', acento: 'default', card: 'shadow', radio: '2', pat: 'solid',    btn: 'default', nav: 'solid',  link: 'pill' },
            noche:     { modo: 'activado',    menu: 'dark',    acento: 'indigo',  card: 'glass',  radio: '2', pat: 'gradient', btn: 'pill',    nav: 'glass',  link: 'block' },
            esmeralda: { modo: 'activado',    menu: 'success', acento: 'emerald', card: 'shadow', radio: '3', pat: 'dots',     btn: 'pill',    nav: 'accent', link: 'block' },
            papel:     { modo: 'desactivado', menu: 'dark',    acento: 'default', card: 'flat',   radio: '1', pat: 'grid',     btn: 'square',  nav: 'solid',  link: 'minimal' },
            carmesi:   { modo: 'desactivado', menu: 'danger',  acento: 'crimson', card: 'shadow', radio: '2', pat: 'gradient', btn: 'pill',    nav: 'accent', link: 'pill' }
        };
        const p = presets[nombre];
        if (!p) return;

        localStorage.setItem('modoOscuro', p.modo);
        localStorage.setItem('sidebarColor', p.menu);
        localStorage.setItem('accentColor', p.acento);
        localStorage.setItem('cardStyle', p.card);
        localStorage.setItem('borderRadiusLevel', p.radio);
        localStorage.setItem('bgPattern', p.pat);
        localStorage.setItem('btnStyle', p.btn);
        localStorage.setItem('navbarStyle', p.nav);
        localStorage.setItem('sidebarLinkStyle', p.link);
        window.location.reload();
    }

    function restablecerApariencia() {
        if (!confirm('¿Restablecer todas las opciones visuales a sus valores originales?')) return;
        ['modoOscuro', 'sidebarColor', 'sidebarCustomColor', 'tableDensity', 'fontSize', 'fontFamily', 'customWallpaper', 'wallpaperDim', 'cardStyle', 'accentColor', 'accentCustomColor', 'borderRadiusLevel', 'bgPattern', 'btnStyle', 'navbarStyle', 'sidebarLinkStyle', 'reduceMotion', 'highContrast'].forEach(k => localStorage.removeItem(k));
        window.location.reload();
    }

    window.quitarFondoCompleto = function() {
        localStorage.removeItem('customWallpaper');
        document.body.style.backgroundImage = 'none';
        document.body.style.removeProperty('background-image');
        document.body.classList.remove('has-wallpaper');
        const bgUrlInput = document.getElementById('bgUrlInput');
        const bgInput = document.getElementById('bgImageInput');
        if (bgUrlInput) bgUrlInput.value = '';
        if (bgInput) bgInput.value = '';
        let toast = document.createElement('div');
        toast.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg';
        toast.style.zIndex = '9999';
        toast.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Fondo eliminado.';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    };

    // ========================================================
    // TOUR MAESTRO EXTENDIDO (12 PASOS COMPLETOS)
    // ========================================================
    let pasoActual = 0;
    const pasosTutorial = [
        { titulo: "1. Bienvenido al Portal RRHH", desc: "Este sistema te permite gestionar todo el talento humano de la institución: expedientes, nómina, asistencias, horarios y jerarquías.", selector: ".sidebar-heading" },
        { titulo: "2. Menú de Navegación", desc: "Accede rápidamente a todos los módulos. Puedes colapsarlo a modo compacto de iconos con el botón superior.", selector: "#sidebar-wrapper" },
        { titulo: "3. Panel Principal (Dashboard)", desc: "Monitorea en tiempo real estadísticas, gráfica de personal, cumpleañeros del mes, eventos y auditoría.", selector: "a[href='dashboard.php']" },
        { titulo: "4. Módulos de Personal", desc: "Cada categoría (Docente, Admin, Obrero) cuenta con su directorio detallado, contactos telefónicos y cargos oficiales.", selector: "a[href='personal.php?tipo=1']" },
        { titulo: "5. Tabla Interactiva y Selección Múltiple", desc: "Ordena alfabéticamente haciendo clic en cualquier columna, arrastra columnas o selecciona casillas para acciones masivas.", selector: "th.sortable" },
        { titulo: "6. Expediente MPPE y Carga Múltiple", desc: "Al registrar o editar a una persona, puedes subir múltiples documentos digitales o vincular archivos del repositorio.", selector: "button[data-bs-target='#modalRegistro']" },
        { titulo: "7. Organigrama Visual de Jerarquías", desc: "Árbol interactivo en cuadrícula con zoom, reasignación por arrastre y exportación offline a PNG, PDF o SVG.", selector: "a[href='organigrama.php']" },
        { titulo: "8. Horario Dinámico por Colores", desc: "Organización visual de turnos semanales clasificados por colores.", selector: "a[href='calendario.php']" },
        { titulo: "9. Control de Novedades y Asistencias", desc: "Registro rápido por cédula (entrada/salida), semáforo de 7 días y cierre automático de jornada.", selector: "a[href='asistencias.php']" },
        { titulo: "10. Cronograma y Archivo Digital", desc: "Agenda institucional y repositorio documental desacoplado con verificación de integridad SHA-256.", selector: "a[href='documentos.php']" },
        { titulo: "11. Auditoría y Trazabilidad", desc: "Bitácora de seguridad que registra cada creación, actualización, eliminación o inicio de sesión.", selector: "a[href='historial.php']" },
        { titulo: "12. Personalización y Exportación", desc: "Ajusta el tema claro/oscuro, colores, tipografía, fondo de pantalla o emite reportes en Excel, Word, PDF y JSON.", selector: "a[href='configuracion.php']" }
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
        if (pasoActual >= pasosTutorial.length) { finalizarTutorial(); return; }
        mostrarPasoTutorial();
    }
    function mostrarPasoTutorial() {
        quitarResaltados();
        let paso = pasosTutorial[pasoActual];
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
                el.style.outline = ''; el.style.boxShadow = '';
            }
        });
    }
    window.iniciarTutorialManual = function() {
        pasoActual = 0;
        document.getElementById('tour-card').classList.remove('d-none');
        mostrarPasoTutorial();
    };

    function aplicarColorSidebar(tema, customColor = null) {
        let el = document.getElementById('sidebar-wrapper');
        if (!el) return;
        el.style.background = '';
        if (tema === 'dark') el.style.background = 'linear-gradient(180deg, #212529 0%, #000000 100%)';
        else if (tema === 'success') el.style.background = 'linear-gradient(180deg, #198754 0%, #146c43 100%)';
        else if (tema === 'danger') el.style.background = 'linear-gradient(180deg, #dc3545 0%, #b02a37 100%)';
        else if (tema === 'custom' && customColor) el.style.background = customColor;
        else el.style.background = 'var(--color-sidebar)';
    }
    function aplicarTipografia(fuente) {
        if (fuente === 'default' || fuente === '') document.body.style.fontFamily = 'var(--fuente-sistema)';
        else document.body.style.fontFamily = fuente;
    }
    function aplicarFondo(urlBase64) {
        if (urlBase64 && urlBase64.trim() !== '') {
            document.body.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)), url('${urlBase64}')`;
            document.body.classList.add('has-wallpaper');
        } else {
            document.body.style.backgroundImage = 'none';
            document.body.classList.remove('has-wallpaper');
        }
    }
    function aplicarDensidadTabla(valor) {
        document.querySelectorAll('.table').forEach(tabla => {
            tabla.classList.remove('table-sm');
            tabla.style.fontSize = '';
            tabla.querySelectorAll('th, td').forEach(c => c.style.padding = '');
            if (valor === '1') { tabla.classList.add('table-sm'); tabla.style.fontSize = '0.85rem'; }
            else if (valor === '3') { tabla.querySelectorAll('th, td').forEach(c => c.style.padding = '1.2rem 0.5rem'); }
        });
    }
    function aplicarFuenteGlobal(valor) {
        if (valor === '1') document.body.style.fontSize = '0.85rem';
        else if (valor === '3') document.body.style.fontSize = '1.1rem';
        else document.body.style.fontSize = '1rem';
    }

    // ========================================================
    // MOTOR GLOBAL DE ATAJOS DE TECLADO CONFIGURABLES
    // ========================================================
    const CATALOGO_ATAJOS = [
        { id: 'busqueda_avanzada', nombre: 'Búsqueda Avanzada', desc: 'Abre el buscador global del sistema', def: 'Ctrl+K' },
        { id: 'abrir_modal_nuevo', nombre: 'Nuevo Registro / Subida', desc: 'Abre el modal de creación de la página actual', def: 'Alt+N' },
        { id: 'toggle_tema',       nombre: 'Cambiar Tema Claro/Oscuro', desc: 'Alterna la apariencia visual al instante', def: 'Alt+T' },
        { id: 'toggle_sidebar',    nombre: 'Contraer/Expandir Menú', desc: 'Alterna el sidebar compacto en escritorio', def: 'Alt+M' },
        { id: 'ir_dashboard',      nombre: 'Ir a Dashboard', desc: 'Panel ejecutivo principal', def: 'Alt+1' },
        { id: 'ir_docentes',       nombre: 'Ir a Personal Docente', desc: 'Directorio de docentes', def: 'Alt+2' },
        { id: 'ir_admins',         nombre: 'Ir a Personal Administrativo', desc: 'Directorio administrativo', def: 'Alt+3' },
        { id: 'ir_obreros',        nombre: 'Ir a Personal Obrero', desc: 'Directorio obrero', def: 'Alt+4' },
        { id: 'ir_organigrama',    nombre: 'Ir al Organigrama', desc: 'Árbol jerárquico institucional', def: 'Alt+O' },
        { id: 'ir_calendario',     nombre: 'Ir a Calendario Laboral', desc: 'Turnos y horarios semanales', def: 'Alt+H' },
        { id: 'ir_asistencias',    nombre: 'Ir a Control de Asistencias', desc: 'Registro rápido y novedades', def: 'Alt+A' },
        { id: 'ir_cronograma',     nombre: 'Ir a Cronograma', desc: 'Agenda diaria de eventos', def: 'Alt+C' },
        { id: 'ir_documentos',     nombre: 'Ir a Archivo Digital', desc: 'Repositorio documental SHA-256', def: 'Alt+D' },
        { id: 'ir_configuracion',  nombre: 'Ir a Configuración', desc: 'Ajustes, catálogos y atajos', def: 'Alt+S' }
    ];

    const ATAJOS_RESERVADOS = ['Ctrl+C', 'Ctrl+V', 'Ctrl+X', 'Ctrl+A', 'Ctrl+Z', 'Ctrl+Y', 'Ctrl+R', 'Ctrl+W', 'Ctrl+T', 'Ctrl+N', 'Ctrl+P', 'Ctrl+F', 'F5', 'F11', 'F12'];

    function obtenerAtajosActivos() {
        let guardados = {};
        try { guardados = JSON.parse(localStorage.getItem('atajosPersonalizados') || '{}'); } catch (e) {}
        const mapa = {};
        CATALOGO_ATAJOS.forEach(item => {
            mapa[item.id] = guardados[item.id] !== undefined ? guardados[item.id] : item.def;
        });
        return mapa;
    }

    function normalizarCombinacion(e) {
        if (['Control', 'Shift', 'Alt', 'Meta', 'CapsLock', 'Tab', 'Escape'].includes(e.key)) return null;
        let parts = [];
        if (e.ctrlKey || e.metaKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey) parts.push('Shift');

        let tecla = '';
        if (e.code && e.code.startsWith('Key')) tecla = e.code.slice(3);
        else if (e.code && e.code.startsWith('Digit')) tecla = e.code.slice(5);
        else if (e.code && e.code.startsWith('Numpad')) tecla = e.code.slice(6);
        else tecla = e.key.length === 1 ? e.key.toUpperCase() : e.key;

        const esFuncion = /^F([2-9]|10)$/.test(tecla);
        if (parts.length === 0 && !esFuncion) return null; // Requiere Ctrl, Alt o F2-F10
        if (parts.length === 1 && parts[0] === 'Shift' && !esFuncion) return null;

        parts.push(tecla);
        return parts.join('+');
    }

    // Actualizar placeholder de búsqueda en navbar con el atajo real configurado
    document.addEventListener("DOMContentLoaded", () => {
        const atajos = obtenerAtajosActivos();
        const navInput = document.getElementById('navSearchInput');
        if (navInput && atajos.busqueda_avanzada) {
            navInput.placeholder = `Buscar cédula, nombre, archivo... (${atajos.busqueda_avanzada})`;
        }
        const swAtajos = document.getElementById('switchAtajosActivos');
        if (swAtajos) {
            swAtajos.checked = localStorage.getItem('atajosHabilitados') !== 'false';
            swAtajos.addEventListener('change', (e) => {
                localStorage.setItem('atajosHabilitados', e.target.checked ? 'true' : 'false');
            });
        }
    });

    // Ejecutor global
    let grabandoAtajoId = null;
    document.addEventListener('keydown', (e) => {
        if (grabandoAtajoId) return; // En modo grabación en Configuración
        if (localStorage.getItem('atajosHabilitados') === 'false') return;

        const combo = normalizarCombinacion(e);
        if (!combo) return;

        const atajos = obtenerAtajosActivos();
        const accionId = Object.keys(atajos).find(k => atajos[k] === combo);
        if (!accionId) return;

        e.preventDefault();
        switch (accionId) {
            case 'busqueda_avanzada': if (typeof abrirModalBusquedaAvanzada === 'function') abrirModalBusquedaAvanzada(); break;
            case 'toggle_tema':       document.getElementById('quickThemeToggle')?.click(); break;
            case 'toggle_sidebar':    document.getElementById('menu-toggle')?.click(); break;
            case 'ir_dashboard':      window.location.href = 'dashboard.php'; break;
            case 'ir_docentes':       window.location.href = 'personal.php?tipo=1'; break;
            case 'ir_admins':         window.location.href = 'personal.php?tipo=2'; break;
            case 'ir_obreros':        window.location.href = 'personal.php?tipo=3'; break;
            case 'ir_organigrama':    window.location.href = 'organigrama.php'; break;
            case 'ir_calendario':     window.location.href = 'calendario.php'; break;
            case 'ir_asistencias':    window.location.href = 'asistencias.php'; break;
            case 'ir_cronograma':     window.location.href = 'cronograma.php'; break;
            case 'ir_documentos':     window.location.href = 'documentos.php'; break;
            case 'ir_configuracion':  window.location.href = 'configuracion.php'; break;
            case 'abrir_modal_nuevo':
                const btnNuevo = document.querySelector('button[data-bs-target="#modalRegistro"], button[data-bs-target="#modalSubirDoc"], button[data-bs-target="#modalTurno"], button[onclick="abrirModalAgregar()"]');
                if (btnNuevo) btnNuevo.click();
                break;
        }
    });

    // UI de Configuración de Atajos (solo actúa en configuracion.php)
    function renderizarTablaAtajos() {
        const tbody = document.getElementById('tablaConfigAtajos');
        if (!tbody) return;
        const atajos = obtenerAtajosActivos();
        tbody.innerHTML = '';

        CATALOGO_ATAJOS.forEach(item => {
            const combo = atajos[item.id];
            const badgeHtml = combo
                ? combo.split('+').map(k => `<kbd class="bg-body-secondary text-body border">${k}</kbd>`).join(' + ')
                : '<span class="text-muted small">Sin asignar</span>';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold">${item.nombre}</td>
                <td class="small text-muted">${item.desc}</td>
                <td class="text-center" id="celda_atajo_${item.id}">${badgeHtml}</td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold me-1" id="btn_grabar_${item.id}" onclick="iniciarGrabacionAtajo('${item.id}')">
                        <i class="bi bi-record-circle me-1"></i>Cambiar
                    </button>
                    <button type="button" class="btn btn-sm btn-icon-theme rounded-circle" onclick="limpiarAtajo('${item.id}')" title="Desactivar atajo">
                        <i class="bi bi-x-lg text-danger"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function iniciarGrabacionAtajo(accionId) {
        grabandoAtajoId = accionId;
        renderizarTablaAtajos();
        const btn = document.getElementById('btn_grabar_' + accionId);
        const celda = document.getElementById('celda_atajo_' + accionId);
        if (btn) {
            btn.classList.replace('btn-outline-primary', 'btn-warning');
            btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1"></span>Presiona teclas...';
        }
        if (celda) celda.innerHTML = '<span class="badge bg-warning text-dark">Esperando combinación (Esc para cancelar)</span>';

        const capturador = (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (e.key === 'Escape') {
                grabandoAtajoId = null;
                document.removeEventListener('keydown', capturador, true);
                renderizarTablaAtajos();
                return;
            }
            const combo = normalizarCombinacion(e);
            if (!combo) return;

            if (ATAJOS_RESERVADOS.includes(combo)) {
                alert(`La combinación "${combo}" está reservada por el navegador/sistema. Usa otra (ej: Alt + Letra o Ctrl + Shift + Letra).`);
                return;
            }

            const atajos = obtenerAtajosActivos();
            const conflicto = Object.keys(atajos).find(k => k !== accionId && atajos[k] === combo);
            if (conflicto) {
                const itemConf = CATALOGO_ATAJOS.find(x => x.id === conflicto);
                if (!confirm(`"${combo}" ya está asignado a "${itemConf?.nombre}". ¿Deseas reasignarlo aquí?`)) return;
                atajos[conflicto] = '';
            }

            atajos[accionId] = combo;
            localStorage.setItem('atajosPersonalizados', JSON.stringify(atajos));
            grabandoAtajoId = null;
            document.removeEventListener('keydown', capturador, true);
            renderizarTablaAtajos();

            const navInput = document.getElementById('navSearchInput');
            if (accionId === 'busqueda_avanzada' && navInput) {
                navInput.placeholder = `Buscar cédula, nombre, archivo... (${combo})`;
            }
        };

        document.addEventListener('keydown', capturador, true);
    }

    function limpiarAtajo(accionId) {
        const atajos = obtenerAtajosActivos();
        atajos[accionId] = '';
        localStorage.setItem('atajosPersonalizados', JSON.stringify(atajos));
        renderizarTablaAtajos();
    }

    function restablecerAtajosDefault() {
        if (confirm('¿Restablecer todos los atajos de teclado a sus valores predeterminados?')) {
            localStorage.removeItem('atajosPersonalizados');
            localStorage.setItem('atajosHabilitados', 'true');
            const sw = document.getElementById('switchAtajosActivos');
            if (sw) sw.checked = true;
            renderizarTablaAtajos();
        }
    }
    </script>
</body>
</html>