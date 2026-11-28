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
                    <h6 class="fw-bold mb-1">Espacio Reservado para Captura de Pantalla</h6>
                    <p class="small text-muted mb-2" style="max-width: 540px;"><?= htmlspecialchars($descripcionCaptura) ?></p>
                    <span class="badge bg-secondary-subtle text-body border px-3 py-2">
                        <i class="bi bi-folder2 me-1"></i> Guardar imagen en: <?= htmlspecialchars($rutaRelativa) ?>
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
.manual-img-placeholder { border: 2px dashed var(--bs-border-color); background-color: rgba(var(--bs-primary-rgb), 0.03); min-height: 215px; }
.manual-nav-btn { text-align: left; border-radius: 0.75rem !important; font-weight: 500; font-size: 0.87rem; padding: 0.58rem 0.85rem; transition: all 0.2s ease; }
.manual-nav-btn.active { font-weight: 700; box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.25); }
.manual-callout { border-left: 4px solid var(--bs-primary); background-color: rgba(var(--bs-primary-rgb), 0.06); border-radius: 0.5rem; padding: 0.85rem 1rem; }
.manual-callout.callout-warn { border-left-color: var(--bs-warning); background-color: rgba(var(--bs-warning-rgb), 0.1); }
.manual-callout.callout-success { border-left-color: var(--bs-success); background-color: rgba(var(--bs-success-rgb), 0.08); }

@media print {
    #sidebar-wrapper, .navbar-top, #ajustesTab, #manualSidebarCol, .manual-no-print, #tour-card { display: none !important; }
    #page-content-wrapper, .container-fluid, #manualContentCol { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
    body.printing-manual .manual-section { display: block !important; opacity: 1 !important; visibility: visible !important; page-break-after: always; border: none !important; box-shadow: none !important; }
    body.printing-manual .manual-section:last-child { page-break-after: auto; }
}
</style>

<!-- CABECERA DEL CENTRO DE DOCUMENTACIÓN -->
<div class="card shadow-sm border-0 mb-4 border-start border-4 border-warning bg-body-tertiary manual-no-print">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center">
            <div class="icon-circle bg-warning-subtle text-warning-emphasis me-3" style="width: 58px; height: 58px;">
                <i class="bi bi-book-half fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">Manual de Usuario del Portal RRHH</h4>
                <p class="small text-muted mb-0">Guía completa paso a paso para operar cada módulo de la U.E.N Luis Urdaneta.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning rounded-pill px-3 fw-bold shadow-sm text-dark" onclick="iniciarTutorialManual()">
                <i class="bi bi-play-circle-fill me-1"></i> Iniciar Recorrido Guiado
            </button>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 fw-bold" onclick="imprimirManualCompleto()">
                <i class="bi bi-printer-fill me-1"></i> Imprimir Manual (PDF)
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold" onclick="localStorage.removeItem('tutorial_realizado'); alert('La bienvenida inicial volverá a mostrarse al iniciar sesión.');">
                <i class="bi bi-arrow-repeat me-1"></i> Reiniciar Bienvenida
            </button>
        </div>
    </div>
</div>

