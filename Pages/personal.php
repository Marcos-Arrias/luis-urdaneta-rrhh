<?php
require_once '../PHP/connection.php';

$tipo_id = isset($_GET['tipo']) ? intval($_GET['tipo']) : 1;
$filtro_cargo = isset($_GET['cargo']) ? intval($_GET['cargo']) : null;

$titulos = [
    1 => ['titulo' => 'Personal Docente', 'icono' => 'bi-book', 'btn' => 'Nuevo Docente', 'etiqueta' => 'Materia Asignada'],
    2 => ['titulo' => 'Personal Administrativo', 'icono' => 'bi-pc-display', 'btn' => 'Nuevo Admin', 'etiqueta' => 'Cargo Principal'],
    3 => ['titulo' => 'Personal Obrero', 'icono' => 'bi-tools', 'btn' => 'Nuevo Obrero', 'etiqueta' => 'Área de Mantenimiento']
];
$ui = $titulos[$tipo_id] ?? $titulos[1];

// La lógica POST de Crear/Editar/Eliminar se mantiene intacta aquí...
// (Imagina que aquí está tu bloque if ($_SERVER['REQUEST_METHOD'] === 'POST') idéntico al anterior para no alargar el código)

// ==========================================
// CONSULTA DINÁMICA (CON O SIN FILTRO DE CARGO)
// ==========================================
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
           pda.*
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

// Obtener nombre del cargo filtrado para el banner
$nombre_filtro = "";
if ($filtro_cargo) {
    $stmtF = $pdo->prepare("SELECT nombre FROM cat_cargos_areas WHERE id = ?");
    $stmtF->execute([$filtro_cargo]);
    $nombre_filtro = $stmtF->fetchColumn();
}

$lista_cargos = $pdo->prepare("SELECT id, nombre FROM cat_cargos_areas WHERE tipo_personal_id = ? ORDER BY nombre ASC");
$lista_cargos->execute([$tipo_id]);
$lista_cargos = $lista_cargos->fetchAll();

include '../Includes/header.php'; include '../Includes/sidebar.php';
?>

