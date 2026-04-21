<?php
/**
 * WMS_1 - Gestión de STOCK
 * Vista avanzada con cálculos de paletizado y trazabilidad por lote.
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

// Gestión robusta de cliente_id: Priorizamos el de la sesión, asegurando que no sea 0 si hay un usuario logueado.
$cliente_id = (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] != 0) ? $_SESSION['cliente_id'] : ($_SESSION['user_cliente_id'] ?? 0);

// Si aún es 0 y el usuario es SuperAdmin, podría estar viendo todo o ser un error de sesión
if ($cliente_id == 0 && strtolower($_SESSION['rol'] ?? '') !== 'superadmin') {
    die("Error: No se ha podido identificar el Cliente asociado a su sesión.");
}

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
        'lote'          => $_POST['lote'] ?? null,
        'medida'        => $_POST['medida'] ?? 'Unidades',
        'paletizado_a'  => (int)($_POST['paletizado_a'] ?? 0),
        'ean_upc'       => $_POST['ean_upc'] ?? '',
        'stock_minimo'  => $_POST['stock_minimo'] ?? 0
    ];
    
    $resultado = $controller->saveArticulo($data);
    
    if ($resultado['success']) {
        $mensaje = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> Registro de stock actualizado correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } else {
        $error_msg = htmlspecialchars($resultado['error'] ?? 'Error desconocido');
        $mensaje = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Error en la Base de Datos:</div>
                        <div class="small mt-1 text-break">' . $error_msg . '</div>
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
    .badge-pallet { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; font-weight: 700; }
    .badge-pico { background-color: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; font-weight: 700; }
</style>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">Gestión de STOCK</h2>
            <p class="text-muted small mb-0">Control de inventario, paletizado y trazabilidad por lote.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <?php if (!$isReadOnly): ?>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-3 fw-bold" onclick="openModal()">
                    <i class="bi bi-plus-lg me-2"></i> Nuevo Registro
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaStock" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th>Lote</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Medida</th>
                            <th class="text-center">Pallets</th>
                            <th class="text-center">Picos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $art): 
                            $total = (float)$art['stock_actual'];
                            // Usamos las columnas generadas directamente de la base de datos para los cálculos logísticos
                            $pallets = (int)($art['pallets_completos'] ?? 0);
                            $pico = (float)($art['pico_unidades'] ?? 0);
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($art['sku']) ?></td>
                            <td><?= htmlspecialchars($art['descripcion']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($art['lote'] ?? '-') ?></td>
                            <td class="text-center fw-bold"><?= number_format($total, 2) ?></td>
                            <td class="text-center small text-muted"><?= htmlspecialchars($art['medida']) ?></td>
                            <td class="text-center">
                                <?php if ($pallets > 0): ?>
                                    <span class="badge badge-pallet px-3 py-2"><i class="bi bi-stack me-1"></i><?= $pallets ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($pico > 0): ?>
                                    <span class="badge badge-pico px-3 py-2 text-warning"><i class="bi bi-box me-1"></i><?= number_format($pico, 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <button class="btn btn-sm btn-outline-info" onclick='viewStockDetailed(<?= $art['id'] ?>, "<?= htmlspecialchars($art['sku']) ?>")' title="Ver Ubicaciones">
                                        <i class="bi bi-geo-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick='editArticulo(<?= json_encode($art) ?>)'>
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

<!-- Modal de Registro -->
<div class="modal fade" id="articuloModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-brand-dark text-white rounded-top-4" style="background-color: #0f172a;">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo Registro de Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="articuloForm" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="art_id">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SKU / Código</label>
                            <input type="text" name="sku" id="art_sku" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Descripción</label>
                            <input type="text" name="descripcion" id="art_descripcion" class="form-control rounded-3" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lote (Trazabilidad)</label>
                            <input type="text" name="lote" id="art_lote" class="form-control rounded-3" placeholder="Ej: L24-001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Medida</label>
                            <select name="medida" id="art_medida" class="form-select rounded-3">
                                <option value="Unidades">Unidades</option>
                                <option value="Metros">Metros</option>
                                <option value="Kilogramos">Kilogramos</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Paletizado A:</label>
                            <input type="number" name="paletizado_a" id="art_paletizado" class="form-control rounded-3" placeholder="Cant. por pallet">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">EAN / UPC</label>
                            <input type="text" name="ean_upc" id="art_ean" class="form-control rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stock Mínimo Alerta</label>
                            <input type="number" name="stock_minimo" id="art_min" class="form-control rounded-3" value="0">
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
                <h5 class="modal-title fw-bold">Desglose por Ubicación: <span id="desgloseSku"></span></h5>
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
    $('#tablaStock').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        pageLength: 10,
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
    });
});

function openModal() {
    $('#modalTitle').text('Nuevo Registro de Stock');
    $('#articuloForm')[0].reset();
    $('#art_id').val('');
    $('#articuloModal').modal('show');
}

function editArticulo(data) {
    $('#modalTitle').text('Editar Registro');
    $('#art_id').val(data.id);
    $('#art_sku').val(data.sku);
    $('#art_descripcion').val(data.descripcion);
    $('#art_lote').val(data.lote);
    $('#art_medida').val(data.medida);
    $('#art_paletizado').val(data.paletizado_a);
    $('#art_ean').val(data.ean_upc);
    $('#art_min').val(data.stock_minimo);
    $('#articuloModal').modal('show');
}

function viewStockDetailed(id, sku) {
    $('#desgloseSku').text(sku);
    $('#desgloseContent').html('<div class="text-center p-4"><div class="spinner-border text-info"></div></div>');
    $('#desgloseModal').modal('show');
    
    $.ajax({
        url: 'stock.php',
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
