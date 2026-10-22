// ==========================================
// 1. CONTROL DEL MENÚ LATERAL
// ==========================================
const menuToggle = document.getElementById("menu-toggle");
if (menuToggle) {
    menuToggle.addEventListener("click", function(e) {
        e.preventDefault();
        document.getElementById("wrapper").classList.toggle("toggled");
    });
}

// Función súper segura para evitar caídas de JS si falta un campo
const setVal = (id, val) => { 
    let el = document.getElementById(id); 
    if(el) el.value = val; 
}

// ==========================================
// 2. FORMULARIO DE PERSONAL (LIMPIEZA SEGURA)
// ==========================================
function limpiarFormulario() {
    if(!document.getElementById("accion")) return; 
    
    // Básicos
    setVal("accion", "crear");
    setVal("registro_id", "");
    setVal("cedula", "");
    setVal("nombres", "");
    setVal("apellidos", "");
    setVal("telefono", "");
    setVal("cargo_area_id", ""); 
    setVal("estado_laboral", "Activo"); 
    setVal("fecha_ingreso", "");

    // MPPE y Ocultos
    setVal("fecha_ingreso_mppe", "");
    setVal("cargo_nominal", "");
    setVal("codigo_cargo", "");
    setVal("turno", "Mañana");
    setVal("horas_laborales", "0");
    setVal("dependencia", "");
    setVal("codigo_dependencia", "");

    setVal("fecha_nacimiento", "");
    setVal("etnia", "");
    setVal("estado_civil", "Soltero/a");
    setVal("cantidad_hijos", "0");
    setVal("nivel_academico", "");
    setVal("correo_electronico", "");
    setVal("municipio", "");
    setVal("parroquia", "");
    setVal("direccion_habitacion", "");
    setVal("centro_votacion", "");

    setVal("tipo_sangre", "");
    setVal("patologias", "");
    setVal("medicamentos_permanentes", "");
    setVal("alergias_medicas", "");
    setVal("condiciones_especiales", "");
    setVal("talla_camisa", "");
    setVal("talla_pantalon", "");
    setVal("talla_calzado", "");
    setVal("banco_nomina", "");
    setVal("cuenta_bancaria", "");
    
    // Ocultar colapso si está abierto
    let colapso = document.getElementById("datosMiscelaneos");
    if(colapso && colapso.classList.contains('show')) {
        new bootstrap.Collapse(colapso, {toggle: false}).hide();
    }
}

// ==========================================
// 3. EDITAR REGISTRO (CARGA SEGURA)
// ==========================================
function editarRegistro(btn) {
    if(!document.getElementById("accion")) return;
    
    try {
        let d = JSON.parse(btn.getAttribute("data-empleado"));
        
        setVal("accion", "editar");
        setVal("registro_id", d.id);
        setVal("cedula", d.cedula);
        setVal("nombres", d.nombres);
        setVal("apellidos", d.apellidos);
        setVal("telefono", d.telefono || "");
        setVal("cargo_area_id", d.cargo_area_id); 
        setVal("estado_laboral", d.estado_laboral); 
        setVal("fecha_ingreso", d.fecha_ingreso);

        setVal("fecha_ingreso_mppe", d.fecha_ingreso_mppe || "");
        setVal("cargo_nominal", d.cargo_nominal || "");
        setVal("codigo_cargo", d.codigo_cargo || "");
        setVal("turno", d.turno || "Mañana");
        setVal("horas_laborales", d.horas_laborales || "0");
        setVal("dependencia", d.dependencia || "");
        setVal("codigo_dependencia", d.codigo_dependencia || "");

        setVal("fecha_nacimiento", d.fecha_nacimiento || "");
        setVal("etnia", d.etnia || "");
        setVal("estado_civil", d.estado_civil || "Soltero/a");
        setVal("cantidad_hijos", d.cantidad_hijos || "0");
        setVal("nivel_academico", d.nivel_academico || "");
        setVal("correo_electronico", d.correo_electronico || "");
        setVal("municipio", d.municipio || "");
        setVal("parroquia", d.parroquia || "");
        setVal("direccion_habitacion", d.direccion_habitacion || "");
        setVal("centro_votacion", d.centro_votacion || "");

        setVal("tipo_sangre", d.tipo_sangre || "");
        setVal("patologias", d.patologias || "");
        setVal("medicamentos_permanentes", d.medicamentos_permanentes || "");
        setVal("alergias_medicas", d.alergias_medicas || "");
        setVal("condiciones_especiales", d.condiciones_especiales || "");
        setVal("talla_camisa", d.talla_camisa || "");
        setVal("talla_pantalon", d.talla_pantalon || "");
        setVal("talla_calzado", d.talla_calzado || "");
        setVal("banco_nomina", d.banco_nomina || "");
        setVal("cuenta_bancaria", d.cuenta_bancaria || "");

    } catch (e) { console.error("Error cargando datos:", e); }
}

