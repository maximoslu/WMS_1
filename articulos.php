<?php
/**
 * WMS_1 - Maestro de Artículos
 * Vista principal con DataTables y Modal de Gestión.
 */
require_once 'config/setup.php';
require_once 'config/db.php';
require_once 'includes/ArticuloController.php';
require_once 'includes/InventarioController.php';

// Verificación de acceso por rol
$rol = strtolower($_SESSION['rol'] ?? '');
$isReadOnly = ($rol === 'cliente_admin');

$controller = new ArticuloController($pdo);
$invController = new InventarioController($pdo);
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// AJAX Handler para desglose de stock por ubicación
if (isset($_GET['action']) && $_GET['action'] === 'get_desglose') {
    $art_id = $_GET['id'];
    $desglose = $invController->getDesgloseStock($art_id, $cliente_id);
    
    if (empty($desglose)) {
        echo '<div class="p-4 text-center text-muted"><i class="bi bi-info-circle me-2"></i>No hay stock disponible físicamente.</div>';
    } else {
        echo '<div class="table-responsive"><table class="table table-sm mb-0">';
        echo '<thead class="table-light"><tr><th class="ps-3">Ubicación</th><th class="text-end pe-3">Cantidad</th></tr></thead>';
        echo '<tbody>';
        foreach ($desglose as $d) {
            echo '<tr>
                    <td class="ps-3">
                        <div class="fw-bold">' . htmlspecialchars($d['codigo']) . '</div>
                        <small class="text-muted">' . htmlspecialchars($d['tipo']) . '</small>
                    </td>
                    <td class="text-end pe-3 fw-bold fs-5">' . number_format($d['cantidad'], 2) . '</td>
                  </tr>';
        }
        echo '</tbody></table></div>';
    }
    exit;
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isReadOnly) {
    $data = [
        'id'            => $_POST['id'] ?? null,
        'cliente_id'    => $cliente_id,
        'sku'           => $_POST['sku'] ?? '',
        'descripcion'   => $_POST['descripcion'] ?? '',
        'estado'        => $_POST['estado'] ?? 'DISPONIBLE',
        'stock_minimo'  => $_POST['stock_minimo'] ?? 0
    ];
    
    if ($controller->saveArticulo($data)) {
        $mensaje = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> Artículo guardado correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } else {
        $mensaje = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> Error al guardar el artículo. Verifique el SKU (debe ser único).
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

$articulos = $controller->getArticulos($cliente_id);

include 'includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    .dt-buttons .btn { border-radius: 8px; font-weight: 600; }
    .table thead th { background-color: #f1f5f9; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #64748b; border-top: none; }
    .status-pill { padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
    .status-low { background-color: #fee2e2; color: #dc2626; }
    .status-ok { background-color: #dcfce7; color: #16a34a; }
    .estado-disponible { background-color: #dcfce7; color: #16a34a; }
    .estado-bloqueado { background-color: #fee2e2; color: #dc2626; }
    .estado-obsoleto { background-color: #f1f5f9; color: #64748b; }
</style>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">Maestro de Artículos</h2>
            <p class="text-muted small mb-0">Gestión de catálogo y stock mínimo para Cliente ID: <?= $cliente_id ?></p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <?php if (!$isReadOnly): ?>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-3 fw-bold" onclick="openModal()">
                    <i class="bi bi-plus-lg me-2"></i> Nuevo Artículo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaArticulos" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th class="text-center">Min.</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Nivel Stock</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $art): 
                            $isLow = ($art['stock_actual'] <= $art['stock_minimo']);
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($art['sku']) ?></td>
                            <td><?= htmlspecialchars($art['descripcion']) ?></td>
                            <td>
                                <?php 
                                    $estado = $art['estado'] ?? 'DISPONIBLE';
                                    $estadoClass = 'estado-disponible';
                                    if ($estado === 'BLOQUEADO') $estadoClass = 'estado-bloqueado';
                                    if ($estado === 'OBSOLETO') $estadoClass = 'estado-obsoleto';
                                ?>
                                <span class="status-pill <?= $estadoClass ?>"><?= htmlspecialchars($estado) ?></span>
                            </td>
                            <td class="text-center"><?= number_format($art['stock_minimo'], 0) ?></td>
                            <td class="text-center fw-bold"><?= number_format($art['stock_actual'], 2) ?></td>
                            <td class="text-center">
                                <span class="status-pill <?= $isLow ? 'status-low' : 'status-ok' ?>">
                                    <?= $isLow ? 'Stock Bajo' : 'Normal' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <button class="btn btn-sm btn-outline-info" onclick='viewStockDetailed(<?= $art['id'] ?>, "<?= htmlspecialchars($art['sku']) ?>")' title="Ver Desglose">
                                        <i class="bi bi-geo-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick='editArticulo(<?= json_encode($art) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$isReadOnly): ?>
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Modal de Artículo -->
<div class="modal fade" id="articuloModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-brand-dark text-white rounded-top-4" style="background-color: #0f172a;">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo Artículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="articuloForm" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="art_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SKU (Código Interno)</label>
                        <input type="text" name="sku" id="art_sku" class="form-control rounded-3" required placeholder="EJ: PROD-001">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="art_descripcion" class="form-control rounded-3" rows="2" required placeholder="Nombre o descripción del producto"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-semibold">Estado</label>
                            <select name="estado" id="art_estado" class="form-select rounded-3">
                                <option value="DISPONIBLE" selected>DISPONIBLE</option>
                                <option value="BLOQUEADO">BLOQUEADO</option>
                                <option value="OBSOLETO">OBSOLETO</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-semibold">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" id="art_min" class="form-control rounded-3" value="0" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2 rounded-3 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Desglose Stock -->
<div class="modal fade" id="desgloseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-info text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Stock por Ubicación: <span id="desgloseSku"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="desgloseContent" class="p-4 text-center">
                    <div class="spinner-border text-info" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaArticulos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        pageLength: 10,
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
    });
});

function openModal() {
    $('#modalTitle').text('Nuevo Artículo');
    $('#articuloForm')[0].reset();
    $('#art_id').val('');
    $('#articuloModal').modal('show');
}

function editArticulo(data) {
    $('#modalTitle').text('Editar Artículo');
    $('#art_id').val(data.id);
    $('#art_sku').val(data.sku);
    $('#art_descripcion').val(data.descripcion);
    $('#art_estado').val(data.estado || 'DISPONIBLE');
    $('#art_min').val(data.stock_minimo);
    $('#articuloModal').modal('show');
}

function viewStockDetailed(id, sku) {
    $('#desgloseSku').text(sku);
    $('#desgloseContent').html('<div class="text-center p-4"><div class="spinner-border text-info"></div></div>');
    $('#desgloseModal').modal('show');
    
    $.ajax({
        url: 'articulos.php',
        type: 'GET',
        data: { action: 'get_desglose', id: id },
        success: function(response) {
            $('#desgloseContent').html(response);
        },
        error: function() {
            $('#desgloseContent').html('<div class="alert alert-danger m-3">Error al cargar datos.</div>');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
