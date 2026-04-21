<?php
/**
 * WMS_1 - Gestión de Ubicaciones
 * Vista para mapear los espacios físicos del almacén.
 */
require_once 'config/setup.php';
require_once 'config/db.php';
require_once 'includes/UbicacionController.php';

$controller = new UbicacionController($pdo);
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id'            => $_POST['id'] ?? null,
        'cliente_id'    => $cliente_id,
        'codigo'        => strtoupper($_POST['codigo'] ?? ''),
        'descripcion'   => $_POST['descripcion'] ?? '',
        'tipo'          => $_POST['tipo'] ?? 'PICKING'
    ];
    
    if ($controller->saveUbicacion($data)) {
        $mensaje = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> Ubicación guardada exitosamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } else {
        $mensaje = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> Error al guardar. El código de ubicación debe ser único para este cliente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

$ubicaciones = $controller->getUbicaciones($cliente_id);

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    .badge-type { font-size: 0.75rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; }
    .type-picking { background-color: #dcfce7; color: #16a34a; }
    .type-pulmon { background-color: #dbeafe; color: #2563eb; }
    .type-recepcion { background-color: #fef9c3; color: #ca8a04; }
    .type-expedicion { background-color: #f3e8ff; color: #9333ea; }
</style>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">Gestión de Ubicaciones</h2>
            <p class="text-muted small">Mapeo físico de zonas de almacenamiento para Cliente ID: <?= $cliente_id ?></p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button type="button" class="btn btn-primary px-4 py-2 rounded-3 fw-bold" onclick="openModal()">
                <i class="bi bi-plus-lg me-2"></i> Nueva Ubicación
            </button>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaUbicaciones" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Tipo de Zona</th>
                            <th class="text-center">Artículos Actuales</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ubicaciones as $ubi): ?>
                        <tr>
                            <td class="fw-bold text-dark"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><?= htmlspecialchars($ubi['codigo']) ?></td>
                            <td><?= htmlspecialchars($ubi['descripcion']) ?></td>
                            <td>
                                <span class="badge-type type-<?= strtolower($ubi['tipo']) ?>">
                                    <?= htmlspecialchars($ubi['tipo']) ?>
                                </span>
                            </td>
                            <td class="text-center">--</td> <!-- Se implementará con inventario_ubicaciones -->
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <button class="btn btn-sm btn-outline-secondary" onclick='editUbicacion(<?= json_encode($ubi) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ubicación -->
<div class="modal fade" id="ubicacionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-brand-dark text-white rounded-top-4" style="background-color: #0f172a;">
                <h5 class="modal-title fw-bold" id="modalTitle">Configurar Ubicación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="ubicacionForm" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="ubi_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Código de Ubicación</label>
                        <input type="text" name="codigo" id="ubi_codigo" class="form-control rounded-3" required placeholder="EJ: A-01-01-A">
                        <div class="form-text">Use un formato consistente (Pasillo-Estantería-Nivel).</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción / Referencia</label>
                        <input type="text" name="descripcion" id="ubi_descripcion" class="form-control rounded-3" placeholder="EJ: Pasillo Principal, Zona Fría">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de Ubicación</label>
                        <select name="tipo" id="ubi_tipo" class="form-select rounded-3">
                            <option value="PICKING">PICKING (Acceso directo)</option>
                            <option value="PULMON">PULMON (Reserva/Altura)</option>
                            <option value="RECEPCION">RECEPCIÓN (Muelle entrada)</option>
                            <option value="EXPEDICION">EXPEDICIÓN (Muelle salida)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2 rounded-3 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold">Guardar Parámetros</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaUbicaciones').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
    });
});

function openModal() {
    $('#modalTitle').text('Nueva Ubicación');
    $('#ubicacionForm')[0].reset();
    $('#ubi_id').val('');
    $('#ubicacionModal').modal('show');
}

function editUbicacion(data) {
    $('#modalTitle').text('Editar Ubicación');
    $('#ubi_id').val(data.id);
    $('#ubi_codigo').val(data.codigo);
    $('#ubi_descripcion').val(data.descripcion);
    $('#ubi_tipo').val(data.tipo);
    $('#ubicacionModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>
