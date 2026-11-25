<?php
require_once '../PHP/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

$usuario_actual_id = $_SESSION['admin_id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS cat_tipos_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave CHAR(3) NOT NULL UNIQUE,
    nombre VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_documentos")->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO cat_tipos_documentos (id, clave, nombre) VALUES
        (1, 'CED', 'Cédula de Identidad'),
        (2, 'FOT', 'Fotografía Tamaño Carnet'),
        (3, 'TIT', 'Título Universitario o Certificado'),
        (4, 'MED', 'Informe o Certificado Médico'),
        (5, 'SOP', 'Soporte de Justificación / Reposo'),
        (6, 'MEM', 'Memorándum o Acta Institucional'),
        (7, 'OTR', 'Otro Documento')");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento_id INT NOT NULL,
    codigo_archivo VARCHAR(100) NOT NULL UNIQUE,
    nombre_original VARCHAR(200) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamano_bytes BIGINT DEFAULT 0,
    hash_verificacion VARCHAR(64) DEFAULT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_documento_id) REFERENCES cat_tipos_documentos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS documentos_enlaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    tabla_destino ENUM('personal','asistencias','cronograma_eventos','institucional') NOT NULL,
    registro_id INT NOT NULL,
    notas VARCHAR(255) DEFAULT NULL,
    KEY idx_busqueda_enlace (tabla_destino, registro_id),
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$puedeLeer     = tienePermisoReBAC($pdo, $usuario_actual_id, 'documentos', 'leer');
$puedeCrear    = tienePermisoReBAC($pdo, $usuario_actual_id, 'documentos', 'crear');
$puedeEditar   = tienePermisoReBAC($pdo, $usuario_actual_id, 'documentos', 'editar');
$puedeEliminar = tienePermisoReBAC($pdo, $usuario_actual_id, 'documentos', 'eliminar');

