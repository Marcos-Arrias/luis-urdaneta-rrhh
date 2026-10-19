<?php
require_once '../PHP/connection.php';

// Asegurar columnas y tablas requeridas
$pdo->exec("CREATE TABLE IF NOT EXISTS personal_archivos_digitales (
  id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NOT NULL, codigo_archivo VARCHAR(100) NOT NULL UNIQUE,
  tipo_documento_clave CHAR(3) NOT NULL, numero_secuencia INT NOT NULL DEFAULT 1, nombre_original VARCHAR(150) NOT NULL,
  ruta_archivo VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NULL, fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
)");
$pdo->exec("ALTER TABLE cat_cargos_areas ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) DEFAULT 'Cátedra / Función institucional.'");

$tipo_id = isset($_GET['tipo']) ? intval($_GET['tipo']) : 1;

$titulos = [
    1 => ['titulo' => 'Personal Docente', 'icono' => 'bi-book', 'btn' => 'Nuevo Docente', 'etiqueta' => 'Materia Asignada'],
    2 => ['titulo' => 'Personal Administrativo', 'icono' => 'bi-pc-display', 'btn' => 'Nuevo Admin', 'etiqueta' => 'Cargo Principal'],
    3 => ['titulo' => 'Personal Obrero', 'icono' => 'bi-tools', 'btn' => 'Nuevo Obrero', 'etiqueta' => 'Área de Mantenimiento']
];
$ui = $titulos[$tipo_id] ?? $titulos[1];

