<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal RRHH - U.E.N Luis Urdaneta</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill='%231e3c72' d='M32 4L8 14v16c0 15.2 10.2 28.8 24 32 13.8-3.2 24-16.8 24-32V14L32 4z'/%3E%3Ctext x='50%25' y='56%25' dominant-baseline='middle' text-anchor='middle' fill='%23ffffff' font-size='24' font-family='Arial' font-weight='bold'%3ELU%3C/text%3E%3C/svg%3E">

    <!-- Aplicar tema oscuro antes de renderizar -->
    <script>
        if (localStorage.getItem('modoOscuro') === 'activado') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>

    <!-- Bootstrap 5 + Iconos + CSS Propio (100% Offline) -->
    <link href="../CSS/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../CSS/bootstrap-5.3.8-dist/css/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../CSS/estilos.css?v=<?= time() ?>" rel="stylesheet">

    <script>
        // CONTROL DE INSTANCIA ÚNICA (PESTAÑAS) - SIN COLORES FIJOS NI EMOJIS
        const appChannel = new BroadcastChannel('rrhh_app_channel');
        let soyLaInstanciaOriginal = true;

        appChannel.postMessage('nueva_instancia_abierta');

        appChannel.onmessage = (evento) => {
            if (evento.data === 'nueva_instancia_abierta' && soyLaInstanciaOriginal) {
                appChannel.postMessage('instancia_previa_activa');
            } else if (evento.data === 'instancia_previa_activa') {
                soyLaInstanciaOriginal = false;
                document.body.innerHTML = `
                    <div class="d-flex justify-content-center align-items-center min-vh-100 p-3 bg-body-tertiary">
                        <div class="card border-0 shadow-lg text-center p-4 p-md-5" style="max-width: 480px;">
                            <div class="mx-auto mb-3 icon-circle bg-warning-subtle text-warning-emphasis" style="width: 72px; height: 72px;">
                                <i class="bi bi-shield-exclamation fs-1"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Sistema en Uso</h4>
                            <p class="text-muted small mb-4">
                                El Portal RRHH ya se encuentra abierto en otra pestaña principal. Para proteger la integridad de los datos, esta instancia secundaria ha sido bloqueada.
                            </p>
                            <button onclick="cerrarPestanaSeguro()" class="btn btn-danger rounded-pill px-4 fw-bold">
                                <i class="bi bi-x-circle me-1"></i> Cerrar esta pestaña
                            </button>
                        </div>
                    </div>
                `;
                window.stop();
            }
        };

        function cerrarPestanaSeguro() {
            window.close();
            setTimeout(() => { window.location.href = "about:blank"; }, 100);
        }
    </script>
</head>
<body>
<div id="wrapper">