if (!$puedeLeer) {
    include '../Includes/header.php'; include '../Includes/sidebar.php';
    echo '<div class="alert alert-danger shadow-sm p-4 text-center rounded-4 mt-5">
            <i class="bi bi-shield-lock-fill fs-1 d-block mb-2"></i>
            <h4>Acceso Restringido</h4>
            <p class="mb-0">Tu cuenta no tiene permisos habilitados para ver la sección de documentos.</p>
          </div>';
    include '../Includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'subir_documento') {
            if (!$puedeCrear) die("Sin permiso para subir documentos.");
            
            if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                header("Location: documentos.php?msg=err_archivo"); exit;
            }

            $tipo_id = intval($_POST['tipo_documento_id']);
            $tabla_destino = $_POST['tabla_destino'] ?? 'institucional';
            $registro_id = intval($_POST['registro_id'] ?? 0);
            $notas = trim($_POST['notas'] ?? '');

            if ($tabla_destino !== 'institucional' && $registro_id <= 0) {
                header("Location: documentos.php?msg=err_destino"); exit;
            }

            $nombreOrig = $_FILES['archivo']['name'];
            $ext = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            if (!in_array($ext, $permitidos)) {
                header("Location: documentos.php?msg=err_ext"); exit;
            }

            $stmtT = $pdo->prepare("SELECT clave FROM cat_tipos_documentos WHERE id = ?");
            $stmtT->execute([$tipo_id]);
            $clave = $stmtT->fetchColumn() ?: 'OTR';

            $dir = '../documentos/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);

            $codigo = sprintf("DOC_%s_%s_%03d.%s", $clave, date('Ymd_His'), rand(100, 999), $ext);
            $rutaFinal = $dir . $codigo;

            $tamano = $_FILES['archivo']['size'];
            $mime = $_FILES['archivo']['type'] ?: mime_content_type($_FILES['archivo']['tmp_name']);
            $hash = hash_file('sha256', $_FILES['archivo']['tmp_name']);

            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFinal)) {
                $pdo->beginTransaction();
                $stmtDoc = $pdo->prepare("INSERT INTO documentos (tipo_documento_id, codigo_archivo, nombre_original, ruta_archivo, mime_type, tamano_bytes, hash_verificacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtDoc->execute([$tipo_id, $codigo, $nombreOrig, $rutaFinal, $mime, $tamano, $hash]);
                $docId = $pdo->lastInsertId();

                if ($tabla_destino === 'institucional') $registro_id = 0;
                $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, ?, ?, ?)")
                    ->execute([$docId, $tabla_destino, $registro_id, $notas]);

                registrarHistorial($pdo, 'Documentos', 'CREAR', "Subió el archivo $nombreOrig ($codigo).", $docId);
                $pdo->commit();
                header("Location: documentos.php?msg=subido"); exit;
            }
        }
        elseif ($accion === 'vincular_extra') {
            if (!$puedeEditar) die("Sin permiso para asociar documentos.");
            
            $docId = intval($_POST['documento_id']);
            $tabla_destino = $_POST['tabla_destino'];
            $registro_id = ($tabla_destino === 'institucional') ? 0 : intval($_POST['registro_id'] ?? 0);
            $notas = trim($_POST['notas'] ?? '');

            if ($tabla_destino !== 'institucional' && $registro_id <= 0) {
                header("Location: documentos.php?msg=err_destino"); exit;
            }

            $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, ?, ?, ?)")
                ->execute([$docId, $tabla_destino, $registro_id, $notas]);

            registrarHistorial($pdo, 'Documentos', 'ACTUALIZAR', "Asoció el documento #$docId con otro registro.", $docId);
            header("Location: documentos.php?msg=vinculado"); exit;
        }
        elseif ($accion === 'eliminar_documento') {
            if (!$puedeEliminar) die("Sin permiso para eliminar documentos.");
            
            $docId = intval($_POST['id']);
            $stmtD = $pdo->prepare("SELECT codigo_archivo, ruta_archivo FROM documentos WHERE id = ?");
            $stmtD->execute([$docId]);
            $doc = $stmtD->fetch();

            if ($doc) {
                if (file_exists($doc['ruta_archivo'])) @unlink($doc['ruta_archivo']);
                $rutaThumb = dirname($doc['ruta_archivo']) . '/thumbs/thumb_' . pathinfo($doc['ruta_archivo'], PATHINFO_FILENAME) . '.jpg';
if (file_exists($rutaThumb)) @unlink($rutaThumb);
                $pdo->prepare("DELETE FROM documentos WHERE id = ?")->execute([$docId]);
                registrarHistorial($pdo, 'Documentos', 'ELIMINAR', "Eliminó el documento {$doc['codigo_archivo']}.", $docId);
            }
            header("Location: documentos.php?msg=eliminado"); exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error procesando el documento: " . $e->getMessage());
    }
}