function guardarArchivoEnClave($pdo, $personal_id, $cedula, $file, $tipo_clave) {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'])) return false;

    $dir = '../documentos/';
    if (!file_exists($dir)) { mkdir($dir, 0777, true); }

    $stmt = $pdo->prepare("SELECT MAX(numero_secuencia) FROM personal_archivos_digitales WHERE personal_id = ? AND tipo_documento_clave = ?");
    $stmt->execute([$personal_id, $tipo_clave]);
    $secuencia = intval($stmt->fetchColumn()) + 1;

    $cedula_limpia = preg_replace('/[^0-9]/', '', $cedula);
    $nombre_codigo = sprintf("V%s_%d_%s_%02d.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, $ext);
    $ruta_dest = $dir . $nombre_codigo;

    if (move_uploaded_file($file['tmp_name'], $ruta_dest)) {
        $stmtIns = $pdo->prepare("INSERT INTO personal_archivos_digitales (personal_id, codigo_archivo, tipo_documento_clave, numero_secuencia, nombre_original, ruta_archivo, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$personal_id, $nombre_codigo, $tipo_clave, $secuencia, $file['name'], $ruta_dest, $file['type']]);
        return $nombre_codigo;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['accion'] ?? '';
    $nombre_completo = trim($_POST['nombres'] . ' ' . $_POST['apellidos']);
    
    try {
        $pdo->beginTransaction();
        $fnac = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
        $fmppe = !empty($_POST['fecha_ingreso_mppe']) ? $_POST['fecha_ingreso_mppe'] : null;

        if ($a === 'crear') {
            $stmt = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_nacimiento, fecha_ingreso, estado_laboral) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['cedula'], $_POST['nombres'], $_POST['apellidos'], $fnac, $_POST['fecha_ingreso'], $_POST['estado_laboral']]);
            $registro_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion) VALUES (?, ?, ?)")
                ->execute([$registro_id, $_POST['cargo_area_id'], $_POST['fecha_ingreso']]);

            if(!empty($_POST['telefono'])) {
                $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$registro_id, $_POST['telefono']]);
            }

            $stmtP = $pdo->prepare("INSERT INTO personal_datos_adicionales (
                personal_id, estado_civil, cantidad_hijos, etnia, fecha_ingreso_mppe, dependencia, codigo_dependencia, 
                turno, nivel_academico, correo_electronico, banco_nomina, cuenta_bancaria, cargo_nominal, codigo_cargo, 
                horas_laborales, direccion_habitacion, municipio, parroquia, centro_votacion, patologias, medicamentos_permanentes, 
                tipo_sangre, alergias_medicas, condiciones_especiales, talla_camisa, talla_pantalon, talla_calzado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmtP->execute([
                $registro_id, $_POST['estado_civil'], intval($_POST['cantidad_hijos']), $_POST['etnia'], $fmppe, $_POST['dependencia'], $_POST['codigo_dependencia'],
                $_POST['turno'], $_POST['nivel_academico'], $_POST['correo_electronico'], $_POST['banco_nomina'], $_POST['cuenta_bancaria'], $_POST['cargo_nominal'], $_POST['codigo_cargo'],
                intval($_POST['horas_laborales']), $_POST['direccion_habitacion'], $_POST['municipio'], $_POST['parroquia'], $_POST['centro_votacion'], $_POST['patologias'], $_POST['medicamentos_permanentes'],
                $_POST['tipo_sangre'], $_POST['alergias_medicas'], $_POST['condiciones_especiales'], $_POST['talla_camisa'], $_POST['talla_pantalon'], $_POST['talla_calzado']
            ]);

            if (!empty($_FILES['archivo_expediente']['name'])) {
                guardarArchivoEnClave($pdo, $registro_id, $_POST['cedula'], $_FILES['archivo_expediente'], $_POST['tipo_archivo_clave']);
            }

            registrarHistorial($pdo, 'Personal', 'CREAR', "Registró al personal: $nombre_completo.", $registro_id);
            $pdo->commit(); header("Location: personal.php?tipo=$tipo_id&msg=creado"); exit;
        
        } elseif ($a === 'editar') {
            $id = $_POST['id'];
            $pdo->prepare("UPDATE personal SET cedula=?, nombres=?, apellidos=?, fecha_nacimiento=?, fecha_ingreso=?, estado_laboral=? WHERE id=?")
                ->execute([$_POST['cedula'], $_POST['nombres'], $_POST['apellidos'], $fnac, $_POST['fecha_ingreso'], $_POST['estado_laboral'], $id]);

            $pdo->prepare("UPDATE personal_cargos SET cargo_area_id=? WHERE personal_id=? AND estado_cargo='Vigente'")
                ->execute([$_POST['cargo_area_id'], $id]);

            $pdo->prepare("DELETE FROM personal_contactos WHERE personal_id=? AND tipo_contacto='Teléfono Personal'")->execute([$id]);
            if(!empty($_POST['telefono'])) {
                $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$id, $_POST['telefono']]);
            }

            $stmtUp = $pdo->prepare("INSERT INTO personal_datos_adicionales (
                personal_id, estado_civil, cantidad_hijos, etnia, fecha_ingreso_mppe, dependencia, codigo_dependencia, 
                turno, nivel_academico, correo_electronico, banco_nomina, cuenta_bancaria, cargo_nominal, codigo_cargo, 
                horas_laborales, direccion_habitacion, municipio, parroquia, centro_votacion, patologias, medicamentos_permanentes, 
                tipo_sangre, alergias_medicas, condiciones_especiales, talla_camisa, talla_pantalon, talla_calzado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                estado_civil=VALUES(estado_civil), cantidad_hijos=VALUES(cantidad_hijos), etnia=VALUES(etnia), fecha_ingreso_mppe=VALUES(fecha_ingreso_mppe),
                dependencia=VALUES(dependencia), codigo_dependencia=VALUES(codigo_dependencia), turno=VALUES(turno), nivel_academico=VALUES(nivel_academico),
                correo_electronico=VALUES(correo_electronico), banco_nomina=VALUES(banco_nomina), cuenta_bancaria=VALUES(cuenta_bancaria), cargo_nominal=VALUES(cargo_nominal),
                codigo_cargo=VALUES(codigo_cargo), horas_laborales=VALUES(horas_laborales), direccion_habitacion=VALUES(direccion_habitacion), municipio=VALUES(municipio),
                parroquia=VALUES(parroquia), centro_votacion=VALUES(centro_votacion), patologias=VALUES(patologias), medicamentos_permanentes=VALUES(medicamentos_permanentes),
                tipo_sangre=VALUES(tipo_sangre), alergias_medicas=VALUES(alergias_medicas), condiciones_especiales=VALUES(condiciones_especiales),
                talla_camisa=VALUES(talla_camisa), talla_pantalon=VALUES(talla_pantalon), talla_calzado=VALUES(talla_calzado)");

            $stmtUp->execute([
                $id, $_POST['estado_civil'], intval($_POST['cantidad_hijos']), $_POST['etnia'], $fmppe, $_POST['dependencia'], $_POST['codigo_dependencia'],
                $_POST['turno'], $_POST['nivel_academico'], $_POST['correo_electronico'], $_POST['banco_nomina'], $_POST['cuenta_bancaria'], $_POST['cargo_nominal'], $_POST['codigo_cargo'],
                intval($_POST['horas_laborales']), $_POST['direccion_habitacion'], $_POST['municipio'], $_POST['parroquia'], $_POST['centro_votacion'], $_POST['patologias'], $_POST['medicamentos_permanentes'],
                $_POST['tipo_sangre'], $_POST['alergias_medicas'], $_POST['condiciones_especiales'], $_POST['talla_camisa'], $_POST['talla_pantalon'], $_POST['talla_calzado']
            ]);

            if (!empty($_FILES['archivo_expediente']['name'])) {
                guardarArchivoEnClave($pdo, $id, $_POST['cedula'], $_FILES['archivo_expediente'], $_POST['tipo_archivo_clave']);
            }

            registrarHistorial($pdo, 'Personal', 'ACTUALIZAR', "Actualizó expediente de: $nombre_completo.", $id);
            $pdo->commit(); header("Location: personal.php?tipo=$tipo_id&msg=editado"); exit;
        
        } elseif ($a === 'eliminar') {
            $pdo->prepare("DELETE FROM personal WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Personal', 'ELIMINAR', "Eliminó a un trabajador.", $_POST['id']);
            $pdo->commit(); header("Location: personal.php?tipo=$tipo_id&msg=eliminado"); exit;
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) { header("Location: personal.php?tipo=$tipo_id&msg=duplicado"); exit; } 
        else { die("Error DB: " . $e->getMessage()); }
    }
}

