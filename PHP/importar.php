<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['admin_id'])) { die("Acceso no autorizado."); }

$tipo = $_POST['tipo_importacion'] ?? '';

// ==========================================
// 1. CARGA MASIVA CSV DE PERSONAL
// ==========================================
if ($tipo === 'csv_personal' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    
    if (($gestor = fopen($archivo, "r")) !== FALSE) {
        $pdo->beginTransaction();
        $fila = 0;
        try {
            while (($datos = fgetcsv($gestor, 1000, ",")) !== FALSE) {
                $fila++;
                if ($fila === 1) continue; // Saltar cabecera
                
                $cedula = trim($datos[0] ?? '');
                $nombres = trim($datos[1] ?? '');
                $apellidos = trim($datos[2] ?? '');
                $cargo_id = intval($datos[3] ?? 1);
                $fecha_ing = !empty($datos[4]) ? trim($datos[4]) : date('Y-m-d');

                if (!empty($cedula) && !empty($nombres)) {
                    $stmt = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_ingreso, estado_laboral) VALUES (?, ?, ?, ?, 'Activo') ON DUPLICATE KEY UPDATE nombres=VALUES(nombres)");
                    $stmt->execute([$cedula, $nombres, $apellidos, $fecha_ing]);
                    $pid = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM personal WHERE cedula = '$cedula'")->fetchColumn();

                    $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion) VALUES (?, ?, ?)")
                        ->execute([$pid, $cargo_id, $fecha_ing]);
                }
            }
            $pdo->commit();
            fclose($gestor);
            registrarHistorial($pdo, 'Importación', 'CREAR', "Importó $fila registros desde archivo CSV.");
            header("Location: ../Pages/configuracion.php?msg=import_csv_ok#importar"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: ../Pages/configuracion.php?msg=import_err#importar"); exit;
        }
    }
}

// ==========================================
// 2. CARGA DE DOCUMENTOS / FOTOS EN CLAVE (CARPETA AUTOMÁTICA)
// ==========================================
elseif ($tipo === 'documento_personal' && isset($_FILES['archivo_adjunto'])) {
    $personal_id = intval($_POST['personal_id'] ?? 0);
    $tipo_clave = $_POST['tipo_documento'] ?? 'OTR';
    
    // Obtener la cédula para el nombre en clave
    $stmtC = $pdo->prepare("SELECT cedula FROM personal WHERE id = ?");
    $stmtC->execute([$personal_id]);
    $cedula = $stmtC->fetchColumn();

    if ($cedula && !empty($_FILES['archivo_adjunto']['tmp_name'])) {
        // CREACIÓN AUTOMÁTICA DE LA CARPETA
        $directorio = '../documentos/';
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['archivo_adjunto']['name'], PATHINFO_EXTENSION));
        
        // Calcular correlativo
        $stmtS = $pdo->prepare("SELECT MAX(numero_secuencia) FROM personal_archivos_digitales WHERE personal_id = ? AND tipo_documento_clave = ?");
        $stmtS->execute([$personal_id, $tipo_clave]);
        $secuencia = intval($stmtS->fetchColumn()) + 1;

        // Nombre codificado: V12345678_42_CED_01.jpg
        $cedula_limpia = preg_replace('/[^0-9]/', '', $cedula);
        $nombre_codigo = sprintf("V%s_%d_%s_%02d.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, $ext);
        $rutaFinal = $directorio . $nombre_codigo;

        if (move_uploaded_file($_FILES['archivo_adjunto']['tmp_name'], $rutaFinal)) {
            $pdo->prepare("INSERT INTO personal_archivos_digitales (personal_id, codigo_archivo, tipo_documento_clave, numero_secuencia, nombre_original, ruta_archivo, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$personal_id, $nombre_codigo, $tipo_clave, $secuencia, $_FILES['archivo_adjunto']['name'], $rutaFinal, $_FILES['archivo_adjunto']['type']]);
            
            registrarHistorial($pdo, 'Documentos', 'CREAR', "Adjuntó $nombre_codigo al expediente.");
            header("Location: ../Pages/configuracion.php?msg=import_doc_ok#importar"); exit;
        }
    }
    
    header("Location: ../Pages/configuracion.php?msg=import_err#importar"); exit;
}

