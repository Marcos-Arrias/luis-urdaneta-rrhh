<?php
require_once '../PHP/connection.php';

exigirPermisoReBAC($pdo, 'expediente_personal', 'leer');
$usuario_actual_id = $_SESSION['admin_id'] ?? 0;

// Migración automática transparente de archivos antiguos al repositorio
try {
    $hayLegacy = $pdo->query("SELECT COUNT(*) FROM personal_archivos_digitales")->fetchColumn();
    if ($hayLegacy > 0) {
        $legacyDocs = $pdo->query("SELECT * FROM personal_archivos_digitales")->fetchAll();
        foreach ($legacyDocs as $ld) {
            $chk = $pdo->prepare("SELECT id FROM documentos WHERE codigo_archivo = ?");
            $chk->execute([$ld['codigo_archivo']]);
            $docExistId = $chk->fetchColumn();

            if (!$docExistId) {
                $stmtTipo = $pdo->prepare("SELECT id FROM cat_tipos_documentos WHERE clave = ?");
                $stmtTipo->execute([$ld['tipo_documento_clave']]);
                $tipoDocId = $stmtTipo->fetchColumn() ?: 7;

                $tam = file_exists($ld['ruta_archivo']) ? filesize($ld['ruta_archivo']) : 0;
                $hash = file_exists($ld['ruta_archivo']) ? hash_file('sha256', $ld['ruta_archivo']) : null;
                $mime = $ld['mime_type'] ?: 'application/octet-stream';

                $pdo->prepare("INSERT INTO documentos (tipo_documento_id, codigo_archivo, nombre_original, ruta_archivo, mime_type, tamano_bytes, hash_verificacion, fecha_subida) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$tipoDocId, $ld['codigo_archivo'], $ld['nombre_original'], $ld['ruta_archivo'], $mime, $tam, $hash, $ld['fecha_subida']]);
                $docExistId = $pdo->lastInsertId();
            }

            $chkEnl = $pdo->prepare("SELECT id FROM documentos_enlaces WHERE documento_id = ? AND tabla_destino = 'personal' AND registro_id = ?");
            $chkEnl->execute([$docExistId, $ld['personal_id']]);
            if (!$chkEnl->fetchColumn()) {
                $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, 'personal', ?, 'Migrado desde expediente personal')")
                    ->execute([$docExistId, $ld['personal_id']]);
            }
        }
    }
} catch (Exception $e) {}

