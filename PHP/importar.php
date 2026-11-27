<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['admin_id'])) { die("Acceso no autorizado."); }

$tipo = $_POST['tipo_importacion'] ?? '';

// 1. CARGA MASIVA CSV DE PERSONAL (100% CONSULTAS PREPARADAS)
if ($tipo === 'csv_personal' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    if (($gestor = fopen($archivo, "r")) !== FALSE) {
        $pdo->beginTransaction();
        $fila = 0;
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_ingreso, estado_laboral) VALUES (?, ?, ?, ?, 'Activo') ON DUPLICATE KEY UPDATE nombres=VALUES(nombres), apellidos=VALUES(apellidos)");
            $stmtBuscarId = $pdo->prepare("SELECT id FROM personal WHERE cedula = ? LIMIT 1");
            $stmtCargo = $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion) VALUES (?, ?, ?)");

            while (($datos = fgetcsv($gestor, 1000, ",")) !== FALSE) {
                $fila++;
                if ($fila === 1) continue;
                
                $cedula = trim($datos[0] ?? '');
                $nombres = trim($datos[1] ?? '');
                $apellidos = trim($datos[2] ?? '');
                $cargo_id = intval($datos[3] ?? 1);
                $fecha_ing = !empty($datos[4]) ? trim($datos[4]) : date('Y-m-d');

                if (!empty($cedula) && !empty($nombres)) {
                    $stmtInsert->execute([$cedula, $nombres, $apellidos, $fecha_ing]);
                    $pid = $pdo->lastInsertId();
                    if (!$pid) {
                        $stmtBuscarId->execute([$cedula]);
                        $pid = $stmtBuscarId->fetchColumn();
                    }
                    if ($pid) {
                        $stmtCargo->execute([$pid, $cargo_id, $fecha_ing]);
                    }
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

// 2. CARGA DE DOCUMENTO -> REPOSITORIO UNIFICADO
elseif ($tipo === 'documento_personal' && isset($_FILES['archivo_adjunto'])) {
    $personal_id = intval($_POST['personal_id'] ?? 0);
    $tipo_clave = $_POST['tipo_documento'] ?? 'OTR';
    
    $stmtC = $pdo->prepare("SELECT cedula FROM personal WHERE id = ?");
    $stmtC->execute([$personal_id]);
    $cedula = $stmtC->fetchColumn();

    if ($cedula && !empty($_FILES['archivo_adjunto']['tmp_name'])) {
        $directorio = '../documentos/';
        if (!file_exists($directorio)) mkdir($directorio, 0777, true);

        $ext = strtolower(pathinfo($_FILES['archivo_adjunto']['name'], PATHINFO_EXTENSION));
        $stmtT = $pdo->prepare("SELECT id FROM cat_tipos_documentos WHERE clave = ?");
        $stmtT->execute([$tipo_clave]);
        $tipoDocId = $stmtT->fetchColumn() ?: 7;

        $stmtS = $pdo->prepare("SELECT COUNT(*) FROM documentos d JOIN documentos_enlaces de ON d.id = de.documento_id WHERE de.tabla_destino = 'personal' AND de.registro_id = ? AND d.tipo_documento_id = ?");
        $stmtS->execute([$personal_id, $tipoDocId]);
        $secuencia = intval($stmtS->fetchColumn()) + 1;

        $cedula_limpia = preg_replace('/[^0-9]/', '', $cedula);
        $nombre_codigo = sprintf("V%s_%d_%s_%02d.%s", $cedula_limpia, $personal_id, $tipo_clave, $secuencia, $ext);
        $rutaFinal = $directorio . $nombre_codigo;

        $tamano = $_FILES['archivo_adjunto']['size'];
        $mime = $_FILES['archivo_adjunto']['type'] ?: mime_content_type($_FILES['archivo_adjunto']['tmp_name']);
        $hash = hash_file('sha256', $_FILES['archivo_adjunto']['tmp_name']);

        if (move_uploaded_file($_FILES['archivo_adjunto']['tmp_name'], $rutaFinal)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO documentos (tipo_documento_id, codigo_archivo, nombre_original, ruta_archivo, mime_type, tamano_bytes, hash_verificacion) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$tipoDocId, $nombre_codigo, $_FILES['archivo_adjunto']['name'], $rutaFinal, $mime, $tamano, $hash]);
                $docId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, 'personal', ?, 'Subido desde módulo de importación')")
                    ->execute([$docId, $personal_id]);
                registrarHistorial($pdo, 'Documentos', 'CREAR', "Adjuntó $nombre_codigo al expediente.", $docId);
                $pdo->commit();
                header("Location: ../Pages/configuracion.php?msg=import_doc_ok#importar"); exit;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
    header("Location: ../Pages/configuracion.php?msg=import_err#importar"); exit;
}

// 3. RESTAURAR RESPALDO JSON CON TABLAS 3FN
elseif ($tipo === 'json_restore' && isset($_FILES['archivo_json'])) {
    $contenido = file_get_contents($_FILES['archivo_json']['tmp_name']);
    $data = json_decode($contenido, true);

    if (is_array($data)) {
        $pdo->beginTransaction();
        try {
            $stmtP = $pdo->prepare("INSERT INTO personal (cedula, nombres, apellidos, fecha_nacimiento, fecha_ingreso, estado_laboral) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE nombres=VALUES(nombres), apellidos=VALUES(apellidos), fecha_nacimiento=VALUES(fecha_nacimiento), estado_laboral=VALUES(estado_laboral)");
            $stmtQ = $pdo->prepare("SELECT id FROM personal WHERE cedula = ?");

            foreach ($data as $d) {
                if (!empty($d['cedula']) && !empty($d['nombres'])) {
                    $stmtP->execute([$d['cedula'], $d['nombres'], $d['apellidos'], $d['fecha_nacimiento'] ?? null, $d['fecha_ingreso'], $d['estado_laboral'] ?? 'Activo']);
                    $pid = $pdo->lastInsertId();
                    if (!$pid) { $stmtQ->execute([$d['cedula']]); $pid = $stmtQ->fetchColumn(); }

                    if (!empty($d['cargo_area_id'])) {
                        $pdo->prepare("UPDATE personal_cargos SET estado_cargo='Finalizado' WHERE personal_id=? AND estado_cargo='Vigente'")->execute([$pid]);
                        $pdo->prepare("INSERT INTO personal_cargos (personal_id, cargo_area_id, fecha_asignacion, estado_cargo) VALUES (?, ?, ?, 'Vigente')")
                            ->execute([$pid, $d['cargo_area_id'], $d['fecha_ingreso']]);
                    }

                    $pdo->prepare("DELETE FROM personal_contactos WHERE personal_id=?")->execute([$pid]);
                    if (!empty($d['telefono'])) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Teléfono Personal', ?)")->execute([$pid, $d['telefono']]);
                    if (!empty($d['correo_electronico'])) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Email', ?)")->execute([$pid, $d['correo_electronico']]);
                    if (!empty($d['direccion_habitacion'])) $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Dirección Habitación', ?)")->execute([$pid, $d['direccion_habitacion']]);

                    $banco_id         = resolverBancoId($pdo, $d['banco_nomina'] ?? '', $d['cuenta_bancaria'] ?? '');
                    $dependencia_id   = resolverDependenciaId($pdo, $d['dependencia'] ?? '', $d['codigo_dependencia'] ?? '');
                    $cargo_nominal_id = resolverCargoNominalId($pdo, $d['cargo_nominal'] ?? '', $d['codigo_cargo'] ?? '');
                    $parroquia_id     = resolverParroquiaId($pdo, $d['municipio'] ?? '', $d['parroquia'] ?? '');

                    $pdo->prepare("INSERT INTO personal_datos_adicionales (
                        personal_id, estado_civil, cantidad_hijos, etnia, fecha_ingreso_mppe, dependencia_id,
                        turno, nivel_academico, banco_id, cuenta_bancaria, cargo_nominal_id,
                        horas_laborales, parroquia_id, centro_votacion, tipo_sangre, talla_camisa, talla_pantalon, talla_calzado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        estado_civil=VALUES(estado_civil), cantidad_hijos=VALUES(cantidad_hijos), etnia=VALUES(etnia),
                        fecha_ingreso_mppe=VALUES(fecha_ingreso_mppe), dependencia_id=VALUES(dependencia_id), turno=VALUES(turno),
                        nivel_academico=VALUES(nivel_academico), banco_id=VALUES(banco_id), cuenta_bancaria=VALUES(cuenta_bancaria),
                        cargo_nominal_id=VALUES(cargo_nominal_id), horas_laborales=VALUES(horas_laborales), parroquia_id=VALUES(parroquia_id),
                        centro_votacion=VALUES(centro_votacion), tipo_sangre=VALUES(tipo_sangre), talla_camisa=VALUES(talla_camisa),
                        talla_pantalon=VALUES(talla_pantalon), talla_calzado=VALUES(talla_calzado)")
                    ->execute([
                        $pid, $d['estado_civil'] ?? 'Soltero/a', intval($d['cantidad_hijos'] ?? 0), $d['etnia'] ?? null,
                        $d['fecha_ingreso_mppe'] ?? null, $dependencia_id, $d['turno'] ?? 'Mañana', $d['nivel_academico'] ?? null,
                        $banco_id, $d['cuenta_bancaria'] ?? null, $cargo_nominal_id, intval($d['horas_laborales'] ?? 0),
                        $parroquia_id, $d['centro_votacion'] ?? null, $d['tipo_sangre'] ?? null,
                        $d['talla_camisa'] ?? null, $d['talla_pantalon'] ?? null, $d['talla_calzado'] ?? null
                    ]);

                    sincronizarPatologiasPersonal($pdo, $pid, $d['patologias'] ?? '', $d['alergias_medicas'] ?? '', $d['condiciones_especiales'] ?? '', $d['medicamentos_permanentes'] ?? '');
                }
            }
            $pdo->commit();
            registrarHistorial($pdo, 'Importación', 'ACTUALIZAR', "Restauró exitosamente la base de datos desde copia de seguridad.");
            header("Location: ../Pages/configuracion.php?msg=import_json_ok#importar"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: ../Pages/configuracion.php?msg=import_err#importar"); exit;
        }
    }
}
header("Location: ../Pages/configuracion.php#importar");
exit;