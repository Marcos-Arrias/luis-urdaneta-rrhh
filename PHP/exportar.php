<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['admin_id'])) { die("Acceso denegado."); }

$formato = $_GET['formato'] ?? 'excel';
$tipo_filtro = $_GET['tipo'] ?? 'todos';

$sql = "
    SELECT p.cedula, p.nombres, p.apellidos, p.fecha_ingreso, p.estado_laboral, 
           c.nombre as cargo, t.nombre as tipo,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           pda.correo_electronico, pda.cargo_nominal, pda.codigo_cargo, pda.fecha_ingreso_mppe, pda.turno, pda.horas_laborales, pda.banco_nomina, pda.cuenta_bancaria
    FROM personal p 
    JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id 
    LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
";

$params = [];
if ($tipo_filtro !== 'todos') {
    $sql .= " WHERE t.id = ? ";
    $params[] = intval($tipo_filtro);
}
$sql .= " ORDER BY t.id ASC, p.apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personal = $stmt->fetchAll();

registrarHistorial($pdo, 'Exportación', 'EXPORTAR', "Generó reporte en formato: " . strtoupper($formato));

// ==========================================
// 1. POWERPOINT (.PPT / PPTX)
// ==========================================
if ($formato === 'pptx' || $formato === 'ppt') {
    header("Content-Type: application/vnd.ms-powerpoint; charset=utf-8");
    header("Content-Disposition: attachment; filename=Asamblea_Personal_" . date('Y-m-d') . ".ppt");
    echo "\xEF\xBB\xBF";
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:p="urn:schemas-microsoft-com:office:powerpoint">
    <head><meta charset="utf-8"></head>
    <body style="font-family: Arial, sans-serif; background: #1e3c72; color: white;">
        <div style="text-align: center; padding: 50px;">
            <h1 style="font-size: 32px;">U.E.N LUIS URDANETA</h1>
            <h2 style="font-size: 24px; color: #ffca28;">Nómina General y Consejo Docente</h2>
            <p>Emisión: <?= date('d/m/Y') ?></p>
        </div>
        <div style="page-break-before: always; background: white; color: #333; padding: 30px;">
            <h2 style="color: #1e3c72; border-bottom: 2px solid #1e3c72;">Resumen de Trabajadores</h2>
            <table border="1" cellpadding="5" style="width: 100%; border-collapse: collapse;">
                <tr style="background: #eee;"><th>Cédula</th><th>Nombre</th><th>Cargo Asignado</th><th>Turno</th></tr>
                <?php foreach ($personal as $p): ?>
                <tr>
                    <td><?= $p['cedula'] ?></td>
                    <td><?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?></td>
                    <td><?= htmlspecialchars($p['cargo']) ?> (<?= $p['tipo'] ?>)</td>
                    <td><?= $p['turno'] ?? 'Mañana' ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </body></html>
    <?php exit;
}

