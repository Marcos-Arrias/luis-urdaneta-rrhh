<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: Pages/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Portal RRHH</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill='%231e3c72' d='M32 4L8 14v16c0 15.2 10.2 28.8 24 32 13.8-3.2 24-16.8 24-32V14L32 4z'/%3E%3Ctext x='50%25' y='56%25' dominant-baseline='middle' text-anchor='middle' fill='%23ffffff' font-size='24' font-family='Arial' font-weight='bold'%3ELU%3C/text%3E%3C/svg%3E">

    <!-- Aplicar tema oscuro antes de renderizar (cero parpadeo) -->
    <script>
        if (localStorage.getItem('modoOscuro') === 'activado') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>

    <!-- Bootstrap 5 Local (100% Offline) -->
    <link href="CSS/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/bootstrap-5.3.8-dist/css/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="CSS/estilos.css?v=<?= time() ?>" rel="stylesheet">

    <style>
        body.login-bg {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
        }
        .login-card {
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 1.5rem !important;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
            background-color: rgba(var(--bs-body-bg-rgb), 0.94);
            backdrop-filter: blur(12px);
        }
        .logo-container {
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -58px auto 18px auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            border: 4px solid var(--bs-body-bg);
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            padding: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.25s ease;
            color: #fff;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(30, 60, 114, 0.45);
            color: #fff;
        }
    </style>
</head>
<body class="login-bg d-flex justify-content-center align-items-center p-3">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                
                <div class="card login-card mt-5">
                    <div class="card-body p-4 p-md-5">
                        
                        <!-- Escudo Institucional SVG -->
                        <div class="logo-container">
                            <svg width="46" height="46" viewBox="0 0 64 64" fill="none">
                                <path d="M32 6L10 15V30C10 43.8 19.4 56.2 32 59C44.6 56.2 54 43.8 54 30V15L32 6Z" stroke="#ffffff" stroke-width="4" fill="rgba(255,255,255,0.12)"/>
                                <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-size="20" font-family="Arial, sans-serif" font-weight="bold">LU</text>
                            </svg>
                        </div>

                        <h3 class="text-center mb-1 fw-bold">Portal RRHH</h3>
                        <p class="text-center text-muted small mb-4">U.E.N Luis Urdaneta</p>

                        <?php if (isset($_GET['error'])): ?>
                            <?php if ($_GET['error'] == 2): ?>
                                <div class="alert alert-warning text-center py-2 shadow-sm small fw-bold" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Esta cuenta ya está abierta en otro dispositivo (sesión previa activa).
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger text-center py-2 shadow-sm small" role="alert">
                                    <i class="bi bi-shield-x me-1"></i> Credenciales inválidas o cuenta bloqueada.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info py-2 text-center small border-0 bg-info-subtle text-info-emphasis">
                                <i class="bi bi-shield-lock me-1"></i> Acceso Administrativo Seguro
                            </div>
                        <?php endif; ?>

                        <form action="PHP/procesar_login.php" method="POST">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required autofocus autocomplete="username">
                                <label for="usuario"><i class="bi bi-person-badge me-2 text-primary"></i>Usuario</label>
                            </div>

                            <div class="input-group mb-4">
                                <div class="form-floating flex-grow-1">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                                    <label for="password"><i class="bi bi-key me-2 text-primary"></i>Contraseña</label>
                                </div>
                                <button class="btn btn-icon-theme px-3" type="button" id="toggleLoginPass" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-login rounded-pill">
                                    Ingresar al Sistema <i class="bi bi-box-arrow-in-right ms-2"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" id="loginThemeToggle">
                                <i class="bi bi-moon-stars me-1"></i> Cambiar tema Claro / Oscuro
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 text-white opacity-75 small">
                    &copy; <?= date('Y') ?> Departamento de Recursos Humanos · U.E.N Luis Urdaneta
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mostrar / ocultar contraseña
        const passInput = document.getElementById('password');
        const togglePass = document.getElementById('toggleLoginPass');
        togglePass.addEventListener('click', () => {
            const isPass = passInput.type === 'password';
            passInput.type = isPass ? 'text' : 'password';
            togglePass.innerHTML = isPass ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });

        // Cambiar tema desde el login
        document.getElementById('loginThemeToggle').addEventListener('click', () => {
            const esOscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const nuevo = esOscuro ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', nuevo);
            localStorage.setItem('modoOscuro', nuevo === 'dark' ? 'activado' : 'desactivado');
        });
    </script>
</body>
</html>