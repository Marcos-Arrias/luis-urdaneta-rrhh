<?php
require_once '../PHP/connection.php';

// ==========================================
// 1. PROCESAMIENTO DEL FORMULARIO (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $tipo_actual = $_GET['tipo'] ?? 1;

    try {
        $pdo->beginTransaction();

        // -------------------------------------------
        // A. ACCIONES MASIVAS (MULTIPLES SUJETOS)
        // -------------------------------------------
        if ($accion === 'bulk_delete' && !empty($_POST['bulk_ids'])) {
            $ids = explode(',', $_POST['bulk_ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM personal WHERE id IN ($placeholders)")->execute($ids);
            registrarHistorial($pdo, 'Personal', 'ELIMINAR', "Eliminación masiva de " . count($ids) . " registros.");
        } 
        elseif ($accion === 'bulk_move' && !empty($_POST['bulk_ids']) && !empty($_POST['nuevo_cargo_id'])) {
            $ids = explode(',', $_POST['bulk_ids']);
            $nuevo_cargo = $_POST['nuevo_cargo_id'];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Se actualiza el cargo vigente de todos los seleccionados
            $params = array_merge([$nuevo_cargo], $ids);
            $pdo->prepare("UPDATE personal_cargos SET cargo_area_id = ? WHERE personal_id IN ($placeholders) AND estado_cargo='Vigente'")->execute($params);
            registrarHistorial($pdo, 'Personal', 'ACTUALIZAR', "Cambio de cargo masivo a " . count($ids) . " trabajadores.");
        }
        // -------------------------------------------
        // B. CREAR O EDITAR INDIVIDUAL
        // -------------------------------------------
        elseif ($accion === 'crear' || $accion === 'editar') {
            $cedula = trim($_POST['cedula']);
            $nombres = trim($_POST['nombres']);
            $apellidos = trim($_POST['apellidos']);
            $fecha_ingreso = $_POST['fecha_ingreso'];
            $estado_laboral = $_POST['estado_laboral'] ?? 'Activo';
            $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
            $cargo_area_id = $_POST['cargo_area_id'];
            $telefono = trim($_POST['telefono'] ?? '');

            if ($accion === 'crear') {
                $stmt = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_nacimiento, fecha_ingreso, estado_laboral) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cedula, $nombres, $apellidos, $fecha_nacimiento, $fecha_ingreso, $estado_laboral]);
                $personal_id = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion) VALUES (?, ?, ?)")->execute([$personal_id, $cargo_area_id, $fecha_ingreso]);

                if (!empty($telefono)) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$personal_id, $telefono]);
                registrarHistorial($pdo, 'Personal', 'CREAR', "Registró al trabajador C.I: $cedula");

            } elseif ($accion === 'editar') {
                $personal_id = $id;
                $pdo->prepare("UPDATE personal SET cedula=?, nombres=?, apellidos=?, fecha_nacimiento=?, fecha_ingreso=?, estado_laboral=? WHERE id=?")->execute([$cedula, $nombres, $apellidos, $fecha_nacimiento, $fecha_ingreso, $estado_laboral, $personal_id]);
                $pdo->prepare("UPDATE personal_cargos SET cargo_area_id=? WHERE personal_id=? AND estado_cargo='Vigente'")->execute([$cargo_area_id, $personal_id]);
                
                $pdo->prepare("DELETE FROM personal_contactos WHERE personal_id=? AND tipo_contacto='Teléfono Personal'")->execute([$personal_id]);
                if (!empty($telefono)) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$personal_id, $telefono]);
                registrarHistorial($pdo, 'Personal', 'ACTUALIZAR', "Actualizó al trabajador C.I: $cedula");
            }

            // Guardar Datos Extra
            $stmtExtra = $pdo->prepare("
                INSERT INTO personal_datos_adicionales (
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
                    talla_camisa=VALUES(talla_camisa), talla_pantalon=VALUES(talla_pantalon), talla_calzado=VALUES(talla_calzado)
            ");
            $stmtExtra->execute([$personal_id, $_POST['estado_civil']??'Soltero/a', intval($_POST['cantidad_hijos']??0), $_POST['etnia']??null, !empty($_POST['fecha_ingreso_mppe'])?$_POST['fecha_ingreso_mppe']:null, $_POST['dependencia']??null, $_POST['codigo_dependencia']??null, $_POST['turno']??'Mañana', $_POST['nivel_academico']??null, $_POST['correo_electronico']??null, $_POST['banco_nomina']??null, $_POST['cuenta_bancaria']??null, $_POST['cargo_nominal']??null, $_POST['codigo_cargo']??null, intval($_POST['horas_laborales']??0), $_POST['direccion_habitacion']??null, $_POST['municipio']??null, $_POST['parroquia']??null, $_POST['centro_votacion']??null, $_POST['patologias']??null, $_POST['medicamentos_permanentes']??null, $_POST['tipo_sangre']??null, $_POST['alergias_medicas']??null, $_POST['condiciones_especiales']??null, $_POST['talla_camisa']??null, $_POST['talla_pantalon']??null, $_POST['talla_calzado']??null]);

            // Archivo Digitalizado
            if (isset($_FILES['archivo_expediente']) && $_FILES['archivo_expediente']['error'] === UPLOAD_ERR_OK) {
                $tipo_clave = $_POST['tipo_archivo_clave'] ?? 'OTR';
                $directorio = '../documentos/';
                if (!file_exists($directorio)) mkdir($directorio, 0777, true);
                $ext = strtolower(pathinfo($_FILES['archivo_expediente']['name'], PATHINFO_EXTENSION));
                $stmtS = $pdo->prepare("SELECT MAX(numero_secuencia) FROM personal_archivos_digitales WHERE personal_id = ? AND tipo_documento_clave = ?");
                $stmtS->execute([$personal_id, $tipo_clave]);
                $secuencia = intval($stmtS->fetchColumn()) + 1;
                $cedula_limpia = preg_replace('/[^0-9]/', '', $cedula);
                $nombre_codigo = sprintf("V%s_%d_%s_%02d.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, $ext);
                $rutaFinal = $directorio . $nombre_codigo;

                if (move_uploaded_file($_FILES['archivo_expediente']['tmp_name'], $rutaFinal)) {
                    $pdo->prepare("INSERT INTO personal_archivos_digitales (personal_id, codigo_archivo, tipo_documento_clave, numero_secuencia, nombre_original, ruta_archivo, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$personal_id, $nombre_codigo, $tipo_clave, $secuencia, $_FILES['archivo_expediente']['name'], $rutaFinal, $_FILES['archivo_expediente']['type']]);
                }
            }
        } 
        elseif ($accion === 'eliminar') {
            $pdo->prepare("DELETE FROM personal WHERE id=?")->execute([$id]);
            registrarHistorial($pdo, 'Personal', 'ELIMINAR', "Eliminó registro de personal individual.");
        }

        $pdo->commit();
        header("Location: personal.php?tipo=" . $tipo_actual); 
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) die("<script>alert('Error: La cédula ya existe o conflicto de datos.'); window.history.back();</script>");
        else die("Error DB: " . $e->getMessage());
    }
}

