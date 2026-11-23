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
                    <p class="text-muted small mb-4">¿Es tu primera vez administrando el sistema? Realiza el <strong>Tour Maestro Interactivo</strong> que recorrerá en vivo cada página, pestaña y formulario del portal explicándote cómo usarlos.</p>
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
    <!-- TARJETA FLOTANTE DEL TOUR MAESTRO MULTI-PÁGINA           -->
    <!-- ======================================================== -->
    <div id="tour-card" class="card shadow-lg border-2 border-primary position-fixed rounded-4 d-none" style="width: 370px; max-width: 92vw; z-index: 1095; bottom: 22px; right: 22px; transition: all 0.25s ease;">
        <div class="progress rounded-top-4" style="height: 6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="tour-progress" style="width: 5%;"></div>
        </div>
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white fw-bold px-2 py-1 rounded-pill" id="tour-step-badge">Paso 1</span>
                    <span class="badge bg-body-secondary text-body border small" id="tour-location-badge">Dashboard</span>
                </div>
                <button type="button" class="btn-close small" onclick="finalizarTutorial()" title="Finalizar Tour"></button>
            </div>
            <h6 class="fw-bold mb-1 text-primary" id="tour-title">Título</h6>
            <p class="small text-muted mb-3" id="tour-desc" style="line-height: 1.45;">Descripción...</p>
            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <button type="button" class="btn btn-sm btn-icon-theme rounded-pill px-3 fw-bold" id="tour-prev-btn" onclick="moverPasoTutorial(-1)">
                    <i class="bi bi-arrow-left me-1"></i>Atrás
                </button>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" id="tour-next-btn" onclick="moverPasoTutorial(1)">
                    Siguiente<i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- SCRIPT GLOBAL DE APARIENCIA                              -->
    <!-- ======================================================== -->
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
        // 1. Sidebar colapsable
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

        // 2. Modo Oscuro sincronizado + Sol/Luna
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

        // 3. Color de acento (Dropdown + Picker)
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
                if (val !== 'custom' && accentPicker) accentPicker.value = ACCENT_PRESETS[val] || '#0d6efd';
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

        // 4. Contenedores, texturas, botones, navbar, sidebar link
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

        // 5. Redondeado y accesibilidad
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

        // 6. Sidebar color, tablas, fuente, wallpaper + dimmer
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

        // 7. Lanzar bienvenida o continuar Tour Maestro si está activo entre páginas
        if (localStorage.getItem('tour_activo') === 'true') {
            setTimeout(() => {
                pasoActual = parseInt(localStorage.getItem('tour_paso') || '0', 10);
                document.getElementById('tour-card')?.classList.remove('d-none');
                mostrarPasoTutorial();
            }, 380);
        } else if (!localStorage.getItem('tutorial_realizado')) {
            setTimeout(() => {
                let modalDom = document.getElementById('modalBienvenidaTutorial');
                if (modalDom && typeof bootstrap !== 'undefined') new bootstrap.Modal(modalDom).show();
            }, 800);
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
        document.body.style.removeProperty('background-image');
        document.body.classList.remove('has-wallpaper');
        const bgUrlInput = document.getElementById('bgUrlInput');
        const bgInput = document.getElementById('bgImageInput');
        if (bgUrlInput) bgUrlInput.value = '';
        if (bgInput) bgInput.value = '';
        const bdg = document.getElementById('badgeWallpaperActivo');
        if (bdg) bdg.classList.add('d-none');
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
    // TOUR MAESTRO INTERACTIVO (MULTI-PÁGINA, PESTAÑAS Y MODALES)
    // ========================================================
    let pasoActual = 0;
    const pasosTutorial = [
        {
            loc: 'Dashboard',
            pagina: 'dashboard.php',
            url: 'dashboard.php',
            titulo: 'Panel Ejecutivo RRHH',
            desc: 'Aquí ves el resumen en tiempo real: personal activo, asistencia de hoy, archivo digital, gráfico circular SVG, cumpleaños del mes, eventos y auditoría reciente.',
            selector: '.row.g-3.mb-4',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Navbar · Búsqueda',
            pagina: 'dashboard.php',
            url: 'dashboard.php',
            titulo: 'Búsqueda Avanzada Global (Ctrl+K)',
            desc: 'Desde cualquier página puedes buscar cédulas, nombres, archivos, horarios o eventos. Usa el selector de tabla para desplegar filtros específicos por módulo.',
            selector: '#modalBusquedaAvanzada .modal-content',
            accion: 'abrir_buscador'
        },
        {
            loc: 'Personal',
            pagina: 'personal.php',
            query: 'tipo=1',
            url: 'personal.php?tipo=1',
            titulo: 'Directorio de Personal y Traslados',
            desc: 'Haz clic en las columnas para ordenar o arrástralas para reordenar. Marca casillas a la izquierda para trasladar empleados entre Docente, Administrativo u Obrero de forma masiva.',
            selector: '#tablaPersonal',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Personal · Modal',
            pagina: 'personal.php',
            query: 'tipo=1',
            url: 'personal.php?tipo=1',
            titulo: 'Carga Múltiple de Archivos (Panel Amarillo)',
            desc: 'En este modal puedes arrastrar varios archivos a la vez y elegir obligatoriamente qué tipo es cada uno (CED, FOT, TIT, MED, SOP, MEM). Todo lo subido va directo al Archivo Digital.',
            selector: '#panelSubirDocs',
            accion: 'abrir_modal_personal'
        },
        {
            loc: 'Personal · Ficha MPPE',
            pagina: 'personal.php',
            query: 'tipo=1',
            url: 'personal.php?tipo=1',
            titulo: 'Vincular Existentes y Ficha MPPE',
            desc: 'El panel azul permite vincular los 5 archivos más recientes o buscar en el repositorio, y el botón inferior despliega los datos ministeriales, salud, tallas y nómina.',
            selector: '#panelVincularDocs',
            accion: 'expandir_personal_vincular_mppe'
        },
        {
            loc: 'Organigrama',
            pagina: 'organigrama.php',
            url: 'organigrama.php',
            titulo: 'Organigrama Jerárquico Interactivo',
            desc: 'Arrastra tarjetas para reasignar jefes, usa la rueda para zoom y haz clic en cualquier empleado para ver detalles o cambiar supervisor. Si hay cambios sin guardar, el botón superior parpadeará en rojo.',
            selector: '#tree-container',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Organigrama · Modal',
            pagina: 'organigrama.php',
            url: 'organigrama.php',
            titulo: 'Añadir Trabajadores al Árbol',
            desc: 'Desde aquí incorporas empleados activos al organigrama eligiendo quién es su supervisor directo (o dejándolo como nodo raíz).',
            selector: '#modalAddNode .modal-content',
            accion: 'abrir_modal_org_agregar'
        },
        {
            loc: 'Organigrama · Exportar',
            pagina: 'organigrama.php',
            url: 'organigrama.php',
            titulo: 'Vista Monocromática y Exportación Offline',
            desc: 'Activa Blanco y Negro, oculta avatares, cambia la distribución (Mixto, Vertical u Horizontal) y descarga en PNG, PDF o SVG sin usar internet ni mostrar nexos.',
            selector: '#modalExportacion .modal-content',
            accion: 'abrir_modal_org_exportar'
        },
        {
            loc: 'Calendario Laboral',
            pagina: 'calendario.php',
            url: 'calendario.php',
            titulo: 'Horario Dinámico Semanal',
            desc: 'Visualiza los turnos por día y franja horaria en bloques de color. Haz clic en cualquier bloque para gestionar al personal asignado en ese horario.',
            selector: '.schedule-grid',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Calendario · Modal',
            pagina: 'calendario.php',
            url: 'calendario.php',
            titulo: 'Modal de Asignación de Turno',
            desc: 'Asigna trabajador, día de la semana, hora de entrada/salida, actividad y color identificador del bloque.',
            selector: '#modalTurno .modal-content',
            accion: 'abrir_modal:#modalTurno'
        },
        {
            loc: 'Asistencias',
            pagina: 'asistencias.php',
            url: 'asistencias.php',
            titulo: 'Registro Rápido por Cédula y Cierre Diario',
            desc: 'Escribe una cédula y presiona Enter: la 1.ª vez marca Entrada (Presente) y la 2.ª marca Salida. Al terminar el día (18:00 / 23:59), los que no asistieron quedan Ausentes automáticamente.',
            selector: 'input[name="cedula_rapida"]',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Asistencias · Modal',
            pagina: 'asistencias.php',
            url: 'asistencias.php',
            titulo: 'Reporte Manual Antiduplicados',
            desc: 'Si reportas una novedad manual para alguien que ya tenía asistencia ese día, el modal te avisa y actualiza el registro existente automáticamente.',
            selector: '#modalRegistro .modal-content',
            accion: 'abrir_modal:#modalRegistro'
        },
        {
            loc: 'Cronograma',
            pagina: 'cronograma.php',
            url: 'cronograma.php',
            titulo: 'Cronograma y Agenda Diaria',
            desc: 'Navega por la tira de 7 días para consultar consejos docentes, reuniones y jornadas programadas en la institución.',
            selector: '.day-card',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Cronograma · Modal',
            pagina: 'cronograma.php',
            url: 'cronograma.php',
            titulo: 'Crear Actividad con Participantes',
            desc: 'Define título, rango horario, notas y vincula múltiples trabajadores activos al evento.',
            selector: '#modalRegistro .modal-content',
            accion: 'abrir_modal:#modalRegistro'
        },
        {
            loc: 'Archivo Digital',
            pagina: 'documentos.php',
            url: 'documentos.php',
            titulo: 'Repositorio Documental (SHA-256)',
            desc: 'Almacena documentos independientes o vinculados a múltiples personas/eventos. Verifica automáticamente su firma SHA-256 (Íntegro) y permite previsualizar imágenes y PDFs.',
            selector: '#tablaDocumentos',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Archivo · Subir',
            pagina: 'documentos.php',
            url: 'documentos.php',
            titulo: 'Subir y Vincular con Buscador de Personal',
            desc: 'Elige si el documento es Institucional o vincúlalo a un trabajador usando nuestro buscador inteligente por cédula, nombre o cargo.',
            selector: '#modalSubirDoc .modal-content',
            accion: 'abrir_modal:#modalSubirDoc'
        },
        {
            loc: 'Auditoría',
            pagina: 'historial.php',
            url: 'historial.php',
            titulo: 'Auditoría y Trazabilidad',
            desc: 'Bitácora inmutable de las últimas 50 operaciones del sistema: creaciones, ediciones, eliminaciones, logins y exportaciones con fecha y usuario responsable.',
            selector: '.timeline',
            accion: 'cerrar_modales'
        },
        {
            loc: 'Config · Apariencia',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Apariencia y Temas',
            desc: 'Despliega Temas Rápidos o Wallpaper arriba, y personaliza el tema Sol/Luna, color del menú, color de acento global, contenedores (3D/2D/Cristal), texturas y accesibilidad.',
            selector: '#apariencia',
            accion: 'abrir_tab:#apariencia'
        },
        {
            loc: 'Config · Cargos',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Cargos y Modal Nuevo Cargo',
            desc: 'Administra el catálogo de cátedras y cargos por categoría. El modal te permite crear nuevas áreas con su descripción institucional.',
            selector: '#modalCrearCargo .modal-content',
            accion: 'abrir_tab_y_modal:#cargos|#modalCrearCargo'
        },
        {
            loc: 'Config · Exportar',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Exportación Multi-Formato',
            desc: 'Filtra por tipo de personal y descarga nóminas oficiales en Excel (.XLS), PDF Imprimible, Word (.DOC), Imagen (.JPG), CSV y respaldo JSON completo.',
            selector: '#exportar',
            accion: 'abrir_tab:#exportar'
        },
        {
            loc: 'Config · Importar',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Importar y Restauración JSON',
            desc: 'Realiza cargas masivas de nómina desde CSV, digitaliza archivos en clave o restaura toda la base de datos de personal desde un archivo JSON.',
            selector: '#importar',
            accion: 'abrir_tab:#importar'
        },
        {
            loc: 'Config · Atajos',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Atajos de Teclado',
            desc: 'Graba tus propias combinaciones de teclas rápidas para navegar por todo el sistema o abrir modales al instante.',
            selector: '#atajos',
            accion: 'abrir_tab:#atajos'
        },
        {
            loc: 'Config · Manual',
            pagina: 'configuracion.php',
            url: 'configuracion.php',
            titulo: 'Pestaña Tutorial y Manual Completo',
            desc: 'Aquí concluye el tour. Siempre podrás consultar este manual de 11 capítulos, imprimirlo en PDF para documentación o volver a lanzar este recorrido guiado.',
            selector: '#tutorial',
            accion: 'abrir_tab:#tutorial'
        }
    ];

    function estaEnPaginaPaso(paso) {
        const rutaOk = window.location.pathname.endsWith('/' + paso.pagina);
        if (!rutaOk) return false;
        if (paso.query && !window.location.search.includes(paso.query)) return false;
        return true;
    }

    function cerrarModalesTour() {
        document.querySelectorAll('.modal.show').forEach(m => {
            if (typeof bootstrap !== 'undefined') bootstrap.Modal.getInstance(m)?.hide();
        });
    }

    function abrirModalTourSeguro(selectorModal) {
        cerrarModalesTour();
        const el = document.querySelector(selectorModal);
        if (el && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(el, { focus: false }).show();
        }
    }

    function activarTabTourSeguro(selectorTab) {
        const btn = document.querySelector(`button[data-bs-target="${selectorTab}"]`);
        if (btn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    }

    function ejecutarAccionPasoTour(accion) {
        if (!accion) return;
        if (accion === 'cerrar_modales') {
            cerrarModalesTour();
        } else if (accion === 'abrir_buscador') {
            cerrarModalesTour();
            if (typeof abrirModalBusquedaAvanzada === 'function') abrirModalBusquedaAvanzada();
        } else if (accion === 'abrir_modal_personal') {
            if (typeof limpiarFormulario === 'function') limpiarFormulario();
            abrirModalTourSeguro('#modalRegistro');
        } else if (accion === 'expandir_personal_vincular_mppe') {
            const modalReg = document.getElementById('modalRegistro');
            if (modalReg && !modalReg.classList.contains('show') && typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(modalReg, { focus: false }).show();
            }
            if (typeof alternarPanelDoc === 'function') alternarPanelDoc('panelVincularDocs', false);
            const colMppe = document.getElementById('datosMiscelaneos');
            if (colMppe && typeof bootstrap !== 'undefined') {
                bootstrap.Collapse.getOrCreateInstance(colMppe, { toggle: false }).show();
            }
        } else if (accion === 'abrir_modal_org_agregar') {
            cerrarModalesTour();
            if (typeof abrirModalAgregar === 'function') abrirModalAgregar();
        } else if (accion === 'abrir_modal_org_exportar') {
            cerrarModalesTour();
            if (typeof abrirModalExportacion === 'function') abrirModalExportacion();
        } else if (accion.startsWith('abrir_modal:')) {
            abrirModalTourSeguro(accion.split(':')[1]);
        } else if (accion.startsWith('abrir_tab:')) {
            cerrarModalesTour();
            activarTabTourSeguro(accion.split(':')[1]);
        } else if (accion.startsWith('abrir_tab_y_modal:')) {
            const [tabSel, modalSel] = accion.split(':')[1].split('|');
            activarTabTourSeguro(tabSel);
            setTimeout(() => abrirModalTourSeguro(modalSel), 200);
        }
    }

    function iniciarRecorridoGuiado() {
        bootstrap.Modal.getInstance(document.getElementById('modalBienvenidaTutorial'))?.hide();
        pasoActual = 0;
        localStorage.setItem('tour_activo', 'true');
        localStorage.setItem('tour_paso', '0');
        if (!estaEnPaginaPaso(pasosTutorial[0])) {
            window.location.href = pasosTutorial[0].url;
            return;
        }
        document.getElementById('tour-card').classList.remove('d-none');
        mostrarPasoTutorial();
    }

    window.iniciarTutorialManual = function() {
        iniciarRecorridoGuiado();
    };

    function omitirTutorial() {
        bootstrap.Modal.getInstance(document.getElementById('modalBienvenidaTutorial'))?.hide();
        localStorage.setItem('tutorial_realizado', 'true');
        localStorage.removeItem('tour_activo');
        localStorage.removeItem('tour_paso');
    }

    function finalizarTutorial() {
        cerrarModalesTour();
        quitarResaltados();
        document.getElementById('tour-card')?.classList.add('d-none');
        localStorage.removeItem('tour_activo');
        localStorage.removeItem('tour_paso');
        localStorage.setItem('tutorial_realizado', 'true');
    }

    function moverPasoTutorial(direccion) {
        const siguiente = pasoActual + direccion;
        if (siguiente < 0) return;
        if (siguiente >= pasosTutorial.length) {
            finalizarTutorial();
            return;
        }
        pasoActual = siguiente;
        localStorage.setItem('tour_activo', 'true');
        localStorage.setItem('tour_paso', String(pasoActual));

        const paso = pasosTutorial[pasoActual];
        if (!estaEnPaginaPaso(paso)) {
            window.location.href = paso.url;
            return;
        }
        mostrarPasoTutorial();
    }

    function mostrarPasoTutorial() {
        quitarResaltados();
        if (pasoActual < 0 || pasoActual >= pasosTutorial.length) pasoActual = 0;
        const paso = pasosTutorial[pasoActual];

        ejecutarAccionPasoTour(paso.accion);

        const total = pasosTutorial.length;
        const porcentaje = Math.round(((pasoActual + 1) / total) * 100);
        document.getElementById('tour-progress').style.width = porcentaje + '%';
        document.getElementById('tour-step-badge').innerText = `Paso ${pasoActual + 1} de ${total}`;
        document.getElementById('tour-location-badge').innerText = paso.loc;
        document.getElementById('tour-title').innerText = paso.titulo;
        document.getElementById('tour-desc').innerText = paso.desc;
        document.getElementById('tour-prev-btn').disabled = (pasoActual === 0);
        document.getElementById('tour-next-btn').innerHTML = (pasoActual === total - 1)
            ? 'Finalizar <i class="bi bi-check-lg ms-1"></i>'
            : 'Siguiente<i class="bi bi-arrow-right ms-1"></i>';

        setTimeout(() => {
            if (paso.selector) {
                const el = document.querySelector(paso.selector);
                if (el) {
                    el.dataset.tourHighlight = '1';
                    el.style.outline = "4px solid #ffca28";
                    el.style.boxShadow = "0 0 22px rgba(255, 202, 40, 0.85)";
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }, 280);
    }

    function quitarResaltados() {
        document.querySelectorAll('[data-tour-highlight="1"]').forEach(el => {
            el.style.outline = '';
            el.style.boxShadow = '';
            delete el.dataset.tourHighlight;
        });
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
        CATALOGO_ATAJOS.forEach(item => { mapa[item.id] = guardados[item.id] !== undefined ? guardados[item.id] : item.def; });
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
        if (parts.length === 0 && !esFuncion) return null;
        if (parts.length === 1 && parts[0] === 'Shift' && !esFuncion) return null;

        parts.push(tecla);
        return parts.join('+');
    }

    document.addEventListener("DOMContentLoaded", () => {
        const atajos = obtenerAtajosActivos();
        const navInput = document.getElementById('navSearchInput');
        if (navInput && atajos.busqueda_avanzada) {
            navInput.placeholder = `Buscar cédula, nombre, archivo... (${atajos.busqueda_avanzada})`;
        }
        const swAtajos = document.getElementById('switchAtajosActivos');
        if (swAtajos) {
            swAtajos.checked = localStorage.getItem('atajosHabilitados') !== 'false';
            swAtajos.addEventListener('change', (e) => localStorage.setItem('atajosHabilitados', e.target.checked ? 'true' : 'false'));
        }
    });

    let grabandoAtajoId = null;
    document.addEventListener('keydown', (e) => {
        if (grabandoAtajoId) return;
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

    <?php
// Detectar IP local de red LAN automáticamente para el código QR del Smartphone
$ipServidorLAN = $_SERVER['SERVER_ADDR'] ?? '';
if (empty($ipServidorLAN) || $ipServidorLAN === '127.0.0.1' || $ipServidorLAN === '::1') {
    $ipServidorLAN = gethostbyname(gethostname());
}
if (empty($ipServidorLAN) || $ipServidorLAN === '127.0.0.1') {
    $ipServidorLAN = '192.168.1.X';
}
$baseProyecto = str_replace(' ', '%20', dirname(dirname($_SERVER['SCRIPT_NAME'])));
if ($baseProyecto === '/' || $baseProyecto === '\\') $baseProyecto = '';
$urlMovilAuto = 'http://' . $ipServidorLAN . $baseProyecto . '/index.php';
?>

<!-- MODAL: CONECTAR SMARTPHONE (MODO PORTABLE) -->
<div class="modal fade" id="modalModoPortable" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-phone-fill me-2"></i>Modo Portable (Conectar Smartphone)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary p-4">
                <p class="small text-muted mb-3">
                    Conecta tu teléfono al <strong>mismo Wi-Fi</strong> que esta computadora y escanea el código o escribe la dirección. Cuando inicies sesión en el teléfono, te pediremos autorización aquí.
                </p>

                <div class="d-flex justify-content-center mb-3">
                    <div id="qrContenedorPortable" class="p-3 bg-white rounded-4 shadow-sm border d-inline-block"></div>
                </div>

                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text fw-bold">IP Servidor</span>
                    <input type="text" id="inputIpPortable" class="form-control" value="<?= htmlspecialchars($ipServidorLAN) ?>" oninput="regenerarQRPortable()">
                </div>

                <div class="input-group input-group-sm">
                    <input type="text" id="inputUrlPortable" class="form-control font-monospace fw-bold text-primary" value="<?= htmlspecialchars($urlMovilAuto) ?>" readonly>
                    <button type="button" class="btn btn-primary fw-bold" onclick="navigator.clipboard.writeText(document.getElementById('inputUrlPortable').value); this.innerText='¡Copiado!';">
                        Copiar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EN LA PC: AUTORIZAR ACCESO DESDE SMARTPHONE -->
<div class="modal fade" id="modalSolicitudPortable" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-2 border-warning shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-phone-vibrate-fill me-2"></i>Solicitud de Modo Portable</h5>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="icon-circle bg-warning-subtle text-warning-emphasis mx-auto mb-3" style="width:68px; height:68px;">
                    <i class="bi bi-shield-check fs-1"></i>
                </div>
                <h5 class="fw-bold mb-2">Un smartphone solicita usar tu sesión</h5>
                <p class="small text-muted mb-3">
                    Dispositivo: <strong id="solPortDispositivo">-</strong><br>
                    Dirección IP: <strong id="solPortIP">-</strong>
                </p>
                <div class="alert alert-warning bg-warning-subtle text-warning-emphasis border-0 small mb-0">
                    Si aceptas, <strong>tu sesión en esta computadora se cerrará</strong> y el teléfono pasará a ser la única instancia activa.
                </div>
            </div>
            <div class="modal-footer border-0 d-flex gap-2">
                <button type="button" class="btn btn-outline-danger flex-fill fw-bold rounded-pill" onclick="responderModoPortable('rechazado')">
                    <i class="bi bi-x-lg me-1"></i> Rechazar
                </button>
                <button type="button" class="btn btn-success flex-fill fw-bold rounded-pill" onclick="responderModoPortable('aprobado')">
                    <i class="bi bi-check-lg me-1"></i> Admitir y Pasar al Teléfono
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../JS/qrcode.min.js" onerror="console.log('qrcode.min.js opcional no cargado')"></script>
<script>
const BASE_PATH_PORTABLE = <?= json_encode($baseProyecto . '/index.php') ?>;

function regenerarQRPortable() {
    const ip = document.getElementById('inputIpPortable')?.value.trim() || '127.0.0.1';
    const urlFinal = 'http://' + ip + BASE_PATH_PORTABLE;
    const urlInput = document.getElementById('inputUrlPortable');
    if (urlInput) urlInput.value = urlFinal;

    const qrBox = document.getElementById('qrContenedorPortable');
    if (!qrBox) return;
    qrBox.innerHTML = '';
    if (typeof QRCode !== 'undefined') {
        new QRCode(qrBox, { text: urlFinal, width: 170, height: 170 });
    } else {
        qrBox.innerHTML = '<div class="text-muted small p-3"><i class="bi bi-qr-code fs-1 d-block mb-1"></i>Coloca <code>qrcode.min.js</code> en <code>JS/</code> para ver el QR, o copia la URL de abajo.</div>';
    }
}

document.getElementById('modalModoPortable')?.addEventListener('shown.bs.modal', regenerarQRPortable);

// Latido de sesión en la PC + escucha de solicitudes de Modo Portable cada 3 segundos
let solicitudPortableActualId = null;
let modalPortableMostrado = false;

function sonarAvisoPortable() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(660, ctx.currentTime);
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + 0.18);
    } catch (e) {}
}

setInterval(async () => {
    try {
        const res = await fetch('../PHP/ajax_modo_portable.php?accion=latido_y_pendientes');
        const data = await res.json();

        if (!data.sesion_valida) {
            if (data.desconectado_por_pc) {
                window.location.href = '../index.php?desconectado_pc=1';
            } else {
                window.location.href = '../index.php';
            }
            return;
        }
        // Solo la computadora principal muestra el modal de autorización
        if (data.es_pc && data.pendiente && !modalPortableMostrado) {
            solicitudPortableActualId = data.pendiente.id;
            modalPortableMostrado = true;
            document.getElementById('solPortDispositivo').innerText = data.pendiente.dispositivo_corto;
            document.getElementById('solPortIP').innerText = data.pendiente.ip_solicitante;
            sonarAvisoPortable();
            new bootstrap.Modal(document.getElementById('modalSolicitudPortable')).show();
        }
    } catch (e) {}
}, 3000);

async function responderModoPortable(decision) {
    if (!solicitudPortableActualId) return;
    const fd = new FormData();
    fd.append('accion', 'responder');
    fd.append('solicitud_id', solicitudPortableActualId);
    fd.append('decision', decision);

    const res = await fetch('../PHP/ajax_modo_portable.php', { method: 'POST', body: fd });
    const data = await res.json();
    bootstrap.Modal.getInstance(document.getElementById('modalSolicitudPortable'))?.hide();
    modalPortableMostrado = false;
    solicitudPortableActualId = null;

    if (data.decision === 'aprobado') {
        // Llevar la PC a su panel de control para poder desconectar el teléfono cuando quiera
        window.location.href = '../index.php?monitor_portable=1';
    }
}
</script>
</body>
</html>