<?php 
$paginaActual = basename($_SERVER['PHP_SELF']); 
$tipoActual = $_GET['tipo'] ?? '';

$nombreUserNav = $_SESSION['admin_user'] ?? 'admin';
$inicialesNav = mb_strtoupper(mb_substr($nombreUserNav, 0, 2, 'UTF-8'), 'UTF-8');
$perfilNav = function_exists('obtenerPerfilSeguridad') && isset($_SESSION['admin_id'])
    ? obtenerPerfilSeguridad($pdo, $_SESSION['admin_id'])
    : null;
$rolUserNav = $perfilNav['rol_nombre'] ?? 'SuperAdministrador';
?>

<!-- SIDEBAR: Offcanvas en < 992px | Colapsable con iconos en >= 992px -->
<div id="sidebar-wrapper" class="offcanvas-lg offcanvas-start" tabindex="-1" aria-labelledby="sidebarLabel">
    <div class="sidebar-heading justify-content-between">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
            <!-- Escudo Institucional SVG -->
            <svg width="32" height="32" viewBox="0 0 64 64" class="flex-shrink-0">
                <path d="M32 4L8 14v16c0 15.2 10.2 28.8 24 32 13.8-3.2 24-16.8 24-32V14L32 4z" fill="rgba(255,255,255,0.18)" stroke="#ffffff" stroke-width="3"/>
                <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-size="22" font-family="Arial, sans-serif" font-weight="bold">LU</text>
            </svg>
            <span class="sidebar-text text-truncate" id="sidebarLabel">U.E.N Luis Urdaneta</span>
        </div>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar-wrapper" aria-label="Cerrar menú"></button>
    </div>

    <div class="py-2 flex-grow-1">
        <div class="sidebar-section-title">Personal</div>
        <a href="dashboard.php" class="menu-link <?= ($paginaActual == 'dashboard.php') ? 'active' : ''; ?>" title="Dashboard">
            <i class="bi bi-speedometer2 me-3"></i><span class="sidebar-text">Dashboard</span>
        </a>
        <a href="personal.php?tipo=1" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '1') ? 'active' : ''; ?>" title="Personal Docente">
            <i class="bi bi-book me-3"></i><span class="sidebar-text">Personal Docente</span>
        </a>
        <a href="personal.php?tipo=2" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '2') ? 'active' : ''; ?>" title="Personal Administrativo">
            <i class="bi bi-pc-display me-3"></i><span class="sidebar-text">Personal Administrativo</span>
        </a>
        <a href="personal.php?tipo=3" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '3') ? 'active' : ''; ?>" title="Personal Obrero">
            <i class="bi bi-tools me-3"></i><span class="sidebar-text">Personal Obrero</span>
        </a>
        <a href="organigrama.php" class="menu-link <?= ($paginaActual == 'organigrama.php') ? 'active' : ''; ?>" title="Organigrama">
            <i class="bi bi-diagram-3 me-3"></i><span class="sidebar-text">Organigrama</span>
        </a>

        <div class="sidebar-section-title">Gestión</div>
        <a href="calendario.php" class="menu-link <?= ($paginaActual == 'calendario.php') ? 'active' : ''; ?>" title="Calendario Laboral">
            <i class="bi bi-calendar3 me-3"></i><span class="sidebar-text">Calendario Laboral</span>
        </a>
        <a href="asistencias.php" class="menu-link <?= ($paginaActual == 'asistencias.php') ? 'active' : ''; ?>" title="Control Asistencias">
            <i class="bi bi-person-check me-3"></i><span class="sidebar-text">Control Asistencias</span>
        </a>
        <a href="cronograma.php" class="menu-link <?= ($paginaActual == 'cronograma.php') ? 'active' : ''; ?>" title="Cronograma">
            <i class="bi bi-calendar-event me-3"></i><span class="sidebar-text">Cronograma</span>
        </a>
        <a href="documentos.php" class="menu-link <?= ($paginaActual == 'documentos.php') ? 'active' : ''; ?>" title="Archivo Digital">
            <i class="bi bi-folder2-open me-3"></i><span class="sidebar-text">Archivo Digital</span>
        </a>

        <div class="sidebar-section-title">Sistema</div>
        <a href="historial.php" class="menu-link <?= ($paginaActual == 'historial.php') ? 'active' : ''; ?>" title="Auditoría">
            <i class="bi bi-clock-history me-3"></i><span class="sidebar-text">Auditoría</span>
        </a>
        <a href="configuracion.php" class="menu-link <?= ($paginaActual == 'configuracion.php') ? 'active' : ''; ?>" title="Configuración">
            <i class="bi bi-gear me-3"></i><span class="sidebar-text">Configuración</span>
        </a>
    </div>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-top px-3 px-md-4 py-2 d-flex justify-content-between align-items-center gap-2">
        
        <!-- Botón colapsar escritorio (>=992px) -->
        <button class="btn btn-icon-theme shadow-sm d-none d-lg-inline-flex align-items-center justify-content-center" id="menu-toggle" aria-label="Contraer o expandir menú lateral" title="Modo compacto">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- Botón offcanvas móvil/tablet (<992px) -->
        <button class="btn btn-icon-theme shadow-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar-wrapper" aria-controls="sidebar-wrapper" aria-label="Abrir menú de navegación">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- BARRA DE BÚSQUEDA GLOBAL EN NAVBAR -->
        <div class="position-relative flex-grow-1 mx-2" style="max-width: 460px;" id="navSearchContainer">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-body-tertiary border-end-0 text-primary"><i class="bi bi-search"></i></span>
                <input type="text" id="navSearchInput" class="form-control bg-body-tertiary border-start-0 border-end-0 shadow-none" placeholder="Buscar cédula, nombre, archivo... (Ctrl+K)" autocomplete="off" aria-label="Búsqueda rápida en el sistema">
                <button class="btn btn-icon-theme border-start-0 text-primary" type="button" onclick="abrirModalBusquedaAvanzada()" aria-label="Abrir búsqueda avanzada con filtros" title="Búsqueda Avanzada">
                    <i class="bi bi-sliders"></i>
                </button>
            </div>
            <div id="navSearchDropdown" class="list-group position-absolute w-100 shadow-lg d-none mt-1" style="z-index: 1080; max-height: 400px; overflow-y: auto;"></div>
        </div>

        <!-- ACCIONES DERECHA: CONFIGURACIÓN + PERFIL CON INICIALES -->
        <div class="d-flex align-items-center ms-auto gap-2">
            <a href="configuracion.php" class="btn btn-icon-theme rounded-circle shadow-sm d-none d-sm-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" aria-label="Configuración del sistema" title="Configuración">
                <i class="bi bi-gear-fill fs-6"></i>
            </a>

            <div class="dropdown">
                <button class="btn btn-icon-theme rounded-pill shadow-sm d-flex align-items-center gap-2 py-1 ps-1 pe-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                    <span class="rounded-circle bg-primary text-white fw-bold d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?= htmlspecialchars($inicialesNav) ?>
                    </span>
                    <span class="text-start d-none d-md-block lh-1">
                        <span class="d-block fw-bold small"><?= htmlspecialchars($nombreUserNav) ?></span>
                        <small class="text-muted" style="font-size: 0.68rem;"><?= htmlspecialchars($rolUserNav) ?></small>
                    </span>
                    <i class="bi bi-chevron-down small text-muted"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="min-width: 220px;">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold small"><?= htmlspecialchars($nombreUserNav) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($rolUserNav) ?> · ReBAC Activo</small>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item py-2 d-flex align-items-center justify-content-between" id="quickThemeToggle">
                            <span><i class="bi bi-moon-stars me-2 text-primary"></i><span id="quickThemeLabel">Modo Oscuro</span></span>
                            <span class="badge bg-secondary-subtle text-body border" id="quickThemeBadge">Off</span>
                        </button>
                    </li>
                    <li><a class="dropdown-item py-2" href="configuracion.php"><i class="bi bi-gear me-2 text-secondary"></i>Ajustes del Sistema</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="../PHP/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <?php include_once __DIR__ . '/buscador_avanzado.php'; ?>
    <div class="container-fluid p-3 p-md-4">