// ==========================================
// 2. UI CONFIG & QUERIES
// ==========================================
$tipo_id = isset($_GET['tipo']) ? intval($_GET['tipo']) : 1;
$filtro_cargo = isset($_GET['cargo']) ? intval($_GET['cargo']) : null;

$titulos = [
    1 => ['titulo' => 'Personal Docente', 'icono' => 'bi-book', 'btn' => 'Nuevo Docente', 'etiqueta' => 'Materia Asignada'],
    2 => ['titulo' => 'Personal Administrativo', 'icono' => 'bi-pc-display', 'btn' => 'Nuevo Admin', 'etiqueta' => 'Cargo Principal'],
    3 => ['titulo' => 'Personal Obrero', 'icono' => 'bi-tools', 'btn' => 'Nuevo Obrero', 'etiqueta' => 'Área de Mantenimiento']
];
$ui = $titulos[$tipo_id] ?? $titulos[1];

$sql = "
    SELECT p.*, 
           TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad,
           TIMESTAMPDIFF(YEAR, pda.fecha_ingreso_mppe, CURDATE()) as anios_servicio,
           pc.cargo_area_id, c.nombre as cargo_nombre, c.descripcion as cargo_descripcion,
           (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'FOT' ORDER BY id DESC LIMIT 1) as ruta_foto_carnet,
           (SELECT ruta_archivo FROM personal_archivos_digitales WHERE personal_id = p.id AND tipo_documento_clave = 'CED' ORDER BY id DESC LIMIT 1) as ruta_foto_cedula,
           (SELECT GROUP_CONCAT(CONCAT(tipo_documento_clave, '::', codigo_archivo, '::', ruta_archivo) SEPARATOR '||') 
            FROM personal_archivos_digitales WHERE personal_id = p.id) as galeria_archivos,
           pda.*,
           p.id -- ¡LA SOLUCIÓN! Forzamos a que el ID real del personal sea el último en leerse
    FROM personal p 
    INNER JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
    INNER JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id 
    LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
    WHERE c.tipo_personal_id = :tipo 
