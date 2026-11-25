<?php
if (!function_exists('renderPlaceholderImagen')) {
    function renderPlaceholderImagen($rutaRelativa, $numFigura, $titulo, $descripcionCaptura) {
        $rutaFisica = '../' . $rutaRelativa;
        $existe = file_exists($rutaFisica);
        ?>
        <figure class="my-4 p-3 rounded-4 border bg-body-tertiary text-center manual-figure">
            <?php if ($existe): ?>
                <img src="../<?= htmlspecialchars($rutaRelativa) ?>" alt="<?= htmlspecialchars($titulo) ?>" class="img-fluid rounded-3 shadow-sm border" style="max-height: 440px;">
            <?php else: ?>
                <div class="manual-img-placeholder d-flex flex-column justify-content-center align-items-center p-4 p-md-5 rounded-3">
                    <div class="icon-circle bg-primary-subtle text-primary mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-camera-fill fs-2"></i>
                    </div>
                    <h6 class="fw-bold mb-1">Espacio Reservado para Imagen</h6>
                    <p class="small text-muted mb-2" style="max-width: 540px;"><?= htmlspecialchars($descripcionCaptura) ?></p>
                    <span class="badge bg-secondary-subtle text-body border px-3 py-2">
                        <i class="bi bi-folder2 me-1"></i> Guardar imagen como: <?= htmlspecialchars($rutaRelativa) ?>
                    </span>
                </div>
            <?php endif; ?>
            <figcaption class="figure-caption mt-2 fw-medium">
                <strong>Figura <?= $numFigura ?>:</strong> <?= htmlspecialchars($titulo) ?>
            </figcaption>
        </figure>
        <?php
    }
}
?>

<style>
.manual-img-placeholder { border: 2px dashed var(--bs-border-color); background-color: rgba(var(--bs-primary-rgb), 0.03); min-height: 210px; }
.manual-nav-btn { text-align: left; border-radius: 0.75rem !important; font-weight: 500; font-size: 0.87rem; padding: 0.58rem 0.85rem; transition: all 0.2s ease; }
.manual-nav-btn.active { font-weight: 700; box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.25); }
.manual-callout { border-left: 4px solid var(--bs-primary); background-color: rgba(var(--bs-primary-rgb), 0.06); border-radius: 0.5rem; padding: 0.85rem 1rem; }
.manual-callout.callout-warn { border-left-color: var(--bs-warning); background-color: rgba(var(--bs-warning-rgb), 0.1); }

@media print {
    #sidebar-wrapper, .navbar-top, #ajustesTab, #manualSidebarCol, .manual-no-print, #tour-card { display: none !important; }
    #page-content-wrapper, .container-fluid, #manualContentCol { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
    body.printing-manual .manual-section { display: block !important; opacity: 1 !important; visibility: visible !important; page-break-after: always; border: none !important; box-shadow: none !important; }
    body.printing-manual .manual-section:last-child { page-break-after: auto; }
}
</style>

<div class="card shadow-sm border-0 mb-4 border-start border-4 border-warning bg-body-tertiary manual-no-print">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center">
            <div class="icon-circle bg-warning-subtle text-warning-emphasis me-3" style="width: 58px; height: 58px;">
                <i class="bi bi-book-half fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">Guía Rápida de Uso del Sistema</h4>
                <p class="small text-muted mb-0">Manual paso a paso para el manejo diario del Portal de Recursos Humanos.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning rounded-pill px-3 fw-bold shadow-sm text-dark" onclick="iniciarTutorialManual()">
                <i class="bi bi-play-circle-fill me-1"></i> Iniciar Recorrido Guiado
            </button>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 fw-bold" onclick="imprimirManualCompleto()">
                <i class="bi bi-printer-fill me-1"></i> Imprimir Guía (PDF)
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold" onclick="localStorage.removeItem('tutorial_realizado'); alert('La bienvenida inicial volverá a mostrarse al iniciar sesión.');">
                <i class="bi bi-arrow-repeat me-1"></i> Reiniciar Bienvenida
            </button>
        </div>
    </div>
</div>