<!-- CUERPO DEL MANUAL -->
<div class="row g-4 align-items-start">
    <div class="col-lg-3 manual-no-print" id="manualSidebarCol">
        <div class="card border-0 shadow-sm sticky-top" style="top: 15px;">
            <div class="card-body p-3">
                <h6 class="fw-bold text-uppercase small text-muted mb-2"><i class="bi bi-list-nested me-1"></i> Capítulos (10)</h6>
                <input type="text" id="manualFiltroIndice" class="form-control form-control-sm mb-3" placeholder="Buscar tema o palabra...">
                
                <div class="nav flex-column nav-pills gap-1" id="manualPillsTab" role="tablist">
                    <button class="nav-link manual-nav-btn active" data-bs-toggle="pill" data-bs-target="#man-sec-1" type="button"><i class="bi bi-shield-lock me-2"></i>1. Ingreso y Seguridad</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-2" type="button"><i class="bi bi-speedometer2 me-2"></i>2. Panel Principal (Inicio)</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-3" type="button"><i class="bi bi-search me-2"></i>3. Buscador Global</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-4" type="button"><i class="bi bi-people me-2"></i>4. Gestión de Personal</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-5" type="button"><i class="bi bi-diagram-3 me-2"></i>5. Organigrama Institucional</button>
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

            <!-- CAPÍTULO 1 -->
            <div class="tab-pane fade show active manual-section card border-0 shadow-sm p-4" id="man-sec-1">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 1</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Ingreso al Sistema y Protección de la Cuenta</h3>
                <p>El <strong>Portal de Recursos Humanos de la U.E.N Luis Urdaneta</strong> funciona completamente de forma local en su computadora, por lo que no necesita conexión a internet para trabajar en ninguna de sus pantallas.</p>
                
                <h6 class="fw-bold mt-4">1.1 Cómo Iniciar Sesión</h6>
                <ol class="lh-lg">
                    <li>Abra el navegador web en su computadora e ingrese al sistema.</li>
                    <li>Escriba su <strong>Usuario</strong> (predeterminado: <code>admin</code>) y su <strong>Contraseña</strong> (predeterminada: <code>admin123</code>).</li>
                    <li>Si necesita revisar si escribió bien su clave, haga clic en el botón del ojo (<i class="bi bi-eye"></i>) situado a la derecha del campo de contraseña.</li>
                    <li>Si prefiere trabajar con colores oscuros desde el inicio, haga clic en <em>Cambiar tema Claro / Oscuro</em> debajo del botón de ingreso.</li>
                    <li>Presione <strong>Ingresar al Sistema</strong> (o la tecla <kbd>Enter</kbd>) para acceder al Panel Principal.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/01_login.png', 1, 'Pantalla de Inicio de Sesión', 'Muestre la tarjeta de acceso con el escudo LU, usuario, contraseña, el botón del ojo y la opción de tema.'); ?>

                <div class="manual-callout callout-warn mt-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Control de Pestaña Única y Cierre de Sesión</h6>
                    <p class="small mb-0">Para evitar confusiones o que un formulario se guarde dos veces por error, el sistema solo permite mantener <strong>una pestaña abierta</strong> a la vez en el navegador (si intenta abrir otra, le pedirá usar la primera). Al terminar su jornada laboral, haga clic en su nombre en la esquina superior derecha y seleccione <strong>Cerrar Sesión</strong>.</p>
                </div>

                <h6 class="fw-bold mt-4">1.2 Permisos por Nivel de Cuenta</h6>
                <p class="small text-muted mb-0">Cada cuenta dentro del portal tiene un rol asignado (por ejemplo, <em>SuperAdministrador</em>) que controla qué secciones puede abrir y si puede crear, modificar o eliminar registros de manera segura.</p>
            </div>

            <!-- CAPÍTULO 2 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-2">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 2</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-speedometer2 text-primary me-2"></i>Interfaz Principal y Panel de Inicio</h3>
                <p>Al iniciar sesión verá el <strong>Panel Ejecutivo</strong> junto con la barra de navegación superior y el menú lateral izquierdo.</p>

                <h6 class="fw-bold mt-3">2.1 Menú Lateral y Barra Superior</h6>
                <ul class="lh-lg">
                    <li><strong>Secciones organizadas:</strong> El menú izquierdo agrupa las pantallas en tres bloques: <em>Personal</em>, <em>Gestión</em> y <em>Sistema</em>.</li>
                    <li><strong>Modo compacto del menú:</strong> En computadoras, presione el botón (<i class="bi bi-list"></i>) arriba a la izquierda (o <kbd>Alt</kbd> + <kbd>M</kbd>) para encoger el menú a solo íconos y ganar más espacio en pantalla.</li>
                    <li><strong>Menú de usuario y cambio rápido de tema:</strong> Arriba a la derecha verá sus iniciales y rol. Al hacer clic se despliega un menú con acceso rápido a <strong>Modo Oscuro / Claro</strong>, Ajustes y Cerrar Sesión.</li>
                </ul>

                <h6 class="fw-bold mt-4">2.2 Resúmenes y Gráficos del Panel Principal</h6>
                <ul class="lh-lg">
                    <li><strong>Cuadros informativos superiores:</strong> Muestran el total de trabajadores activos (separados por Docentes, Administrativos y Obreros), cuántos asistieron hoy, cuántos documentos hay en el Archivo Digital y los próximos eventos.</li>
                    <li><strong>Gráficos visuales:</strong> Un gráfico circular con los porcentajes por categoría y barras de progreso indicando cuántos trabajadores están Activos, de Permiso, de Reposo, Jubilados o Inactivos.</li>
                    <li><strong>Cumpleañeros del mes y agenda:</strong> Lista automática de los empleados que cumplen años en el mes actual (destacando con <code>🎂 ¡Hoy!</code> al cumpleañero del día), junto con las próximas reuniones y los últimos movimientos realizados.</li>
                    <li><strong>Accesos rápidos:</strong> En la parte superior derecha puede saltar directamente a <em>Marcar Asistencia</em>, <em>Nuevo Personal</em> (eligiendo en 3 botones cuadrados entre Docente, Administrativo u Obrero) o <em>Subir Archivo</em>.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/02_dashboard.png', 2, 'Panel Principal y Accesos Rápidos', 'Capture la pantalla completa del Dashboard mostrando los 4 cuadros superiores, el gráfico circular, cumpleaños y accesos rápidos.'); ?>
            </div>

            <!-- CAPÍTULO 3 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-3">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 3</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-search text-primary me-2"></i>Buscador Global con Filtros (<kbd>Ctrl</kbd> + <kbd>K</kbd>)</h3>
                <p>El buscador de la barra superior le permite encontrar cualquier dato del sistema sin importar en qué pantalla se encuentre.</p>

                <h6 class="fw-bold mt-3">3.1 Búsqueda Rápida Instantánea</h6>
                <p class="small">Haga clic en el cuadro superior (o presione <kbd>Ctrl</kbd> + <kbd>K</kbd>) y escriba una cédula (con o sin puntos ni <code>V-</code>), nombre, apellido, materia, nombre de documento o título de evento. Al instante aparecerán las coincidencias agrupadas por sección.</p>

                <h6 class="fw-bold mt-3">3.2 Búsqueda Avanzada por Sección</h6>
                <ol class="lh-lg">
                    <li>Presione <kbd>Enter</kbd> en el buscador o haga clic en el botón de filtros (<i class="bi bi-sliders"></i>).</li>
                    <li>Elija si desea buscar en <strong>Todas las Tablas</strong> o en una sección específica (<em>Personal</em>, <em>Documentos</em>, <em>Asistencias</em>, <em>Calendario Laboral</em> o <em>Cronograma</em>).</li>
                    <li>Al seleccionar una sección se mostrarán <strong>filtros adicionales</strong> (como Estado Laboral, Tipo de Documento, Rango de Fechas o Día de la Semana).</li>
                    <li>Al hacer clic sobre cualquier resultado, el sistema lo llevará directo a esa fecha o abrirá automáticamente la ficha del trabajador.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/03_buscador.png', 3, 'Ventana de Búsqueda Avanzada con Filtros', 'Muestre la ventana de Búsqueda Avanzada abierta con una sección seleccionada y sus filtros desplegados.'); ?>
            </div>

            <!-- CAPÍTULO 4 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-4">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 4</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>Gestión de Personal y Expediente MPPE</h3>
                <p>El directorio se divide en tres listas en el menú izquierdo: <strong>Personal Docente</strong>, <strong>Personal Administrativo</strong> y <strong>Personal Obrero</strong>.</p>

                <h6 class="fw-bold mt-3">4.1 Herramientas de la Lista de Personal</h6>
                <ul class="lh-lg">
                    <li><strong>Ordenar y mover columnas:</strong> Haga clic sobre el título de cualquier columna para ordenar de la A a la Z (o por fecha/cédula). También puede arrastrar los títulos para cambiar el orden de las columnas.</li>
                    <li><strong>Paginación de listas largas:</strong> En la parte inferior derecha puede avanzar entre páginas, y a la izquierda elegir cuántos trabajadores ver por página (<code>15</code>, <code>30</code>, <code>50</code> o <code>Todos</code>).</li>
                    <li><strong>Vista previa rápida:</strong> Pase el cursor por encima de la cédula o de la foto de perfil para ampliar la imagen al instante.</li>
                    <li><strong>Botones de fila:</strong> El ícono de carpeta (<i class="bi bi-folder2-open"></i>) abre la ficha completa de solo lectura en un panel lateral; el botón (<i class="bi bi-three-dots-vertical"></i>) permite <em>Editar datos</em> o <em>Eliminar</em>.</li>
                    <li><strong>Traslado masivo entre tipos de personal:</strong> Marque una o varias casillas a la izquierda para activar la barra inferior flotante y haga clic en <strong>Cambiar Tipo de Personal</strong> para mover empleados entre Docente, Administrativo u Obrero eligiendo su nuevo cargo.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/04_personal_lista.png', 4, 'Directorio de Personal, Paginación y Barra de Traslado', 'Muestre la tabla con avatares, casillas marcadas, menú de tres puntos y la barra flotante inferior.'); ?>

                <h6 class="fw-bold mt-4">4.2 Registrar o Editar un Trabajador y sus Documentos</h6>
                <ol class="lh-lg">
                    <li>Haga clic en <strong>+ Nuevo Docente / Admin / Obrero</strong> (o presione <kbd>Alt</kbd> + <kbd>N</kbd>).</li>
                    <li>Llene la <em>Identificación Básica</em>. Si falta un cargo o materia en la lista, presione el botón verde <strong>+</strong> al lado para crearlo de inmediato sin perder lo escrito.</li>
                    <li><strong>Subir Documentos Nuevos (Cuadro Amarillo):</strong> Arrastre o seleccione varios archivos a la vez. Debajo aparecerá cada archivo con su miniatura y una lista <strong>obligatoria</strong> para indicar qué es (<em>Foto Carnet, Cédula, Título, Informe Médico, Reposo, Memorándum u Otro</em>). Todo lo que suba aquí quedará guardado automáticamente en el <em>Archivo Digital</em>.</li>
                    <li><strong>Usar Documentos Guardados (Cuadro Azul):</strong> Puede expandirse o minimizarse a la derecha. Muestra los 5 documentos más recientes y un buscador para asociar archivos que ya existían en el sistema.</li>
                    <li><strong>Ficha Completa MPPE:</strong> Haga clic en el botón desplegable inferior para ingresar fecha de ingreso al MPPE, código de cargo/dependencia, horas semanales, dirección, centro de votación, datos médicos/alergias, tallas de uniforme y cuenta bancaria.</li>
                </ol>

                <div class="manual-callout callout-success mt-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-pencil-square me-1"></i> Borradores Automáticos</h6>
                    <p class="small mb-0">Si está llenando un formulario y necesita ir a otra sección del menú para consultar algo, no cierre la ventana con la <code>×</code>: sus datos se guardarán automáticamente como un borrador y verá un aviso en la esquina inferior derecha para continuar exactamente donde lo dejó.</p>
                </div>

                <?php renderPlaceholderImagen('IMG/manual/05_personal_formulario.png', 5, 'Formulario de Expediente MPPE y Carga Múltiple', 'Muestre el formulario abierto con varios documentos en la cola amarilla y sus selectores de tipo.'); ?>
            </div>

            <!-- CAPÍTULO 5 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-5">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 5</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Organigrama Institucional</h3>
                <p>Permite construir y visualizar gráficamente la jerarquía de mando entre el personal activo de la institución.</p>

                <ol class="lh-lg">
                    <li><strong>Agregar trabajadores:</strong> Pulse el botón verde <strong>Añadir Personal</strong>, elija al empleado e indique quién es su jefe inmediato (o déjelo como autoridad máxima sin jefe).</li>
                    <li><strong>Modificar jerarquía:</strong> Arrastre una tarjeta sobre otra para cambiar su supervisor, o haga clic sobre cualquier persona para abrir su menú (<em>Ver Detalles, Cambiar de Jefe, Ir a Editar en Personal o Quitar del Organigrama</em>).</li>
                    <li><strong>Zoom y líneas limpias:</strong> Use la rueda del ratón para acercar o alejar, y arrastre el fondo para moverse. Los botones pequeños de contraer ramas (<kbd>-</kbd>/<kbd>+</kbd>) aparecen al pasar el ratón o hacer clic.</li>
                    <li><strong>Aviso de guardado:</strong> Cualquier cambio hará que el botón superior <strong>Guardar Cambios</strong> parpadee en rojo y el sistema le avisará si intenta salir sin guardar.</li>
                    <li><strong>Vista y Exportación:</strong> En el botón <strong>Vista y Exportar</strong> puede activar <em>Blanco y Negro</em>, <em>Ocultar Fotos</em>, cambiar entre diseño <em>Mixto, Vertical u Horizontal</em> y descargar en imagen o PDF limpio listo para imprimir.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/06_organigrama.png', 6, 'Organigrama Institucional y Opciones de Exportación', 'Capture el diagrama de jerarquía junto con el cuadro de Vista y Exportación.'); ?>
            </div>

            <!-- CAPÍTULO 6 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-6">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 6</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Control de Asistencias y Cierre del Día</h3>
                <p>Permite registrar rápidamente la llegada y salida diaria del personal y llevar control de inasistencias o retardos.</p>

                <h6 class="fw-bold mt-3">6.1 Barra de Asistencia Rápida por Cédula</h6>
                <ol class="lh-lg">
                    <li>En el cuadro verde superior, escriba la cédula del trabajador (acepta puntos o <code>V-</code> y muestra sugerencias mientras escribe) y presione <kbd>Enter</kbd>.</li>
                    <li><strong>Primera marca del día:</strong> Registra al trabajador como <strong>Presente</strong> guardando la hora exacta de entrada.</li>
                    <li><strong>Segunda marca del día:</strong> Registra la <strong>Hora de Salida</strong> cerrando su jornada.</li>
                </ol>

                <h6 class="fw-bold mt-3">6.2 Calendario de 7 Días, Novedades y Cierre Automático</h6>
                <ul class="lh-lg">
                    <li><strong>Semáforo semanal:</strong> La tira de 7 días muestra puntos de colores por fecha (verde = presentes, amarillo = retardos, azul = justificados, rojo = ausentes).</li>
                    <li><strong>Reportar novedad manual:</strong> Úselo para registrar permisos justificados, retardos o inasistencias con observaciones. Si la persona ya tenía un registro ese día, el sistema le avisará y actualizará ese mismo registro sin crear duplicados.</li>
                    <li><strong>Cierre automático del día:</strong> Al terminar la jornada laboral (configurada a las <code>6:00 PM</code> y al cierre del día), toda persona que marcó entrada sin salida se cierra automáticamente y todo trabajador activo que no registró asistencia queda marcado como <strong>Ausente</strong> con la etiqueta <code>Auto</code>.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/07_asistencias.png', 7, 'Control de Asistencias con Barra Rápida y Semáforo', 'Muestre la barra verde de cédula, el reloj en vivo, los 4 cuadros de resumen y el listado.'); ?>
            </div>

            <!-- CAPÍTULO 7 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-7">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 7</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Calendario Laboral y Cronograma de Actividades</h3>

                <h6 class="fw-bold mt-3">7.1 Calendario Laboral (Horarios por Turno)</h6>
                <p class="small">Muestra los turnos de Lunes a Domingo organizados por bloques horarios y colores. Haga clic en cualquier tarjeta para ver la lista de trabajadores asignados a esa misma hora y actividad, editarlos o eliminarlos.</p>

                <h6 class="fw-bold mt-3">7.2 Cronograma (Agenda Diaria)</h6>
                <p class="small">Sirve para planificar consejos docentes, asambleas o jornadas especiales con hora de inicio y fin. La tira superior de 7 días indica cuántas actividades hay por fecha, y al crear una actividad puede mantener presionada la tecla <kbd>Ctrl</kbd> para seleccionar varios participantes a la vez.</p>

                <?php renderPlaceholderImagen('IMG/manual/08_horarios_cronograma.png', 8, 'Calendario de Turnos y Agenda Diaria', 'Muestre las columnas semanales con tarjetas de colores o la agenda del cronograma.'); ?>
            </div>

            <!-- CAPÍTULO 8 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-8">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 8</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-folder2-open text-primary me-2"></i>Archivo Digital</h3>
                <p>Reúne todos los documentos guardados de la institución y del personal en un solo lugar, con miniaturas optimizadas para cargar rápido.</p>

                <ol class="lh-lg">
                    <li><strong>Subir Documento:</strong> Presione <strong>Subir Documento</strong>, elija el archivo (PDF, imagen, Word o Excel), seleccione su tipo e indique si es un <em>Documento General de la Institución</em> o si pertenece a un trabajador o evento usando el buscador integrado.</li>
                    <li><strong>Ver, Descargar o Asociar:</strong> En la tabla de documentos puede usar (<i class="bi bi-eye-fill"></i>) para abrir imágenes o PDFs en pantalla, (<i class="bi bi-download"></i>) para descargar el archivo original o (<i class="bi bi-link-45deg"></i>) para asociar ese mismo documento con otra persona o actividad sin tener que subirlo dos veces.</li>
                    <li><strong>Filtros y Paginación:</strong> Filtre por nombre, código, tipo de documento o pertenencia en la parte superior, y elija abajo cuántos archivos mostrar por página (<code>10</code>, <code>25</code>, <code>50</code> o <code>Todos</code>).</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/09_archivo_digital.png', 9, 'Archivo Digital con Miniaturas y Paginación', 'Capture la pantalla de Archivo Digital mostrando los cuadros superiores, filtros, tabla con miniaturas y barra de páginas.'); ?>
            </div>

            <!-- CAPÍTULO 9 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-9">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 9</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Historial de Movimientos</h3>
                <p>Línea de tiempo cronológica que registra las últimas <strong>50 acciones</strong> realizadas en el portal para mantener el control administrativo.</p>
                <ul class="lh-lg">
                    <li><strong>Etiquetas por color:</strong> Verde para registros nuevos (<em>Crear</em>), amarillo para cambios (<em>Actualizar</em>), rojo para borrados (<em>Eliminar</em>) y azul para ingresos al sistema.</li>
                    <li>Cada tarjeta muestra el detalle exacto de lo realizado, la fecha, la hora y el usuario responsable.</li>
                </ul>
            </div>

            <!-- CAPÍTULO 10 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-10">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 10</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-gear text-primary me-2"></i>Ajustes, Reportes, Respaldos y Atajos</h3>

                <h6 class="fw-bold mt-3">10.1 Apariencia</h6>
                <p class="small">Puede elegir un tema completo de 1 clic en <em>Temas Rápidos</em>, subir una imagen desde su computadora en <em>Fondo de Pantalla</em> graduando su oscurecimiento, alternar Claro/Oscuro, personalizar colores del menú y botones, elegir tarjetas (3D, 2D o Cristal), texturas de fondo, tipo y tamaño de letra, redondeado, densidad de tablas y opciones de accesibilidad.</p>

                <h6 class="fw-bold mt-3">10.2 Cargos, Exportar e Importar</h6>
                <ul class="small lh-lg">
                    <li><strong>Cargos y Materias:</strong> Cree o elimine cátedras y funciones (el sistema protege cualquier cargo que tenga trabajadores activos asignados).</li>
                    <li><strong>Exportar:</strong> Genere listados filtrados por categoría en <strong>Excel, PDF, Word o Imagen JPG</strong>, además de listados compatibles y copias de seguridad completas.</li>
                    <li><strong>Importar y Respaldos:</strong> Cargue nóminas completas desde Excel (en formato <code>.csv</code> con columnas <code>Cédula, Nombres, Apellidos, ID_Cargo, Fecha_Ingreso</code>), suba documentos individuales o restaure una copia de seguridad guardada.</li>
                </ul>

                <h6 class="fw-bold mt-3">10.3 Atajos de Teclado Predeterminados (Personalizables en la pestaña <em>Atajos</em>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-3 small">
                        <thead class="table-light">
                            <tr><th>Atajo</th><th>Acción</th><th>Atajo</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><kbd>Ctrl</kbd>+<kbd>K</kbd></td><td>Abrir Buscador Global</td><td><kbd>Alt</kbd>+<kbd>1</kbd> .. <kbd>4</kbd></td><td>Inicio / Docentes / Admin / Obreros</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>N</kbd></td><td>Abrir ventana "Nuevo" en la página actual</td><td><kbd>Alt</kbd>+<kbd>A</kbd></td><td>Ir a Control de Asistencias</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>T</kbd></td><td>Cambiar entre Modo Claro y Oscuro</td><td><kbd>Alt</kbd>+<kbd>D</kbd></td><td>Ir a Archivo Digital</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>M</kbd></td><td>Encoger / Expandir Menú Lateral</td><td><kbd>Alt</kbd>+<kbd>O</kbd> / <kbd>H</kbd> / <kbd>C</kbd> / <kbd>S</kbd></td><td>Organigrama / Horarios / Cronograma / Ajustes</td></tr>
                        </tbody>
                    </table>
                </div>

                <?php renderPlaceholderImagen('IMG/manual/10_configuracion.png', 10, 'Configuración, Apariencia y Atajos de Teclado', 'Capture la pestaña de Apariencia o la tabla de Atajos de Teclado en Configuración.'); ?>
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