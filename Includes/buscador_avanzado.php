<?php
// Cargar catálogos para los filtros del Modal de Búsqueda Avanzada
$busqTiposPersonal = $pdo->query("SELECT id, nombre FROM cat_tipos_personal ORDER BY id ASC")->fetchAll();
$busqTiposDoc = $pdo->query("SELECT id, clave, nombre FROM cat_tipos_documentos ORDER BY clave ASC")->fetchAll();

// =========================================================================
// FUNCIÓN IMPORTABLE / REUTILIZABLE: SELECTOR AVANZADO DE TRABAJADORES
// =========================================================================

if (!function_exists('renderBuscadorTrabajador')) {
    function renderBuscadorTrabajador($inputId, $inputName = 'personal_id', $placeholder = 'Cédula, nombre, apellido o cargo...', $soloActivos = false) {
        global $busqTiposPersonal;
        $uid = preg_replace('/[^a-zA-Z0-9_]/', '', $inputId);
        $soloActVal = $soloActivos ? '1' : '0';
        ?>
        <div class="widget-buscador-personal position-relative" id="widget_<?= $uid ?>">
            <input type="hidden" name="<?= htmlspecialchars($inputName) ?>" id="<?= htmlspecialchars($inputId) ?>" class="wb-hidden-id">

            <div class="row g-1 align-items-center">
                <div class="col-4">
                    <select class="form-select form-select-sm bg-body wb-filtro-tipo" title="Filtrar categoría">
                        <option value="0">Todos</option>
                        <?php foreach ($busqTiposPersonal as $tp): ?>
                            <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-8">
                    <div class="input-group input-group-sm shadow-sm">
                        <span class="input-group-text bg-body"><i class="bi bi-search text-primary"></i></span>
                        <input type="text" class="form-control wb-input-texto" placeholder="<?= htmlspecialchars($placeholder) ?>" autocomplete="off" data-solo-activos="<?= $soloActVal ?>">
                        <button type="button" class="btn btn-outline-secondary wb-btn-limpiar d-none" title="Limpiar selección"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>

            <!-- Etiqueta visual cuando ya seleccionaron a alguien -->
            <div class="wb-seleccionado d-none mt-2">
                <div class="px-2 py-1 rounded bg-success-subtle text-success border border-success-subtle small d-flex justify-content-between align-items-center">
                    <span class="wb-seleccionado-txt fw-bold text-truncate me-2"></span>
                    <button type="button" class="btn btn-sm p-0 text-success wb-btn-cambiar text-nowrap" title="Cambiar"><i class="bi bi-pencil-square"></i> Cambiar</button>
                </div>
            </div>

            <!-- Dropdown flotante de resultados -->
            <div class="wb-dropdown list-group position-absolute w-100 shadow-lg d-none mt-1" style="z-index: 1080; max-height: 220px; overflow-y: auto; left: 0;"></div>
        </div>
        <?php
    }
}
?>