<div class="row g-4 align-items-start">
    <div class="col-lg-3 manual-no-print" id="manualSidebarCol">
        <div class="card border-0 shadow-sm sticky-top" style="top: 15px;">
            <div class="card-body p-3">
                <h6 class="fw-bold text-uppercase small text-muted mb-2"><i class="bi bi-list-nested me-1"></i> Secciones</h6>
                <input type="text" id="manualFiltroIndice" class="form-control form-control-sm mb-3" placeholder="Buscar tema...">
                
                <div class="nav flex-column nav-pills gap-1" id="manualPillsTab" role="tablist">
                    <button class="nav-link manual-nav-btn active" data-bs-toggle="pill" data-bs-target="#man-sec-1" type="button"><i class="bi bi-shield-lock me-2"></i>1. Ingreso al Sistema</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-2" type="button"><i class="bi bi-speedometer2 me-2"></i>2. Panel Principal (Inicio)</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-3" type="button"><i class="bi bi-search me-2"></i>3. Buscador Global</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-4" type="button"><i class="bi bi-people me-2"></i>4. Registro de Personal</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-5" type="button"><i class="bi bi-diagram-3 me-2"></i>5. Organigrama</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-6" type="button"><i class="bi bi-person-check me-2"></i>6. Control de Asistencias</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-7" type="button"><i class="bi bi-calendar3 me-2"></i>7. Horarios y Cronograma</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-8" type="button"><i class="bi bi-folder2-open me-2"></i>8. Archivo Digital</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-9" type="button"><i class="bi bi-clock-history me-2"></i>9. Historial de Movimientos</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-10" type="button"><i class="bi bi-gear me-2"></i>10. Ajustes, Reportes y Atajos</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9" id="manualContentCol">
        <div class="tab-content" id="manualTabContent">

            <!-- 1. INGRESO -->
            <div class="tab-pane fade show active manual-section card border-0 shadow-sm p-4" id="man-sec-1">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 1</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Ingreso al Sistema</h3>
                <p>El portal de la <strong>U.E.N Luis Urdaneta</strong> trabaja completamente en su computadora sin necesitar conexión a internet.</p>
                <ol class="lh-lg">
                    <li>Escriba su <strong>Usuario</strong> y su <strong>Contraseña</strong> en los cuadros de inicio.</li>
                    <li>Si desea verificar la contraseña escrita, haga clic en el ícono del ojo (<i class="bi bi-eye"></i>).</li>
                    <li>También puede cambiar entre <em>Modo Claro</em> o <em>Modo Oscuro</em> antes de entrar desde el enlace inferior.</li>
                </ol>
                <?php renderPlaceholderImagen('IMG/manual/01_login.png', 1, 'Pantalla de Inicio de Sesión', 'Captura de la ventana de acceso con usuario y contraseña.'); ?>
                <div class="manual-callout callout-warn mt-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Una sola ventana a la vez</h6>
                    <p class="small mb-0">Para evitar confusiones o pérdida de información, el sistema solo permite mantener una pestaña abierta. Al finalizar su jornada, haga clic en su nombre arriba a la derecha y elija <strong>Cerrar Sesión</strong>.</p>
                </div>
            </div>

            <!-- 2. DASHBOARD -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-2">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 2</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-speedometer2 text-primary me-2"></i>Panel Principal (Inicio)</h3>
                <p>Es la primera pantalla que verá al ingresar y resume toda la actividad de la institución:</p>
                <ul class="lh-lg">
                    <li><strong>Cuadros superiores:</strong> Cantidad de personal activo (Docentes, Administrativos y Obreros), asistencias registradas hoy, total de documentos guardados y eventos próximos.</li>
                    <li><strong>Gráficos visuales:</strong> Distribución porcentual del personal y estado laboral actual (Activos, Permisos, Reposos, Jubilados e Inactivos).</li>
                    <li><strong>Avisos útiles:</strong> Lista automática de cumpleañeros del mes, actividades cercanas y últimos movimientos realizados.</li>
                    <li><strong>Botones rápidos:</strong> En la parte superior derecha puede ir directo a marcar asistencias, registrar un nuevo trabajador (eligiendo entre Docente, Administrativo u Obrero) o subir un documento.</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/02_dashboard.png', 2, 'Panel Principal del Sistema', 'Captura del panel principal mostrando las tarjetas de resumen, gráficos y cumpleañeros.'); ?>
            </div>

            <!-- 3. BUSCADOR GLOBAL -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-3">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 3</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-search text-primary me-2"></i>Buscador Global (<kbd>Ctrl</kbd> + <kbd>K</kbd>)</h3>
                <p>En la barra superior encontrará un buscador disponible desde cualquier pantalla:</p>
                <ol class="lh-lg">
                    <li>Escriba una cédula, nombre, apellido, materia o nombre de documento para ver coincidencias inmediatas.</li>
                    <li>Presione <kbd>Enter</kbd> o el botón de filtros (<i class="bi bi-sliders"></i>) para abrir la búsqueda detallada, donde puede filtrar por sección (Personal, Documentos, Asistencias, Horarios o Cronograma) y por fechas o estados.</li>
                    <li>Al hacer clic en un resultado, el sistema abrirá directamente la ficha o fecha correspondiente.</li>
                </ol>
                <?php renderPlaceholderImagen('IMG/manual/03_buscador.png', 3, 'Ventana de Búsqueda con Filtros', 'Captura del buscador superior con resultados y filtros por sección.'); ?>
            </div>

            <!-- 4. PERSONAL -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-4">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 4</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>Registro y Control de Personal</h3>
                <p>El menú izquierdo divide al personal en tres listas: <strong>Docente</strong>, <strong>Administrativo</strong> y <strong>Obrero</strong>.</p>
                <h6 class="fw-bold mt-3">4.1 Opciones de la Lista</h6>
                <ul class="lh-lg">
                    <li><strong>Ordenar y paginar:</strong> Haga clic en cualquier título de columna para ordenar la lista. En la parte inferior de la tabla puede elegir ver de 10, 25, 50 o todos los registros por página.</li>
                    <li><strong>Ver o editar:</strong> El ícono de carpeta (<i class="bi bi-folder2-open"></i>) muestra el expediente completo del trabajador; el botón de tres puntos (<i class="bi bi-three-dots-vertical"></i>) permite editar sus datos o eliminarlo.</li>
                    <li><strong>Cambiar tipo de personal:</strong> Marque las casillas de la izquierda para seleccionar uno o varios trabajadores y use el botón inferior <strong>Cambiar Tipo de Personal</strong> para moverlos entre Docente, Administrativo u Obrero.</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/04_personal_lista.png', 4, 'Lista de Personal y Opciones', 'Captura del listado de personal con las casillas de selección y botones de acción.'); ?>
                <h6 class="fw-bold mt-4">4.2 Registrar o Editar Trabajador</h6>
                <ol class="lh-lg">
                    <li>Llene los datos básicos (cédula, nombre, apellido, teléfono, materia/cargo e ingreso). Si falta un cargo o materia, presione el botón verde <strong>+</strong> para añadirlo al instante.</li>
                    <li>Puede adjuntar varios documentos nuevos en el cuadro amarillo (indicando si es foto, cédula, título, reposo, etc.) o seleccionar archivos que ya estén guardados en el cuadro azul.</li>
                    <li>Presione <strong>Ficha Completa de Datos MPPE</strong> para llenar información ministerial, residencia, tallas de uniforme, salud y cuenta bancaria.</li>
                </ol>
                <?php renderPlaceholderImagen('IMG/manual/05_personal_formulario.png', 5, 'Formulario de Trabajador y Documentos', 'Captura del formulario abierto mostrando la zona de carga de archivos y datos del MPPE.'); ?>
            </div>

            <!-- 5. ORGANIGRAMA -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-5">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 5</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Organigrama Institucional</h3>
                <p>Permite construir visualmente la estructura jerárquica de la escuela.</p>
                <ol class="lh-lg">
                    <li>Pulse <strong>Añadir Personal</strong> para agregar un trabajador y seleccionar quién es su jefe inmediato.</li>
                    <li>Haga clic sobre cualquier tarjeta para ver su ficha, cambiarle de jefe o quitarlo del esquema. También puede arrastrar tarjetas para reorganizarlas.</li>
                    <li>Si realiza cambios, el botón <strong>Guardar Cambios</strong> parpadeará en rojo para recordarle guardar antes de salir.</li>
                    <li>En <strong>Vista y Exportar</strong> puede cambiar entre formato vertical u horizontal, activar modo blanco y negro, ocultar fotos y guardar el organigrama como imagen o PDF listo para imprimir.</li>
                </ol>
                <?php renderPlaceholderImagen('IMG/manual/06_organigrama.png', 6, 'Organigrama de la Institución', 'Captura del organigrama con varias tarjetas conectadas y opciones de exportación.'); ?>
            </div>

            <!-- 6. ASISTENCIAS -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-6">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 6</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Control de Asistencias</h3>
                <ol class="lh-lg">
                    <li><strong>Registro rápido por cédula:</strong> Escriba la cédula en la barra verde superior y presione <kbd>Enter</kbd>. La primera vez registra la <strong>hora de entrada</strong> como Presente; la segunda vez registra la <strong>hora de salida</strong>.</li>
                    <li><strong>Navegación de días:</strong> La tira de 7 días muestra puntos de colores indicando si hubo presentes, retardos, justificados o ausencias.</li>
                    <li><strong>Reportes manuales:</strong> Use el botón <em>Reportar Novedad Manual</em> para justificar faltas o retardos. Si la persona ya tenía registro ese día, el sistema actualizará el existente sin duplicarlo.</li>
                    <li><strong>Cierre automático del día:</strong> Al finalizar la jornada laboral, el sistema cierra las salidas pendientes y registra automáticamente como <em>Ausente</em> al personal activo que no asistió ese día.</li>
                </ol>
                <?php renderPlaceholderImagen('IMG/manual/07_asistencias.png', 7, 'Control Diario de Asistencias', 'Captura de la pantalla de asistencias con la barra rápida de cédula y el calendario semanal.'); ?>
            </div>

            <!-- 7. HORARIOS Y CRONOGRAMA -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-7">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 7</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Calendario Laboral y Cronograma</h3>
                <ul class="lh-lg">
                    <li><strong>Calendario Laboral:</strong> Muestra los turnos de la semana en tarjetas de colores. Haga clic sobre cualquier bloque para ver qué trabajadores tienen ese horario, editarlo o quitarlo.</li>
                    <li><strong>Cronograma:</strong> Agenda diaria para anotar consejos docentes, reuniones o eventos escolares, permitiendo vincular al personal convocado.</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/08_horarios_cronograma.png', 8, 'Calendario de Horarios y Cronograma', 'Captura de los bloques de horarios semanales o la agenda de actividades.'); ?>
            </div>

            <!-- 8. ARCHIVO DIGITAL -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-8">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 8</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-folder2-open text-primary me-2"></i>Archivo Digital</h3>
                <p>Reúne todos los documentos de la institución y los archivos subidos en las fichas del personal.</p>
                <ul class="lh-lg">
                    <li>Puede subir documentos generales (actas, circulares, memorándums) o asociarlos a trabajadores y actividades mediante el buscador.</li>
                    <li>Las imágenes cuentan con miniaturas ligeras para cargar rápidamente. Desde la columna de acciones puede ver imágenes o PDFs en pantalla (<i class="bi bi-eye-fill"></i>), descargarlos (<i class="bi bi-download"></i>) o asociarlos con otra persona (<i class="bi bi-link-45deg"></i>).</li>
                    <li>Use el selector inferior para navegar por páginas (10, 25, 50 o todos los documentos) o filtre por tipo en la parte superior.</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/09_archivo_digital.png', 9, 'Archivo Digital de Documentos', 'Captura de la lista de documentos con miniaturas, paginación y filtros.'); ?>
            </div>

            <!-- 9. HISTORIAL -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-9">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 9</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Historial de Movimientos</h3>
                <p>Muestra en orden de tiempo los últimos 50 cambios realizados en el portal (registros creados, ediciones, eliminaciones, exportaciones e ingresos), indicando la fecha, hora y usuario que ejecutó cada acción.</p>
            </div>

            <!-- 10. AJUSTES, REPORTES Y ATAJOS -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-10">
                <span class="badge bg-primary-subtle text-primary mb-2">Sección 10</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-gear text-primary me-2"></i>Configuración, Reportes y Atajos</h3>
                <ul class="lh-lg">
                    <li><strong>Apariencia:</strong> Cambie entre modo claro y oscuro, elija temas rápidos, cambie colores de menú y botones, suba un fondo de pantalla desde su computadora y ajuste tamaños de letra o tablas.</li>
                    <li><strong>Cargos y Materias:</strong> Agregue nuevas cátedras o cargos administrativos para tenerlos disponibles al registrar personal.</li>
                    <li><strong>Exportar e Importar:</strong> Descargue listados oficiales en Excel, PDF, Word o Imagen, o cargue listados desde Excel y restaure copias de seguridad del sistema.</li>
                    <li><strong>Atajos de Teclado:</strong> Personalice teclas rápidas para abrir secciones o formularios sin usar el ratón (por ejemplo <kbd>Ctrl</kbd>+<kbd>K</kbd> para buscar o <kbd>Alt</kbd>+<kbd>N</kbd> para crear un nuevo registro).</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/10_configuracion.png', 10, 'Configuración y Personalización', 'Captura de la pantalla de ajustes y atajos de teclado.'); ?>
            </div>

        </div>
    </div>
</div>

<script>
document.getElementById('manualFiltroIndice')?.addEventListener('input', function(e) {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('#manualPillsTab .manual-nav-btn').forEach(btn => {
        const targetId = btn.getAttribute('data-bs-target');
        const secTexto = document.querySelector(targetId)?.innerText.toLowerCase() || '';
        const coincide = btn.innerText.toLowerCase().includes(q) || secTexto.includes(q);
        btn.classList.toggle('d-none', q !== '' && !coincide);
    });
});

function imprimirManualCompleto() {
    document.body.classList.add('printing-manual');
    setTimeout(() => {
        window.print();
        setTimeout(() => document.body.classList.remove('printing-manual'), 500);
    }, 200);
}
</script>