// ==========================================
// 1. PROCESAMIENTO DEL FORMULARIO (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? null;
    $tipo_actual = intval($_GET['tipo'] ?? 1);

    try {
        $pdo->beginTransaction();

        if ($accion === 'bulk_delete' && !empty($_POST['bulk_ids'])) {
            if (!tienePermisoReBAC($pdo, $usuario_actual_id, 'expediente_personal', 'eliminar')) die("Sin permiso ReBAC para eliminar.");
            $ids = array_map('intval', explode(',', $_POST['bulk_ids']));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM documentos_enlaces WHERE tabla_destino = 'personal' AND registro_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM personal WHERE id IN ($placeholders)")->execute($ids);
            registrarHistorial($pdo, 'Personal', 'ELIMINAR', "Eliminación masiva de " . count($ids) . " registros.");
            $pdo->commit();
            header("Location: personal.php?tipo=" . $tipo_actual);
            exit;
        } 
        elseif ($accion === 'bulk_move' && !empty($_POST['bulk_ids']) && !empty($_POST['nuevo_cargo_id'])) {
            if (!tienePermisoReBAC($pdo, $usuario_actual_id, 'expediente_personal', 'editar')) die("Sin permiso ReBAC para editar.");
            $ids = array_map('intval', explode(',', $_POST['bulk_ids']));
            $nuevo_cargo = intval($_POST['nuevo_cargo_id']);
            $nuevo_tipo = intval($_POST['nuevo_tipo_id'] ?? $tipo_actual);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Cerrar cargo anterior como Finalizado y asignar el nuevo Vigente
            $pdo->prepare("UPDATE personal_cargos SET estado_cargo = 'Finalizado' WHERE personal_id IN ($placeholders) AND estado_cargo = 'Vigente'")->execute($ids);
            $stmtNuevo = $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion, estado_cargo) VALUES (?, ?, CURDATE(), 'Vigente')");
            foreach ($ids as $pidMove) {
                $stmtNuevo->execute([$pidMove, $nuevo_cargo]);
            }

            registrarHistorial($pdo, 'Personal', 'ACTUALIZAR', "Trasladó " . count($ids) . " trabajador(es) al tipo de personal #$nuevo_tipo (Cargo #$nuevo_cargo).");
            $pdo->commit();
            header("Location: personal.php?tipo=" . $tipo_actual . "&msg=trasladado&dest_tipo=" . $nuevo_tipo);
            exit;
        }
        elseif ($accion === 'crear' || $accion === 'editar') {
            if ($accion === 'crear' && !tienePermisoReBAC($pdo, $usuario_actual_id, 'expediente_personal', 'crear')) die("Sin permiso ReBAC para crear.");
            if ($accion === 'editar' && !tienePermisoReBAC($pdo, $usuario_actual_id, 'expediente_personal', 'editar', intval($id))) die("Sin permiso ReBAC para editar.");

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
                registrarHistorial($pdo, 'Personal', 'CREAR', "Registró al trabajador C.I: $cedula", $personal_id);

            } elseif ($accion === 'editar') {
                $personal_id = intval($id);
                $pdo->prepare("UPDATE personal SET cedula=?, nombres=?, apellidos=?, fecha_nacimiento=?, fecha_ingreso=?, estado_laboral=? WHERE id=?")->execute([$cedula, $nombres, $apellidos, $fecha_nacimiento, $fecha_ingreso, $estado_laboral, $personal_id]);
                $pdo->prepare("UPDATE personal_cargos SET cargo_area_id=? WHERE personal_id=? AND estado_cargo='Vigente'")->execute([$cargo_area_id, $personal_id]);
                $pdo->prepare("DELETE FROM personal_contactos WHERE personal_id=? AND tipo_contacto='Teléfono Personal'")->execute([$personal_id]);
                if (!empty($telefono)) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$personal_id, $telefono]);
                registrarHistorial($pdo, 'Personal', 'ACTUALIZAR', "Actualizó al trabajador C.I: $cedula", $personal_id);
            }

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

            $seleccionados = array_map('intval', $_POST['vincular_documentos_existentes'] ?? []);
            $stmtActuales = $pdo->prepare("SELECT documento_id FROM documentos_enlaces WHERE tabla_destino = 'personal' AND registro_id = ?");
            $stmtActuales->execute([$personal_id]);
            $actuales = $stmtActuales->fetchAll(PDO::FETCH_COLUMN);

            $paraDesvincular = array_diff($actuales, $seleccionados);
            if (!empty($paraDesvincular)) {
                $ph = implode(',', array_fill(0, count($paraDesvincular), '?'));
                $pdo->prepare("DELETE FROM documentos_enlaces WHERE tabla_destino = 'personal' AND registro_id = ? AND documento_id IN ($ph)")
                    ->execute(array_merge([$personal_id], $paraDesvincular));
            }
            $paraVincular = array_diff($seleccionados, $actuales);
            if (!empty($paraVincular)) {
                $stmtLink = $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, 'personal', ?, 'Vinculado desde ficha de personal')");
                foreach ($paraVincular as $docVincId) $stmtLink->execute([$docVincId, $personal_id]);
            }

            if (isset($_FILES['archivo_expediente']) && is_array($_FILES['archivo_expediente']['name'])) {
                $directorio = '../documentos/';
                if (!file_exists($directorio)) mkdir($directorio, 0777, true);
                $cedula_limpia = preg_replace('/[^0-9]/', '', $cedula);

                foreach ($_FILES['archivo_expediente']['name'] as $idx => $nombreOriginal) {
                    if ($_FILES['archivo_expediente']['error'][$idx] === UPLOAD_ERR_OK) {
                        $tipo_clave = $_POST['tipo_archivo_clave'][$idx] ?? 'OTR';
                        $tmpName = $_FILES['archivo_expediente']['tmp_name'][$idx];
                        $mime = $_FILES['archivo_expediente']['type'][$idx] ?: mime_content_type($tmpName);
                        $tamano = $_FILES['archivo_expediente']['size'][$idx];
                        $hash = hash_file('sha256', $tmpName);
                        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

                        $stmtT = $pdo->prepare("SELECT id FROM cat_tipos_documentos WHERE clave = ?");
                        $stmtT->execute([$tipo_clave]);
                        $tipoDocId = $stmtT->fetchColumn() ?: 7;

                        $stmtS = $pdo->prepare("SELECT COUNT(*) FROM documentos d JOIN documentos_enlaces de ON d.id = de.documento_id WHERE de.tabla_destino = 'personal' AND de.registro_id = ? AND d.tipo_documento_id = ?");
                        $stmtS->execute([$personal_id, $tipoDocId]);
                        $secuencia = intval($stmtS->fetchColumn()) + 1;

                        $nombre_codigo = sprintf("V%s_%d_%s_%02d.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, $ext);
                        $chkCod = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE codigo_archivo = ?");
                        $chkCod->execute([$nombre_codigo]);
                        if ($chkCod->fetchColumn() > 0) {
                            $nombre_codigo = sprintf("V%s_%d_%s_%02d_%s.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, substr(uniqid(), -4), $ext);
                        }

                        $rutaFinal = $directorio . $nombre_codigo;
                        if (move_uploaded_file($tmpName, $rutaFinal)) {
                            $pdo->prepare("INSERT INTO documentos (tipo_documento_id, codigo_archivo, nombre_original, ruta_archivo, mime_type, tamano_bytes, hash_verificacion) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$tipoDocId, $nombre_codigo, $nombreOriginal, $rutaFinal, $mime, $tamano, $hash]);
                            $nuevoDocId = $pdo->lastInsertId();

                            $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, 'personal', ?, 'Subido desde ficha de personal')")
                                ->execute([$nuevoDocId, $personal_id]);

                            registrarHistorial($pdo, 'Documentos', 'CREAR', "Subió archivo $nombre_codigo al repositorio y lo vinculó al expediente #$personal_id.", $nuevoDocId);
                        }
                    }
                }
            }
        } 
        elseif ($accion === 'eliminar') {
            if (!tienePermisoReBAC($pdo, $usuario_actual_id, 'expediente_personal', 'eliminar', intval($id))) die("Sin permiso ReBAC para eliminar.");
            $pdo->prepare("DELETE FROM documentos_enlaces WHERE tabla_destino = 'personal' AND registro_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM personal WHERE id=?")->execute([$id]);
            registrarHistorial($pdo, 'Personal', 'ELIMINAR', "Eliminó registro de personal individual.", $id);
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
           (SELECT d.ruta_archivo FROM documentos d 
            JOIN documentos_enlaces de ON d.id = de.documento_id 
            JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
            WHERE de.tabla_destino = 'personal' AND de.registro_id = p.id AND td.clave = 'FOT' 
            ORDER BY d.id DESC LIMIT 1) as ruta_foto_carnet,
           (SELECT d.ruta_archivo FROM documentos d 
            JOIN documentos_enlaces de ON d.id = de.documento_id 
            JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
            WHERE de.tabla_destino = 'personal' AND de.registro_id = p.id AND td.clave = 'CED' 
            ORDER BY d.id DESC LIMIT 1) as ruta_foto_cedula,
           (SELECT GROUP_CONCAT(CONCAT(td.clave, '::', d.codigo_archivo, '::', d.ruta_archivo) SEPARATOR '||') 
            FROM documentos d 
            JOIN documentos_enlaces de ON d.id = de.documento_id 
            JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
            WHERE de.tabla_destino = 'personal' AND de.registro_id = p.id) as galeria_archivos,
           (SELECT GROUP_CONCAT(de.documento_id)
            FROM documentos_enlaces de
            WHERE de.tabla_destino = 'personal' AND de.registro_id = p.id) as documentos_vinculados_ids,
           pda.*,
           p.id
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

// Todos los tipos de personal y sus cargos para el traslado masivo entre tipos
$tipos_personal_all = $pdo->query("SELECT id, nombre FROM cat_tipos_personal ORDER BY id ASC")->fetchAll();
$todos_cargos_raw = $pdo->query("SELECT id, nombre, tipo_personal_id FROM cat_cargos_areas ORDER BY nombre ASC")->fetchAll();
$cargos_por_tipo = [];
foreach ($todos_cargos_raw as $cr) {
    $cargos_por_tipo[$cr['tipo_personal_id']][] = ['id' => $cr['id'], 'nombre' => $cr['nombre']];
}

$docsRepositorio = $pdo->query("
    SELECT d.id, d.codigo_archivo, d.nombre_original, d.fecha_subida, td.clave
    FROM documentos d
    JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
    ORDER BY d.fecha_subida DESC, d.id DESC
")->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<style>
.doc-split-container { display: flex; gap: 0.85rem; align-items: stretch; transition: all 0.25s ease; }
.doc-panel { flex: 1 1 0; min-width: 0; transition: all 0.25s ease; }
.doc-panel.minimizado { flex: 0 0 54px !important; max-width: 54px !important; overflow: hidden; cursor: pointer; }
.doc-panel.minimizado:hover { filter: brightness(0.96); }
.doc-panel.minimizado .panel-contenido-abierto { display: none !important; }
.doc-panel:not(.minimizado) .panel-contenido-minimizado { display: none !important; }
.panel-contenido-minimizado { display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 0.85rem 0.25rem; height: 100%; min-height: 155px; user-select: none; }
.texto-vertical { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; font-weight: 700; font-size: 0.78rem; letter-spacing: 0.4px; margin-top: 0.65rem; }
@media (max-width: 991.98px) {
    .doc-split-container { flex-direction: column; }
    .doc-panel.minimizado { flex: 0 0 auto !important; max-width: 100% !important; }
    .panel-contenido-minimizado { flex-direction: row; justify-content: space-between; min-height: auto; padding: 0.65rem 1rem; }
    .texto-vertical { writing-mode: horizontal-tb; transform: none; margin-top: 0; margin-left: 0.5rem; }
}
.dropzone-multiple { border: 2px dashed rgba(255, 193, 7, 0.75); border-radius: 12px; padding: 1rem; text-align: center; background-color: rgba(255, 193, 7, 0.06); transition: all 0.2s ease; }
.dropzone-multiple.dragover { background-color: rgba(255, 193, 7, 0.18); border-color: #ffc107; transform: scale(1.01); }
.file-queue-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h2 class="fw-bold mb-0"><i class="bi <?= $ui['icono'] ?> me-2 text-primary"></i> <?= $ui['titulo'] ?></h2>
    <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-2"></i> <?= $ui['btn'] ?>
    </button>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'trasladado'): 
    $dest = intval($_GET['dest_tipo'] ?? 1);
    $nombresDest = [1 => 'Personal Docente', 2 => 'Personal Administrativo', 3 => 'Personal Obrero'];
?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="bi bi-arrow-left-right me-2"></i>
            <strong>¡Traslado realizado!</strong> El personal seleccionado fue reasignado a <strong><?= $nombresDest[$dest] ?? 'la nueva categoría' ?></strong>.
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($dest !== $tipo_id): ?>
                <a href="personal.php?tipo=<?= $dest ?>" class="btn btn-sm btn-success fw-bold rounded-pill px-3">
                    Ir a <?= $nombresDest[$dest] ?> <i class="bi bi-arrow-right ms-1"></i>
                </a>
            <?php endif; ?>
            <button type="button" class="btn-close position-static" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" id="tablaPersonal">
            <thead class="table-light text-secondary">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input class="form-check-input border-secondary" type="checkbox" id="checkAll" onclick="toggleAll(this)" aria-label="Seleccionar todos">
                    </th>
                    <th class="ps-3 sortable" draggable="true" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="drop(event)">Cédula</th>
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
                            <input class="form-check-input border-secondary row-check" type="checkbox" value="<?= $d['id'] ?>" onclick="checkRow(this)" aria-label="Seleccionar fila">
                        </td>
                        
                        <td class="ps-3 text-nowrap">
                            <?php if (!empty($d['ruta_foto_cedula'])): ?>
                                <span class="fw-bold text-primary preview-hover-trigger" style="cursor: pointer;" data-preview-img="<?= htmlspecialchars($d['ruta_foto_cedula'], ENT_QUOTES, 'UTF-8') ?>" data-preview-title="Cédula V-<?= htmlspecialchars($d['cedula'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($d['cedula'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <span class="fw-bold text-body"><?= htmlspecialchars($d['cedula'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="text-nowrap">
                            <div class="d-flex align-items-center <?= !empty($d['ruta_foto_carnet']) ? 'preview-hover-trigger' : '' ?>" <?= !empty($d['ruta_foto_carnet']) ? 'data-preview-img="' . htmlspecialchars($d['ruta_foto_carnet'], ENT_QUOTES, 'UTF-8') . '" data-preview-title="' . htmlspecialchars($d['nombres'] . ' ' . $d['apellidos'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <?php if (!empty($d['ruta_foto_carnet'])): ?>
                                    <img src="<?= htmlspecialchars($d['ruta_foto_carnet'], ENT_QUOTES, 'UTF-8') ?>" class="avatar-thumb me-3 shadow-sm" alt="Foto">
                                <?php else: ?>
                                    <?php 
                                    $avBg = 'bg-primary text-white';
                                    if ($tipo_id == 2) $avBg = 'bg-success text-white';
                                    if ($tipo_id == 3) $avBg = 'bg-warning text-dark';
                                    ?>
                                    <div class="avatar-thumb me-3 <?= $avBg ?> d-flex align-items-center justify-content-center fw-bold shadow-sm">
                                        <?= mb_strtoupper(mb_substr($d['nombres'], 0, 1, 'UTF-8') . mb_substr($d['apellidos'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-bold name-val d-block"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($d['edad']): ?><small class="text-muted"><?= $d['edad'] ?> años</small><?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td class="text-nowrap">
                            <span class="badge bg-body-secondary text-body border px-3 py-2 rounded-pill">
                                <?= htmlspecialchars($d['cargo_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>

                        <td class="text-nowrap"><?= !empty($d['telefono']) ? formatearTelefono($d['telefono']) : '<span class="text-muted small">S/R</span>' ?></td>
                        <td class="text-center text-muted small text-nowrap"><?= date('d/m/y', strtotime($d['fecha_ingreso'])) ?></td>
                        <td class="text-center text-nowrap">
                            <?php 
                            $est = $d['estado_laboral'];
                            $bClass = 'bg-success-subtle text-success border border-success-subtle'; 
                            if ($est == 'Reposo') $bClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'; 
                            if ($est == 'Permiso') $bClass = 'bg-info-subtle text-info-emphasis border border-info-subtle'; 
                            if ($est == 'Inactivo') $bClass = 'bg-danger-subtle text-danger border border-danger-subtle'; 
                            if ($est == 'Jubilado') $bClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle'; 
                            ?>
                            <span class="badge <?= $bClass ?> px-3 py-1 rounded-pill"><?= $est ?></span>
                        </td>
                        
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-icon-theme rounded-circle" onclick="verDetallesExtra(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="offcanvas" data-bs-target="#panelDatosExtra" title="Ver Expediente" aria-label="Ver expediente">
                                    <i class="bi bi-folder2-open text-primary" style="pointer-events:none;"></i>
                                </button>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon-theme rounded-circle" data-bs-toggle="dropdown" aria-label="Más acciones">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li>
                                            <button type="button" class="dropdown-item" onclick="editarRegistro(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="modal" data-bs-target="#modalRegistro">
                                                <i class="bi bi-pencil me-2 text-warning"></i>Editar datos
                                            </button>
                                        </li>
                                        <li>
                                            <form action="" method="POST" onsubmit="return confirm('¿Eliminar registro?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
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

<!-- CONTENEDOR FLOTANTE PARA VISTA PREVIA DE IMÁGENES -->
<div id="floating-img-preview" class="d-none position-fixed p-2 bg-body border rounded-3 shadow-lg text-center" style="z-index: 1095; width: 190px; pointer-events: none;"></div>

<!-- BARRA FLOTANTE INFERIOR (ACCIONES MASIVAS) -->
<div id="bulk-toolbar">
    <span id="bulk-count" class="fw-bold border-end border-secondary pe-3 me-2">0 seleccionados</span>
    <button type="button" class="btn btn-sm btn-info fw-bold text-white rounded-pill" onclick="abrirModalBulkMove()"><i class="bi bi-arrow-left-right me-1"></i> Cambiar Tipo de Personal</button>
    <button type="button" class="btn btn-sm btn-danger fw-bold rounded-pill" onclick="ejecutarBulkDelete()"><i class="bi bi-trash me-1"></i> Eliminar</button>
    <button type="button" class="btn btn-sm btn-outline-light rounded-circle ms-2" onclick="limpiarSeleccion()" title="Desmarcar Todos"><i class="bi bi-x-lg"></i></button>
</div>

<form id="bulkForm" method="POST" style="display:none;">
    <input type="hidden" name="accion" id="bulkAccion">
    <input type="hidden" name="bulk_ids" id="bulkIds">
    <input type="hidden" name="nuevo_tipo_id" id="bulkNuevoTipo">
    <input type="hidden" name="nuevo_cargo_id" id="bulkNuevoCargo">
</form>

<!-- MODAL: CAMBIAR TIPO DE PERSONAL (DOCENTE / ADMIN / OBRERO) -->
<div class="modal fade" id="modalBulkMove" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Cambiar Tipo de Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <p class="small text-muted mb-3">Reasigna al personal seleccionado entre <strong>Docente</strong>, <strong>Administrativo</strong> u <strong>Obrero</strong> y define su nuevo cargo dentro de esa categoría.</p>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">1. Nuevo Tipo de Personal:</label>
                    <select id="selectNuevoTipoMasivo" class="form-select border-info shadow-sm fw-bold" onchange="cargarCargosTraslado()">
                        <?php foreach ($tipos_personal_all as $tp): ?>
                            <option value="<?= $tp['id'] ?>" <?= $tp['id'] == $tipo_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold">2. Cargo / Área en el Nuevo Tipo:</label>
                    <select id="selectNuevoCargoMasivo" class="form-select border-info shadow-sm"></select>
                </div>
            </div>
            <div class="modal-footer border-0 bg-body">
                <button type="button" class="btn btn-info text-white w-100 fw-bold" onclick="ejecutarBulkMove()">Confirmar Cambio de Tipo</button>
            </div>
        </div>
    </div>
</div>

<!-- OFFCANVAS (EXPEDIENTE MPPE + ARCHIVOS DEL REPOSITORIO) -->
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

      <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="text-warning fw-bold mb-0"><i class="bi bi-images me-2"></i>Archivos Vinculados</h6>
          <a href="documentos.php" class="small text-decoration-none fw-bold">Ir a Archivos <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <div id="oc_galeria" class="d-flex flex-wrap gap-2"></div>
      </div>

      <h6 class="text-primary fw-bold mb-2"><i class="bi bi-briefcase-fill me-2"></i>Datos Laborales MPPE</h6>
      <div class="card border-0 shadow-sm p-3 mb-3 bg-body">
          <p class="mb-1 small"><strong>Cargo Nominal:</strong> <span id="oc_cargo_nominal">-</span> (Cód: <span id="oc_codigo_cargo">-</span>)</p>
          <p class="mb-1 small"><strong>Dependencia:</strong> <span id="oc_dependencia">-</span> (Cód: <span id="oc_cod_dependencia">-</span>)</p>
          <p class="mb-1 small"><strong>Turno:</strong> <span id="oc_turno" class="badge bg-info-subtle text-info-emphasis border">-</span> | <strong>Horas:</strong> <span id="oc_horas">0</span>h</p>
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

<!-- MODAL DE REGISTRO CON PANELES MINIMIZABLES Y SELECCIÓN MÚLTIPLE CÓMODA -->
<div class="modal fade" id="modalRegistro" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" id="formRegistroPersonal">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard me-2"></i>Gestión de Expediente MPPE</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" name="accion" id="accion" value="crear">
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

                <!-- SECCIÓN DE ARCHIVOS ERGONÓMICA (MINIMIZABLE IZQ / DER) -->
                <div class="doc-split-container mb-3">
                    <div class="card border-0 shadow-sm border-start border-4 border-warning doc-panel" id="panelSubirDocs">
                        <div class="panel-contenido-minimizado bg-warning-subtle text-warning-emphasis rounded-end" onclick="alternarPanelDoc('panelSubirDocs', false)" title="Expandir panel de subida">
                            <i class="bi bi-chevron-bar-right fs-5"></i>
                            <span class="badge bg-warning text-dark mt-1" id="badgeColaSubida">0</span>
                            <span class="texto-vertical"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Subir Nuevos</span>
                        </div>

                        <div class="card-body panel-contenido-abierto">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-warning-emphasis fw-bold mb-0">
                                    <i class="bi bi-cloud-arrow-up-fill me-1 text-warning"></i> Subir Documentos Nuevos
                                </h6>
                                <button type="button" class="btn btn-sm btn-icon-theme rounded-pill px-2 py-0" onclick="alternarPanelDoc('panelSubirDocs', true)" title="Minimizar a la izquierda">
                                    <i class="bi bi-chevron-bar-left"></i> Minimizar
                                </button>
                            </div>

                            <input type="file" name="archivo_expediente[]" id="inputArchivosReales" multiple accept=".jpg,.jpeg,.png,.pdf,.webp,.doc,.docx" class="d-none">

                            <div class="dropzone-multiple mb-2" id="dropzoneArchivos">
                                <i class="bi bi-files text-warning fs-3 d-block mb-1"></i>
                                <div class="small text-muted mb-2">Arrastra varios documentos aquí o haz clic para seleccionarlos todos de una vez</div>
                                <label for="inputArchivosReales" class="btn btn-warning btn-sm fw-bold rounded-pill px-4 shadow-sm text-dark" style="cursor: pointer;">
                                    <i class="bi bi-folder2-open me-1"></i> Elegir Múltiples Archivos...
                                </label>
                            </div>

                            <div id="listaArchivosSeleccionados" class="d-flex flex-column gap-2" style="max-height: 210px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm border-start border-4 border-info doc-panel minimizado" id="panelVincularDocs">
                        <div class="panel-contenido-minimizado bg-info-subtle text-info-emphasis rounded-end" onclick="alternarPanelDoc('panelVincularDocs', false)" title="Expandir para vincular archivos existentes">
                            <i class="bi bi-chevron-bar-left fs-5"></i>
                            <span class="badge bg-info text-dark mt-1" id="badgeVinculadosCount">0</span>
                            <span class="texto-vertical"><i class="bi bi-link-45deg me-1"></i> Vincular Existentes</span>
                        </div>

                        <div class="card-body panel-contenido-abierto">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-info-emphasis fw-bold mb-0">
                                    <i class="bi bi-link-45deg me-1 text-info"></i>  Usar Documentos Guardados 
                                </h6>
                                <button type="button" class="btn btn-sm btn-icon-theme rounded-pill px-2 py-0" onclick="alternarPanelDoc('panelVincularDocs', true)" title="Minimizar a la derecha">
                                    Minimizar <i class="bi bi-chevron-bar-right"></i>
                                </button>
                            </div>

                            <div class="d-flex gap-2 mb-2">
                                <input type="text" id="buscarDocVincular" class="form-control form-control-sm" placeholder="Buscar entre todos los archivos...">
                                <button type="button" class="btn btn-sm btn-outline-info text-nowrap fw-bold" id="btnToggleTodosDocs" onclick="toggleMostrarTodosDocs()">
                                    Ver todos (<?= count($docsRepositorio) ?>)
                                </button>
                            </div>

                            <div class="small text-muted mb-1 d-flex justify-content-between">
                                <span id="etiquetaFiltroRecientes"><i class="bi bi-clock-history me-1"></i>Mostrando los 5 más recientes</span>
                            </div>
                            
                            <div class="border rounded p-2 bg-body" style="max-height: 175px; overflow-y: auto;" id="listaCheckboxesDocs">
                                <?php if (count($docsRepositorio) > 0): ?>
                                    <?php foreach ($docsRepositorio as $idx => $dr): 
                                        $esReciente = ($idx < 5) ? '1' : '0';
                                    ?>
                                        <div class="form-check small item-doc-vincular py-1 border-bottom border-light-subtle <?= $esReciente === '0' ? 'd-none' : '' ?>" data-reciente="<?= $esReciente ?>">
                                            <input class="form-check-input chk-vincular-doc" type="checkbox" name="vincular_documentos_existentes[]" value="<?= $dr['id'] ?>" id="vinc_doc_<?= $dr['id'] ?>" onchange="actualizarContadorVinculados()">
                                            <label class="form-check-label d-flex justify-content-between align-items-center" for="vinc_doc_<?= $dr['id'] ?>" style="cursor:pointer;">
                                                <span class="text-truncate me-2">
                                                    <span class="badge bg-secondary-subtle text-body border me-1"><?= $dr['clave'] ?></span>
                                                    <strong><?= htmlspecialchars($dr['nombre_original']) ?></strong>
                                                    <small class="text-muted">(<?= htmlspecialchars($dr['codigo_archivo']) ?>)</small>
                                                </span>
                                                <small class="text-muted text-nowrap" style="font-size: 0.7rem;"><?= date('d/m/y', strtotime($dr['fecha_subida'])) ?></small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small d-block text-center py-3">No hay documentos en el repositorio aún.</span>
                                <?php endif; ?>
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
            <div class="modal-footer bg-body border-top">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Expediente y Archivos</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AGREGAR CARGO RÁPIDO (AJAX) -->
<div class="modal fade" id="modalQuickAddCargo" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Cargo Rápido</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" id="quick_tipo_id">
                <input type="hidden" id="quick_select_id">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nombre del Cargo/Materia</label>
                    <input type="text" id="quick_nombre_cargo" class="form-control form-control-sm" placeholder="Ej: Biología" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Descripción (Opcional)</label>
                    <textarea id="quick_descripcion_cargo" class="form-control form-control-sm" rows="2" placeholder="Breve descripción..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-2">
                <button type="button" class="btn btn-success w-100 fw-bold" id="btnGuardarQuick" onclick="guardarCargoModal()">Guardar y Seleccionar</button>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Paneles minimizables Izquierda/Derecha
function alternarPanelDoc(panelId, minimizar) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    if (minimizar) panel.classList.add('minimizado');
    else panel.classList.remove('minimizado');
}

// 2. Selección múltiple de archivos con DataTransfer
let dtArchivos = new DataTransfer();
let tiposSeleccionadosMap = new Map();
const inputArchivosReales = document.getElementById('inputArchivosReales');
const dropzoneArchivos = document.getElementById('dropzoneArchivos');

function claveUnicaArchivo(file) { return `${file.name}__${file.size}__${file.lastModified}`; }

function agregarFilesACola(fileList) {
    for (let i = 0; i < fileList.length; i++) {
        let f = fileList[i];
        let key = claveUnicaArchivo(f);
        let yaExiste = Array.from(dtArchivos.files).some(ex => claveUnicaArchivo(ex) === key);
        if (!yaExiste) dtArchivos.items.add(f);
    }
    inputArchivosReales.files = dtArchivos.files;
    renderizarColaArchivos();
}

inputArchivosReales.addEventListener('change', function() {
    if (this.files.length > 0) agregarFilesACola(this.files);
});

['dragenter', 'dragover'].forEach(ev => dropzoneArchivos.addEventListener(ev, (e) => { e.preventDefault(); dropzoneArchivos.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(ev => dropzoneArchivos.addEventListener(ev, (e) => { e.preventDefault(); dropzoneArchivos.classList.remove('dragover'); }));
dropzoneArchivos.addEventListener('drop', (e) => { if (e.dataTransfer.files.length > 0) agregarFilesACola(e.dataTransfer.files); });

function guardarTipoArchivo(key, valor) { tiposSeleccionadosMap.set(key, valor); }

function quitarArchivoDeCola(index) {
    let nuevaDT = new DataTransfer();
    let files = dtArchivos.files;
    for (let i = 0; i < files.length; i++) {
        if (i !== index) nuevaDT.items.add(files[i]);
        else tiposSeleccionadosMap.delete(claveUnicaArchivo(files[i]));
    }
    dtArchivos = nuevaDT;
    inputArchivosReales.files = dtArchivos.files;
    renderizarColaArchivos();
}

function renderizarColaArchivos() {
    const contenedor = document.getElementById('listaArchivosSeleccionados');
    document.getElementById('badgeColaSubida').innerText = dtArchivos.files.length;
    contenedor.innerHTML = '';

    Array.from(dtArchivos.files).forEach((file, idx) => {
        let key = claveUnicaArchivo(file);
        let tipoPrevio = tiposSeleccionadosMap.get(key) || '';
        let tamKB = (file.size / 1024).toFixed(1) + ' KB';

        let thumbHTML = '<div class="file-queue-thumb bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center border"><i class="bi bi-file-earmark-text-fill fs-5"></i></div>';
        if (file.type.startsWith('image/')) thumbHTML = `<img src="${URL.createObjectURL(file)}" class="file-queue-thumb border shadow-sm">`;
        else if (file.type === 'application/pdf') thumbHTML = '<div class="file-queue-thumb bg-danger-subtle text-danger d-flex align-items-center justify-content-center border"><i class="bi bi-file-earmark-pdf-fill fs-5"></i></div>';

        let fila = document.createElement('div');
        fila.className = 'd-flex flex-wrap flex-sm-nowrap align-items-center gap-2 p-2 rounded-3 bg-body border shadow-sm';
        fila.innerHTML = `
            ${thumbHTML}
            <div class="flex-grow-1 text-truncate" style="min-width: 120px;">
                <div class="small fw-bold text-truncate">${file.name}</div>
                <small class="text-muted" style="font-size: 0.72rem;">${tamKB}</small>
            </div>
            <div style="min-width: 210px;">
                <select name="tipo_archivo_clave[]" class="form-select form-select-sm border-warning fw-medium" required onchange="guardarTipoArchivo('${key.replace(/'/g, "\\'")}', this.value)">
                    <option value="" disabled ${tipoPrevio === '' ? 'selected' : ''}>¿Qué tipo de documento es?...</option>
                    <option value="FOT" ${tipoPrevio === 'FOT' ? 'selected' : ''}>FOT - Foto Tipo Carnet</option>
                    <option value="CED" ${tipoPrevio === 'CED' ? 'selected' : ''}>CED - Cédula Escaneada</option>
                    <option value="TIT" ${tipoPrevio === 'TIT' ? 'selected' : ''}>TIT - Título / Certificado</option>
                    <option value="MED" ${tipoPrevio === 'MED' ? 'selected' : ''}>MED - Informe Médico</option>
                    <option value="SOP" ${tipoPrevio === 'SOP' ? 'selected' : ''}>SOP - Justificación / Reposo</option>
                    <option value="MEM" ${tipoPrevio === 'MEM' ? 'selected' : ''}>MEM - Memorándum / Acta</option>
                    <option value="OTR" ${tipoPrevio === 'OTR' ? 'selected' : ''}>OTR - Otro Documento</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm text-danger border-0 p-1" onclick="quitarArchivoDeCola(${idx})" title="Quitar archivo">
                <i class="bi bi-x-circle-fill fs-5"></i>
            </button>
        `;
        contenedor.appendChild(fila);
    });
}

function resetearColaArchivos() {
    dtArchivos = new DataTransfer();
    tiposSeleccionadosMap.clear();
    inputArchivosReales.files = dtArchivos.files;
    renderizarColaArchivos();
}

document.getElementById('formRegistroPersonal')?.addEventListener('submit', function() {
    for (let s of document.querySelectorAll('#listaArchivosSeleccionados select[required]')) {
        if (!s.value) { alternarPanelDoc('panelSubirDocs', false); break; }
    }
});

// 3. Top 5 recientes y buscador de vinculación
let mostrandoTodosDocs = false;
function actualizarVisibilidadDocs() {
    const query = document.getElementById('buscarDocVincular').value.trim().toLowerCase();
    const etiqueta = document.getElementById('etiquetaFiltroRecientes');
    const items = document.querySelectorAll('.item-doc-vincular');

    if (query !== '') {
        etiqueta.innerHTML = `<i class="bi bi-search me-1"></i>Resultados para "${query}"`;
        items.forEach(item => item.classList.toggle('d-none', !item.innerText.toLowerCase().includes(query)));
    } else if (mostrandoTodosDocs) {
        etiqueta.innerHTML = `<i class="bi bi-list-ul me-1"></i>Mostrando todos los documentos`;
        items.forEach(item => item.classList.remove('d-none'));
    } else {
        etiqueta.innerHTML = `<i class="bi bi-clock-history me-1"></i>Mostrando los 5 más recientes (y los vinculados)`;
        items.forEach(item => {
            let esReciente = item.getAttribute('data-reciente') === '1';
            let estaMarcado = item.querySelector('.chk-vincular-doc')?.checked;
            item.classList.toggle('d-none', !(esReciente || estaMarcado));
        });
    }
}
function toggleMostrarTodosDocs() {
    mostrandoTodosDocs = !mostrandoTodosDocs;
    document.getElementById('btnToggleTodosDocs').innerText = mostrandoTodosDocs ? 'Solo 5 recientes' : 'Ver todos (<?= count($docsRepositorio) ?>)';
    actualizarVisibilidadDocs();
}
document.getElementById('buscarDocVincular')?.addEventListener('input', actualizarVisibilidadDocs);

function actualizarContadorVinculados() {
    document.getElementById('badgeVinculadosCount').innerText = document.querySelectorAll('.chk-vincular-doc:checked').length;
}
function limpiarCheckboxesDocumentos() {
    mostrandoTodosDocs = false;
    document.getElementById('btnToggleTodosDocs').innerText = 'Ver todos (<?= count($docsRepositorio) ?>)';
    document.getElementById('buscarDocVincular').value = '';
    document.querySelectorAll('.chk-vincular-doc').forEach(cb => cb.checked = false);
    actualizarContadorVinculados();
    actualizarVisibilidadDocs();
}
function cargarCheckboxesDocumentos(btn) {
    limpiarCheckboxesDocumentos();
    try {
        let d = JSON.parse(btn.getAttribute("data-empleado"));
        if (d.documentos_vinculados_ids) {
            let ids = d.documentos_vinculados_ids.split(',').map(s => s.trim());
            document.querySelectorAll('.chk-vincular-doc').forEach(cb => cb.checked = ids.includes(cb.value));
        }
    } catch(e) {}
    actualizarContadorVinculados();
    actualizarVisibilidadDocs();
}

// 4. Selección múltiple y cambio de TIPO DE PERSONAL masivo
const cargosPorTipo = <?= json_encode($cargos_por_tipo) ?>;
let selectedIds = new Set();

function checkRow(checkbox) {
    let tr = checkbox.closest('tr');
    if (checkbox.checked) { selectedIds.add(checkbox.value); tr.classList.add('selected'); } 
    else { selectedIds.delete(checkbox.value); tr.classList.remove('selected'); document.getElementById('checkAll').checked = false; }
    actualizarToolbar();
}
function toggleAll(masterCheck) { document.querySelectorAll('.row-check').forEach(cb => { cb.checked = masterCheck.checked; checkRow(cb); }); }
function limpiarSeleccion() { document.getElementById('checkAll').checked = false; toggleAll(document.getElementById('checkAll')); }
function actualizarToolbar() {
    let toolbar = document.getElementById('bulk-toolbar');
    if (selectedIds.size > 0) {
        document.getElementById('bulk-count').innerText = selectedIds.size + (selectedIds.size === 1 ? " seleccionado" : " seleccionados");
        toolbar.classList.add('show');
    } else toolbar.classList.remove('show');
}
function getSelectedArray() { return Array.from(selectedIds).join(','); }
function ejecutarBulkDelete() {
    if(confirm(`¿Estás seguro de eliminar a los ${selectedIds.size} trabajadores seleccionados? (Sus documentos se mantendrán a salvo en Archivo Digital).`)) {
        document.getElementById('bulkAccion').value = 'bulk_delete';
        document.getElementById('bulkIds').value = getSelectedArray();
        document.getElementById('bulkForm').submit();
    }
}
function cargarCargosTraslado() {
    const tipoId = document.getElementById('selectNuevoTipoMasivo').value;
    const selectCargo = document.getElementById('selectNuevoCargoMasivo');
    selectCargo.innerHTML = '';
    const lista = cargosPorTipo[tipoId] || [];
    if (lista.length === 0) {
        selectCargo.add(new Option('Sin cargos creados en esta categoría', ''));
    } else {
        lista.forEach(c => selectCargo.add(new Option(c.nombre, c.id)));
    }
}
function abrirModalBulkMove() {
    cargarCargosTraslado();
    new bootstrap.Modal(document.getElementById('modalBulkMove')).show();
}
function ejecutarBulkMove() {
    const selTipo = document.getElementById('selectNuevoTipoMasivo');
    const nuevoTipo = selTipo.value;
    const nombreTipo = selTipo.options[selTipo.selectedIndex].text;
    const nuevoCargo = document.getElementById('selectNuevoCargoMasivo').value;
    if (!nuevoCargo) return alert("Debe existir al menos un cargo en esa categoría para asignar.");
    if (confirm(`¿Cambiar a los ${selectedIds.size} trabajador(es) al tipo "${nombreTipo}"?`)) {
        document.getElementById('bulkAccion').value = 'bulk_move';
        document.getElementById('bulkIds').value = getSelectedArray();
        document.getElementById('bulkNuevoTipo').value = nuevoTipo;
        document.getElementById('bulkNuevoCargo').value = nuevoCargo;
        document.getElementById('bulkForm').submit();
    }
}

// 5. Ordenar y arrastrar columnas
const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;
const comparer = (idx, asc) => (a, b) => ((v1, v2) => 
    v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
    )(getCellValue(asc ? a : b, idx), getCellValue(asc ? b : a, idx));

document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', (() => {
    const table = th.closest('table'); const tbody = table.querySelector('tbody');
    Array.from(th.parentNode.children).forEach(s => { if(s !== th) s.classList.remove('asc', 'desc'); });
    let asc = th.classList.contains('asc');
    th.classList.toggle('asc', !asc); th.classList.toggle('desc', asc);
    Array.from(tbody.querySelectorAll('tr.fila-personal'))
        .sort(comparer(Array.from(th.parentNode.children).indexOf(th), !asc))
        .forEach(tr => tbody.appendChild(tr));
})));

renderizarPaginacionPersonal()

let dragColIndex = null;
function dragStart(e) { dragColIndex = Array.from(e.target.parentNode.children).indexOf(e.target); e.dataTransfer.effectAllowed = "move"; }
function dragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = "move"; }
function drop(e) {
    e.preventDefault(); let targetTh = e.target.closest('th'); if (!targetTh) return;
    let targetIndex = Array.from(targetTh.parentNode.children).indexOf(targetTh);
    if (dragColIndex === targetIndex || dragColIndex === 0 || targetIndex === 0 || targetIndex === 7) return;
    targetTh.closest('table').querySelectorAll('tr').forEach(row => {
        let cells = row.children;
        if (cells.length > Math.max(dragColIndex, targetIndex)) {
            if (dragColIndex < targetIndex) cells[targetIndex].after(cells[dragColIndex]);
            else cells[targetIndex].before(cells[dragColIndex]);
        }
    });
}

// 6. Inicialización una vez cargado footer.php
document.addEventListener("DOMContentLoaded", () => {
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('#tablaPersonal [data-bs-toggle="dropdown"]').forEach(el => {
            new bootstrap.Dropdown(el, { popperConfig: { strategy: 'fixed' } });
        });
    }

    const previewBox = document.getElementById('floating-img-preview');
    document.querySelectorAll('.preview-hover-trigger').forEach(el => {
        el.addEventListener('mouseenter', () => {
            const imgSrc = el.getAttribute('data-preview-img');
            const title = el.getAttribute('data-preview-title') || '';
            if (!imgSrc) return;
            const rect = el.getBoundingClientRect();
            previewBox.innerHTML = `<img src="${imgSrc}" class="img-fluid rounded mb-1" style="max-height:150px; object-fit:cover;"><div class="small fw-bold text-truncate">${title}</div>`;
            previewBox.style.left = Math.min(rect.right + 10, window.innerWidth - 210) + 'px';
            previewBox.style.top = Math.max(10, Math.min(rect.top - 20, window.innerHeight - 200)) + 'px';
            previewBox.classList.remove('d-none');
        });
        el.addEventListener('mouseleave', () => previewBox.classList.add('d-none'));
    });

    const originalLimpiar = window.limpiarFormulario;
    window.limpiarFormulario = function() {
        if (typeof originalLimpiar === 'function') originalLimpiar();
        resetearColaArchivos();
        limpiarCheckboxesDocumentos();
        alternarPanelDoc('panelSubirDocs', false);
        alternarPanelDoc('panelVincularDocs', true);
    };

    const originalEditar = window.editarRegistro;
    window.editarRegistro = function(btn) {
        if (typeof originalEditar === 'function') originalEditar(btn);
        resetearColaArchivos();
        cargarCheckboxesDocumentos(btn);
        alternarPanelDoc('panelSubirDocs', false);
        alternarPanelDoc('panelVincularDocs', true);
    };

    const urlParams = new URLSearchParams(window.location.search);
    const autoEditId = urlParams.get('auto_edit');
    if (autoEditId) {
        let targetBtn = null;
        for (let btn of document.querySelectorAll('button[onclick="editarRegistro(this)"]')) {
            try {
                if (JSON.parse(btn.getAttribute('data-empleado')).id == autoEditId) { targetBtn = btn; break; }
            } catch (e) {}
        }
        if (targetBtn) {
            window.editarRegistro(targetBtn);
            setTimeout(() => { bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRegistro')).show(); }, 300);
        }
        window.history.replaceState(null, '', window.location.pathname + '?tipo=' + urlParams.get('tipo'));
    }
});

let persPaginaActual = 1;
function renderizarPaginacionPersonal() {
    const porPagina = parseInt(document.getElementById('persPorPagina')?.value || '15', 10);
    const filas = Array.from(document.querySelectorAll('#tablaPersonal tbody tr.fila-personal'));
    const total = filas.length;
    const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
    if (persPaginaActual > totalPaginas) persPaginaActual = totalPaginas;

    const inicio = (persPaginaActual - 1) * porPagina;
    const fin = inicio + porPagina;

    filas.forEach((tr, idx) => { tr.style.display = (idx >= inicio && idx < fin) ? '' : 'none'; });

    const info = document.getElementById('persInfoPaginacion');
    if (info) info.innerText = total === 0 ? 'Sin registros' : `Mostrando ${inicio + 1}–${Math.min(fin, total)} de ${total}`;

    const ul = document.getElementById('persPaginacionUl');
    if (!ul) return;
    ul.innerHTML = '';
    if (totalPaginas <= 1) return;

    const crearBtn = (txt, pag, dis, act) => {
        const li = document.createElement('li');
        li.className = `page-item ${dis ? 'disabled' : ''} ${act ? 'active' : ''}`;
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'page-link'; b.innerHTML = txt;
        if (!dis && !act) b.onclick = () => { persPaginaActual = pag; renderizarPaginacionPersonal(); };
        li.appendChild(b); ul.appendChild(li);
    };
    crearBtn('&laquo;', persPaginaActual - 1, persPaginaActual === 1, false);
    for (let p = 1; p <= totalPaginas; p++) {
        if (p === 1 || p === totalPaginas || Math.abs(p - persPaginaActual) <= 1) crearBtn(p, p, false, p === persPaginaActual);
        else if (Math.abs(p - persPaginaActual) === 2) crearBtn('...', p, true, false);
    }
    crearBtn('&raquo;', persPaginaActual + 1, persPaginaActual === totalPaginas, false);
}
document.getElementById('persPorPagina')?.addEventListener('change', () => { persPaginaActual = 1; renderizarPaginacionPersonal(); });
document.addEventListener('DOMContentLoaded', () => { renderizarPaginacionPersonal(); });
</script>

<?php include '../Includes/footer.php'; ?>