";
if ($filtro_cargo) { $sql .= " AND c.id = :cargo "; }
$sql .= " ORDER BY p.apellidos ASC";

$datos = $pdo->prepare($sql);
$datos->bindParam(':tipo', $tipo_id, PDO::PARAM_INT);
if ($filtro_cargo) { $datos->bindParam(':cargo', $filtro_cargo, PDO::PARAM_INT); }
$datos->execute();
$personal = $datos->fetchAll();

$lista_cargos = $pdo->prepare("SELECT id, nombre FROM cat_cargos_areas WHERE tipo_personal_id = ? ORDER BY nombre ASC");
$lista_cargos->execute([$tipo_id]);
$lista_cargos = $lista_cargos->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<style>
/* CSS del Popover, Hover y Checkboxes */
.popover-content { display: none; position: absolute; z-index: 9999 !important; left: 70px; top: -40px; width: 220px; background-color: var(--bs-body-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color); border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.5); padding: 15px; pointer-events: none; }
[data-bs-theme="dark"] .popover-content { background-color: #212529 !important; border-color: #495057 !important; color: #ffffff !important; }
[data-bs-theme="dark"] .popover-content span, [data-bs-theme="dark"] .popover-content strong { color: #ffffff !important; }
.popover-hover:hover .popover-content { display: block; animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateX(-15px); } to { opacity: 1; transform: translateX(0); } }
.avatar-thumb { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--bs-primary); transition: transform 0.2s; }
.popover-hover:hover .avatar-thumb { transform: scale(1.1); }
.badge-interactive { transition: all 0.2s ease; cursor: pointer; display: inline-flex; align-items: center; }
.badge-interactive:hover { background-color: var(--bs-primary) !important; color: white !important; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }

/* ESTILOS PARA ORDENAR Y ARRASTRAR COLUMNAS */
th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 20px !important; }
th.sortable:hover { background-color: rgba(0,0,0,0.05); }
th.sortable::after { content: '↕'; position: absolute; right: 8px; opacity: 0.3; }
th.sortable.asc::after { content: '↑'; opacity: 1; color: var(--bs-primary); }
th.sortable.desc::after { content: '↓'; opacity: 1; color: var(--bs-primary); }
th[draggable="true"] { cursor: grab; }
th[draggable="true"]:active { cursor: grabbing; }

/* TR COLOR SELECCIONADO */
tr.selected { background-color: rgba(13, 110, 253, 0.1) !important; }

/* BARRA FLOTANTE (BULK ACTIONS) */
#bulk-toolbar {
    position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%);
    background-color: var(--bs-dark); color: white; border-radius: 50px;
    padding: 12px 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 1050; display: flex; align-items: center; gap: 15px;
    transition: bottom 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