// CONSULTA AVANZADA (Con extracción directa de Foto Carnet, Foto Cédula y Descripción de Cargo)
$datos = $pdo->prepare("
    SELECT p.*, 
           TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad,
           TIMESTAMPDIFF(YEAR, pda.fecha_ingreso_mppe, CURDATE()) as anios_servicio,
           pc.cargo_area_id, c.nombre as cargo_nombre, c.descripcion as cargo_descripcion,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as ruta_foto_carnet,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'CED' ORDER BY id DESC LIMIT 1) as ruta_foto_cedula,
           (SELECT GROUP_CONCAT(CONCAT(tipo_documento_clave, '::', codigo_archivo, '::', ruta_archivo) SEPARATOR '||') 
            FROM personal_archivos_digitales WHERE personal_id = p.id) as galeria_archivos,
           pda.*
    FROM personal p 
    INNER JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    INNER JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
    WHERE c.tipo_personal_id = ? 
    ORDER BY p.apellidos ASC
");
$datos->execute([$tipo_id]);
$personal = $datos->fetchAll();

$lista_cargos = $pdo->prepare("SELECT id, nombre FROM cat_cargos_areas WHERE tipo_personal_id = ? ORDER BY nombre ASC");
$lista_cargos->execute([$tipo_id]);
$lista_cargos = $lista_cargos->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<!-- ESTILOS PARA PREVIEWS FLOTANTES (ADAPTABLES A MODO OSCURO) -->
<style>
.popover-hover { position: relative; display: inline-block; }
.popover-content {
    display: none; position: absolute; z-index: 1060; left: 105%; top: -15px;
    width: 260px; background-color: var(--bs-body-bg); color: var(--bs-body-color);
    border: 1px solid var(--bs-border-color); border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.25); padding: 12px; pointer-events: none;
}
.popover-hover:hover .popover-content { display: block; animation: fadeIn 0.15s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.avatar-thumb { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid var(--bs-border-color); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi <?= $ui['icono'] ?> me-2 text-primary"></i> <?= $ui['titulo'] ?></h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarFormulario()">
        <i class="bi bi-plus-circle me-2"></i> <?= $ui['btn'] ?>
    </button>
</div>

<!-- ALERTAS -->
<?php if (isset($_GET['msg'])): ?>
    <?php 
    $alertas = [
        'creado' => ['class' => 'alert-success', 'text' => 'Expediente registrado con éxito.'],
        'editado' => ['class' => 'alert-info', 'text' => 'Datos y archivos actualizados.'],
        'eliminado' => ['class' => 'alert-warning', 'text' => 'Registro eliminado.'],
        'duplicado' => ['class' => 'alert-danger', 'text' => 'Cédula ya registrada en el sistema.']
    ];
    $msg = $_GET['msg'];
    if(isset($alertas[$msg])): ?>
        <div class="alert <?= $alertas[$msg]['class'] ?> alert-dismissible fade show shadow-sm">
            <i class="bi bi-info-circle-fill me-2"></i> <?= $alertas[$msg]['text'] ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive" style="overflow: visible;">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Cédula</th>
                        <th>Apellidos y Nombres</th>
                        <th><?= $ui['etiqueta'] ?></th>
                        <th>Teléfono / WhatsApp</th>
                        <th class="text-center">F. Ingreso</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($personal) > 0): ?>
                        <?php foreach ($personal as $d): ?>
                        <tr>
                            <!-- 1. CÉDULA CON HOVER PREVIEW -->
                            <td class="ps-3 fw-bold">
                                <div class="popover-hover">
                                    <span class="text-primary text-decoration-underline" style="cursor: help;">
                                        <?= htmlspecialchars($d['cedula']) ?>
                                    </span>
                                    <div class="popover-content">
                                        <div class="fw-bold text-center border-bottom pb-1 mb-2 small text-primary"><i class="bi bi-card-image me-1"></i> Cédula Digitalizada</div>
                                        <?php if (!empty($d['ruta_foto_cedula'])): ?>
                                            <img src="<?= $d['ruta_foto_cedula'] ?>" class="img-fluid rounded border shadow-sm" alt="Cédula">
                                        <?php else: ?>
                                            <div class="text-center text-muted small py-3"><i class="bi bi-file-earmark-x fs-2 d-block text-secondary"></i>Sin documento escaneado.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. NOMBRE CON AVATAR Y HOVER PREVIEW -->
                            <td>
                                <div class="popover-hover d-flex align-items-center">
                                    <?php if (!empty($d['ruta_foto_carnet'])): ?>
                                        <img src="<?= $d['ruta_foto_carnet'] ?>" class="avatar-thumb me-2" alt="Foto">
                                    <?php else: ?>
                                        <div class="avatar-thumb me-2 bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="font-size: 0.75rem;">
                                            <?= strtoupper(substr($d['nombres'], 0, 1) . substr($d['apellidos'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>

                                    <span class="fw-medium"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></span>
                                    
                                    <div class="popover-content">
                                        <div class="text-center">
                                            <?php if (!empty($d['ruta_foto_carnet'])): ?>
                                                <img src="<?= $d['ruta_foto_carnet'] ?>" class="rounded-circle mb-2 border shadow-sm" style="width: 80px; height: 80px; object-fit: cover;" alt="Foto">
                                            <?php else: ?>
                                                <i class="bi bi-person-circle fs-1 text-secondary d-block mb-1"></i>
                                            <?php endif; ?>
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($d['nombres'] . ' ' . $d['apellidos']) ?></h6>
                                            <small class="text-muted d-block mb-2">V-<?= $d['cedula'] ?> | <?= $d['edad'] ? $d['edad'] . ' Años' : 'Edad S/R' ?></small>
                                            <span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($d['cargo_nombre']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. MATERIA CON HOVER PREVIEW DE DESCRIPCIÓN -->
                            <td>
                                <div class="popover-hover">
                                    <span class="badge bg-secondary" style="cursor: help;">
                                        <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($d['cargo_nombre']) ?>
                                    </span>
                                    <div class="popover-content">
                                        <strong class="d-block small text-primary mb-1"><i class="bi bi-mortarboard me-1"></i> Información de Cátedra:</strong>
                                        <p class="small text-muted mb-0"><?= htmlspecialchars($d['cargo_descripcion'] ?? 'Función asignada oficialmente según nómina del plantel.') ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- 4. TELÉFONO CON DETECCIÓN Y HOVER DE WHATSAPP -->
                            <td>
                                <div class="popover-hover">
                                    <?= formatearTelefono($d['telefono']) ?>
                                    <div class="popover-content" style="width: 220px;">
                                        <div class="small fw-bold text-success mb-1"><i class="bi bi-whatsapp me-1"></i> Enlace WhatsApp Directo</div>
                                        <?php if (!empty($d['telefono'])): ?>
                                            <?php $cleanTel = preg_replace('/[^0-9]/', '', $d['telefono']); ?>
                                            <p class="small text-muted mb-2">Canal de comunicación instantáneo listo.</p>
                                            <a href="https://wa.me/<?= $cleanTel ?>" target="_blank" class="btn btn-sm btn-success w-100 py-1" style="font-size: 0.75rem;">
                                                <i class="bi bi-chat-dots me-1"></i> Abrir Chat Directo
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">No posee número móvil registrado.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center"><?= date('d/m/Y', strtotime($d['fecha_ingreso'])) ?></td>
                            <td class="text-center"><span class="badge bg-<?= $d['estado_laboral'] == 'Activo' ? 'success' : 'danger' ?>"><?= $d['estado_laboral'] ?></span></td>
                            
                            <td class="text-center text-nowrap">
                                <button class="btn btn-sm btn-info text-white me-1" 
                                        onclick="verDetallesExtra(this)" 
                                        data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-bs-toggle="offcanvas" data-bs-target="#panelDatosExtra" 
                                        title="Expediente y Archivos">
                                    <i class="bi bi-folder-symlink-fill" style="pointer-events: none;"></i>
                                </button>
                                
                                <button class="btn btn-sm btn-warning me-1" 
                                        onclick="editarRegistro(this)" 
                                        data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-bs-toggle="modal" data-bs-target="#modalRegistro" title="Editar">
                                    <i class="bi bi-pencil" style="pointer-events: none;"></i>
                                </button>
                                
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro completamente?');">
                                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay personal registrado en esta área.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- OFFCANVAS (EXPEDIENTE MPPE + ARCHIVOS EN CLAVE) -->
<!-- ========================================== -->
<div class="offcanvas offcanvas-end shadow" tabindex="-1" id="panelDatosExtra" style="width: 480px;">
  <div class="offcanvas-header bg-primary text-white">
    <h5 class="offcanvas-title mb-0"><i class="bi bi-folder2-open me-2"></i>Expediente Digitalizado</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body bg-body-tertiary p-4">
      <div class="text-center mb-4 pb-2 border-bottom">
          <h4 id="oc_nombre" class="fw-bold mb-1">Nombre</h4>
          <span id="oc_cedula" class="badge bg-primary fs-6 me-1">Cédula</span>
          <span id="oc_edad" class="badge bg-secondary fs-6">Edad: -</span>
      </div>

      <h6 class="text-warning fw-bold mb-2"><i class="bi bi-images me-2"></i>Archivos Digitales Registrados</h6>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <div id="oc_galeria" class="d-flex flex-wrap gap-2">
              <span class="text-muted small">Sin archivos adjuntos.</span>
          </div>
      </div>

      <h6 class="text-primary fw-bold mb-2"><i class="bi bi-briefcase-fill me-2"></i>Datos Laborales MPPE</h6>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <p class="mb-1 small"><strong>Cargo Nominal:</strong> <span id="oc_cargo_nominal">-</span> (Cód: <span id="oc_codigo_cargo">-</span>)</p>
          <p class="mb-1 small"><strong>Dependencia:</strong> <span id="oc_dependencia">-</span> (Cód: <span id="oc_cod_dependencia">-</span>)</p>
          <p class="mb-1 small"><strong>Turno:</strong> <span id="oc_turno" class="badge bg-info text-dark">-</span> | <strong>Horas:</strong> <span id="oc_horas">0</span>h</p>
          <p class="mb-1 small"><strong>Ingreso MPPE:</strong> <span id="oc_fecha_mppe">-</span></p>
          <p class="mb-0 small"><strong>Años de Servicio:</strong> <span id="oc_servicio" class="badge bg-success">0 años</span></p>
      </div>

      <h6 class="text-success fw-bold mb-2"><i class="bi bi-person-badge-fill me-2"></i>Datos Personales y Residencia</h6>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <p class="mb-1 small"><strong>Estado Civil:</strong> <span id="oc_civil">-</span> | <strong>Hijos:</strong> <span id="oc_hijos">0</span></p>
          <p class="mb-1 small"><strong>Etnia:</strong> <span id="oc_etnia">-</span> | <strong>Nivel Acad.:</strong> <span id="oc_academico">-</span></p>
          <p class="mb-1 small"><strong>Correo:</strong> <span id="oc_correo">-</span></p>
          <p class="mb-1 small"><strong>Dirección:</strong> <span id="oc_direccion">-</span></p>
          <p class="mb-1 small"><strong>Mun/Parr:</strong> <span id="oc_mun_parr">-</span></p>
          <p class="mb-0 small"><strong>Centro Votación:</strong> <span id="oc_votacion">-</span></p>
      </div>

      <h6 class="text-danger fw-bold mb-2"><i class="bi bi-heart-pulse-fill me-2"></i>Salud, Dotación y Nómina</h6>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <p class="mb-1 small"><strong>Tipo Sangre:</strong> <span id="oc_sangre" class="badge bg-danger">-</span></p>
          <p class="mb-1 small"><strong>Patologías:</strong> <span id="oc_patologias">-</span></p>
          <p class="mb-1 small"><strong>Medicamentos:</strong> <span id="oc_medicamentos">-</span></p>
          <p class="mb-1 small"><strong>Tallas:</strong> Camisa: <span id="oc_camisa">-</span> | Pantalón: <span id="oc_pantalon">-</span> | Calzado: <span id="oc_calzado">-</span></p>
          <p class="mb-0 small"><strong>Banco:</strong> <span id="oc_banco">-</span> | <strong>Cuenta:</strong> <span id="oc_cuenta">-</span></p>
      </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL DE REGISTRO CON SUBIDA DE ARCHIVO EN CLAVE -->
<!-- ========================================== -->
<div class="modal fade" id="modalRegistro" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard me-2"></i>Gestión de Expediente MPPE</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body bg-body-tertiary">
                    <input type="hidden" name="accion" id="accion">
                    <input type="hidden" name="id" id="registro_id">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="text-primary fw-bold mb-3"><i class="bi bi-1-circle-fill me-1"></i> Identificación Básica</h6>
                            <div class="row g-2">
                                <div class="col-md-3"><label class="small text-muted">Cédula</label><input type="text" name="cedula" id="cedula" class="form-control" required></div>
                                <div class="col-md-3"><label class="small text-muted">Nombres</label><input type="text" name="nombres" id="nombres" class="form-control" required></div>
                                <div class="col-md-3"><label class="small text-muted">Apellidos</label><input type="text" name="apellidos" id="apellidos" class="form-control" required></div>
                                <div class="col-md-3"><label class="small text-muted">Teléfono Móvil</label><input type="text" name="telefono" id="telefono" class="form-control"></div>
                                
                                <div class="col-md-4">
                                    <label class="small text-muted"><?= $ui['etiqueta'] ?></label>
                                    <div class="input-group">
                                        <select name="cargo_area_id" id="cargo_area_id" class="form-select" required>
                                            <option value="">Seleccione...</option>
                                            <?php foreach ($lista_cargos as $cargo): ?>
                                                <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-success" onclick="agregarCargoRapido(<?= $tipo_id ?>, 'cargo_area_id')"><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4"><label class="small text-muted">F. Ingreso Institución</label><input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control" required></div>
                                <div class="col-md-4">
                                    <label class="small text-muted">Estado Laboral</label>
                                    <select name="estado_laboral" id="estado_laboral" class="form-select">
                                        <option value="Activo">Activo</option><option value="Inactivo">Inactivo</option>
                                        <option value="Permiso">Permiso</option><option value="Reposo">Reposo</option><option value="Jubilado">Jubilado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-warning">
                        <div class="card-body">
                            <h6 class="text-warning fw-bold mb-2"><i class="bi bi-camera-fill me-1"></i> Adjuntar Archivo Digitalizado (Codificación Automática)</h6>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4">
                                    <label class="small text-muted">Tipo de Archivo:</label>
                                    <select name="tipo_archivo_clave" class="form-select form-select-sm">
                                        <option value="FOT">FOT - Foto Tipo Carnet</option>
                                        <option value="CED">CED - Cédula Escaneada</option>
                                        <option value="TIT">TIT - Título / Fondo Negro</option>
                                        <option value="MED">MED - Certificado Médico</option>
                                        <option value="SOP">SOP - Constancia / Reposo</option>
                                        <option value="OTR">OTR - Otro Documento</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="small text-muted">Seleccionar Imagen o Documento:</label>
                                    <input type="file" name="archivo_expediente" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-outline-primary w-100 fw-bold border-2 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#datosMiscelaneos">
                        <i class="bi bi-caret-down-square-fill me-2"></i>Ficha Completa de Datos MPPE
                    </button>

                    <div class="collapse" id="datosMiscelaneos">
                        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
                            <div class="card-body">
                                <h6 class="text-primary fw-bold mb-2"><i class="bi bi-building me-1"></i> Datos Ministeriales (MPPE)</h6>
                                <div class="row g-2">
                                    <div class="col-md-3"><label class="small text-muted">F. Ingreso MPPE</label><input type="date" name="fecha_ingreso_mppe" id="fecha_ingreso_mppe" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><label class="small text-muted">Cargo Nominal</label><input type="text" name="cargo_nominal" id="cargo_nominal" class="form-control form-control-sm" placeholder="Ej: Docente IV"></div>
                                    <div class="col-md-2"><label class="small text-muted">Cód. Cargo</label><input type="text" name="codigo_cargo" id="codigo_cargo" class="form-control form-control-sm"></div>
                                    <div class="col-md-2">
                                        <label class="small text-muted">Turno</label>
                                        <select name="turno" id="turno" class="form-select form-select-sm">
                                            <option value="Mañana">Mañana</option><option value="Tarde">Tarde</option><option value="Completo">Completo</option><option value="Integral">Integral</option><option value="Nocturno">Nocturno</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2"><label class="small text-muted">Horas Semanales</label><input type="number" name="horas_laborales" id="horas_laborales" class="form-control form-control-sm" value="0"></div>
                                    <div class="col-md-6"><label class="small text-muted">Dependencia</label><input type="text" name="dependencia" id="dependencia" class="form-control form-control-sm"></div>
                                    <div class="col-md-6"><label class="small text-muted">Cód. Dependencia</label><input type="text" name="codigo_dependencia" id="codigo_dependencia" class="form-control form-control-sm"></div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-success">
                            <div class="card-body">
                                <h6 class="text-success fw-bold mb-2"><i class="bi bi-geo-alt me-1"></i> Datos Personales y Residencia</h6>
                                <div class="row g-2">
                                    <div class="col-md-3"><label class="small text-muted">F. Nacimiento</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control form-control-sm"></div>
                                    <div class="col-md-2"><label class="small text-muted">Etnia</label><input type="text" name="etnia" id="etnia" class="form-control form-control-sm"></div>
                                    <div class="col-md-2">
                                        <label class="small text-muted">Estado Civil</label>
                                        <select name="estado_civil" id="estado_civil" class="form-select form-select-sm">
                                            <option value="Soltero/a">Soltero/a</option><option value="Casado/a">Casado/a</option><option value="Divorciado/a">Divorciado/a</option><option value="Viudo/a">Viudo/a</option><option value="Concubinato">Concubinato</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2"><label class="small text-muted">N° Hijos</label><input type="number" name="cantidad_hijos" id="cantidad_hijos" class="form-control form-control-sm" value="0"></div>
                                    <div class="col-md-3"><label class="small text-muted">Nivel Académico</label><input type="text" name="nivel_academico" id="nivel_academico" class="form-control form-control-sm"></div>

                                    <div class="col-md-4"><label class="small text-muted">Correo Electrónico</label><input type="email" name="correo_electronico" id="correo_electronico" class="form-control form-control-sm"></div>
                                    <div class="col-md-4"><label class="small text-muted">Municipio</label><input type="text" name="municipio" id="municipio" class="form-control form-control-sm"></div>
                                    <div class="col-md-4"><label class="small text-muted">Parroquia</label><input type="text" name="parroquia" id="parroquia" class="form-control form-control-sm"></div>
                                    <div class="col-md-6"><label class="small text-muted">Dirección de Habitación</label><input type="text" name="direccion_habitacion" id="direccion_habitacion" class="form-control form-control-sm"></div>
                                    <div class="col-md-6"><label class="small text-muted">Centro de Votación</label><input type="text" name="centro_votacion" id="centro_votacion" class="form-control form-control-sm"></div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-danger">
                            <div class="card-body">
                                <h6 class="text-danger fw-bold mb-2"><i class="bi bi-heart me-1"></i> Salud, Dotación y Nómina</h6>
                                <div class="row g-2">
                                    <div class="col-md-2"><label class="small text-muted">Tipo Sangre</label><input type="text" name="tipo_sangre" id="tipo_sangre" class="form-control form-control-sm" placeholder="O+"></div>
                                    <div class="col-md-5"><label class="small text-muted">Patologías</label><input type="text" name="patologias" id="patologias" class="form-control form-control-sm"></div>
                                    <div class="col-md-5"><label class="small text-muted">Medicamentos</label><input type="text" name="medicamentos_permanentes" id="medicamentos_permanentes" class="form-control form-control-sm"></div>
                                    <div class="col-md-6"><label class="small text-muted">Alergias</label><input type="text" name="alergias_medicas" id="alergias_medicas" class="form-control form-control-sm"></div>
                                    <div class="col-md-6"><label class="small text-muted">Condiciones Especiales</label><input type="text" name="condiciones_especiales" id="condiciones_especiales" class="form-control form-control-sm"></div>

                                    <div class="col-md-2"><label class="small text-muted">Talla Camisa</label><input type="text" name="talla_camisa" id="talla_camisa" class="form-control form-control-sm"></div>
                                    <div class="col-md-2"><label class="small text-muted">Talla Pantalón</label><input type="text" name="talla_pantalon" id="talla_pantalon" class="form-control form-control-sm"></div>
                                    <div class="col-md-2"><label class="small text-muted">N° Calzado</label><input type="text" name="talla_calzado" id="talla_calzado" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><label class="small text-muted">Banco Nómina</label><input type="text" name="banco_nomina" id="banco_nomina" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><label class="small text-muted">N° Cuenta Bancaria</label><input type="text" name="cuenta_bancaria" id="cuenta_bancaria" class="form-control form-control-sm"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Expediente y Archivo</button></div>
            </form>
        </div>
    </div>
</div>

<?php include '../Includes/footer.php'; ?>