// ==========================================
// 2. IMAGEN (JPG) CON HTML CANVAS
// ==========================================
elseif ($formato === 'jpg') { ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Generando Imagen...</title>
        <script src="../JS/html2canvas.min.js"></script>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f3; padding: 20px; display: flex; flex-direction: column; align-items: center; }
            #captura { width: 900px; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header-box { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; font-size: 13px; }
            th { background-color: #f8f9fa; color: #1e3c72; font-weight: bold; }
        </style>
    </head>
    <body>
        <div id="captura">
            <div class="header-box">
                <h2 style="margin: 0;">U.E.N LUIS URDANETA</h2>
                <h4 style="margin: 5px 0 0; font-weight: 400;">Infografía Oficial de Personal Activo</h4>
                <small>Fecha: <?= date('d/m/Y h:i A') ?></small>
            </div>
            <table>
                <thead><tr><th>Cédula</th><th>Trabajador</th><th>Función</th><th>Teléfono</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($personal as $p): ?>
                    <tr>
                        <td><strong>V-<?= $p['cedula'] ?></strong></td>
                        <td><?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?></td>
                        <td><?= htmlspecialchars($p['cargo']) ?></td>
                        <td><?= htmlspecialchars($p['telefono'] ?? 'S/T') ?></td>
                        <td><span style="color: green; font-weight: bold;"><?= $p['estado_laboral'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            window.onload = function() {
                html2canvas(document.getElementById('captura'), { scale: 2 }).then(canvas => {
                    let enlace = document.createElement('a');
                    enlace.download = 'Directorio_Personal_<?= date('Y-m-d') ?>.jpg';
                    enlace.href = canvas.toDataURL('image/jpeg', 0.95);
                    enlace.click();
                    setTimeout(() => window.close(), 1500);
                });
            }
        </script>
    </body></html>
    <?php exit;
}

// ==========================================
// 3. EXCEL (.XLS)
// ==========================================
elseif ($formato === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Nomina_Personal_" . date('Y-m-d') . ".xls");
    echo "\xEF\xBB\xBF";
    ?>
    <table border="1">
        <tr style="background-color: #1e3c72; color: white;">
            <th colspan="10" style="font-size: 16px; text-align: center;">DIRECTORIO GENERAL MPPE - U.E.N LUIS URDANETA</th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th>Cédula</th><th>Apellidos y Nombres</th><th>Tipo</th><th>Cargo / Especialidad</th><th>Cargo Nominal</th><th>Cód. Cargo</th><th>Teléfono</th><th>F. Ingreso MPPE</th><th>F. Ingreso Inst.</th><th>Estado</th>
        </tr>
        <?php foreach ($personal as $p): ?>
        <tr>
            <td><?= $p['cedula'] ?></td>
            <td><?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?></td>
            <td><?= $p['tipo'] ?></td>
            <td><?= htmlspecialchars($p['cargo']) ?></td>
            <td><?= htmlspecialchars($p['cargo_nominal'] ?? '-') ?></td>
            <td><?= htmlspecialchars($p['codigo_cargo'] ?? '-') ?></td>
            <td><?= htmlspecialchars($p['telefono'] ?? 'S/T') ?></td>
            <td><?= $p['fecha_ingreso_mppe'] ?? 'S/R' ?></td>
            <td><?= $p['fecha_ingreso'] ?></td>
            <td><?= $p['estado_laboral'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php exit;
}

// ==========================================
// 4. CSV PLANO
// ==========================================
elseif ($formato === 'csv') {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=Directorio_" . date('Y-m-d') . ".csv");
    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Cedula', 'Nombres', 'Apellidos', 'Tipo', 'Cargo', 'Telefono', 'Correo', 'Estado', 'Banco', 'Cuenta']);
    foreach ($personal as $p) {
        fputcsv($salida, [
            $p['cedula'], $p['nombres'], $p['apellidos'], $p['tipo'], $p['cargo'],
            $p['telefono'] ?? '', $p['correo_electronico'] ?? '', $p['estado_laboral'],
            $p['banco_nomina'] ?? '', $p['cuenta_bancaria'] ?? ''
        ]);
    }
    fclose($salida);
    exit;
}

// ==========================================
// 5. WORD (.DOC)
// ==========================================
elseif ($formato === 'word') {
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=Ficha_Personal_" . date('Y-m-d') . ".doc");
    echo "\xEF\xBB\xBF";
    ?>
    <html><body>
        <h2 style="color:#1e3c72; text-align:center;">U.E.N LUIS URDANETA</h2>
        <h4 style="text-align:center;">Expediente General de Trabajadores</h4><hr>
        <?php foreach ($personal as $p): ?>
            <p><strong>Cédula:</strong> V-<?= $p['cedula'] ?> | <strong>Nombre:</strong> <?= htmlspecialchars($p['apellidos'] . ' ' . $p['nombres']) ?></p>
            <p><strong>Función:</strong> <?= $p['cargo'] ?> (<?= $p['tipo'] ?>) | <strong>Cargo Nominal:</strong> <?= $p['cargo_nominal'] ?? 'N/A' ?></p>
            <p><strong>Teléfono:</strong> <?= $p['telefono'] ?? 'N/A' ?> | <strong>Estado:</strong> <?= $p['estado_laboral'] ?></p>
            <hr style="border-top:1px dashed #ccc;">
        <?php endforeach; ?>
    </body></html>
    <?php exit;
}

// ==========================================
// 6. PDF OFICIAL
// ==========================================
elseif ($formato === 'pdf') { ?>
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>Directorio Oficial MPPE</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; font-size: 12px; }
        .text-center { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #999; padding: 6px; text-align: left; }
        th { background-color: #1e3c72; color: white; }
        @media print { .no-print { display: none; } }
    </style></head>
    <body onload="window.print()">
        <div class="no-print" style="margin-bottom: 20px; text-align:center;">
            <button onclick="window.print()" style="padding:8px 16px; cursor:pointer;">🖨️ Imprimir / Guardar como PDF</button>
            <button onclick="window.close()" style="padding:8px 16px; cursor:pointer;">❌ Cerrar</button>
        </div>
        <div class="text-center">
            <h3>REPÚBLICA BOLIVARIANA DE VENEZUELA</h3>
            <h4>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</h4>
            <h2>U.E.N LUIS URDANETA</h2>
            <p>Reporte de Nómina Activa - Emisión: <?= date('d/m/Y h:i A') ?></p>
        </div>
        <table>
            <thead><tr><th>Cédula</th><th>Apellidos y Nombres</th><th>Tipo</th><th>Cargo Asignado</th><th>Cargo Nominal</th><th>Teléfono</th><th>Estado</th></tr></thead>
            <tbody>
                <?php foreach ($personal as $p): ?>
                <tr>
                    <td><?= $p['cedula'] ?></td>
                    <td><strong><?= htmlspecialchars($p['apellidos'] . ', ' . $p['nombres']) ?></strong></td>
                    <td><?= $p['tipo'] ?></td>
                    <td><?= htmlspecialchars($p['cargo']) ?></td>
                    <td><?= htmlspecialchars($p['cargo_nominal'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['telefono'] ?? '-') ?></td>
                    <td><?= $p['estado_laboral'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body></html>
    <?php exit;
}

// ==========================================
// JSON ESTRUCTURADO Y COMPLETO PARA RESPALDO
// ==========================================
elseif ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=Respaldo_Completo_RRHH_" . date('Y-m-d') . ".json");
    
    // Consulta profunda con todos los datos de cada trabajador
    $queryCompleta = "
        SELECT p.cedula, p.nombres, p.apellidos, p.fecha_nacimiento, p.fecha_ingreso, p.estado_laboral,
               pc.cargo_area_id, c.nombre as cargo_nombre, t.nombre as tipo_personal,
               (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
               pda.*
        FROM personal p
        JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
        JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
        JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id
        LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
    ";
    
    $params = [];
    if ($tipo_filtro !== 'todos') {
        $queryCompleta .= " WHERE t.id = ? ";
        $params[] = intval($tipo_filtro);
    }
    
    $stmtJson = $pdo->prepare($queryCompleta);
    $stmtJson->execute($params);
    $exportData = $stmtJson->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>