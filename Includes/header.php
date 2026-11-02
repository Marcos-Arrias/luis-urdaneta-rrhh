<?php
// Verifica si la sesión NO ha sido iniciada previamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay una sesión activa, lo patea al login
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
    <title>Gestión de Personal - CRUD</title>
    
    <!-- Carga Bootstrap desde la carpeta CSS local -->
    <link href="../CSS/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../CSS/bootstrap-5.3.8-dist/css/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        
        
        body { 
            margin: 0; 
            overflow: hidden; 
            background-color: var(--bs-body-bg); 
            color: var(--bs-body-color);
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            transition: background 0.3s ease;
        }
        
        .navbar-top { 
            background-color: var(--bs-body-bg); /* Adaptable al modo oscuro */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            z-index: 10; 
            border-bottom: 1px solid var(--bs-border-color); 
        }
        /* EL RESTO DE TU CSS SE MANTIENE IGUAL */

        #wrapper { display: flex; width: 100vw; height: 100vh; }
        
        #sidebar-wrapper { 
            width: 260px; 
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%); 
            transition: all 0.3s; flex-shrink: 0; display: flex; flex-direction: column;
        }
        
        #page-content-wrapper { 
            flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; transition: all 0.3s;
        }
        
        #wrapper.toggled #sidebar-wrapper { margin-left: -260px; }
        
        .sidebar-heading { padding: 1.5rem 1.25rem; font-size: 1.2rem; color: white; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-link { color: rgba(255,255,255,0.85); padding: 12px 20px; text-decoration: none; display: block; transition: 0.2s; font-weight: 500;}
        
        /* Efecto Hover */
        .menu-link:hover { background-color: rgba(255,255,255,0.1); color: white; }
        
        /* Efecto de PÁGINA ACTIVA (Oscurece el fondo) */
        .menu-link.active { 
            background-color: rgba(0, 0, 0, 0.25); 
            color: white; 
            border-radius: 0 25px 25px 0; 
            margin-right: 15px; 
            border-left: 4px solid #fff; 
        }
</style>

<script>
    // ==========================================
    // CONTROL DE INSTANCIA ÚNICA (PESTAÑAS)
    // ==========================================
    const appChannel = new BroadcastChannel('rrhh_app_channel');
    let soyLaInstanciaOriginal = true;

    // Al cargar la página, grito a las demás pestañas: "¡Me acabo de abrir!"
    appChannel.postMessage('nueva_instancia_abierta');

    // Escuchar mensajes de otras pestañas
    appChannel.onmessage = (evento) => {
        
        // Si alguien grita que se acaba de abrir, y YO soy el original...
        if (evento.data === 'nueva_instancia_abierta' && soyLaInstanciaOriginal) {
            // Le respondo: "¡Oye, yo llegué primero!"
            appChannel.postMessage('instancia_previa_activa');
        } 
        
        // Si acabo de entrar y alguien me dice que ya estaba antes...
        else if (evento.data === 'instancia_previa_activa') {
            soyLaInstanciaOriginal = false;
            
            // Destruyo la interfaz visual de esta nueva pestaña (Priorizo la antigua)
            document.body.innerHTML = `
                <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; background:#212529; color:white; font-family:sans-serif; text-align:center; padding: 20px;">
                    <h1 style="color:#ffc107;">⚠️ Sistema en Uso</h1>
                    <p style="font-size: 1.2rem;">Ya tienes este sistema abierto en otra pestaña.</p>
                    <p style="color:#adb5bd;">Por razones de seguridad y para evitar conflictos de datos, solo se permite una instancia a la vez. Por favor, cierra esta pestaña y usa la original (la más antigua).</p>
                    </div>
            `;
            
            // Detengo cualquier otro script
            window.stop(); 
        }
    };
</script>

</head>
<body>
<div id="wrapper">