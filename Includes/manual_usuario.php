<?php
// Helper inteligente: muestra la captura real si existe en IMG/manual/, o un placeholder profesional si aún no se toma
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
                    <span class="badge bg-secondary-subtle text-body border font-monospace px-3 py-2">
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
.manual-img-placeholder {
    border: 2px dashed var(--bs-border-color);
    background-color: rgba(var(--bs-primary-rgb), 0.03);
    min-height: 220px;
}
.manual-nav-btn {
    text-align: left;
    border-radius: 0.75rem !important;
    font-weight: 500;
    font-size: 0.87rem;
    padding: 0.58rem 0.85rem;
    transition: all 0.2s ease;
}
.manual-nav-btn.active {
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.25);
}
.manual-callout {
    border-left: 4px solid var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.06);
    border-radius: 0.5rem;
    padding: 0.85rem 1rem;
}
.manual-callout.callout-warn {
    border-left-color: var(--bs-warning);
    background-color: rgba(var(--bs-warning-rgb), 0.1);
}
.manual-callout.callout-success {
    border-left-color: var(--bs-success);
    background-color: rgba(var(--bs-success-rgb), 0.08);
}

/* MODO IMPRESIÓN DEL MANUAL COMPLETO (PDF PARA TESIS) */
@media print {
    #sidebar-wrapper, .navbar-top, #ajustesTab, #manualSidebarCol, .manual-no-print, #tour-card {
        display: none !important;
    }
    #page-content-wrapper, .container-fluid, #manualContentCol {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }
    body.printing-manual .manual-section {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        page-break-after: always;
        border: none !important;
        box-shadow: none !important;
    }
    body.printing-manual .manual-section:last-child {
        page-break-after: auto;
    }
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
                <h4 class="fw-bold mb-1">Manual de Usuario y Documentación Oficial</h4>
                <p class="small text-muted mb-0">Guía técnica y operativa completa del Portal RRHH (U.E.N Luis Urdaneta).</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning rounded-pill px-3 fw-bold shadow-sm text-dark" onclick="iniciarTutorialManual()">
                <i class="bi bi-play-circle-fill me-1"></i> Iniciar Tour Guiado Completo
            </button>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 fw-bold" onclick="imprimirManualCompleto()">
                <i class="bi bi-printer-fill me-1"></i> Imprimir Manual (PDF)
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold" onclick="localStorage.removeItem('tutorial_realizado'); alert('Bienvenida inicial restablecida para el próximo inicio de sesión.');">
                <i class="bi bi-arrow-repeat me-1"></i> Reiniciar Bienvenida
            </button>
        </div>
    </div>
</div>