// ==========================================
// 3. RESTAURAR RESPALDO JSON
// ==========================================
// ==========================================
// 3. RESTAURAR RESPALDO JSON COMPLETO (REPARADO)
// ==========================================
elseif ($tipo === 'json_restore' && isset($_FILES['archivo_json'])) {
    $contenido = file_get_contents($_FILES['archivo_json']['tmp_name']);
    $data = json_decode($contenido, true);

    if (is_array($data)) {
        $pdo->beginTransaction();
        try {
            foreach ($data as $d) {
                if (!empty($d['cedula']) && !empty($d['nombres'])) {
                    // 1. Guardar o actualizar en tabla Personal
                    $stmt = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_nacimiento, fecha_ingreso, estado_laboral) 
                        VALUES (?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE nombres=VALUES(nombres), apellidos=VALUES(apellidos), fecha_nacimiento=VALUES(fecha_nacimiento), estado_laboral=VALUES(estado_laboral)");
                    $stmt->execute([$d['cedula'], $d['nombres'], $d['apellidos'], $d['fecha_nacimiento'] ?? null, $d['fecha_ingreso'], $d['estado_laboral']]);
                    
                    // Obtener ID del registro
                    $pid = $pdo->lastInsertId();
                    if(!$pid) {
                        $q = $pdo->prepare("SELECT id FROM personal WHERE cedula = ?");
                        $q->execute([$d['cedula']]);
                        $pid = $q->fetchColumn();
                    }

                    // 2. Restaurar cargo
                    if (!empty($d['cargo_area_id'])) {
                        $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cargo_area_id=VALUES(cargo_area_id)")
                            ->execute([$pid, $d['cargo_area_id'], $d['fecha_ingreso']]);
                    }

                    // 3. Restaurar teléfono
                    if (!empty($d['telefono'])) {
                        $pdo->prepare("DELETE FROM personal_contactos WHERE personal_id=? AND tipo_contacto='Teléfono Personal'")->execute([$pid]);
                        $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$pid, $d['telefono']]);
                    }

                    // 4. Restaurar Ficha Completa MPPE y Datos Adicionales
                    $stmtP = $pdo->prepare("INSERT INTO personal_datos_adicionales (
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

                    $stmtP->execute([
                        $pid, $d['estado_civil'] ?? 'Soltero/a', intval($d['cantidad_hijos'] ?? 0), $d['etnia'] ?? null, $d['fecha_ingreso_mppe'] ?? null, $d['dependencia'] ?? null, $d['codigo_dependencia'] ?? null,
                        $d['turno'] ?? 'Mañana', $d['nivel_academico'] ?? null, $d['correo_electronico'] ?? null, $d['banco_nomina'] ?? null, $d['cuenta_bancaria'] ?? null, $d['cargo_nominal'] ?? null, $d['codigo_cargo'] ?? null,
                        intval($d['horas_laborales'] ?? 0), $d['direccion_habitacion'] ?? null, $d['municipio'] ?? null, $d['parroquia'] ?? null, $d['centro_votacion'] ?? null, $d['patologias'] ?? null, $d['medicamentos_permanentes'] ?? null,
                        $d['tipo_sangre'] ?? null, $d['alergias_medicas'] ?? null, $d['condiciones_especiales'] ?? null, $d['talla_camisa'] ?? null, $d['talla_pantalon'] ?? null, $d['talla_calzado'] ?? null
                    ]);
                }
            }
            $pdo->commit();
            registrarHistorial($pdo, 'Importación', 'ACTUALIZAR', "Restauró exitosamente la base de datos completa desde archivo JSON.");
            header("Location: ../Pages/configuracion.php?msg=import_json_ok#importar"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: ../Pages/configuracion.php?msg=import_err#importar"); exit;
        }
    }
}
header("Location: ../Pages/configuracion.php#importar");
exit;