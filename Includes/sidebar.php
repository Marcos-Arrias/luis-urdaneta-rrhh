<?php 
$paginaActual = basename($_SERVER['PHP_SELF']); 
$tipoActual = $_GET['tipo'] ?? ''; // Para iluminar el menú correcto
?>

<div id="sidebar-wrapper">
    <div class="sidebar-heading text-center"><i class="bi bi-bank me-2"></i>U.E.N Luis Urdaneta</div>
    <div class="mt-3">
        <a href="dashboard.php" class="menu-link <?= ($paginaActual == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-speedometer2 me-3"></i>Dashboard</a>
        
        <!-- ENLACES UNIFICADOS APUNTANDO A personal.php -->
        <a href="personal.php?tipo=1" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '1') ? 'active' : ''; ?>"><i class="bi bi-book me-3"></i>Personal Docente</a>
        <a href="personal.php?tipo=2" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '2') ? 'active' : ''; ?>"><i class="bi bi-pc-display me-3"></i>Personal Administrativo</a>
        <a href="personal.php?tipo=3" class="menu-link <?= ($paginaActual == 'personal.php' && $tipoActual == '3') ? 'active' : ''; ?>"><i class="bi bi-tools me-3"></i>Personal Obrero</a>
        <a href="organigrama.php" class="menu-link <?= ($paginaActual == 'organigrama.php') ? 'active' : ''; ?>"><i class="bi bi-diagram-3 me-3"></i>Organigrama</a>
        <a href="calendario.php" class="menu-link <?= ($paginaActual == 'calendario.php') ? 'active' : ''; ?>"><i class="bi bi-calendar3 me-3"></i>Calendario Laboral</a>
        <a href="asistencias.php" class="menu-link <?= ($paginaActual == 'asistencias.php') ? 'active' : ''; ?>"><i class="bi bi-person-x me-3"></i>Control Asistencias</a>
        <a href="cronograma.php" class="menu-link <?= ($paginaActual == 'cronograma.php') ? 'active' : ''; ?>"><i class="bi bi-calendar-event me-3"></i>Cronograma</a>
        <a href="documentos.php" class="menu-link <?= ($paginaActual == 'documentos.php') ? 'active' : ''; ?>"><i class="bi bi-folder2-open me-3"></i>Documentos</a>
        <a href="historial.php" class="menu-link <?= ($paginaActual == 'historial.php') ? 'active' : ''; ?>"><i class="bi bi-clock-history me-3"></i>Auditoría</a>
    </div>
</div>
<!-- RESTO DE TU SIDEBAR HTML (Navbar superior, Ajustes, etc.) QUEDA IGUAL -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-top px-4 py-2 d-flex justify-content-between align-items-center gap-3">
                <button class="btn btn-outline-secondary shadow-sm" id="menu-toggle"><i class="bi bi-list fs-5"></i></button>
                
                <!-- BARRA DE BÚSQUEDA AVANZADA EN NAVBAR -->
                <div class="position-relative flex-grow-1 mx-2" style="max-width: 480px;" id="navSearchContainer">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-body-tertiary border-end-0 text-primary"><i class="bi bi-search"></i></span>
                        <input type="text" id="navSearchInput" class="form-control bg-body-tertiary border-start-0 border-end-0 shadow-none" placeholder="Buscar cédula, nombre, archivo, evento... (Ctrl+K)" autocomplete="off">
                        <button class="btn btn-body-tertiary border border-start-0 text-primary fw-bold" type="button" onclick="abrirModalBusquedaAvanzada()" title="Búsqueda Avanzada con Filtros">
                            <i class="bi bi-sliders"></i>
                        </button>
                    </div>
                    <!-- Resultados instantáneos desplegables -->
                    <div id="navSearchDropdown" class="list-group position-absolute w-100 shadow-lg d-none mt-1" style="z-index: 1080; max-height: 400px; overflow-y: auto;"></div>
                </div>

                <div class="d-flex align-items-center ms-auto">
                    <a href="configuracion.php" class="btn btn-light rounded-circle shadow-sm me-3 border text-secondary" style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-gear-fill fs-5"></i></a>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle rounded-pill shadow-sm px-4 py-2" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Administrador') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><a class="dropdown-item py-2 text-danger fw-bold" href="../PHP/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            <?php include_once __DIR__ . '/buscador_avanzado.php'; ?>
            <div class="container-fluid p-4">