// ==========================================
// 4. VER EXPEDIENTE EN OFFCANVAS
// ==========================================
function verDetallesExtra(btn) {
    try {
        let d = JSON.parse(btn.getAttribute("data-empleado"));
        const setTxt = (id, val) => { let el = document.getElementById(id); if(el) el.innerText = val; }

        setTxt("oc_nombre", d.nombres + " " + d.apellidos);
        setTxt("oc_cedula", "V-" + d.cedula);
        setTxt("oc_edad", d.edad ? d.edad + " Años" : "Edad: N/A");

        let galeria = document.getElementById("oc_galeria");
        if(galeria) {
            galeria.innerHTML = "";
            if (d.galeria_archivos) {
                let lista = d.galeria_archivos.split("||");
                lista.forEach(item => {
                    let [clave, codigo, ruta] = item.split("::");
                    let badgeClass = clave === 'FOT' ? 'bg-success' : (clave === 'CED' ? 'bg-primary' : 'bg-secondary');
                    galeria.innerHTML += `<a href="${ruta}" target="_blank" class="badge ${badgeClass} text-decoration-none p-2 shadow-sm"><i class="bi bi-file-earmark-arrow-down me-1"></i> ${codigo}</a>`;
                });
            } else {
                galeria.innerHTML = '<span class="text-muted small">No hay archivos adjuntos.</span>';
            }
        }

        setTxt("oc_cargo_nominal", d.cargo_nominal || "N/A");
        setTxt("oc_codigo_cargo", d.codigo_cargo || "S/C");
        setTxt("oc_dependencia", d.dependencia || "N/A");
        setTxt("oc_cod_dependencia", d.codigo_dependencia || "S/C");
        setTxt("oc_turno", d.turno || "Mañana");
        setTxt("oc_horas", d.horas_laborales || "0");
        setTxt("oc_fecha_mppe", d.fecha_ingreso_mppe || "No registrada");
        setTxt("oc_servicio", (d.anios_servicio !== null) ? d.anios_servicio + " Años" : "0 Años");

        setTxt("oc_civil", d.estado_civil || "N/A");
        setTxt("oc_hijos", d.cantidad_hijos || "0");
        setTxt("oc_etnia", d.etnia || "N/A");
        setTxt("oc_academico", d.nivel_academico || "N/A");
        setTxt("oc_correo", d.correo_electronico || "No registrado");
        setTxt("oc_direccion", d.direccion_habitacion || "No registrada");
        setTxt("oc_mun_parr", (d.municipio || "-") + " / " + (d.parroquia || "-"));
        setTxt("oc_votacion", d.centro_votacion || "No asignado");

        setTxt("oc_sangre", d.tipo_sangre || "N/A");
        setTxt("oc_patologias", d.patologias || "Ninguna");
        setTxt("oc_medicamentos", d.medicamentos_permanentes || "Ninguno");
        setTxt("oc_camisa", d.talla_camisa || "-");
        setTxt("oc_pantalon", d.talla_pantalon || "-");
        setTxt("oc_calzado", d.talla_calzado || "-");
        setTxt("oc_banco", d.banco_nomina || "N/A");
        setTxt("oc_cuenta", d.cuenta_bancaria || "N/A");
    } catch (e) { console.error("Error Offcanvas:", e); }
}

// ==========================================
// 5. AGREGAR CARGO RÁPIDO (AJAX REPARADO)
// ==========================================
function agregarCargoRapido(tipoPersonalId, selectId) {
    setVal('quick_tipo_id', tipoPersonalId);
    setVal('quick_select_id', selectId);
    setVal('quick_nombre_cargo', ''); 
    setVal('quick_descripcion_cargo', ''); 
    new bootstrap.Modal(document.getElementById('modalQuickAddCargo')).show();
}

async function guardarCargoModal() {
    let nombre = document.getElementById('quick_nombre_cargo').value.trim();
    let descripcion = document.getElementById('quick_descripcion_cargo').value.trim();
    let tipoId = document.getElementById('quick_tipo_id').value;
    let selectId = document.getElementById('quick_select_id').value;
    let btn = document.getElementById('btnGuardarQuick');

    if (nombre === "") { alert("Por favor escriba el nombre."); return; }

    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        let formData = new FormData();
        formData.append('nombre', nombre);
        formData.append('descripcion', descripcion);
        formData.append('tipo_personal_id', tipoId);

        let response = await fetch('../PHP/ajax_agregar_cargo.php', { method: 'POST', body: formData });
        let data = await response.json();

        if (data.success) {
            let select = document.getElementById(selectId);
            if(select) {
                select.add(new Option(data.nombre, data.id));
                select.value = data.id; 
            }
            bootstrap.Modal.getInstance(document.getElementById('modalQuickAddCargo')).hide();
        } else { alert("Error: " + data.error); }
    } catch (error) { alert("Error de conexión al guardar."); }
    
    btn.disabled = false; btn.innerHTML = 'Guardar en Catálogo';
}