<?php
session_start();
if(isset($_SESSION['admin_id'])) {
    header("Location: Pages/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal RRHH</title>
    
<!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .login-card { border: none; border-radius: 1.5rem; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5); background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .logo-container { width: 90px; height: 90px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: -50px auto 20px auto; box-shadow: 0 8px 15px rgba(0,0,0,0.2); border: 5px solid #fff; }
        .logo-container i { font-size: 2.8rem; color: #ffffff; }
        .btn-login { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border: none; padding: 14px; font-weight: bold; letter-spacing: 1px; transition: all 0.3s ease; color: white;}
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4); color: white;}
    </style>
</head>
<body class="d-flex justify-content-center align-items-center">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                
                <div class="card login-card mt-5">
                    <div class="card-body p-4 p-md-5">
                        
                        <div class="logo-container">
                            <i class="bi bi-person-workspace"></i>
                        </div>

                        <h3 class="text-center mb-1 fw-bold text-dark">Portal RRHH</h3>
                        <p class="text-center text-muted mb-3">U.E.N Luis Urdaneta</p>

                        <!-- Mensaje de Error PHP -->
                        <?php if(isset($_GET['error'])): ?>
                            <div class="alert alert-danger text-center py-2 shadow-sm" role="alert" style="font-size: 0.9rem;">
                                <i class="bi bi-shield-x me-1"></i> Credenciales inválidas o cuenta bloqueada.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info py-2 text-center" style="font-size: 0.85rem;">
                                <i class="bi bi-info-circle me-1"></i> Ingrese sus credenciales administrativas
                            </div>
                        <?php endif; ?>

                        <!-- FORMULARIO APUNTANDO AL NUEVO PROCESAR_LOGIN.PHP -->
                        <form action="PHP/procesar_login.php" method="POST" class="needs-validation" novalidate>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required autofocus>
                                <label for="usuario"><i class="bi bi-person-badge me-2 text-primary"></i>Usuario Admin</label>
                            </div>

                            <div class="form-floating mb-4">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                                <label for="password"><i class="bi bi-shield-lock me-2 text-primary"></i>Clave de Seguridad</label>
                            </div>

                            <div class="d-grid mt-2">
                                <button type="submit" class="btn btn-login rounded-pill">
                                    Ingresar al Sistema <i class="bi bi-box-arrow-in-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4 text-white opacity-75" style="font-size: 0.85rem;">
                    &copy; <?= date('Y') ?> Departamento de Recursos Humanos.<br>Todos los derechos reservados.
                </div>

            </div>
        </div>
    </div>
</body>
</html>