<!-- ========================================================================= -->
<!-- MODAL DE BÚSQUEDA AVANZADA GLOBAL (CON FILTROS POR TABLA)                -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalBusquedaAvanzada" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-search-heart me-2 text-info"></i>Búsqueda Avanzada del Sistema</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary p-4">
                
                <!-- Barra Principal de Búsqueda + Selector de Tabla -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-7">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-body border-primary-subtle text-primary"><i class="bi bi-search"></i></span>
                                    <input type="text" id="advSearchQuery" class="form-control border-primary-subtle shadow-none" placeholder="Cédula, nombres, apellidos, archivos, actividades, cargos, patologías...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="advSearchTabla" class="form-select form-select-lg border-primary-subtle fw-bold" onchange="cambiarFiltrosTablaAvanzada()">
                                    <option value="todos">🌐 Todas las Tablas</option>
                                    <option value="personal">👥 Personal / Trabajadores</option>
                                    <option value="documentos">📁 Documentos / Archivos</option>
                                    <option value="asistencias">📋 Control de Asistencias</option>
                                    <option value="horarios">🗓️ Calendario Laboral</option>
                                    <option value="cronograma">📌 Cronograma de Eventos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-lg w-100 fw-bold" onclick="ejecutarBusquedaAvanzada()">
                                    Buscar
                                </button>
                            </div>
                        </div>

                        <!-- FILTROS ADICIONALES DINÁMICOS PARA CADA TABLA -->
                        <div id="advFiltrosContenedor" class="mt-3 pt-3 border-top d-none">
                            <!-- Filtros de PERSONAL -->
                            <div class="row g-2 adv-filtro-grupo d-none" data-tabla="personal">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Tipo de Personal:</label>
                                    <select id="fil_p_tipo" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="0">Todos los tipos</option>
                                        <?php foreach ($busqTiposPersonal as $tp): ?>
                                            <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Estado Laboral:</label>
                                    <select id="fil_p_estado" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="">Cualquier estado</option>
                                        <option value="Activo">Activo</option>
                                        <option value="Permiso">Permiso</option>
                                        <option value="Reposo">Reposo</option>
                                        <option value="Inactivo">Inactivo</option>
                                        <option value="Jubilado">Jubilado</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Filtros de DOCUMENTOS -->
                            <div class="row g-2 adv-filtro-grupo d-none" data-tabla="documentos">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Tipo de Documento:</label>
                                    <select id="fil_d_tipo" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="0">Cualquier tipo</option>
                                        <?php foreach ($busqTiposDoc as $td): ?>
                                            <option value="<?= $td['id'] ?>"><?= $td['clave'] ?> - <?= htmlspecialchars($td['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Vinculado a:</label>
                                    <select id="fil_d_entidad" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="">Cualquier entidad</option>
                                        <option value="institucional">🏛️ Institucional / Libre</option>
                                        <option value="personal">👤 Expedientes de Personal</option>
                                        <option value="cronograma_eventos">📅 Eventos / Cronograma</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Filtros de ASISTENCIAS -->
                            <div class="row g-2 adv-filtro-grupo d-none" data-tabla="asistencias">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Estado de Asistencia:</label>
                                    <select id="fil_a_estado" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="">Todos</option>
                                        <option value="Presente">Presente</option>
                                        <option value="Ausente">Ausente</option>
                                        <option value="Retardo">Retardo</option>
                                        <option value="Falta Justificada">Falta Justificada</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Desde Fecha:</label>
                                    <input type="date" id="fil_a_desde" class="form-control form-control-sm adv-auto-trigger">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Hasta Fecha:</label>
                                    <input type="date" id="fil_a_hasta" class="form-control form-control-sm adv-auto-trigger">
                                </div>
                            </div>

                            <!-- Filtros de HORARIOS (CALENDARIO LABORAL) -->
                            <div class="row g-2 adv-filtro-grupo d-none" data-tabla="horarios">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Día de la Semana:</label>
                                    <select id="fil_h_dia" class="form-select form-select-sm adv-auto-trigger">
                                        <option value="">Cualquier día</option>
                                        <option value="Lunes">Lunes</option>
                                        <option value="Martes">Martes</option>
                                        <option value="Miércoles">Miércoles</option>
                                        <option value="Jueves">Jueves</option>
                                        <option value="Viernes">Viernes</option>
                                        <option value="Sábado">Sábado</option>
                                        <option value="Domingo">Domingo</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Filtros de CRONOGRAMA -->
                            <div class="row g-2 adv-filtro-grupo d-none" data-tabla="cronograma">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Desde Fecha:</label>
                                    <input type="date" id="fil_c_desde" class="form-control form-control-sm adv-auto-trigger">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Hasta Fecha:</label>
                                    <input type="date" id="fil_c_hasta" class="form-control form-control-sm adv-auto-trigger">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenedor de Resultados -->
                <div id="advSearchResultados">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-compass fs-1 opacity-25 d-block mb-2"></i>
                        <p class="mb-0">Escribe cualquier palabra clave, cédula o aplica un filtro para explorar todas las tablas del sistema.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Mover el modal al <body> para evitar problemas de z-index con la navbar
    const modalAdv = document.getElementById('modalBusquedaAvanzada');
    if (modalAdv) document.body.appendChild(modalAdv);

    // Listeners para el buscador rápido en la Navbar
    const navInput = document.getElementById('navSearchInput');
    const navDrop = document.getElementById('navSearchDropdown');
    let navTimer = null;

    if (navInput && navDrop) {
        navInput.addEventListener('input', () => {
            clearTimeout(navTimer);
            const q = navInput.value.trim();
            if (q.length < 2) {
                navDrop.classList.add('d-none');
                return;
            }
            navTimer = setTimeout(() => buscarEnNavbar(q), 250);
        });

        navInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                navDrop.classList.add('d-none');
                abrirModalBusquedaAvanzada(navInput.value.trim());
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#navSearchContainer')) {
                navDrop.classList.add('d-none');
            }
        });
    }

    // Listeners del Modal de Búsqueda Avanzada
    let advTimer = null;
    document.getElementById('advSearchQuery')?.addEventListener('input', () => {
        clearTimeout(advTimer);
        advTimer = setTimeout(ejecutarBusquedaAvanzada, 280);
    });
    document.querySelectorAll('.adv-auto-trigger').forEach(el => {
        el.addEventListener('change', ejecutarBusquedaAvanzada);
    });

    // Inicializar todos los widgets importables de trabajador en la página
    inicializarWidgetsTrabajador();
});

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// =========================================================================
// 1. BÚSQUEDA RÁPIDA EN NAVBAR
// =========================================================================
async function buscarEnNavbar(q) {
    const navDrop = document.getElementById('navSearchDropdown');
    navDrop.classList.remove('d-none');
    navDrop.innerHTML = '<div class="p-3 text-center small text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Buscando en el sistema...</div>';

    try {
        const res = await fetch(`../PHP/ajax_busqueda.php?modo=global&tabla=todos&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success) return;

        const r = data.resultados;
        let html = '';
        let total = 0;

        if (r.personal.length) {
            total += r.personal.length;
            html += `<div class="px-3 py-1 bg-body-tertiary small fw-bold text-primary border-bottom">👥 Personal (${r.personal.length})</div>`;
            r.personal.forEach(p => {
                html += `<a href="personal.php?tipo=${p.tipo_id || 1}&auto_edit=${p.id}" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><strong>${escHtml(p.apellidos)}, ${escHtml(p.nombres)}</strong> <small class="text-muted">(V-${escHtml(p.cedula)})</small></div>
                        <span class="badge bg-primary-subtle text-primary">${escHtml(p.cargo || 'S/C')}</span>
                    </div>
                </a>`;
            });
        }
        if (r.documentos.length) {
            total += r.documentos.length;
            html += `<div class="px-3 py-1 bg-body-tertiary small fw-bold text-warning-emphasis border-bottom">📁 Archivos (${r.documentos.length})</div>`;
            r.documentos.forEach(d => {
                html += `<a href="documentos.php?q=${encodeURIComponent(d.codigo_archivo)}" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-truncate me-2"><strong>${escHtml(d.nombre_original)}</strong> <small class="text-muted">${escHtml(d.codigo_archivo)}</small></div>
                        <span class="badge bg-secondary">${escHtml(d.tipo_clave)}</span>
                    </div>
                </a>`;
            });
        }
        if (r.asistencias.length) {
            total += r.asistencias.length;
            html += `<div class="px-3 py-1 bg-body-tertiary small fw-bold text-success border-bottom">📋 Asistencias (${r.asistencias.length})</div>`;
            r.asistencias.forEach(a => {
                html += `<a href="asistencias.php?fecha_filtro=${a.fecha}" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between">
                        <span><strong>${escHtml(a.apellidos)}, ${escHtml(a.nombres)}</strong></span>
                        <small class="text-muted">${a.fecha} · <b>${escHtml(a.estado_asistencia)}</b></small>
                    </div>
                </a>`;
            });
        }
        if (r.horarios.length) {
            total += r.horarios.length;
            html += `<div class="px-3 py-1 bg-body-tertiary small fw-bold text-info border-bottom">🗓️ Horarios (${r.horarios.length})</div>`;
            r.horarios.forEach(h => {
                html += `<a href="calendario.php" class="list-group-item list-group-item-action py-2">
                    <div><strong>${escHtml(h.actividad)}</strong> · <small>${escHtml(h.dia_semana)} (${h.hora_inicio.slice(0,5)} - ${h.hora_fin.slice(0,5)})</small></div>
                    <small class="text-muted">${escHtml(h.apellidos)}, ${escHtml(h.nombres)}</small>
                </a>`;
            });
        }
        if (r.cronograma.length) {
            total += r.cronograma.length;
            html += `<div class="px-3 py-1 bg-body-tertiary small fw-bold text-danger border-bottom">📌 Cronograma (${r.cronograma.length})</div>`;
            r.cronograma.forEach(c => {
                html += `<a href="cronograma.php?fecha_filtro=${c.fecha}" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between">
                        <strong>${escHtml(c.titulo)}</strong>
                        <small class="text-muted">${c.fecha}</small>
                    </div>
                </a>`;
            });
        }

        if (total === 0) {
            html = '<div class="p-3 text-center small text-muted">No se encontraron coincidencias rápidas.</div>';
        }
        html += `<button type="button" class="list-group-item list-group-item-action text-center bg-primary-subtle text-primary fw-bold py-2" onclick="abrirModalBusquedaAvanzada('${escHtml(q)}')">
            <i class="bi bi-sliders me-1"></i> Ver todos con Filtros Avanzados
        </button>`;

        navDrop.innerHTML = html;
    } catch (err) {
        navDrop.innerHTML = '<div class="p-3 text-danger small">Error de conexión.</div>';
    }
}

// =========================================================================
// 2. MODAL DE BÚSQUEDA AVANZADA CON FILTROS POR TABLA
// =========================================================================
function abrirModalBusquedaAvanzada(textoInicial = null) {
    document.getElementById('navSearchDropdown')?.classList.add('d-none');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBusquedaAvanzada'));
    modal.show();
    
    const qInput = document.getElementById('advSearchQuery');
    if (textoInicial !== null) qInput.value = textoInicial;
    else if (document.getElementById('navSearchInput')?.value) qInput.value = document.getElementById('navSearchInput').value;

    setTimeout(() => { qInput.focus(); }, 300);
    if (qInput.value.trim() !== '') ejecutarBusquedaAvanzada();
}

function cambiarFiltrosTablaAvanzada() {
    const tabla = document.getElementById('advSearchTabla').value;
    const cont = document.getElementById('advFiltrosContenedor');
    document.querySelectorAll('.adv-filtro-grupo').forEach(g => {
        g.classList.toggle('d-none', g.getAttribute('data-tabla') !== tabla);
    });
    cont.classList.toggle('d-none', tabla === 'todos');
    ejecutarBusquedaAvanzada();
}

async function ejecutarBusquedaAvanzada() {
    const q = document.getElementById('advSearchQuery').value.trim();
    const tabla = document.getElementById('advSearchTabla').value;
    const contRes = document.getElementById('advSearchResultados');

    contRes.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-2"></div><div>Consultando base de datos...</div></div>';

    const params = new URLSearchParams({
        modo: 'global',
        q: q,
        tabla: tabla,
        p_tipo: document.getElementById('fil_p_tipo')?.value || '0',
        p_estado: document.getElementById('fil_p_estado')?.value || '',
        d_tipo: document.getElementById('fil_d_tipo')?.value || '0',
        d_entidad: document.getElementById('fil_d_entidad')?.value || '',
        a_estado: document.getElementById('fil_a_estado')?.value || '',
        a_desde: document.getElementById('fil_a_desde')?.value || '',
        a_hasta: document.getElementById('fil_a_hasta')?.value || '',
        h_dia: document.getElementById('fil_h_dia')?.value || '',
        c_desde: document.getElementById('fil_c_desde')?.value || '',
        c_hasta: document.getElementById('fil_c_hasta')?.value || ''
    });

    try {
        const res = await fetch(`../PHP/ajax_busqueda.php?${params.toString()}`);
        const data = await res.json();
        const r = data.resultados;
        let html = '';

        // 1. PERSONAL
        if (r.personal.length) {
            html += `<h6 class="fw-bold text-primary mt-2 mb-2"><i class="bi bi-people-fill me-1"></i> Personal (${r.personal.length})</h6>
            <div class="list-group shadow-sm mb-3">`;
            r.personal.forEach(p => {
                html += `<a href="personal.php?tipo=${p.tipo_id || 1}&auto_edit=${p.id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${escHtml(p.apellidos)}, ${escHtml(p.nombres)} <span class="badge bg-secondary-subtle text-body border ms-1">V-${escHtml(p.cedula)}</span></div>
                        <small class="text-muted">${escHtml(p.tipo_nombre || '')} · ${escHtml(p.cargo || 'Sin cargo')} ${p.correo_electronico ? '· ' + escHtml(p.correo_electronico) : ''}</small>
                    </div>
                    <span class="badge bg-primary rounded-pill">${escHtml(p.estado_laboral)} · Editar <i class="bi bi-arrow-right"></i></span>
                </a>`;
            });
            html += '</div>';
        }

        // 2. DOCUMENTOS
        if (r.documentos.length) {
            html += `<h6 class="fw-bold text-warning-emphasis mt-2 mb-2"><i class="bi bi-folder2-open me-1"></i> Documentos / Archivos (${r.documentos.length})</h6>
            <div class="list-group shadow-sm mb-3">`;
            r.documentos.forEach(d => {
                html += `<div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold"><span class="badge bg-warning text-dark me-1">${escHtml(d.tipo_clave)}</span> ${escHtml(d.nombre_original)}</div>
                        <small class="text-muted font-monospace">${escHtml(d.codigo_archivo)}</small>
                        ${d.vinculos_txt ? `<div class="small text-info mt-1">${escHtml(d.vinculos_txt)}</div>` : ''}
                    </div>
                    <div class="d-flex gap-2">
                        <a href="${escHtml(d.ruta_archivo)}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-eye"></i> Abrir</a>
                        <a href="documentos.php?q=${encodeURIComponent(d.codigo_archivo)}" class="btn btn-sm btn-primary rounded-pill">Ver en Archivos</a>
                    </div>
                </div>`;
            });
            html += '</div>';
        }

        // 3. ASISTENCIAS
        if (r.asistencias.length) {
            html += `<h6 class="fw-bold text-success mt-2 mb-2"><i class="bi bi-person-check-fill me-1"></i> Asistencias (${r.asistencias.length})</h6>
            <div class="list-group shadow-sm mb-3">`;
            r.asistencias.forEach(a => {
                html += `<a href="asistencias.php?fecha_filtro=${a.fecha}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${escHtml(a.apellidos)}, ${escHtml(a.nombres)} <small class="text-muted">(V-${escHtml(a.cedula)})</small></div>
                        <small class="text-muted">${a.fecha} · ${escHtml(a.observaciones || 'Sin observaciones')}</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border">${escHtml(a.estado_asistencia)}</span>
                </a>`;
            });
            html += '</div>';
        }

        // 4. HORARIOS
        if (r.horarios.length) {
            html += `<h6 class="fw-bold text-info mt-2 mb-2"><i class="bi bi-calendar-week me-1"></i> Calendario Laboral (${r.horarios.length})</h6>
            <div class="list-group shadow-sm mb-3">`;
            r.horarios.forEach(h => {
                html += `<a href="calendario.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${escHtml(h.actividad)}</div>
                        <small class="text-muted">${escHtml(h.apellidos)}, ${escHtml(h.nombres)} (${escHtml(h.cargo || 'S/C')})</small>
                    </div>
                    <span class="badge bg-info text-dark">${escHtml(h.dia_semana)} ${h.hora_inicio.slice(0,5)} - ${h.hora_fin.slice(0,5)}</span>
                </a>`;
            });
            html += '</div>';
        }

        // 5. CRONOGRAMA
        if (r.cronograma.length) {
            html += `<h6 class="fw-bold text-danger mt-2 mb-2"><i class="bi bi-calendar2-event me-1"></i> Cronograma de Eventos (${r.cronograma.length})</h6>
            <div class="list-group shadow-sm mb-3">`;
            r.cronograma.forEach(c => {
                html += `<a href="cronograma.php?fecha_filtro=${c.fecha}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${escHtml(c.titulo)}</div>
                        <small class="text-muted">${escHtml(c.descripcion || '')} ${c.participantes ? '· 👥 ' + escHtml(c.participantes) : ''}</small>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border">${c.fecha} (${c.hora_inicio.slice(0,5)})</span>
                </a>`;
            });
            html += '</div>';
        }

        if (html === '') {
            html = `<div class="text-center text-muted py-5">
                <i class="bi bi-emoji-neutral fs-1 opacity-25 d-block mb-2"></i>
                <h5>Sin resultados</h5>
                <p class="small mb-0">No hay registros que coincidan con tu búsqueda o filtros actuales.</p>
            </div>`;
        }

        contRes.innerHTML = html;
    } catch (err) {
        contRes.innerHTML = '<div class="alert alert-danger">Ocurrió un error al consultar la búsqueda avanzada.</div>';
    }
}

// =========================================================================
// 3. LÓGICA DEL WIDGET IMPORTABLE DE TRABAJADORES (renderBuscadorTrabajador)
// =========================================================================
function inicializarWidgetsTrabajador() {
    document.querySelectorAll('.widget-buscador-personal').forEach(widget => {
        if (widget.dataset.init === '1') return;
        widget.dataset.init = '1';

        const hiddenInput = widget.querySelector('.wb-hidden-id');
        const tipoSelect  = widget.querySelector('.wb-filtro-tipo');
        const textInput   = widget.querySelector('.wb-input-texto');
        const dropdown    = widget.querySelector('.wb-dropdown');
        const boxSel      = widget.querySelector('.wb-seleccionado');
        const txtSel      = widget.querySelector('.wb-seleccionado-txt');
        const btnLimpiar  = widget.querySelector('.wb-btn-limpiar');
        const btnCambiar  = widget.querySelector('.wb-btn-cambiar');
        const soloActivos = textInput.dataset.soloActivos || '0';

        let timer = null;

        async function consultarTrabajadores() {
            const q = textInput.value.trim();
            const tipo = tipoSelect.value;
            dropdown.classList.remove('d-none');
            dropdown.innerHTML = '<div class="list-group-item small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Buscando...</div>';

            try {
                const res = await fetch(`../PHP/ajax_busqueda.php?modo=selector_personal&q=${encodeURIComponent(q)}&tipo_personal_id=${tipo}&solo_activos=${soloActivos}`);
                const data = await res.json();
                dropdown.innerHTML = '';

                if (!data.resultados || data.resultados.length === 0) {
                    dropdown.innerHTML = '<div class="list-group-item small text-muted text-center">No se encontraron trabajadores.</div>';
                    return;
                }

                data.resultados.forEach(p => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action py-2 small';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>${escHtml(p.apellidos)}, ${escHtml(p.nombres)}</strong> <span class="text-muted">(V-${escHtml(p.cedula)})</span></div>
                            <span class="badge bg-primary-subtle text-primary">${escHtml(p.cargo || 'S/C')}</span>
                        </div>
                    `;
                    item.addEventListener('click', () => {
                        hiddenInput.value = p.id;
                        txtSel.innerText = `✓ ${p.apellidos}, ${p.nombres} (V-${p.cedula})`;
                        boxSel.classList.remove('d-none');
                        textInput.value = `${p.apellidos}, ${p.nombres}`;
                        btnLimpiar.classList.remove('d-none');
                        dropdown.classList.add('d-none');
                    });
                    dropdown.appendChild(item);
                });
            } catch (e) {
                dropdown.innerHTML = '<div class="list-group-item small text-danger">Error de búsqueda.</div>';
            }
        }

        textInput.addEventListener('focus', () => consultarTrabajadores());
        textInput.addEventListener('input', () => {
            hiddenInput.value = '';
            boxSel.classList.add('d-none');
            clearTimeout(timer);
            timer = setTimeout(consultarTrabajadores, 220);
        });
        tipoSelect.addEventListener('change', () => consultarTrabajadores());

        const limpiar = () => {
            hiddenInput.value = '';
            textInput.value = '';
            boxSel.classList.add('d-none');
            btnLimpiar.classList.add('d-none');
            dropdown.classList.add('d-none');
            textInput.focus();
        };

        btnLimpiar.addEventListener('click', limpiar);
        btnCambiar.addEventListener('click', limpiar);

        document.addEventListener('click', (e) => {
            if (!widget.contains(e.target)) dropdown.classList.add('d-none');
        });
    });
}
</script>