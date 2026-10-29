<?php
require_once '../PHP/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_cargo') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo_id = intval($_POST['tipo_personal_id'] ?? 0);
        $desc = trim($_POST['descripcion'] ?? 'Cátedra / Función institucional.');

        if (!empty($nombre) && $tipo_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES (?, ?, ?)");
            $stmt->execute([$tipo_id, $nombre, $desc]);
            registrarHistorial($pdo, 'Catálogos', 'CREAR', "Creó el cargo: $nombre");
            header("Location: configuracion.php?msg=cargo_creado#cargos"); exit;
        }
    }

    if ($_POST['accion'] === 'eliminar_cargo') {
        try {
            $pdo->prepare("DELETE FROM cat_cargos_areas WHERE id=?")->execute([$_POST['id']]);
            registrarHistorial($pdo, 'Catálogos', 'ELIMINAR', 'Se eliminó un cargo del catálogo.');
            header("Location: configuracion.php?msg=cargo_eliminado#cargos"); exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { header("Location: configuracion.php?msg=cargo_en_uso#cargos"); exit; }
            else { die("Error: " . $e->getMessage()); }
        }
    }
}

$cargos = $pdo->query("
    SELECT c.*, t.nombre as tipo,
           (SELECT COUNT(*) FROM personal_cargos pc WHERE pc.cargo_area_id = c.id AND pc.estado_cargo = 'Vigente') as total_asignados
    FROM cat_cargos_areas c 
    JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id 
    ORDER BY t.id ASC, c.nombre ASC
")->fetchAll();

$tipos_personal = $pdo->query("SELECT * FROM cat_tipos_personal ORDER BY id ASC")->fetchAll();
$personal_lista = $pdo->query("SELECT id, cedula, CONCAT(apellidos, ', ', nombres) as nombre FROM personal WHERE estado_laboral='Activo' ORDER BY apellidos ASC")->fetchAll();

include '../Includes/header.php'; 
include '../Includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear-fill me-2 text-primary"></i> Ajustes y Mantenimiento</h2>
</div>

<!-- SISTEMA DE ALERTAS -->
<?php if (isset($_GET['msg'])): ?>
    <?php 
    $alertas = [
        'cargo_creado' => ['class' => 'alert-success', 'text' => 'Cargo registrado correctamente.'],
        'cargo_eliminado' => ['class' => 'alert-success', 'text' => 'Cargo eliminado con éxito.'],
        'cargo_en_uso' => ['class' => 'alert-danger', 'text' => 'No se puede eliminar: Personal asignado activo.'],
        'import_csv_ok' => ['class' => 'alert-success', 'text' => 'Nómina importada masivamente desde CSV.'],
        'import_doc_ok' => ['class' => 'alert-success', 'text' => 'Archivo adjuntado con código en clave al expediente.'],
        'import_json_ok' => ['class' => 'alert-success', 'text' => 'Base de datos restaurada completamente desde JSON.'],
        'import_err' => ['class' => 'alert-danger', 'text' => 'Error al procesar el archivo. Verifique la estructura del documento.']
    ];
    $m = $_GET['msg'];
    if(isset($alertas[$m])): ?>
        <div class="alert <?= $alertas[$m]['class'] ?> alert-dismissible fade show shadow-sm">
            <i class="bi bi-info-circle-fill me-2"></i> <?= $alertas[$m]['text'] ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<ul class="nav nav-pills mb-4 gap-2" id="ajustesTab" role="tablist">
    <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#apariencia"><i class="bi bi-palette me-1"></i> Apariencia</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#cargos"><i class="bi bi-tags me-1"></i> Cargos y Materias</button></li>
    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#exportar"><i class="bi bi-cloud-arrow-down me-1"></i> Exportar</button></li>
    <li class="nav-item"><button class="nav-link fw-bold text-success" data-bs-toggle="pill" data-bs-target="#importar"><i class="bi bi-cloud-arrow-up me-1"></i> Importar y Cargas</button></li>
</ul>

<div class="tab-content" id="ajustesTabContent">
    
<!-- PESTAÑA 1: APARIENCIA (SUPER PERSONALIZABLE) -->
    <div class="tab-pane fade show active" id="apariencia">
        
        <!-- FONDO DE PANTALLA -->
        <div class="card shadow-sm border-0 mb-4 border-start border-4 border-info bg-body-tertiary">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-image me-2 text-info"></i>Fondo de Pantalla (Wallpaper)</h5>
                <p class="small text-muted mb-3">Sube una imagen de tu computadora o pega un enlace para cambiar el fondo de todo el sistema.</p>
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <label class="small fw-bold text-muted mb-1"><i class="bi bi-upload me-1"></i>Subir desde el PC:</label>
                        <input type="file" id="bgImageInput" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-5">
                        <label class="small fw-bold text-muted mb-1"><i class="bi bi-link-45deg me-1"></i>O pegar Enlace (URL):</label>
                        <input type="url" id="bgUrlInput" class="form-control" placeholder="https://ejemplo.com/fondo.jpg">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="resetBgBtn" class="btn btn-outline-danger w-100 fw-bold"><i class="bi bi-trash"></i> Quitar Fondo</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- MODO OSCURO -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-dark">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-moon-stars-fill fs-1 mb-2 text-dark"></i>
                        <h5 class="fw-bold">Tema Visual</h5>
                        <div class="form-check form-switch d-flex justify-content-center mt-3 fs-5">
                            <input class="form-check-input me-2" type="checkbox" role="switch" id="switchModoOscuro" style="cursor:pointer;">
                            <label class="form-check-label" for="switchModoOscuro">Modo Oscuro</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLOR DEL MENÚ (CON SELECTOR LIBRE) -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-palette-fill fs-2 mb-1 text-primary"></i>
                        <h5 class="fw-bold mb-2">Color del Menú</h5>
                        <select id="selectMenuColor" class="form-select form-select-sm shadow-sm border-0 bg-body-tertiary fw-bold mb-2">
                            <option value="default">🔵 Azul Corporativo</option>
                            <option value="dark">⚫ Negro Carbón</option>
                            <option value="success">🟢 Verde Esmeralda</option>
                            <option value="danger">🔴 Rojo Carmesí</option>
                            <option value="custom">🎨 Color Personalizado...</option>
                        </select>
                        <div class="d-flex align-items-center justify-content-center">
                            <label class="small text-muted me-2 fw-bold">Selector Libre:</label>
                            <input type="color" id="customColorPicker" class="form-control form-control-color p-1 shadow-sm" style="height: 35px; width: 60px; cursor: pointer;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TIPOGRAFÍA -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-success">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-type fs-1 mb-2 text-success"></i>
                        <h5 class="fw-bold">Fuente del Sistema</h5>
                        <select id="selectFontFamily" class="form-select mt-3 shadow-sm border-0 bg-body-tertiary fw-bold">
                            <option value="default">Estándar (Segoe UI / Roboto)</option>
                            <option value="Georgia, serif">Clásica (Georgia)</option>
                            <option value="Tahoma, sans-serif">Compacta (Tahoma)</option>
                            <option value="'Courier New', Courier, monospace">Terminal (Courier Mono)</option>
                            <option value="'Comic Sans MS', cursive, sans-serif">Divertida (Comic Sans)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ESTILO DE TARJETAS -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-warning">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-card-heading fs-1 mb-2 text-warning"></i>
                        <h5 class="fw-bold">Estilo de Contenedores</h5>
                        <div class="btn-group w-100 mt-3 shadow-sm" role="group">
                            <input type="radio" class="btn-check" name="cardStyle" id="styleShadow" value="shadow" autocomplete="off" checked>
                            <label class="btn btn-outline-secondary fw-bold" for="styleShadow">Relieve 3D</label>

                            <input type="radio" class="btn-check" name="cardStyle" id="styleFlat" value="flat" autocomplete="off">
                            <label class="btn btn-outline-secondary fw-bold" for="styleFlat">Plano 2D</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DENSIDAD DE TABLAS -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-secondary">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-table fs-3 me-3 text-secondary"></i>
                            <div><h6 class="fw-bold mb-0">Densidad de Tablas</h6><small class="text-muted">Espacio entre filas.</small></div>
                        </div>
                        <input type="range" class="form-range" id="rangeTableDensity" min="1" max="3" step="1">
                        <div class="d-flex justify-content-between small fw-bold text-muted mt-1">
                            <span>Compacta</span><span>Normal</span><span>Relajada</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAMAÑO DE FUENTE -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 border-bottom border-4 border-danger">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-fonts fs-3 me-3 text-danger"></i>
                            <div><h6 class="fw-bold mb-0">Tamaño de Fuente</h6><small class="text-muted">Ajusta la lectura global.</small></div>
                        </div>
                        <input type="range" class="form-range" id="rangeFontSize" min="1" max="3" step="1">
                        <div class="d-flex justify-content-between small fw-bold text-muted mt-1">
                            <span>Pequeña</span><span>Estándar</span><span>Grande</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <!-- PESTAÑA 2: CARGOS -->
    <div class="tab-pane fade" id="cargos">
        <div class="card shadow-sm border-0 mb-4 bg-body-tertiary">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" id="buscarCargo" class="form-control" placeholder="Buscar por nombre...">
                    </div>
                    <div class="col-md-5">
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="filtrarTipoCargo('todos')">Todos</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Docente')">Docentes</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Administrativo')">Admins</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="filtrarTipoCargo('Obrero')">Obreros</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearCargo">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Cargo
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" id="tablaCargos">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">Cargo / Materia</th>
                                <th>Descripción de Cátedra</th>
                                <th>Categoría</th>
                                <th class="text-center">Personal Asignado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cargos as $c): ?>
                            <tr data-categoria="<?= htmlspecialchars($c['tipo']) ?>">
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($c['descripcion'] ?? 'Sin descripción') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($c['tipo']) ?></span></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-body-secondary text-body border px-3">
                                        <i class="bi bi-people me-1"></i> <?= $c['total_asignados'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form action="" method="POST" onsubmit="return confirm('¿Seguro que desea eliminar?');">
                                        <input type="hidden" name="accion" value="eliminar_cargo"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" <?= $c['total_asignados'] > 0 ? 'title="En uso (Bloqueado)"' : '' ?>><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 3: EXPORTACIÓN MULTI-FORMATO (REPARADA CON DESCRIPCIONES Y JSON) -->
    <div class="tab-pane fade" id="exportar">
        <div class="card shadow-sm border-0 mb-4 border-top border-4 border-primary">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-cloud-arrow-down me-2 text-primary"></i>Generador Multi-Formato Oficial</h5>
                <p class="text-muted small mb-4">Seleccione la categoría de personal para emitir el listado en cualquiera de las siguientes 7 opciones técnicas.</p>
                
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">1. Filtrar Personal:</label>
                        <select id="filtroExportPersonal" class="form-select form-select-lg shadow-sm">
                            <option value="todos">📋 Todo el Personal (Directorio General)</option>
                            <option value="1">👨‍🏫 Solo Personal Docente</option>
                            <option value="2">🏢 Solo Personal Administrativo</option>
                            <option value="3">🛠️ Solo Personal Obrero</option>
                        </select>
                    </div>
                </div>

                <label class="form-label fw-bold mb-3">2. Selecciona el Formato de Salida:</label>
                <div class="row g-3">
                    
                    <!-- 1. EXCEL -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-success shadow-sm">
                            <i class="bi bi-file-earmark-excel-fill text-success fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Excel (.XLS)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Matriz con fórmulas, formato ministerial y celdas estructuradas.</p>
                            <button onclick="descargarReporte('excel')" class="btn btn-success btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <!-- 2. PDF OFICIAL -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-danger shadow-sm">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">PDF Imprimible</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Documento formal listo para imprimir con sellos institucionales.</p>
                            <button onclick="descargarReporte('pdf')" class="btn btn-danger btn-sm w-100 mt-auto"><i class="bi bi-printer"></i> Abrir PDF</button>
                        </div>
                    </div>

                    <!-- 3. WORD -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-primary shadow-sm">
                            <i class="bi bi-file-earmark-word-fill text-primary fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Word (.DOC)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Fichas descriptivas editables para memorándums y trámites.</p>
                            <button onclick="descargarReporte('word')" class="btn btn-primary btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <!-- 4. POWERPOINT -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 shadow-sm" style="border-color: #d24726 !important;">
                            <i class="bi bi-file-earmark-slides-fill fs-1 mb-2" style="color: #d24726;"></i>
                            <h6 class="fw-bold mb-1">PowerPoint (.PPT)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Diapositivas listas para asambleas escolares y consejos docentes.</p>
                            <button onclick="descargarReporte('pptx')" class="btn btn-sm w-100 mt-auto text-white" style="background:#d24726;"><i class="bi bi-download"></i> Diapositivas</button>
                        </div>
                    </div>

                    <!-- 5. IMAGEN JPG -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-info shadow-sm">
                            <i class="bi bi-file-earmark-image-fill text-info fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">Imagen (JPG)</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Infografía visual de alta resolución para enviar por grupos de WhatsApp.</p>
                            <button onclick="descargarReporte('jpg')" class="btn btn-info btn-sm w-100 mt-auto text-white"><i class="bi bi-camera"></i> Generar JPG</button>
                        </div>
                    </div>

                    <!-- 6. CSV -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-secondary shadow-sm">
                            <i class="bi bi-file-earmark-spreadsheet-fill text-secondary fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">CSV Plano</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Valores delimitados para importar a sistemas de nómina externos.</p>
                            <button onclick="descargarReporte('csv')" class="btn btn-secondary btn-sm w-100 mt-auto"><i class="bi bi-download"></i> Descargar</button>
                        </div>
                    </div>

                    <!-- 7. JSON ESTRUCTURADO (AÑADIDO Y COMPLETO) -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center p-3 h-100 border-warning shadow-sm">
                            <i class="bi bi-filetype-json text-warning fs-1 mb-2"></i>
                            <h6 class="fw-bold mb-1">JSON Estructurado</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.75rem;">Respaldo total con objetos anidados (MPPE, salud, contacto y cargos).</p>
                            <button onclick="descargarReporte('json')" class="btn btn-warning btn-sm w-100 mt-auto fw-bold"><i class="bi bi-download"></i> Respaldo JSON</button>
                        </div>
                    </div>
                    
                    <!-- ORGANIGRAMA PDF -->
<div class="col-md-6 col-lg-3">
    <div class="card text-center p-3 h-100 border-dark shadow-sm">
        <i class="bi bi-diagram-3-fill text-dark fs-1 mb-2"></i>
        <h6 class="fw-bold mb-1">Organigrama</h6>
        <p class="small text-muted mb-3" style="font-size: 0.75rem;">Estructura jerárquica visual de la institución.</p>
        <button onclick="window.location.href='organigrama.php'" class="btn btn-dark btn-sm w-100 mt-auto"><i class="bi bi-eye"></i> Ir al Editor</button>
    </div>
</div>


                </div>

            </div>
        </div>
    </div>

    <!-- PESTAÑA 4: IMPORTACIÓN Y CARGAS (LIMPIA, SIN DUPLICADOS) -->
    <div class="tab-pane fade" id="importar">
        <div class="row g-4">
            
            <!-- IMPORTAR CSV NÓMINA -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100 border-top border-4 border-success">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success"><i class="bi bi-filetype-csv me-2"></i>Carga Masiva de Nómina (CSV)</h5>
                        <p class="small text-muted mb-3">Ideal para migrar listados extensos desde Excel hacia la base de datos sin ingresarlos uno a uno.</p>
                        
                        <div class="alert alert-success bg-success bg-opacity-10 border-0 small mb-3">
                            <strong><i class="bi bi-info-circle-fill me-1"></i> Especificaciones del Archivo:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <li>Formato: <code>Valores separados por comas (.csv)</code> en codificación UTF-8.</li>
                                <li>Orden de Columnas: <code>Cédula, Nombres, Apellidos, ID_Cargo, Fecha_Ingreso</code></li>
                                <li>Ejemplo: <code>31664538, Marcus, Vinicius, 1, 2024-09-15</code></li>
                            </ul>
                        </div>

                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tipo_importacion" value="csv_personal">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Seleccionar Archivo CSV:</label>
                                <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Procesar Nómina CSV</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- DIGITALIZACIÓN EN CLAVE -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100 border-top border-4 border-primary">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-primary"><i class="bi bi-folder-plus me-2"></i>Digitalización Individual en Clave</h5>
                        <p class="small text-muted mb-3">Guarda soportes fotográficos o escaneos vinculados a un trabajador específico.</p>
                        
                        <div class="alert alert-primary bg-primary bg-opacity-10 border-0 small mb-3">
                            <strong><i class="bi bi-shield-check me-1"></i> Nomenclatura en Clave Automática:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <li>Formatos: <code>JPG, PNG, PDF, WEBP</code> (Máx. 5MB). Guardado en <code>documentos/</code>.</li>
                                <li>El servidor renombrará bajo la norma: <code>V{Cedula}_{ID}_{TIPO}_{01}.ext</code></li>
                            </ul>
                        </div>

                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tipo_importacion" value="documento_personal">
                            
                            <div class="row g-2 mb-2">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold">Trabajador Destino:</label>
                                    <select name="personal_id" class="form-select form-select-sm" required>
                                        <option value="">Seleccione personal...</option>
                                        <?php foreach ($personal_lista as $p): ?>
                                            <option value="<?= $p['id'] ?>">V-<?= $p['cedula'] ?> - <?= htmlspecialchars($p['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Tipo en Clave:</label>
                                    <select name="tipo_documento" class="form-select form-select-sm" required>
                                        <option value="CED">CED - Cédula</option>
                                        <option value="FOT">FOT - Foto Carnet</option>
                                        <option value="TIT">TIT - Título</option>
                                        <option value="MED">MED - Cert. Médico</option>
                                        <option value="SOP">SOP - Reposo</option>
                                        <option value="OTR">OTR - Otro</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Seleccionar Archivo:</label>
                                <input type="file" name="archivo_adjunto" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold btn-sm"><i class="bi bi-upload me-1"></i> Codificar y Subir</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RESTAURAR JSON (ÚNICA Y REPARADA) -->
            <div class="col-12">
                <div class="card shadow-sm border-0 border-top border-4 border-warning bg-body-tertiary">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1 text-warning"><i class="bi bi-database-fill-up me-2"></i>Restaurar Respaldo Estructurado (JSON)</h5>
                        <p class="small text-muted mb-3">Recuperación de desastres o sincronización integral de trabajadores con expediente MPPE.</p>
                        
                        <div class="alert alert-warning bg-warning bg-opacity-10 border-0 small mb-3">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Motor de Restauración Relacional:</strong>
                            El archivo JSON restaurará automáticamente la tabla principal de personal, actualizará sus cargos vigentes y reescribirá la ficha completa de datos adicionales (tallas, cuentas bancarias, turno y patologías).
                        </div>

                        <form action="../PHP/importar.php" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                            <input type="hidden" name="tipo_importacion" value="json_restore">
                            <input type="file" name="archivo_json" class="form-control" accept=".json" required>
                            <button type="submit" class="btn btn-warning fw-bold text-nowrap"><i class="bi bi-arrow-repeat me-1"></i> Iniciar Restauración</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- MODAL CREAR NUEVO CARGO CON DESCRIPCIÓN -->
<div class="modal fade" id="modalCrearCargo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Cargo / Materia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body bg-body-tertiary">
                    <input type="hidden" name="accion" value="crear_cargo">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Categoría de Personal</label>
                        <select name="tipo_personal_id" class="form-select" required>
                            <option value="">Seleccione categoría...</option>
                            <?php foreach ($tipos_personal as $tp): ?>
                                <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre del Cargo o Materia</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Química General" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Descripción de Cátedra (Para vista previa)</label>
                        <textarea name="descripcion" class="form-control" rows="2" placeholder="Breve resumen de responsabilidades o contenido..."></textarea>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success w-100 fw-bold">Guardar en Catálogo</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('buscarCargo')?.addEventListener('input', function(e) {
    let term = e.target.value.toLowerCase();
    document.querySelectorAll('#tablaCargos tbody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
});

function filtrarTipoCargo(tipo) {
    document.querySelectorAll('#tablaCargos tbody tr').forEach(tr => {
        tr.style.display = (tipo === 'todos' || tr.getAttribute('data-categoria') === tipo) ? '' : 'none';
    });
}

function descargarReporte(formato) {
    let tipo = document.getElementById('filtroExportPersonal').value;
    let url = `../PHP/exportar.php?formato=${formato}&tipo=${tipo}`;
    if (formato === 'pdf' || formato === 'jpg') window.open(url, '_blank');
    else window.location.href = url;
}
</script>

<?php include '../Includes/footer.php'; ?>