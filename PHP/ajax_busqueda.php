<?php
session_start();
require_once 'connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada.']);
    exit;
}

$uid = $_SESSION['admin_id'];
$modo = $_GET['modo'] ?? 'global'; // 'global' o 'selector_personal'
$q = trim($_GET['q'] ?? '');
$tabla = $_GET['tabla'] ?? 'todos';

// Normalizar texto por si buscan cédula con puntos o "V-"
$qSoloNumeros = preg_replace('/[^0-9]/', '', $q);
$like = '%' . $q . '%';
$likeNum = !empty($qSoloNumeros) ? '%' . $qSoloNumeros . '%' : $like;

// =========================================================================
// MODO 1: SELECTOR IMPORTABLE DE TRABAJADORES (Para modales y formularios)
// =========================================================================
if ($modo === 'selector_personal') {
    if (!tienePermisoReBAC($pdo, $uid, 'expediente_personal', 'leer')) {
        echo json_encode(['success' => true, 'resultados' => []]);
        exit;
    }

    $tipo_filtro = intval($_GET['tipo_personal_id'] ?? 0);
    $solo_activos = ($_GET['solo_activos'] ?? '0') === '1';

    $sql = "
        SELECT p.id, p.cedula, p.nombres, p.apellidos, p.estado_laboral,
               c.nombre as cargo, t.id as tipo_id, t.nombre as tipo_nombre
        FROM personal p
        LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
        LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
        LEFT JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id
        LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            p.cedula LIKE ? OR
            REPLACE(REPLACE(UPPER(p.cedula), 'V-', ''), '.', '') LIKE ? OR
            p.nombres LIKE ? OR
            p.apellidos LIKE ? OR
            CONCAT(p.nombres, ' ', p.apellidos) LIKE ? OR
            CONCAT(p.apellidos, ' ', p.nombres) LIKE ? OR
            c.nombre LIKE ? OR
            pda.cargo_nominal LIKE ?
        )";
        array_push($params, $like, $likeNum, $like, $like, $like, $like, $like, $like);
    }

    if ($tipo_filtro > 0) {
        $sql .= " AND t.id = ?";
        $params[] = $tipo_filtro;
    }
    if ($solo_activos) {
        $sql .= " AND p.estado_laboral = 'Activo'";
    }

    $sql .= " GROUP BY p.id ORDER BY p.apellidos ASC LIMIT 25";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'resultados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// =========================================================================
// MODO 2: BÚSQUEDA GLOBAL AVANZADA (NAVBAR Y MODAL CON FILTROS POR TABLA)
// =========================================================================
$resultados = [
    'personal' => [],
    'documentos' => [],
    'asistencias' => [],
    'horarios' => [],
    'cronograma' => []
];

$limit = ($tabla === 'todos') ? 6 : 25;