<style>
.popover-hover { position: relative; display: inline-block; }
.popover-content { display: none; position: absolute; z-index: 1060; left: 105%; top: -15px; width: 260px; background-color: var(--bs-body-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color); border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); padding: 12px; pointer-events: none; }
.popover-hover:hover .popover-content { display: block; animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
.avatar-thumb { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--bs-primary); transition: transform 0.2s; }
.popover-hover:hover .avatar-thumb { transform: scale(1.1); }
.badge-interactive:hover { background-color: var(--bs-primary) !important; color: white !important; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
.badge-interactive { transition: all 0.2s ease; cursor: pointer; display: inline-flex; align-items: center; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi <?= $ui['icono'] ?> me-2 text-primary"></i> <?= $ui['titulo'] ?></h2>
    <button class="btn btn-primary shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalRegistro" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-2"></i> <?= $ui['btn'] ?>
    </button>
</div>

<?php if ($filtro_cargo && $nombre_filtro): ?>
    <div class="alert alert-primary shadow-sm border-0 d-flex justify-content-between align-items-center rounded-4 mb-4">
        <div><i class="bi bi-funnel-fill me-2 fs-5"></i> Mostrando personal asignado a: <strong><?= htmlspecialchars($nombre_filtro) ?></strong></div>
        <a href="personal.php?tipo=<?= $tipo_id ?>" class="btn btn-sm btn-outline-primary rounded-pill">Ver todos</a>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="table-responsive" style="overflow: visible;">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light text-secondary">
                <tr>
                    <th class="ps-4">Cédula</th>
                    <th>Apellidos y Nombres</th>
                    <th><?= $ui['etiqueta'] ?></th>
                    <th>Contacto</th>
                    <th class="text-center">F. Ingreso</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($personal) > 0): ?>
                    <?php foreach ($personal as $d): ?>
                    <tr style="transition: background 0.2s;">
                        <td class="ps-4">
                            <?php if (!empty($d['ruta_foto_cedula'])): ?>
                                <div class="popover-hover">
                                    <span class="fw-bold text-primary" style="cursor: pointer;"><i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($d['cedula']) ?></span>
                                    <div class="popover-content">
                                        <img src="<?= $d['ruta_foto_cedula'] ?>" class="img-fluid rounded border shadow-sm" alt="Cédula">
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="fw-bold text-secondary"><?= htmlspecialchars($d['cedula']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="popover-hover d-flex align-items-center">
                                <?php if (!empty($d['ruta_foto_carnet'])): ?>
                                    <img src="<?= $d['ruta_foto_carnet'] ?>" class="avatar-thumb me-3 shadow-sm" alt="Foto">
                                <?php else: ?>
                                    <div class="avatar-thumb me-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm">
                                        <?= strtoupper(substr($d['nombres'], 0, 1) . substr($d['apellidos'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="fw-bold"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></span>
                                <div class="popover-content text-center">
                                    <?php if (!empty($d['ruta_foto_carnet'])): ?><img src="<?= $d['ruta_foto_carnet'] ?>" class="rounded-circle mb-2 shadow" style="width:90px; height:90px; object-fit:cover;"><?php endif; ?>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($d['nombres']) ?></h6>
                                    <span class="badge bg-primary-subtle text-primary mt-2"><?= $d['edad'] ? $d['edad'].' Años' : 'Edad S/R' ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <!-- BOTÓN INTERACTIVO PARA FILTRAR POR MATERIA -->
                            <div class="popover-hover">
                                <a href="personal.php?tipo=<?= $tipo_id ?>&cargo=<?= $d['cargo_area_id'] ?>" class="badge bg-body-secondary text-body border badge-interactive text-decoration-none px-3 py-2 rounded-pill">
                                    <?= htmlspecialchars($d['cargo_nombre']) ?> <i class="bi bi-search ms-1 opacity-50"></i>
                                </a>
                                <?php if (!empty($d['cargo_descripcion'])): ?>
                                <div class="popover-content">
                                    <strong class="text-primary small d-block mb-1"><i class="bi bi-info-circle"></i> Descripción:</strong>
                                    <span class="small text-muted"><?= htmlspecialchars($d['cargo_descripcion']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($d['telefono'])): ?>
                                <div class="popover-hover">
                                    <?= formatearTelefono($d['telefono']) ?>
                                    <div class="popover-content p-2" style="width: 200px;">
                                        <?php $cleanTel = preg_replace('/[^0-9]/', '', $d['telefono']); ?>
                                        <a href="https://wa.me/<?= $cleanTel ?>" target="_blank" class="btn btn-sm btn-success w-100 rounded-pill shadow-sm"><i class="bi bi-whatsapp me-1"></i> Enviar Mensaje</a>
                                    </div>
                                </div>
                            <?php else: ?><span class="text-muted small">S/R</span><?php endif; ?>
                        </td>

                        <td class="text-center text-muted small"><?= date('d/m/y', strtotime($d['fecha_ingreso'])) ?></td>
                        <td class="text-center">
                            <?php 
                            $bg = 'success'; 
                            if($d['estado_laboral'] == 'Reposo') $bg = 'warning text-dark'; 
                            if($d['estado_laboral'] == 'Permiso') $bg = 'info text-dark'; 
                            if($d['estado_laboral'] == 'Inactivo') $bg = 'danger'; 
                            ?>
                            <span class="badge bg-<?= $bg ?>-subtle text-<?= str_replace(' text-dark','',$bg) ?> border px-2 py-1 rounded-pill"><?= $d['estado_laboral'] ?></span>
                        </td>
                        
                        <td class="text-center text-nowrap">
                            <button class="btn btn-sm btn-light text-primary shadow-sm rounded-circle me-1" onclick="verDetallesExtra(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="offcanvas" data-bs-target="#panelDatosExtra" title="Expediente"><i class="bi bi-folder2-open" style="pointer-events:none;"></i></button>
                            <button class="btn btn-sm btn-light text-warning shadow-sm rounded-circle me-1" onclick="editarRegistro(this)" data-empleado="<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>" data-bs-toggle="modal" data-bs-target="#modalRegistro"><i class="bi bi-pencil" style="pointer-events:none;"></i></button>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-light text-danger shadow-sm rounded-circle"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><img src="https://cdn-icons-png.flaticon.com/512/7486/7486754.png" width="80" class="opacity-25 mb-3 d-block mx-auto"><h5 class="fw-bold">Sin resultados</h5><p>No se encontró personal asignado a esta categoría.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


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

<?php include '../Includes/footer.php'; ?>