<!-- CUERPO DEL MANUAL -->
<div class="row g-4 align-items-start">
    
    <!-- COLUMNA IZQUIERDA: ÍNDICE -->
    <div class="col-lg-3 manual-no-print" id="manualSidebarCol">
        <div class="card border-0 shadow-sm sticky-top" style="top: 15px;">
            <div class="card-body p-3">
                <h6 class="fw-bold text-uppercase small text-muted mb-2"><i class="bi bi-list-nested me-1"></i> Capítulos (11)</h6>
                <input type="text" id="manualFiltroIndice" class="form-control form-control-sm mb-3" placeholder="Filtrar tema o palabra...">
                
                <div class="nav flex-column nav-pills gap-1" id="manualPillsTab" role="tablist">
                    <button class="nav-link manual-nav-btn active" data-bs-toggle="pill" data-bs-target="#man-sec-1" type="button"><i class="bi bi-shield-lock me-2"></i>1. Acceso y Seguridad ReBAC</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-2" type="button"><i class="bi bi-speedometer2 me-2"></i>2. Interfaz y Dashboard</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-3" type="button"><i class="bi bi-search me-2"></i>3. Búsqueda Avanzada Global</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-4" type="button"><i class="bi bi-people me-2"></i>4. Gestión de Personal y MPPE</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-5" type="button"><i class="bi bi-diagram-3 me-2"></i>5. Organigrama Institucional</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-6" type="button"><i class="bi bi-person-check me-2"></i>6. Asistencias y Cierre Diario</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-7" type="button"><i class="bi bi-calendar3 me-2"></i>7. Calendario y Cronograma</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-8" type="button"><i class="bi bi-folder2-open me-2"></i>8. Archivo Digital (SHA-256)</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-9" type="button"><i class="bi bi-clock-history me-2"></i>9. Auditoría y Trazabilidad</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-10" type="button"><i class="bi bi-palette me-2"></i>10. Apariencia, Datos y Respaldos</button>
                    <button class="nav-link manual-nav-btn" data-bs-toggle="pill" data-bs-target="#man-sec-11" type="button"><i class="bi bi-keyboard me-2"></i>11. Atajos y Red Local (LAN)</button>
                </div>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: CONTENIDO -->
    <div class="col-lg-9" id="manualContentCol">
        <div class="tab-content" id="manualTabContent">

            <!-- CAPÍTULO 1 -->
            <div class="tab-pane fade show active manual-section card border-0 shadow-sm p-4" id="man-sec-1">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 1</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Acceso al Sistema, Sesión Única y Seguridad ReBAC</h3>
                <p>El <strong>Portal RRHH de la U.E.N Luis Urdaneta</strong> opera de manera autónoma <strong>100% offline</strong> sobre servidor local Apache/MySQL (XAMPP), garantizando disponibilidad incluso sin conexión a internet.</p>
                
                <h6 class="fw-bold mt-4">1.1 Inicio de Sesión y Verificación</h6>
                <ol class="lh-lg">
                    <li>Abra su navegador web e ingrese a la ruta local del proyecto (o a la dirección IP de la máquina servidor si accede desde otro dispositivo en la red Wi-Fi).</li>
                    <li>Escriba su <strong>Usuario</strong> (predeterminado: <code>admin</code>) y su <strong>Contraseña</strong> (predeterminada: <code>admin123</code>).</li>
                    <li>Utilice el botón del ojo (<i class="bi bi-eye"></i>) a la derecha del campo clave para verificar lo escrito, y si desea, alterne el tema Claro/Oscuro desde el enlace inferior antes de ingresar.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/01_login.png', 1, 'Pantalla de Inicio de Sesión del Portal RRHH', 'Muestre la tarjeta de login con el escudo LU, los campos flotantes de usuario/contraseña, el botón de mostrar clave y el selector de tema.'); ?>

                <h6 class="fw-bold mt-4">1.2 Control de Instancia Única (Pestañas y Sesiones)</h6>
                <ul class="lh-lg">
                    <li><strong>Bloqueo de pestañas duplicadas:</strong> Si abre el portal en una segunda pestaña del mismo navegador, el sistema detecta la instancia previa mediante <code>BroadcastChannel</code> y bloquea la pestaña nueva, priorizando siempre la más antigua para evitar conflictos de guardado.</li>
                    <li><strong>Sesión única en base de datos:</strong> Si un usuario intenta iniciar sesión en otro equipo mientras su token sigue activo (últimas 12 horas), el acceso será rechazado con advertencia. Use siempre <strong>Cerrar Sesión</strong> en el menú superior derecho para liberar su sesión correctamente.</li>
                </ul>

                <h6 class="fw-bold mt-4">1.3 Modelo de Control de Acceso Híbrido (RBAC + ReBAC)</h6>
                <p class="small">Además del rol administrativo tradicional (<code>SuperAdministrador</code>, nivel 10), el sistema evalúa el acceso mediante un grafo <strong>ReBAC (Relationship-Based Access Control)</strong> en cuatro tablas (<code>rebac_recursos</code>, <code>rebac_tipos_relacion</code>, <code>rebac_politicas_permisos</code> y <code>rebac_tuplas_grafo</code>):</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light"><tr><th>Relación ReBAC</th><th>Alcance</th><th>Funcionamiento</th></tr></thead>
                        <tbody>
                            <tr><td><code>superadministrador</code></td><td>Global (<code>objeto_id = 0</code>)</td><td>Control total (Crear, Leer, Editar, Eliminar) en los 8 recursos del sistema.</td></tr>
                            <tr><td><code>supervisor_directo</code></td><td>Dinámico (Organigrama)</td><td>Calcula en tiempo real si el usuario es jefe directo del trabajador en <code>organigrama_jerarquia</code>.</td></tr>
                            <tr><td><code>propietario / coordinador / lector</code></td><td>Granular por registro</td><td>Permite restringir consultas, edición o borrado según vínculo con el objeto.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CAPÍTULO 2 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-2">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 2</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-speedometer2 text-primary me-2"></i>Interfaz Principal y Dashboard Ejecutivo</h3>
                <p>La interfaz se adapta automáticamente a pantallas de escritorio, tablets y teléfonos móviles, recordando sus preferencias visuales en el navegador.</p>

                <h6 class="fw-bold mt-3">2.1 Menú Lateral (Sidebar) y Barra Superior (Navbar)</h6>
                <ul class="lh-lg">
                    <li><strong>Agrupación por categorías:</strong> El menú está organizado en <em>Personal</em>, <em>Gestión</em> y <em>Sistema</em>. En escritorio (<kbd>Alt</kbd> + <kbd>M</kbd> o botón <i class="bi bi-list"></i>), puede contraerlo a modo compacto de solo íconos; en pantallas pequeñas se transforma en menú deslizable (Offcanvas).</li>
                    <li><strong>Menú de Perfil:</strong> En la esquina superior derecha muestra sus iniciales, usuario, rol y un <strong>interruptor rápido Claro/Oscuro</strong> sin tener que ir a Ajustes.</li>
                </ul>

                <h6 class="fw-bold mt-4">2.2 Tarjetas y Gráficas del Dashboard</h6>
                <ul class="lh-lg">
                    <li><strong>4 KPIs ejecutivos:</strong> Total de personal activo (desglosado por Docentes, Administrativos y Obreros), Asistencia de hoy, Documentos en Archivo Digital (con peso en MB) y Próximos eventos.</li>
                    <li><strong>Gráfico Donut SVG Offline y Situación Laboral:</strong> Muestra porcentajes exactos del personal activo y barras de progreso para Activos, Permisos, Reposos, Jubilados e Inactivos.</li>
                    <li><strong>Cumpleañeros del Mes, Agenda y Auditoría:</strong> Resalta automáticamente con etiqueta <code>🎂 ¡Hoy!</code> si algún trabajador cumple años en la fecha actual y enlaza directo a su ficha.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/02_dashboard.png', 2, 'Dashboard Ejecutivo y Navegación Adaptable', 'Capture el Dashboard mostrando los 4 KPIs, el gráfico circular SVG, las barras laborales y la barra de acciones rápidas.'); ?>
            </div>

            <!-- CAPÍTULO 3 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-3">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 3</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-search text-primary me-2"></i>Búsqueda Avanzada Global y Selectores Importables</h3>
                <p>El sistema cuenta con un motor de búsqueda transversal respetuoso de los permisos ReBAC del usuario conectado.</p>

                <h6 class="fw-bold mt-3">3.1 Búsqueda Rápida en la Navbar</h6>
                <p class="small">Al escribir al menos 2 caracteres en el buscador superior, se despliegan resultados instantáneos agrupados por <strong>Personal</strong>, <strong>Archivos</strong>, <strong>Asistencias</strong>, <strong>Horarios</strong> y <strong>Cronograma</strong>. Al buscar cédulas, el motor limpia automáticamente prefijos como <code>V-</code> y puntos.</p>

                <h6 class="fw-bold mt-3">3.2 Modal de Búsqueda Avanzada (<kbd>Ctrl</kbd> + <kbd>K</kbd> o botón <i class="bi bi-sliders"></i>)</h6>
                <ol class="lh-lg">
                    <li>Presione el ícono de filtros o el atajo configurado para abrir el modal de búsqueda profunda.</li>
                    <li>Seleccione una tabla específica en el desplegable para habilitar <strong>filtros dinámicos exclusivos</strong>:
                        <ul>
                            <li><em>Personal:</em> Filtra por Tipo (Docente/Admin/Obrero) y Estado Laboral, además de buscar en metadatos ocultos (correo, dirección, parroquia, cargo nominal, patologías).</li>
                            <li><em>Documentos:</em> Filtra por Categoría documental y Entidad vinculada.</li>
                            <li><em>Asistencias / Cronograma:</em> Filtra por Estado de asistencia, Rango de fechas (Desde / Hasta) o Día de la semana en Horarios.</li>
                        </ul>
                    </li>
                    <li>Hacer clic sobre cualquier resultado lo lleva directamente a la pantalla y fecha exacta, o abre automáticamente el formulario de edición del trabajador.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/03_busqueda_avanzada.png', 3, 'Modal de Búsqueda Avanzada con Filtros por Tabla', 'Muestre el modal de Búsqueda Avanzada abierto con una tabla seleccionada y sus filtros adicionales desplegados.'); ?>

                <div class="manual-callout callout-success mt-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-puzzle-fill me-1"></i> Selector Importable de Trabajadores</h6>
                    <p class="small mb-0">En los modales donde se requiere elegir a un empleado (como al vincular documentos), las listas largas han sido sustituidas por un componente buscador en vivo con filtro de categoría (Todos / Docente / Admin / Obrero).</p>
                </div>
            </div>

            <!-- CAPÍTULO 4 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-4">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 4</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>Gestión de Personal, Expediente MPPE y Traslados</h3>
                <p>Cada categoría (<strong>Personal Docente</strong>, <strong>Administrativo</strong> y <strong>Obrero</strong>) cuenta con un directorio especializado y herramientas avanzadas de tabla.</p>

                <h6 class="fw-bold mt-3">4.1 Funciones de la Tabla y Acciones Masivas</h6>
                <ul class="lh-lg">
                    <li><strong>Ordenamiento y Reorganización:</strong> Haga clic en cualquier encabezado de columna para ordenar ascendente/descendentemente, o arrastre el encabezado para reordenar las columnas visualmente.</li>
                    <li><strong>Previsualización Flotante:</strong> Al pasar el ratón sobre la cédula (si tiene foto) o sobre la fotografía de perfil, aparece una vista previa flotante sin afectar el desplazamiento horizontal en pantallas pequeñas.</li>
                    <li><strong>Menú de Fila:</strong> El ícono de carpeta (<i class="bi bi-folder2-open"></i>) abre el expediente lateral de solo lectura; el menú (<i class="bi bi-three-dots-vertical"></i>) agrupa <em>Editar datos</em> y <em>Eliminar</em>.</li>
                    <li><strong>Traslado Masivo de Tipo de Personal:</strong> Seleccione una o varias casillas a la izquierda para activar la barra flotante inferior. Presione <strong>Cambiar Tipo de Personal</strong>, elija la nueva categoría (Docente, Admin u Obrero) y su nuevo cargo. El sistema cierra el cargo anterior como <code>Finalizado</code> y registra el nuevo como <code>Vigente</code> conservando la trazabilidad histórica.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/04_personal_tabla.png', 4, 'Directorio de Personal y Barra Flotante de Traslado Masivo', 'Muestre la tabla con avatares, estados en insignias sutiles, menú de 3 puntos y la barra oscura flotante activa.'); ?>

                <h6 class="fw-bold mt-4">4.2 Modal de Registro, Carga Múltiple y Vinculación</h6>
                <ol class="lh-lg">
                    <li>Pulse <strong>+ Nuevo</strong> (o presione <kbd>Alt</kbd> + <kbd>N</kbd>). Complete cédula, nombres, apellidos, teléfono y cargo. Si el cargo aún no existe, use el botón verde <strong>+</strong> para crearlo al instante por AJAX.</li>
                    <li><strong>Panel Izquierdo Amarillo (Subir Nuevos Archivos):</strong> Arrastre o seleccione múltiples archivos simultáneamente. Por cada archivo agregado aparecerá una fila con su miniatura, peso en KB y un selector <strong>obligatorio</strong> para indicar qué documento es (<code>FOT</code>, <code>CED</code>, <code>TIT</code>, <code>MED</code>, <code>SOP</code>, <code>MEM</code>, <code>OTR</code>). Todo archivo subido aquí se sella con SHA-256 y se almacena en el <em>Archivo Digital</em>.</li>
                    <li><strong>Panel Derecho Azul (Vincular Existentes):</strong> Puede expandirse o minimizarse hacia la derecha. Muestra por defecto los <strong>5 documentos más recientes</strong> del repositorio (más los ya vinculados al trabajador) con un buscador en vivo para asociar archivos existentes con una casilla.</li>
                    <li><strong>Ficha Completa MPPE:</strong> Despliegue la sección colapsable para cargar fechas ministeriales, código de cargo/dependencia, horas semanales, datos residenciales/electorales, patologías médicas, tallas de uniforme y datos bancarios.</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/05_personal_modal.png', 5, 'Modal de Expediente MPPE con Paneles de Archivos Minimizables', 'Muestre el formulario abierto con varios archivos en la cola amarilla y sus selectores de tipo documental.'); ?>
            </div>

            <!-- CAPÍTULO 5 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-5">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 5</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Organigrama Institucional Interactivo</h3>
                <p>Representa visualmente las líneas de mando mediante una relación recursiva sobre el personal activo.</p>

                <ol class="lh-lg">
                    <li><strong>Añadir y Organizar:</strong> Haga clic en <strong>Añadir Personal</strong> para incluir trabajadores al árbol y elegir su jefe inmediato. Puede cambiar la jerarquía arrastrando una tarjeta sobre otra o haciendo clic en un nodo para abrir el menú de opciones (<em>Ver Detalles, Cambiar de Jefe, Ir a Editar en Personal, Quitar del Organigrama</em>).</li>
                    <li><strong>Navegación y Nexos Limpios:</strong> Use la rueda del ratón para Zoom y arrastre el lienzo para desplazarse. Los botones de expandir/contraer ramas (<code>+/-</code>) aparecen al pasar el cursor o hacer clic sobre las tarjetas.</li>
                    <li><strong>Detector de Cambios Pendientes:</strong> Ante cualquier movimiento, adición o eliminación, el botón <strong>Guardar Cambios</strong> cambia a color rojo parpadeante y el navegador advierte si intenta salir sin guardar.</li>
                    <li><strong>Vista y Exportación 100% Offline:</strong> Desde el botón <strong>Vista y Exportar</strong> puede activar el <em>Modo Monocromático (Blanco y Negro)</em>, <em>Ocultar Fotos e Íconos</em>, cambiar la orientación (<em>Mixto, Vertical u Horizontal</em>) y descargar el organigrama en <strong>PNG</strong>, <strong>PDF (Impresión Horizontal)</strong> o <strong>SVG Vectorial</strong> sin conexión a internet y con líneas limpias (sin nexos).</li>
                </ol>

                <?php renderPlaceholderImagen('IMG/manual/06_organigrama.png', 6, 'Organigrama Institucional y Panel de Exportación Offline', 'Capture el árbol jerárquico junto con el modal de filtros monocromáticos y selector de orientación.'); ?>
            </div>

            <!-- CAPÍTULO 6 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-6">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 6</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Control de Asistencias y Cierre Automático Diario</h3>
                <p>Combina registro veloz por teclado con automatización de fin de jornada basada en el reloj interno.</p>

                <h6 class="fw-bold mt-3">6.1 Barra de Asistencia Rápida por Cédula</h6>
                <ol class="lh-lg">
                    <li>Escriba o escanee la cédula del trabajador en el campo superior verde (acepta puntos, <code>V-</code> y cuenta con sugerencias automáticas) y presione <kbd>Enter</kbd>.</li>
                    <li><strong>Primera marca del día:</strong> Registra al trabajador como <strong>Presente</strong> guardando la hora exacta de entrada.</li>
                    <li><strong>Segunda marca del día:</strong> Completa automáticamente la <strong>Hora de Salida</strong> cerrando su jornada laboral.</li>
                </ol>

                <h6 class="fw-bold mt-3">6.2 Semáforo de 7 Días, Antiduplicados y Cierre Automático</h6>
                <ul class="lh-lg">
                    <li><strong>Navegación por Semáforo:</strong> La tira de 7 días muestra puntos de colores por jornada (verde = presentes, amarillo = retardos, azul = justificados, rojo = ausentes).</li>
                    <li><strong>Reporte Manual Inteligente:</strong> Si usa el botón <em>Reportar Novedad Manual</em> para un trabajador que ya tenía registro ese día, el modal muestra una alerta amarilla, autocompleta su información previa y actualiza el registro sin generar errores de duplicado.</li>
                    <li><strong>Cierre Automático del Día:</strong> Mediante doble respaldo (evento programado de MySQL <code>ev_cierre_automatico_diario</code> + verificación del reloj interno al abrir el módulo después de las <code>6:00 PM</code> o al consultar días hábiles pasados), toda entrada sin salida se cierra a las <code>18:00</code> y todo trabajador activo que no asistió queda registrado como <strong>Ausente</strong> con insignia <code>Auto</code>.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/07_asistencias.png', 7, 'Control de Asistencias con Barra de Cédula y Semáforo', 'Muestre la barra verde de cédula, el reloj en vivo, el semáforo de 7 días y las columnas de Entrada/Salida.'); ?>
            </div>

            <!-- CAPÍTULO 7 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-7">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 7</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Calendario Laboral y Cronograma de Eventos</h3>

                <h6 class="fw-bold mt-3">7.1 Calendario Laboral (Horarios Dinámicos)</h6>
                <p class="small">Muestra la distribución semanal (Lunes a Domingo) agrupada por bloques de horas y colores. Al hacer clic sobre cualquier tarjeta horaria se abre el detalle con todos los empleados asignados a ese bloque para editar o eliminar individualmente.</p>

                <h6 class="fw-bold mt-3">7.2 Cronograma (Agenda Diaria)</h6>
                <p class="small">Permite programar asambleas, consejos docentes o actividades institucionales especificando fecha, hora de inicio/fin, notas descriptivas y seleccionando múltiples participantes activos. La tira de 7 días indica cuántas actividades hay programadas por fecha.</p>

                <?php renderPlaceholderImagen('IMG/manual/08_calendario_cronograma.png', 8, 'Calendario Laboral por Colores y Cronograma de Actividades', 'Muestre las columnas semanales de turnos de colores o la vista de agenda diaria con participantes.'); ?>
            </div>

            <!-- CAPÍTULO 8 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-8">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 8</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-folder2-open text-primary me-2"></i>Archivo Digital (Repositorio Polimórfico SHA-256)</h3>
                <p>Módulo centralizado e independiente para el resguardo seguro de documentos digitales (imágenes, PDF, Word, Excel).</p>

                <ul class="lh-lg">
                    <li><strong>Arquitectura Desacoplada:</strong> Todo archivo subido en este módulo o desde la ficha de Personal reside en la tabla <code>documentos</code> y se asocia a través de <code>documentos_enlaces</code> a uno o múltiples destinos (<em>Institucional / Libre</em>, <em>Trabajadores</em> o <em>Eventos</em>). Si un trabajador es eliminado del directorio, sus documentos permanecen a salvo en el repositorio.</li>
                    <li><strong>Integridad Criptográfica SHA-256:</strong> Al momento de subir cualquier archivo, el servidor calcula su huella digital SHA-256. Cada vez que se abre la vista, verifica que el archivo físico coincida exactamente con su huella original mostrando la insignia verde <strong>Íntegro</strong>.</li>
                    <li><strong>Visor Offline y Vínculos Múltiples:</strong> Previsualice imágenes y PDFs dentro del sistema (<i class="bi bi-eye-fill"></i>), descargue el original (<i class="bi bi-download"></i>) o añada nuevos vínculos a otras personas mediante el ícono de enlace (<i class="bi bi-link-45deg"></i>) usando el buscador de trabajadores.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/09_archivo_digital.png', 9, 'Archivo Digital con KPIs de Almacenamiento e Integridad SHA-256', 'Capture la pantalla del Repositorio Documental mostrando las tarjetas superiores, filtros y tabla de documentos.'); ?>
            </div>

            <!-- CAPÍTULO 9 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-9">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 9</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Auditoría y Trazabilidad del Sistema</h3>
                <p>Línea de tiempo cronológica que registra las últimas 50 operaciones críticas del portal para garantizar transparencia administrativa.</p>
                <ul class="lh-lg">
                    <li>Cada evento almacena el tipo de acción (<code>CREAR</code>, <code>ACTUALIZAR</code>, <code>ELIMINAR</code>, <code>LOGIN</code>, <code>EXPORTAR</code>, <code>ACCESO_CRITICO</code>), tabla afectada, fecha y hora exacta, descripción detallada y el usuario responsable.</li>
                </ul>
                <?php renderPlaceholderImagen('IMG/manual/10_auditoria.png', 10, 'Línea de Tiempo de Auditoría y Trazabilidad', 'Muestre la pestaña Auditoría con los eventos codificados por color según el tipo de acción.'); ?>
            </div>

            <!-- CAPÍTULO 10 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-10">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 10</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-palette text-primary me-2"></i>Apariencia, Catálogos, Exportación y Respaldos</h3>

                <h6 class="fw-bold mt-3">10.1 Personalización Visual (Pestaña <em>Apariencia</em>)</h6>
                <ul class="small lh-lg">
                    <li><strong>Secciones Colapsables Superiores:</strong> Expanda <em>Temas Rápidos (1 Clic)</em> para aplicar perfiles completos al instante, o <em>Fondo de Pantalla</em> para subir un wallpaper local/URL y graduar su oscurecimiento (0% a 80%).</li>
                    <li><strong>Control Estético Total:</strong> Tema Claro/Oscuro (con transición animada Sol/Luna), Color del Menú, Color de Acento global (lista + selector libre), Estilo de Contenedores (<em>Relieve 3D, Plano 2D, Cristal</em>), Fondo Ambiental (<em>Sólido, Degradado, Puntos, Cuadrícula</em>), Forma de Botones, Barra Superior, Indicador de Menú, Redondeado, Fuente, Densidad de Tablas y Accesibilidad.</li>
                </ul>

                <h6 class="fw-bold mt-3">10.2 Catálogos, Exportación e Importación</h6>
                <ul class="small lh-lg">
                    <li><strong>Cargos y Materias:</strong> Administre las especialidades de Docentes, Administrativos y Obreros (bloquea el borrado si hay personal vigente asignado).</li>
                    <li><strong>Exportar:</strong> Genere reportes filtrados en <strong>Excel (.XLS)</strong>, <strong>PDF Oficial</strong>, <strong>Word (.DOC)</strong>, <strong>Imagen (.JPG)</strong>, <strong>CSV</strong> y respaldo completo <strong>JSON</strong>.</li>
                    <li><strong>Importar y Cargas:</strong> Cargue nóminas masivas mediante <code>CSV</code> (orden: <code>Cédula, Nombres, Apellidos, ID_Cargo, Fecha_Ingreso</code>), digitalice soportes codificados en clave o restaure todo un expediente MPPE desde un respaldo <code>JSON</code>.</li>
                </ul>

                <?php renderPlaceholderImagen('IMG/manual/11_configuracion_apariencia.png', 11, 'Panel de Apariencia, Presets Colapsables y Exportación', 'Muestre la pestaña de Apariencia en Configuración con las tarjetas personalizadas.'); ?>
            </div>

            <!-- CAPÍTULO 11 -->
            <div class="tab-pane fade manual-section card border-0 shadow-sm p-4" id="man-sec-11">
                <span class="badge bg-primary-subtle text-primary mb-2">Capítulo 11</span>
                <h3 class="fw-bold mb-3"><i class="bi bi-keyboard text-primary me-2"></i>Atajos de Teclado y Uso en Red Local (LAN)</h3>

                <h6 class="fw-bold mt-3">11.1 Asignación de Atajos de Teclado (Pestaña <em>Atajos</em>)</h6>
                <p class="small">Permite personalizar las combinaciones de teclas rápidas para navegar o abrir modales sin usar el ratón. Haga clic en <strong>Cambiar</strong> y presione la combinación deseada (ej: <kbd>Alt</kbd>+<kbd>A</kbd>, <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>D</kbd> o <kbd>F2</kbd>–<kbd>F10</kbd>). El sistema protege automáticamente atajos reservados del navegador como <code>Ctrl+C</code>, <code>Ctrl+V</code> o <code>F5</code>.</p>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-3 small">
                        <thead class="table-light">
                            <tr><th>Atajo</th><th>Acción</th><th>Atajo</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><kbd>Ctrl</kbd>+<kbd>K</kbd></td><td>Búsqueda Avanzada Global</td><td><kbd>Alt</kbd>+<kbd>1</kbd> .. <kbd>4</kbd></td><td>Dashboard / Docentes / Admin / Obreros</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>N</kbd></td><td>Abrir modal "Nuevo" en la vista actual</td><td><kbd>Alt</kbd>+<kbd>A</kbd></td><td>Ir a Control de Asistencias</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>T</kbd></td><td>Alternar Modo Claro / Oscuro</td><td><kbd>Alt</kbd>+<kbd>D</kbd></td><td>Ir a Archivo Digital</td></tr>
                            <tr><td><kbd>Alt</kbd>+<kbd>M</kbd></td><td>Colapsar / Expandir Menú Lateral</td><td><kbd>Alt</kbd>+<kbd>O</kbd> / <kbd>H</kbd> / <kbd>C</kbd> / <kbd>S</kbd></td><td>Organigrama / Horarios / Cronograma / Ajustes</td></tr>
                        </tbody>
                    </table>
                </div>

                <?php renderPlaceholderImagen('IMG/manual/12_atajos.png', 12, 'Configuración de Atajos de Teclado Personalizables', 'Capture la tabla de Atajos de Teclado dentro de la pestaña Atajos en Configuración.'); ?>

                <div class="manual-callout mt-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-router-fill me-1"></i> Acceso desde otros dispositivos en Red Local (Wi-Fi / LAN)</h6>
                    <p class="small mb-0">Con Apache y MySQL activos en XAMPP, otros equipos o teléfonos en la misma red pueden conectarse ingresando a <code>http://[IP-DEL-SERVIDOR]/nombre_carpeta</code> (verificando que el puerto TCP <code>80</code> esté permitido en el Firewall de Windows).</p>
                </div>
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
        setTimeout(() => {
            document.body.classList.remove('printing-manual');
        }, 500);
    }, 200);
}
</script>