#bulk-toolbar.show { bottom: 30px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi <?= $ui['icono'] ?> me-2 text-primary"></i> <?= $ui['titulo'] ?></h2>
    <button class="btn btn-primary shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-2"></i> <?= $ui['btn'] ?>
    </button>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="table-responsive" style="overflow: visible;">
        <!-- TABLA CON COLUMNAS ARRASTRABLES (DRAGGABLE) Y ORDENABLES (SORTABLE) -->
        <table class="table table-hover mb-0 align-middle" id="tablaPersonal">
            <thead class="table-light text-secondary">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input class="form-check-input border-secondary" type="checkbox" id="checkAll" onclick="toggleAll(this)">
                    </th>
                    <th class="ps-4 sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">Cédula</th>
                    <th class="sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">Apellidos y Nombres</th>
                    <th class="sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)"><?= $ui['etiqueta'] ?></th>
                    <th class="sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">Contacto</th>
                    <th class="text-center sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">F. Ingreso</th>
                    <th class="text-center sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($personal) > 0): ?>
                    <?php foreach ($personal as $d): ?>
                    <tr class="fila-personal">
                        <td class="text-center">
                            <input class="form-check-input border-secondary row-check" type="checkbox" value="<?= $d['id'] ?>" onclick="checkRow(this)">
                        </td>
                        
                        <td class="ps-4">
                            <?php if (!empty($d['ruta_foto_cedula'])): ?>
                                <div class="popover-hover">
                                    <span class="fw-bold text-primary" style="cursor: pointer;"><i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($d['cedula'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="popover-content"><img src="<?= $d['ruta_foto_cedula'] ?>" class="img-fluid rounded border shadow-sm"></div>
                                </div>
                            <?php else: ?>
                                <span class="fw-bold text-secondary"><?= htmlspecialchars($d['cedula'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="popover-hover d-flex align-items-center">
                                <?php if (!empty($d['ruta_foto_carnet'])): ?>
                                    <img src="<?= $d['ruta_foto_carnet'] ?>" class="avatar-thumb me-3 shadow-sm">
                                <?php else: ?>
                                    <?php 
                                    // COLOR DINÁMICO DEL AVATAR SEGÚN EL TIPO DE PERSONAL
                                    $avBg = 'bg-primary text-white';
                                    if ($tipo_id == 2) $avBg = 'bg-success text-white';
                                    if ($tipo_id == 3) $avBg = 'bg-warning text-dark';
                                    ?>
                                    <div class="avatar-thumb me-3 <?= $avBg ?> d-flex align-items-center justify-content-center fw-bold shadow-sm">
                                        <?= mb_strtoupper(mb_substr($d['nombres'], 0, 1, 'UTF-8') . mb_substr($d['apellidos'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <span class="fw-bold name-val"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="popover-content text-center">
                                    <?php if (!empty($d['ruta_foto_carnet'])): ?><img src="<?= $d['ruta_foto_carnet'] ?>" class="rounded-circle mb-2 shadow" style="width:90px; height:90px; object-fit:cover;"><?php endif; ?>
                                    <h6 class="fw-bold mb-0 text-white"><?= htmlspecialchars($d['nombres'], ENT_QUOTES, 'UTF-8') ?></h6>
                                    <span class="badge bg-primary mt-2"><?= $d['edad'] ? $d['edad'].' Años' : 'Edad S/R' ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="popover-hover">
                                <span class="badge bg-body-secondary text-body border px-3 py-2 rounded-pill">
                                    <?= htmlspecialchars($d['cargo_nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </td>

                        <td><?= !empty($d['telefono']) ? formatearTelefono($d['telefono']) : '<span class="text-muted small">S/R</span>' ?></td>
                        <td class="text-center text-muted small"><?= date('d/m/y', strtotime($d['fecha_ingreso'])) ?></td>
                        <td class="text-center">
                            <?php 
                            // REPARACIÓN DE COLORES PARA QUE SEAN LEGIBLES
                            $est = $d['estado_laboral'];
                            $bClass = 'bg-success text-white'; 
                            if($est == 'Reposo') $bClass = 'bg-warning text-dark fw-bold'; 
                            if($est == 'Permiso') $bClass = 'bg-info text-dark fw-bold'; 
                            if($est == 'Inactivo') $bClass = 'bg-danger text-white'; 
                            if($est == 'Jubilado') $bClass = 'bg-secondary text-white'; 
                            ?>
                            <span class="badge <?= $bClass ?> px-3 py-1 rounded-pill shadow-sm"><?= $est ?></span>
                        </td>
                        
                        <td class="text-center text-nowrap">
                            <button class="btn btn-sm btn-light text-primary shadow-sm rounded-circle me-1" onclick="verDetallesExtra(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="offcanvas" data-bs-target="#panelDatosExtra" title="Expediente"><i class="bi bi-folder2-open" style="pointer-events:none;"></i></button>
                            <button class="btn btn-sm btn-light text-warning shadow-sm rounded-circle me-1" onclick="editarRegistro(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-pencil" style="pointer-events:none;"></i></button>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-light text-danger shadow-sm rounded-circle"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No hay personal registrado en esta área.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- BARRA FLOTANTE INFERIOR (ACCIONES MASIVAS) -->
<div id="bulk-toolbar">
    <span id="bulk-count" class="fw-bold border-end border-secondary pe-3 me-2">0 seleccionados</span>
    
    <button class="btn btn-sm btn-info fw-bold text-white rounded-pill" onclick="abrirModalBulkMove()">
        <i class="bi bi-arrow-left-right me-1"></i> Cambiar Cargo
    </button>
    <button class="btn btn-sm btn-danger fw-bold rounded-pill" onclick="ejecutarBulkDelete()">
        <i class="bi bi-trash me-1"></i> Eliminar
    </button>
    <button class="btn btn-sm btn-outline-light rounded-circle ms-2" onclick="limpiarSeleccion()" title="Desmarcar Todos">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

<!-- FORMULARIO OCULTO PARA ENVÍO DE MULTIPLES ACCIONES -->
<form id="bulkForm" method="POST" style="display:none;">
    <input type="hidden" name="accion" id="bulkAccion">
    <input type="hidden" name="bulk_ids" id="bulkIds">
    <input type="hidden" name="nuevo_cargo_id" id="bulkNuevoCargo">
</form>

<!-- MODAL PARA CAMBIAR CARGO MASIVAMENTE -->
<div class="modal fade" id="modalBulkMove" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Traslado de Personal</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <p class="small text-muted mb-2">Seleccione el nuevo cargo/área a donde se moverán los trabajadores seleccionados (Su cargo actual será reemplazado).</p>
                <select id="selectNuevoCargoMasivo" class="form-select border-info shadow-sm">
                    <?php foreach ($lista_cargos as $cargo): ?>
                        <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer border-0 p-2">
                <button class="btn btn-info text-white w-100 fw-bold" onclick="ejecutarBulkMove()">Confirmar Traslado</button>
            </div>
        </div>
    </div>
</div>

<!-- EL RESTO DE TUS MODALES (REGISTRO, OFFCANVAS) PERMANECE IDÉNTICO AQUÍ -->
<!-- ========================================== -->
<!-- OFFCANVAS (EXPEDIENTE MPPE + ARCHIVOS) -->
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
          <div id="oc_galeria" class="d-flex flex-wrap gap-2"></div>
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
<!-- MODAL DE REGISTRO CON SUBIDA DE ARCHIVO -->
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

<!-- ======================================================== -->
<!-- SCRIPT 1: LOGICA DE SELECCION MULTIPLE Y ACCIONES MASIVAS-->
<!-- ======================================================== -->
<script>
let selectedIds = new Set();

function checkRow(checkbox) {
    let tr = checkbox.closest('tr');
    if (checkbox.checked) {
        selectedIds.add(checkbox.value);
        tr.classList.add('selected');
    } else {
        selectedIds.delete(checkbox.value);
        tr.classList.remove('selected');
        document.getElementById('checkAll').checked = false;
    }
    actualizarToolbar();
}

function toggleAll(masterCheck) {
    let checkboxes = document.querySelectorAll('.row-check');
    checkboxes.forEach(cb => {
        cb.checked = masterCheck.checked;
        checkRow(cb);
    });
}

function limpiarSeleccion() {
    document.getElementById('checkAll').checked = false;
    toggleAll(document.getElementById('checkAll'));
}

function actualizarToolbar() {
    let toolbar = document.getElementById('bulk-toolbar');
    let countSpan = document.getElementById('bulk-count');
    
    if (selectedIds.size > 0) {
        countSpan.innerText = selectedIds.size + (selectedIds.size === 1 ? " seleccionado" : " seleccionados");
        toolbar.classList.add('show');
    } else {
        toolbar.classList.remove('show');
    }
}

function getSelectedArray() { return Array.from(selectedIds).join(','); }

function ejecutarBulkDelete() {
    if(confirm(`¿Estás seguro de eliminar a los ${selectedIds.size} trabajadores seleccionados? (Se borrará todo su historial y archivos).`)) {
        document.getElementById('bulkAccion').value = 'bulk_delete';
        document.getElementById('bulkIds').value = getSelectedArray();
        document.getElementById('bulkForm').submit();
    }
}

function abrirModalBulkMove() {
    new bootstrap.Modal(document.getElementById('modalBulkMove')).show();
}

function ejecutarBulkMove() {
    let nuevoCargo = document.getElementById('selectNuevoCargoMasivo').value;
    if(!nuevoCargo) { alert("Debe seleccionar un cargo de la lista."); return; }
    
    if(confirm(`¿Trasladar a los ${selectedIds.size} trabajadores al nuevo cargo?`)) {
        document.getElementById('bulkAccion').value = 'bulk_move';
        document.getElementById('bulkIds').value = getSelectedArray();
        document.getElementById('bulkNuevoCargo').value = nuevoCargo;
        document.getElementById('bulkForm').submit();
    }
}
</script>

<!-- ======================================================== -->
<!-- SCRIPT 2: ORDENAMIENTO (ASC/DESC) Y ARRASTRE DE COLUMNAS -->
<!-- ======================================================== -->
<script>
// ORDENAMIENTO DE FILAS
const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;
const comparer = (idx, asc) => (a, b) => ((v1, v2) => 
    v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
    )(getCellValue(asc ? a : b, idx), getCellValue(asc ? b : a, idx));

document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', (() => {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    
    // Limpiar clases de otras columnas
    Array.from(th.parentNode.children).forEach(sibling => {
        if(sibling !== th) { sibling.classList.remove('asc', 'desc'); }
    });
    
    // Alternar clase
    let asc = th.classList.contains('asc');
    th.classList.toggle('asc', !asc);
    th.classList.toggle('desc', asc);

    // Ordenar y Reacomodar
    Array.from(tbody.querySelectorAll('tr.fila-personal'))
        .sort(comparer(Array.from(th.parentNode.children).indexOf(th), !asc))
        .forEach(tr => tbody.appendChild(tr));
})));

// ARRASTRE Y SOLTAR (DRAG & DROP) DE COLUMNAS
let dragColIndex = null;

function dragStart(e) {
    dragColIndex = Array.from(e.target.parentNode.children).indexOf(e.target);
    e.dataTransfer.effectAllowed = "move";
}

function dragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = "move"; }

function drop(e) {
    e.preventDefault();
    let targetTh = e.target.closest('th');
    if (!targetTh) return;
    
    let targetIndex = Array.from(targetTh.parentNode.children).indexOf(targetTh);
    if (dragColIndex === targetIndex || dragColIndex === 0 || targetIndex === 0 || targetIndex === 7) return; // No mover el checkbox (0) ni acciones (7)

    let table = targetTh.closest('table');
    let rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        let cells = row.children;
        if (cells.length > Math.max(dragColIndex, targetIndex)) {
            let colToMove = cells[dragColIndex];
            let referenceCol = cells[targetIndex];
            
            if (dragColIndex < targetIndex) {
                referenceCol.after(colToMove);
            } else {
                referenceCol.before(colToMove);
            }
        }
    });
}
</script>

<!-- AUTO EDICION (SCRIPT DE ORGANIGRAMA) -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const autoEditId = urlParams.get('auto_edit');
    
    if (autoEditId) {
        console.log("Intentando auto-editar trabajador con ID:", autoEditId);
        
        let targetBtn = null;
        let botonesEdicion = document.querySelectorAll('button[onclick="editarRegistro(this)"]');
        
        // Buscar el botón exacto del trabajador
        for (let btn of botonesEdicion) {
            try {
                let data = JSON.parse(btn.getAttribute('data-empleado'));
                if (data.id == autoEditId) { 
                    targetBtn = btn;
                    break;
                }
            } catch (e) {
                console.error("Error leyendo datos del botón", e);
            }
        }

        if (targetBtn) {
            console.log("Trabajador encontrado. Llenando datos y abriendo modal...");
            
            // 1. Llenar los datos en el formulario
            editarRegistro(targetBtn);
            
            // 2. Usar el método más seguro de Bootstrap 5 para forzar la apertura
            setTimeout(() => {
                let modalDOM = document.getElementById('modalRegistro');
                let modalInstancia = bootstrap.Modal.getOrCreateInstance(modalDOM);
                modalInstancia.show();
            }, 300); // 300ms es el tiempo ideal para que el DOM se asiente
            
        } else {
            console.warn("El trabajador con ID " + autoEditId + " no se encuentra en esta tabla.");
        }
        
        // Limpiamos la URL para no repetir el proceso al recargar
        window.history.replaceState(null, '', window.location.pathname + '?tipo=' + urlParams.get('tipo'));
    }
});
</script>

<?php include '../Includes/footer.php'; ?>