$documentos = $pdo->query("
    SELECT d.*, td.clave, td.nombre as tipo_nombre
    FROM documentos d
    JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
    ORDER BY d.fecha_subida DESC
")->fetchAll();

$enlacesRaw = $pdo->query("
    SELECT de.*, 
           CONCAT(p.apellidos, ', ', p.nombres, ' (V-', p.cedula, ')') as nombre_personal,
           CONCAT(ce.titulo, ' (', DATE_FORMAT(ce.fecha, '%d/%m/%Y'), ')') as nombre_evento
    FROM documentos_enlaces de
    LEFT JOIN personal p ON de.tabla_destino = 'personal' AND de.registro_id = p.id
    LEFT JOIN cronograma_eventos ce ON de.tabla_destino = 'cronograma_eventos' AND de.registro_id = ce.id
")->fetchAll();

$enlacesPorDoc = [];
foreach ($enlacesRaw as $e) {
    $enlacesPorDoc[$e['documento_id']][] = $e;
}

$totalDocs = count($documentos);
$totalBytes = 0;
$archivosDisponibles = 0;
foreach ($documentos as $idx => $doc) {
    $totalBytes += (int)$doc['tamano_bytes'];
    $archivoIntegro = file_exists($doc['ruta_archivo']) && (empty($doc['hash_verificacion']) || hash_file('sha256', $doc['ruta_archivo']) === $doc['hash_verificacion']);
    $documentos[$idx]['integro'] = $archivoIntegro;
    if ($archivoIntegro) $archivosDisponibles++;
}
$tamanoMB = number_format($totalBytes / 1048576, 2);

$tiposDoc = $pdo->query("SELECT * FROM cat_tipos_documentos ORDER BY clave ASC")->fetchAll();
$listaEventos = $pdo->query("SELECT id, titulo, fecha FROM cronograma_eventos ORDER BY fecha DESC LIMIT 50")->fetchAll();

include '../Includes/header.php';
include '../Includes/sidebar.php';
?>

<style>
.file-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 10px; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i> Archivo Digital</h2>
        <p class="text-muted small mb-0">Carpeta centralizada para guardar y organizar los documentos de la institución y del personal.</p>
    </div>
    <?php if ($puedeCrear): ?>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalSubirDoc">
        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Subir Documento
    </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php 
    $msgs = [
        'subido'      => ['success', 'El documento se guardó correctamente.'],
        'vinculado'   => ['info', 'El documento fue asociado correctamente.'],
        'eliminado'   => ['danger', 'El documento fue eliminado.'],
        'err_archivo' => ['warning', 'Debe seleccionar un archivo válido.'],
        'err_ext'     => ['danger', 'Ese tipo de archivo no está permitido.'],
        'err_destino' => ['warning', 'Debes seleccionar a una persona o evento de la lista.']
    ];
    if (isset($msgs[$_GET['msg']])): $m = $msgs[$_GET['msg']]; ?>
        <div class="alert alert-<?= $m[0] ?> alert-dismissible fade show shadow-sm py-2">
            <i class="bi bi-info-circle-fill me-2"></i> <?= $m[1] ?>
            <button type="button" class="btn-close pb-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- RESUMEN -->
<div class="row mb-4">
    <div class="col-md-4 mb-2">
        <div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><span class="text-muted small fw-bold text-uppercase">Total Documentos</span><h2 class="mb-0 fw-black mt-1"><?= $totalDocs ?></h2></div>
                <div class="icon-circle bg-primary-subtle text-primary"><i class="bi bi-files fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><span class="text-muted small fw-bold text-uppercase">Espacio Utilizado</span><h2 class="mb-0 fw-black mt-1"><?= $tamanoMB ?> <small class="fs-6">MB</small></h2></div>
                <div class="icon-circle bg-info-subtle text-info"><i class="bi bi-hdd-fill fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div><span class="text-muted small fw-bold text-uppercase">Archivos en Buen Estado</span><h2 class="mb-0 fw-black mt-1"><?= $archivosDisponibles ?> / <?= $totalDocs ?></h2></div>
                <div class="icon-circle bg-success-subtle text-success"><i class="bi bi-patch-check-fill fs-4"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- BUSCADOR Y FILTROS -->
<div class="card shadow-sm border-0 mb-4 bg-body-tertiary">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-body border-0"><i class="bi bi-search text-primary"></i></span>
                    <input type="text" id="buscadorDocs" class="form-control border-0 shadow-none" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar por nombre, código o persona..." autofocus>
                </div>
            </div>
            <div class="col-md-3">
                <select id="filtroTipoDoc" class="form-select border-0 shadow-sm">
                    <option value="todos">Todos los Tipos</option>
                    <?php foreach ($tiposDoc as $t): ?>
                        <option value="<?= $t['clave'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select id="filtroEntidadDoc" class="form-select border-0 shadow-sm">
                    <option value="todos">Todos los Documentos</option>
                    <option value="institucional">Documentos Generales de la Institución</option>
                    <option value="personal">Documentos de Trabajadores</option>
                    <option value="cronograma_eventos">Documentos de Actividades / Eventos</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- TABLA PRINCIPAL -->
<div class="card shadow-sm border-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" id="tablaDocumentos">
            <thead class="table-light text-secondary">
                <tr>
                    <th class="ps-4">Nombre del Archivo</th>
                    <th>Tipo</th>
                    <th>Asociado a</th>
                    <th class="text-center">Tamaño y Estado</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($documentos) > 0): ?>
                    <?php foreach ($documentos as $d): 
                        $esImagen = strpos($d['mime_type'], 'image/') === 0;
                        $esPdf = $d['mime_type'] === 'application/pdf';
                        $vinculos = $enlacesPorDoc[$d['id']] ?? [];
                        $entidadesLista = implode(',', array_unique(array_column($vinculos, 'tabla_destino')));
                    ?>
                    <tr class="fila-doc" data-tipo="<?= $d['clave'] ?>" data-entidad="<?= $entidadesLista ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <?php if ($esImagen && file_exists($d['ruta_archivo'])): ?>
                                    <img src="<?= htmlspecialchars(obtenerRutaMiniatura($d['ruta_archivo'])) ?>" class="file-thumb me-3 border shadow-sm" loading="lazy" alt="Miniatura">
                                <?php elseif ($esPdf): ?>
                                    <div class="file-thumb me-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center fs-4 border"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                <?php else: ?>
                                    <div class="file-thumb me-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-4 border"><i class="bi bi-file-earmark-text-fill"></i></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-body"><?= htmlspecialchars($d['nombre_original']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($d['codigo_archivo']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-body border px-2 py-1">
                                <?= htmlspecialchars($d['tipo_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php if (count($vinculos) > 0): ?>
                                    <?php foreach ($vinculos as $v): ?>
                                        <?php if ($v['tabla_destino'] === 'institucional'): ?>
                                            <span class="badge bg-body-secondary text-body border rounded-pill"><i class="bi bi-building me-1"></i> Documento General</span>
                                        <?php elseif ($v['tabla_destino'] === 'personal'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"><i class="bi bi-person-fill me-1"></i> <?= htmlspecialchars($v['nombre_personal'] ?? 'Trabajador #' . $v['registro_id']) ?></span>
                                        <?php elseif ($v['tabla_destino'] === 'cronograma_eventos'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border rounded-pill"><i class="bi bi-calendar-event me-1"></i> <?= htmlspecialchars($v['nombre_evento'] ?? 'Evento #' . $v['registro_id']) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-body-secondary text-muted border">Sin asignar</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center small">
                            <div class="fw-bold"><?= number_format($d['tamano_bytes'] / 1024, 1) ?> KB</div>
                            <?php if ($d['integro']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle me-1"></i> Verificado
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border">
                                    <i class="bi bi-exclamation-triangle me-1"></i> No encontrado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-muted small">
                            <?= date('d/m/Y', strtotime($d['fecha_subida'])) ?><br>
                            <small><?= date('h:i A', strtotime($d['fecha_subida'])) ?></small>
                        </td>
                        <td class="text-center text-nowrap">
                            <?php if ($esImagen || $esPdf): ?>
                                <button class="btn btn-sm btn-icon-theme text-info rounded-circle me-1" onclick="previsualizarArchivo('<?= $d['ruta_archivo'] ?>', '<?= htmlspecialchars($d['nombre_original'], ENT_QUOTES) ?>', '<?= $d['mime_type'] ?>')" title="Ver Documento">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            <?php endif; ?>
                            
                            <a href="<?= $d['ruta_archivo'] ?>" download="<?= htmlspecialchars($d['nombre_original']) ?>" class="btn btn-sm btn-icon-theme text-primary rounded-circle me-1" title="Descargar">
                                <i class="bi bi-download"></i>
                            </a>

                            <?php if ($puedeEditar): ?>
                                <button class="btn btn-sm btn-icon-theme text-success rounded-circle me-1" onclick="abrirModalVincular(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nombre_original'], ENT_QUOTES) ?>')" title="Asociar a otra persona o evento">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($puedeEliminar): ?>
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este archivo permanentemente?');">
                                    <input type="hidden" name="accion" value="eliminar_documento">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button class="btn btn-sm btn-icon-theme text-danger rounded-circle" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-folder-x fs-1 opacity-25 d-block mb-3"></i><h5>No hay archivos guardados</h5><p>Haz clic en <strong>Subir Documento</strong> para añadir el primero.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-body border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
     <div class="d-flex align-items-center gap-2 small text-muted">
         <span>Mostrar</span>
         <select id="docsPorPagina" class="form-select form-select-sm" style="width: auto;">
             <option value="10" selected>10</option>
             <option value="25">25</option>
             <option value="50">50</option>
             <option value="999999">Todos</option>
         </select>
         <span id="docsInfoPaginacion">Mostrando 0 de 0</span>
     </div>
     <nav aria-label="Paginación de documentos">
         <ul class="pagination pagination-sm mb-0" id="docsPaginacionUl"></ul>
     </nav>
 </div>
</div>

<!-- MODAL 1: SUBIR NUEVO DOCUMENTO -->
<div class="modal fade" id="modalSubirDoc" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg w-100" id="formSubirDoc">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload me-2"></i>Subir Nuevo Documento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" name="accion" value="subir_documento">

                <div class="mb-3">
                    <label class="form-label small fw-bold">1. Seleccionar Archivo (PDF, Imagen, Word o Excel)</label>
                    <input type="file" name="archivo" class="form-control shadow-sm" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">2. Tipo de Documento</label>
                    <select name="tipo_documento_id" class="form-select shadow-sm" required>
                        <?php foreach ($tiposDoc as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">3. ¿A quién pertenece este archivo?</label>
                    <select name="tabla_destino" id="subir_destino" class="form-select shadow-sm" onchange="toggleDestinoSubir()" required>
                        <option value="institucional">Documento General de la Institución</option>
                        <option value="personal">A un Trabajador</option>
                        <option value="cronograma_eventos">A una Actividad o Evento</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="div_subir_personal">
                    <label class="form-label small fw-bold text-primary">Buscar Trabajador:</label>
                    <?php renderBuscadorTrabajador('sel_subir_personal', 'registro_id', 'Cédula, nombre, apellido o cargo...'); ?>
                </div>

                <div class="mb-3 d-none" id="div_subir_evento">
                    <label class="form-label small fw-bold text-warning-emphasis">Seleccionar Actividad:</label>
                    <select name="registro_id" id="sel_subir_evento" class="form-select border-warning" disabled>
                        <?php foreach ($listaEventos as $ev): ?>
                            <option value="<?= $ev['id'] ?>"><?= date('d/m/Y', strtotime($ev['fecha'])) ?> - <?= htmlspecialchars($ev['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold">Observaciones (Opcional)</label>
                    <input type="text" name="notas" class="form-control form-control-sm" placeholder="Ej: Acta firmada por dirección...">
                </div>
            </div>
            <div class="modal-footer border-0 bg-body">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Guardar Documento</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: ASOCIAR DOCUMENTO EXISTENTE -->
<div class="modal fade" id="modalVincularExtra" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow-lg w-100" id="formVincularExtra">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-link-45deg me-1"></i>Asociar Documento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-body-tertiary">
                <input type="hidden" name="accion" value="vincular_extra">
                <input type="hidden" name="documento_id" id="vinc_doc_id">
                <p class="small fw-bold text-center text-success mb-3 text-truncate" id="vinc_doc_nombre"></p>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Asociar con:</label>
                    <select name="tabla_destino" id="vinc_destino" class="form-select shadow-sm" onchange="toggleDestinoVincular()">
                        <option value="personal">Un Trabajador</option>
                        <option value="cronograma_eventos">Una Actividad o Evento</option>
                    </select>
                </div>

                <div class="mb-3" id="div_vinc_personal">
                    <label class="form-label small fw-bold text-primary">Buscar Trabajador:</label>
                    <?php renderBuscadorTrabajador('sel_vinc_personal', 'registro_id', 'Cédula, nombre, apellido o cargo...'); ?>
                </div>

                <div class="mb-3 d-none" id="div_vinc_evento">
                    <label class="form-label small fw-bold text-warning-emphasis">Actividad:</label>
                    <select name="registro_id" id="sel_vinc_evento" class="form-select border-warning" disabled>
                        <?php foreach ($listaEventos as $ev): ?>
                            <option value="<?= $ev['id'] ?>"><?= date('d/m/Y', strtotime($ev['fecha'])) ?> - <?= htmlspecialchars($ev['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 bg-body">
                <button type="submit" class="btn btn-success w-100 fw-bold">Guardar Asociación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: VISOR DE DOCUMENTOS -->
<div class="modal fade" id="modalPrevisualizar" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg w-100">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="prevTitulo">Vista Previa</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-body-tertiary text-center" id="prevContenedor" style="min-height: 500px; display: flex; align-items: center; justify-content: center;">
            </div>
        </div>
    </div>
</div>

<script>
function toggleDestinoSubir() {
    let val = document.getElementById('subir_destino').value;
    let divP = document.getElementById('div_subir_personal');
    let selP = document.getElementById('sel_subir_personal');
    let divE = document.getElementById('div_subir_evento');
    let selE = document.getElementById('sel_subir_evento');

    divP.classList.add('d-none'); if (selP) selP.disabled = true;
    divE.classList.add('d-none'); if (selE) selE.disabled = true;

    if (val === 'personal') { divP.classList.remove('d-none'); if (selP) selP.disabled = false; }
    if (val === 'cronograma_eventos') { divE.classList.remove('d-none'); if (selE) selE.disabled = false; }
}

function toggleDestinoVincular() {
    let val = document.getElementById('vinc_destino').value;
    let divP = document.getElementById('div_vinc_personal');
    let selP = document.getElementById('sel_vinc_personal');
    let divE = document.getElementById('div_vinc_evento');
    let selE = document.getElementById('sel_vinc_evento');

    divP.classList.add('d-none'); if (selP) selP.disabled = true;
    divE.classList.add('d-none'); if (selE) selE.disabled = true;

    if (val === 'personal') { divP.classList.remove('d-none'); if (selP) selP.disabled = false; }
    if (val === 'cronograma_eventos') { divE.classList.remove('d-none'); if (selE) selE.disabled = false; }
}

document.getElementById('formSubirDoc')?.addEventListener('submit', function(e) {
    if (document.getElementById('subir_destino').value === 'personal' && !document.getElementById('sel_subir_personal').value) {
        e.preventDefault();
        alert('Por favor busca y selecciona un trabajador de la lista.');
    }
});
document.getElementById('formVincularExtra')?.addEventListener('submit', function(e) {
    if (document.getElementById('vinc_destino').value === 'personal' && !document.getElementById('sel_vinc_personal').value) {
        e.preventDefault();
        alert('Por favor busca y selecciona un trabajador de la lista.');
    }
});

function abrirModalVincular(docId, docNombre) {
    document.getElementById('vinc_doc_id').value = docId;
    document.getElementById('vinc_doc_nombre').innerText = docNombre;
    toggleDestinoVincular();
    new bootstrap.Modal(document.getElementById('modalVincularExtra')).show();
}

function previsualizarArchivo(ruta, titulo, mime) {
    document.getElementById('prevTitulo').innerText = titulo;
    let cont = document.getElementById('prevContenedor');
    if (mime.startsWith('image/')) {
        cont.innerHTML = `<img src="${ruta}" class="img-fluid rounded shadow" style="max-height: 80vh;">`;
    } else if (mime === 'application/pdf') {
        cont.innerHTML = `<iframe src="${ruta}" style="width: 100%; height: 80vh; border: none;"></iframe>`;
    }
    new bootstrap.Modal(document.getElementById('modalPrevisualizar')).show();
}
let docsPaginaActual = 1;

function aplicarFiltrosDocs() {
    let txt = document.getElementById('buscadorDocs').value.toLowerCase();
    let tipo = document.getElementById('filtroTipoDoc').value;
    let ent = document.getElementById('filtroEntidadDoc').value;

    document.querySelectorAll('#tablaDocumentos tbody tr.fila-doc').forEach(tr => {
        let coincideTxt = tr.innerText.toLowerCase().includes(txt);
        let coincideTipo = (tipo === 'todos' || tr.getAttribute('data-tipo') === tipo);
        let coincideEnt = (ent === 'todos' || tr.getAttribute('data-entidad').split(',').includes(ent));
        tr.dataset.match = (coincideTxt && coincideTipo && coincideEnt) ? '1' : '0';
    });

    docsPaginaActual = 1;
    renderizarPaginacionDocs();
}

function renderizarPaginacionDocs() {
    const porPagina = parseInt(document.getElementById('docsPorPagina')?.value || '10', 10);
    const todas = Array.from(document.querySelectorAll('#tablaDocumentos tbody tr.fila-doc'));
    const coincidentes = todas.filter(tr => tr.dataset.match !== '0');
    const total = coincidentes.length;
    const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
    if (docsPaginaActual > totalPaginas) docsPaginaActual = totalPaginas;

    const inicio = (docsPaginaActual - 1) * porPagina;
    const fin = inicio + porPagina;

    todas.forEach(tr => tr.style.display = 'none');
    coincidentes.forEach((tr, idx) => {
        tr.style.display = (idx >= inicio && idx < fin) ? '' : 'none';
    });

    const info = document.getElementById('docsInfoPaginacion');
    if (info) {
        info.innerText = total === 0
            ? 'Sin documentos que coincidan'
            : `Mostrando ${inicio + 1}–${Math.min(fin, total)} de ${total}`;
    }

    const ul = document.getElementById('docsPaginacionUl');
    if (!ul) return;
    ul.innerHTML = '';
    if (totalPaginas <= 1) return;

    const crearBtn = (texto, pag, deshabilitado, activo) => {
        const li = document.createElement('li');
        li.className = `page-item ${deshabilitado ? 'disabled' : ''} ${activo ? 'active' : ''}`;
        const a = document.createElement('button');
        a.type = 'button';
        a.className = 'page-link';
        a.innerHTML = texto;
        if (!deshabilitado && !activo) {
            a.onclick = () => { docsPaginaActual = pag; renderizarPaginacionDocs(); };
        }
        li.appendChild(a);
        ul.appendChild(li);
    };

    crearBtn('&laquo;', docsPaginaActual - 1, docsPaginaActual === 1, false);
    for (let p = 1; p <= totalPaginas; p++) {
        if (p === 1 || p === totalPaginas || Math.abs(p - docsPaginaActual) <= 1) {
            crearBtn(p, p, false, p === docsPaginaActual);
        } else if (Math.abs(p - docsPaginaActual) === 2) {
            crearBtn('...', p, true, false);
        }
    }
    crearBtn('&raquo;', docsPaginaActual + 1, docsPaginaActual === totalPaginas, false);
}

document.getElementById('docsPorPagina')?.addEventListener('change', () => {
    docsPaginaActual = 1;
    renderizarPaginacionDocs();
});
document.addEventListener('DOMContentLoaded', () => { aplicarFiltrosDocs(); });

document.getElementById('buscadorDocs').addEventListener('input', aplicarFiltrosDocs);
document.getElementById('filtroTipoDoc').addEventListener('change', aplicarFiltrosDocs);
document.getElementById('filtroEntidadDoc').addEventListener('change', aplicarFiltrosDocs);

document.addEventListener('DOMContentLoaded', () => {
    toggleDestinoSubir();
    toggleDestinoVincular();
    if (document.getElementById('buscadorDocs').value.trim() !== '') aplicarFiltrosDocs();
});
</script>

<?php include '../Includes/footer.php'; ?>