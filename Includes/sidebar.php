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
        
        <a href="calendario.php" class="menu-link <?= ($paginaActual == 'calendario.php') ? 'active' : ''; ?>"><i class="bi bi-calendar3 me-3"></i>Calendario Laboral</a>
        <a href="asistencias.php" class="menu-link <?= ($paginaActual == 'asistencias.php') ? 'active' : ''; ?>"><i class="bi bi-person-x me-3"></i>Control Asistencias</a>
        <a href="cronograma.php" class="menu-link <?= ($paginaActual == 'cronograma.php') ? 'active' : ''; ?>"><i class="bi bi-calendar-event me-3"></i>Cronograma</a>
        <a href="historial.php" class="menu-link <?= ($paginaActual == 'historial.php') ? 'active' : ''; ?>"><i class="bi bi-clock-history me-3"></i>Auditoría</a>
    </div>
</div>
<!-- RESTO DE TU SIDEBAR HTML (Navbar superior, Ajustes, etc.) QUEDA IGUAL -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-top px-4 py-3 d-flex justify-content-between align-items-center">
                <button class="btn btn-outline-secondary shadow-sm" id="menu-toggle"><i class="bi bi-list fs-5"></i></button>
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
            <div class="container-fluid p-4">