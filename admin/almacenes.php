<?php
/**
 * WMS_1 - Gestión de Almacenes y Ubicaciones
 * Estética: Quiet Luxury
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    header("Location: ../dashboard.php");
    exit;
}

// Obtener almacenes
try {
    $stmt = $pdo->query("SELECT * FROM almacenes ORDER BY id DESC");
    $almacenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $almacenes = [];
}

include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><i class="bi bi-house-gear text-primary me-2"></i>Gestión de Almacenes</h2>
            <p class="text-muted small mb-0">Administra almacenes y sus ubicaciones.</p>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i> Operación realizada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- MAESTRO: ALMACENES -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0">Almacenes</h5>
                    <button class="btn btn-primary rounded-3 fw-medium btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAlmacen" onclick="prepararNuevoAlmacen()">
                        <i class="bi bi-plus-lg"></i> Nuevo
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush border-top-0">
                        <?php if (empty($almacenes)): ?>
                            <div class="text-center py-5 text-muted small">No hay almacenes registrados.</div>
                        <?php else: ?>
                            <?php foreach ($almacenes as $a): ?>
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-bottom" style="cursor: pointer; transition: background 0.2s;" onclick="cargarUbicaciones(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['nombre'])) ?>', this)">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($a['nombre']) ?> <span class="badge bg-light text-secondary border ms-2"><?= htmlspecialchars($a['codigo_almacen']) ?></span></div>
                                    <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($a['direccion'] ?? 'Sin dirección') ?></div>
                                </div>
                                <div class="d-flex gap-2" onclick="event.stopPropagation();">
                                    <button class="btn-quiet btn-quiet-edit btn-sm px-2 py-1" onclick='editarAlmacen(<?= json_encode($a) ?>)'><i class="bi bi-pencil"></i></button>
                                    <button class="btn-quiet btn-quiet-delete btn-sm px-2 py-1" onclick="eliminarAlmacen(<?= $a['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETALLE: UBICACIONES -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100" id="panelUbicaciones" style="display: none;">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0">Ubicaciones: <span id="lblAlmacenSeleccionado" class="text-primary"></span></h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary rounded-3 fw-medium btn-sm" data-bs-toggle="modal" data-bs-target="#modalGeneracionMasiva">
                            <i class="bi bi-layers"></i> Generación Masiva
                        </button>
                        <button class="btn btn-primary rounded-3 fw-medium btn-sm" data-bs-toggle="modal" data-bs-target="#modalUbicacion" onclick="prepararNuevaUbicacion()">
                            <i class="bi bi-plus-lg"></i> Nueva
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="ps-4">Ubicación</th>
                                    <th>Tipo</th>
                                    <th class="text-center pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaUbicacionesBody">
                                <!-- Carga por AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 h-100 d-flex align-items-center justify-content-center text-muted" id="panelVacio" style="min-height: 300px; background-color: #f8fafc;">
                <div class="text-center">
                    <i class="bi bi-box-seam fs-1 mb-3 d-block text-secondary opacity-50"></i>
                    <p class="mb-0 fw-medium">Selecciona un almacén para ver sus ubicaciones</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ALMACEN -->
<div class="modal fade" id="modalAlmacen" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formAlmacen" method="POST" action="procesar_almacen.php" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" id="form_accion_almacen" value="insert_almacen">
            <input type="hidden" name="id" id="almacen_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitleAlmacen">Nuevo Almacén</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NOMBRE DEL ALMACÉN</label>
                    <input type="text" name="nombre" id="nombre_almacen" class="form-control rounded-3" placeholder="Ej: Central" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">CÓDIGO (ÚNICO)</label>
                    <input type="text" name="codigo_almacen" id="codigo_almacen" class="form-control rounded-3" placeholder="Ej: ALM-01" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted">DIRECCIÓN</label>
                    <textarea name="direccion" id="direccion_almacen" class="form-control rounded-3" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: UBICACION INDIVIDUAL -->
<div class="modal fade" id="modalUbicacion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formUbicacion" method="POST" action="procesar_ubicacion.php" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" id="form_accion_ubicacion" value="insert_ubicacion">
            <input type="hidden" name="id" id="ubicacion_id">
            <input type="hidden" name="almacen_id" id="ubicacion_almacen_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitleUbicacion">Nueva Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">CÓDIGO (Ej: P01-E02-H1)</label>
                    <input type="text" name="codigo_ubicacion" id="codigo_ubicacion" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DESCRIPCIÓN (OPCIONAL)</label>
                    <textarea name="descripcion" id="descripcion_ubicacion" class="form-control rounded-3" rows="2"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted">TIPO DE UBICACIÓN</label>
                    <select name="tipo" id="tipo_ubicacion" class="form-select rounded-3">
                        <option value="Picking">Picking</option>
                        <option value="Paletización">Paletización</option>
                        <option value="Recepción">Recepción</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: GENERACION MASIVA DE UBICACIONES -->
<div class="modal fade" id="modalGeneracionMasiva" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formMasiva" method="POST" action="procesar_ubicacion.php" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" value="mass_insert_ubicaciones">
            <input type="hidden" name="almacen_id" id="masiva_almacen_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Generación Masiva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border border-primary text-primary mb-4 p-3 rounded-3 small">
                    <i class="bi bi-info-circle me-2"></i> Genera ubicaciones secuenciales automáticamente. Ej: Si Prefijo es <strong>P01-</strong> y configuras desde <strong>1</strong> hasta <strong>10</strong>, se crearán P01-01, P01-02... P01-10.
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">PREFIJO (Ej: P01-)</label>
                    <input type="text" name="prefijo" class="form-control rounded-3" required>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">DESDE NÚMERO</label>
                        <input type="number" name="desde" class="form-control rounded-3" min="1" value="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">HASTA NÚMERO</label>
                        <input type="number" name="hasta" class="form-control rounded-3" min="1" value="10" required>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted">TIPO DE UBICACIÓN</label>
                    <select name="tipo" class="form-select rounded-3">
                        <option value="Picking">Picking</option>
                        <option value="Paletización">Paletización</option>
                        <option value="Recepción">Recepción</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Generar</button>
            </div>
        </form>
    </div>
</div>

<style>
.list-group-item.active-almacen {
    background-color: #f0f5ff !important;
    border-color: #adc6ff !important;
}
</style>

<script>
let currentAlmacenId = null;

function prepararNuevoAlmacen() {
    document.getElementById('formAlmacen').reset();
    document.getElementById('form_accion_almacen').value = 'insert_almacen';
    document.getElementById('modalTitleAlmacen').textContent = 'Nuevo Almacén';
}

function editarAlmacen(data) {
    document.getElementById('modalTitleAlmacen').textContent = 'Editar Almacén';
    document.getElementById('form_accion_almacen').value = 'update_almacen';
    document.getElementById('almacen_id').value = data.id;
    document.getElementById('nombre_almacen').value = data.nombre;
    document.getElementById('codigo_almacen').value = data.codigo_almacen;
    document.getElementById('direccion_almacen').value = data.direccion || '';
    
    var modalEl = document.getElementById('modalAlmacen');
    var modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalObj.show();
}

function eliminarAlmacen(id) {
    if(confirm('¿Seguro que deseas eliminar este almacén? Se eliminarán también todas sus ubicaciones.')) {
        window.location.href = 'procesar_almacen.php?accion=delete_almacen&id=' + id;
    }
}

function cargarUbicaciones(almacenId, nombreAlmacen, element) {
    currentAlmacenId = almacenId;
    
    // Highlight selected item
    document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active-almacen'));
    if(element) element.classList.add('active-almacen');
    
    document.getElementById('lblAlmacenSeleccionado').textContent = nombreAlmacen;
    document.getElementById('panelVacio').style.display = 'none';
    document.getElementById('panelUbicaciones').style.display = 'block';
    
    document.getElementById('ubicacion_almacen_id').value = almacenId;
    document.getElementById('masiva_almacen_id').value = almacenId;
    
    const tbody = document.getElementById('tablaUbicacionesBody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> Cargando...</td></tr>';

    fetch('procesar_ubicacion.php?accion=listar&almacen_id=' + almacenId)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.error) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-danger small">Error del servidor: ' + data.error + '</td></tr>';
                return;
            }
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">No hay ubicaciones registradas en este almacén.</td></tr>';
                return;
            }
            
            data.forEach(u => {
                let badgeClass = 'bg-secondary';
                if(u.tipo === 'Picking') badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                else if(u.tipo === 'Paletización') badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
                else if(u.tipo === 'Recepción') badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-4 fw-bold text-dark" style="font-size: 0.9rem;">${u.codigo_ubicacion}</td>
                    <td><span class="badge ${badgeClass} rounded-pill px-3">${u.tipo}</span></td>
                    <td class="text-center pe-4">
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn-quiet btn-quiet-edit py-1 px-2" onclick='editarUbicacion(${JSON.stringify(u)})' title="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn-quiet btn-quiet-delete py-1 px-2" onclick="eliminarUbicacion(${u.id})" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-danger small">Error al cargar ubicaciones.</td></tr>';
        });
}

function prepararNuevaUbicacion() {
    document.getElementById('formUbicacion').reset();
    document.getElementById('form_accion_ubicacion').value = 'insert_ubicacion';
    document.getElementById('modalTitleUbicacion').textContent = 'Nueva Ubicación';
    document.getElementById('ubicacion_almacen_id').value = currentAlmacenId;
}

function editarUbicacion(data) {
    document.getElementById('modalTitleUbicacion').textContent = 'Editar Ubicación';
    document.getElementById('form_accion_ubicacion').value = 'update_ubicacion';
    document.getElementById('ubicacion_id').value = data.id;
    document.getElementById('ubicacion_almacen_id').value = data.almacen_id;
    document.getElementById('codigo_ubicacion').value = data.codigo_ubicacion;
    document.getElementById('tipo_ubicacion').value = data.tipo;
    document.getElementById('descripcion_ubicacion').value = data.descripcion || '';
    
    var modalEl = document.getElementById('modalUbicacion');
    var modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalObj.show();
}

function eliminarUbicacion(id) {
    if(confirm('¿Seguro que deseas eliminar esta ubicación?')) {
        window.location.href = 'procesar_ubicacion.php?accion=delete_ubicacion&id=' + id + '&almacen_id=' + currentAlmacenId;
    }
}

// Auto-carga si venimos de guardar una ubicación
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const almacenId = urlParams.get('almacen_id');
    if (almacenId) {
        // Encontrar el elemento de la lista para resaltarlo
        const items = document.querySelectorAll('.list-group-item');
        let selectedEl = null;
        let nombreAlmacen = 'Ubicaciones';
        items.forEach(el => {
            if (el.getAttribute('onclick').includes('cargarUbicaciones(' + almacenId + ',')) {
                selectedEl = el;
                const nameDiv = el.querySelector('.fw-bold.text-dark');
                if (nameDiv && nameDiv.childNodes[0]) {
                    nombreAlmacen = nameDiv.childNodes[0].nodeValue.trim();
                }
            }
        });
        cargarUbicaciones(almacenId, nombreAlmacen, selectedEl);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