// 1. BUSCAR EN PERSONAL Y METADATOS
if (($tabla === 'todos' || $tabla === 'personal') && tienePermisoReBAC($pdo, $uid, 'expediente_personal', 'leer')) {
    $f_tipo = intval($_GET['p_tipo'] ?? 0);
    $f_estado = trim($_GET['p_estado'] ?? '');

    $sql = "
        SELECT p.id, p.cedula, p.nombres, p.apellidos, p.estado_laboral, p.fecha_ingreso,
               c.nombre as cargo, t.id as tipo_id, t.nombre as tipo_nombre,
               pda.cargo_nominal, pda.correo_electronico, pda.municipio, pda.parroquia,
               (SELECT valor FROM personal_contactos WHERE personal_id = p.id AND tipo_contacto = 'Teléfono Personal' LIMIT 1) as telefono
        FROM personal p
        LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
        LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
        LEFT JOIN cat_tipos_personal t ON c.tipo_personal_id = t.id
        LEFT JOIN personal_datos_adicionales pda ON p.id = pda.personal_id
        LEFT JOIN personal_contactos pcon ON p.id = pcon.personal_id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            p.cedula LIKE ? OR
            REPLACE(REPLACE(UPPER(p.cedula), 'V-', ''), '.', '') LIKE ? OR
            p.nombres LIKE ? OR
            p.apellidos LIKE ? OR
            CONCAT(p.nombres, ' ', p.apellidos) LIKE ? OR
            CONCAT(p.apellidos, ' ', p.nombres) LIKE ? OR
            c.nombre LIKE ? OR
            pda.cargo_nominal LIKE ? OR
            pda.codigo_cargo LIKE ? OR
            pda.correo_electronico LIKE ? OR
            pda.direccion_habitacion LIKE ? OR
            pda.municipio LIKE ? OR
            pda.parroquia LIKE ? OR
            pda.nivel_academico LIKE ? OR
            pda.patologias LIKE ? OR
            pcon.valor LIKE ?
        )";
        array_push($params, $like, $likeNum, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    if ($f_tipo > 0) { $sql .= " AND t.id = ?"; $params[] = $f_tipo; }
    if ($f_estado !== '') { $sql .= " AND p.estado_laboral = ?"; $params[] = $f_estado; }

    $sql .= " GROUP BY p.id ORDER BY p.apellidos ASC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados['personal'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. BUSCAR EN DOCUMENTOS / ARCHIVOS
if (($tabla === 'todos' || $tabla === 'documentos') && tienePermisoReBAC($pdo, $uid, 'documentos', 'leer')) {
    $f_doc_tipo = intval($_GET['d_tipo'] ?? 0);
    $f_doc_ent = trim($_GET['d_entidad'] ?? '');

    $sql = "
        SELECT d.id, d.codigo_archivo, d.nombre_original, d.ruta_archivo, d.mime_type, d.fecha_subida,
               td.clave as tipo_clave, td.nombre as tipo_nombre,
               GROUP_CONCAT(DISTINCT 
                   CASE 
                       WHEN de.tabla_destino = 'personal' THEN CONCAT('👤 ', p.apellidos, ', ', p.nombres)
                       WHEN de.tabla_destino = 'cronograma_eventos' THEN CONCAT('📅 ', ce.titulo)
                       WHEN de.tabla_destino = 'institucional' THEN '🏛️ Institucional'
                       ELSE de.tabla_destino
                   END SEPARATOR ' | '
               ) as vinculos_txt
        FROM documentos d
        JOIN cat_tipos_documentos td ON d.tipo_documento_id = td.id
        LEFT JOIN documentos_enlaces de ON d.id = de.documento_id
        LEFT JOIN personal p ON de.tabla_destino = 'personal' AND de.registro_id = p.id
        LEFT JOIN cronograma_eventos ce ON de.tabla_destino = 'cronograma_eventos' AND de.registro_id = ce.id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            d.codigo_archivo LIKE ? OR
            d.nombre_original LIKE ? OR
            td.clave LIKE ? OR
            td.nombre LIKE ? OR
            de.notas LIKE ? OR
            p.cedula LIKE ? OR
            p.nombres LIKE ? OR
            p.apellidos LIKE ? OR
            ce.titulo LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    if ($f_doc_tipo > 0) { $sql .= " AND td.id = ?"; $params[] = $f_doc_tipo; }
    if ($f_doc_ent !== '') { $sql .= " AND de.tabla_destino = ?"; $params[] = $f_doc_ent; }

    $sql .= " GROUP BY d.id ORDER BY d.fecha_subida DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados['documentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. BUSCAR EN ASISTENCIAS
if (($tabla === 'todos' || $tabla === 'asistencias') && tienePermisoReBAC($pdo, $uid, 'asistencias', 'leer')) {
    $f_as_estado = trim($_GET['a_estado'] ?? '');
    $f_desde = trim($_GET['a_desde'] ?? '');
    $f_hasta = trim($_GET['a_hasta'] ?? '');

    $sql = "
        SELECT a.id, a.fecha, a.hora_entrada, a.hora_salida, a.estado_asistencia, a.observaciones,
               p.cedula, p.nombres, p.apellidos
        FROM asistencias a
        JOIN personal p ON a.personal_id = p.id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            p.cedula LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR
            CONCAT(p.apellidos, ' ', p.nombres) LIKE ? OR
            a.estado_asistencia LIKE ? OR a.observaciones LIKE ? OR a.fecha LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    if ($f_as_estado !== '') { $sql .= " AND a.estado_asistencia = ?"; $params[] = $f_as_estado; }
    if ($f_desde !== '') { $sql .= " AND a.fecha >= ?"; $params[] = $f_desde; }
    if ($f_hasta !== '') { $sql .= " AND a.fecha <= ?"; $params[] = $f_hasta; }

    $sql .= " ORDER BY a.fecha DESC, a.id DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados['asistencias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4. BUSCAR EN CALENDARIO LABORAL (HORARIOS)
if (($tabla === 'todos' || $tabla === 'horarios') && tienePermisoReBAC($pdo, $uid, 'horarios', 'leer')) {
    $f_dia = trim($_GET['h_dia'] ?? '');

    $sql = "
        SELECT h.id, h.dia_semana, h.hora_inicio, h.hora_fin, h.actividad,
               p.cedula, p.nombres, p.apellidos, c.nombre as cargo
        FROM horarios_laborales h
        JOIN personal p ON h.personal_id = p.id
        LEFT JOIN personal_cargos pc ON p.id = pc.personal_id AND pc.estado_cargo = 'Vigente'
        LEFT JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            h.actividad LIKE ? OR h.dia_semana LIKE ? OR
            p.cedula LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR c.nombre LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if ($f_dia !== '') { $sql .= " AND h.dia_semana = ?"; $params[] = $f_dia; }

    $sql .= " GROUP BY h.id ORDER BY FIELD(h.dia_semana,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), h.hora_inicio ASC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados['horarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 5. BUSCAR EN CRONOGRAMA DE EVENTOS
if (($tabla === 'todos' || $tabla === 'cronograma') && tienePermisoReBAC($pdo, $uid, 'cronograma', 'leer')) {
    $f_desde = trim($_GET['c_desde'] ?? '');
    $f_hasta = trim($_GET['c_hasta'] ?? '');

    $sql = "
        SELECT e.id, e.fecha, e.hora_inicio, e.hora_fin, e.titulo, e.descripcion,
               GROUP_CONCAT(DISTINCT CONCAT(p.apellidos, ' ', p.nombres) SEPARATOR ', ') as participantes
        FROM cronograma_eventos e
        LEFT JOIN cronograma_participantes cp ON e.id = cp.evento_id
        LEFT JOIN personal p ON cp.personal_id = p.id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (
            e.titulo LIKE ? OR e.descripcion LIKE ? OR e.fecha LIKE ? OR
            p.nombres LIKE ? OR p.apellidos LIKE ? OR p.cedula LIKE ?
        )";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if ($f_desde !== '') { $sql .= " AND e.fecha >= ?"; $params[] = $f_desde; }
    if ($f_hasta !== '') { $sql .= " AND e.fecha <= ?"; $params[] = $f_hasta; }

    $sql .= " GROUP BY e.id ORDER BY e.fecha DESC, e.hora_inicio ASC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados['cronograma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode(['success' => true, 'resultados' => $resultados]);
?>