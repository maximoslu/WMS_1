<?php
/**
 * WMS_1 - Panel de Control de Stock (Multicliente)
 * Vista con desglose de Pallets y Picos
 * Estética: Minimalista y Quiet Luxury
 */
require_once '../config/setup.php';
require_once '../config/db.php';
require_once '../includes/InventarioController.php';

// Verificación de acceso por rol
$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario', 'cliente_admin'])) {
    header("Location: ../dashboard.php");
    exit;
}

$isReadOnly = ($rol === 'cliente_admin');
$cliente_id = (int)($_GET['cliente_id'] ?? 0);

// Seguridad: Si es cliente_admin, forzar a su propio ID
if ($isReadOnly) {
    $session_cid = $_SESSION['cliente_id'] ?? ($_SESSION['user_cliente_id'] ?? 0);
    if ($cliente_id != $session_cid) {
        $cliente_id = $session_cid;
    }
}

// Handler AJAX para desglose de ubicaciones
if (isset($_GET['action']) && $_GET['action'] === 'get_desglose') {
    $invController = new InventarioController($pdo);
    $art_id = $_GET['id'];
    $desglose = $invController->getDesgloseStock($art_id, $cliente_id);
    
    if (empty($desglose)) {
        echo '<div class="p-4 text-center text-muted"><i class="bi bi-info-circle me-2"></i>No hay stock ubicado físicamente.</div>';
    } else {
        echo '<div class="table-responsive"><table class="table table-sm mb-0">';
        echo '<thead class="table-light"><tr><th class="ps-3 text-muted" style="font-size:0.75rem;">UBICACIÓN</th><th class="text-end pe-3 text-muted" style="font-size:0.75rem;">CANTIDAD</th></tr></thead>';
        echo '<tbody>';
        foreach ($desglose as $d) {
            echo '<tr>
                    <td class="ps-3">
                        <div class="fw-bold text-dark">' . htmlspecialchars($d['codigo']) . '</div>
                        <small class="text-secondary">' . htmlspecialchars($d['tipo']) . '</small>
                    </td>
                    <td class="text-end pe-3 fw-bold fs-5 text-primary">' . number_format($d['cantidad'], 2) . '</td>
                  </tr>';
        }
        echo '</tbody></table></div>';
    }
    exit;
}

$articulos = [];
$total_pallets_sum = 0;
$nombre_cliente = "";

if ($cliente_id > 0) {
    try {
        // Obtener nombre del cliente
        $stmt_cli = $pdo->prepare("SELECT nombre_empresa FROM clientes WHERE id = ?");
        $stmt_cli->execute([$cliente_id]);
        $nombre_cliente = $stmt_cli->fetchColumn();

        // Obtener artículos del cliente
        $stmt = $pdo->prepare("SELECT * FROM articulos WHERE cliente_id = ? ORDER BY sku ASC");
        $stmt->execute([$cliente_id]);
        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pre-calcular KPI total de pallets
        foreach ($articulos as $art) {
            $total = (float)$art['stock_actual'];
            $paletizado_a = (int)$art['paletizado_a'];
            if ($paletizado_a > 0) {
                $total_pallets_sum += floor($total / $paletizado_a);
            }
        }
    } catch (PDOException $e) {
        $nombre_cliente = "Error al cargar datos";
    }
}

include '../includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    /* Estética Minimalista y Quiet Luxury */
    body { background-color: #f8fafc; }
    .table thead th { 
        background-color: transparent !important; 
        color: #94a3b8; 
        font-size: 0.75rem; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        border-bottom: 2px solid #f1f5f9; 
    }
    .table tbody td {
        vertical-align: middle;
        border-bottom-color: #f8fafc;
    }
    
    /* Círculos de Estado */
    .status-circle {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: white;
        margin: 0 auto;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .status-d { background-color: #34d399; } /* Verde Pastel */
    .status-b { background-color: #fb7185; } /* Rojo Pastel */
    .status-o { background-color: #94a3b8; } /* Gris Pizarra */

    /* Badges Minimalistas */
    .badge-quiet {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 600;
    }
    .badge-pallets {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .badge-picos {
        background-color: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    /* KPI Card */
    .card-kpi {
        background-color: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }
</style>

<div class="container-fluid px-4">
    <?php if ($cliente_id === 0): ?>
        <div class="row mt-5">
            <div class="col-12 d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
                <div class="bg-white p-5 rounded-4 shadow-sm text-center border border-light">
                    <i class="bi bi-diagram-3 text-primary opacity-50 mb-3" style="font-size: 3rem;"></i>
                    <h4 class="fw-bold text-dark">Panel Multicliente</h4>
                    <p class="text-muted mb-0">Seleccione un cliente del menú superior para visualizar su stock.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row mb-4 align-items-end">
            <div class="col-md-8">
                <h2 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.02em;">
                    <?= htmlspecialchars($nombre_cliente) ?>
                </h2>
                <p class="text-muted small mb-0 mt-1">Control de Stock y Desglose de Bultos</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="card-kpi d-inline-block px-4 py-3 text-start">
                    <div class="text-uppercase text-muted" style="font-size: 0.70rem; letter-spacing: 0.05em; font-weight: 700;">Total Pallets</div>
                    <div class="fs-2 fw-bold text-dark mt-1 lh-1"><?= number_format($total_pallets_sum, 0) ?></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaStockMulticliente" class="table table-hover w-100">
                        <thead>
                            <tr>
                                <th class="ps-3">SKU</th>
                                <th>DESCRIPCIÓN</th>
                                <th>LOTE</th>
                                <th class="text-center">ESTADO</th>
                                <th class="text-center">TOTAL</th>
                                <th class="text-center">PALLETS</th>
                                <th class="text-center">PICOS</th>
                                <th class="text-end pe-3">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articulos as $art): 
                                $total = (float)$art['stock_actual'];
                                $paletizado = (int)$art['paletizado_a'];
                                
                                if ($paletizado > 0) {
                                    $pallets = floor($total / $paletizado);
                                    $picos = fmod($total, $paletizado);
                                } else {
                                    $pallets = 0;
                                    $picos = $total;
                                }

                                $estado = $art['estado'] ?? 'DISPONIBLE';
                                $inicial = substr($estado, 0, 1);
                                $clase_estado = 'status-o';
                                if ($estado === 'DISPONIBLE') $clase_estado = 'status-d';
                                elseif ($estado === 'BLOQUEADO') $clase_estado = 'status-b';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($art['sku']) ?></td>
                                <td class="text-secondary"><?= htmlspecialchars($art['descripcion']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($art['lote'] ?? '-') ?></td>
                                <td class="text-center">
                                    <div class="status-circle <?= $clase_estado ?>" title="<?= htmlspecialchars($estado) ?>">
                                        <?= $inicial ?>
                                    </div>
                                </td>
                                <td class="text-center fw-bold fs-6"><?= number_format($total, 2) ?></td>
                                <td class="text-center">
                                    <?php if ($pallets > 0): ?>
                                        <span class="badge badge-pallets px-3 py-2 rounded-pill"><i class="bi bi-stack me-1"></i><?= $pallets ?></span>
                                    <?php else: ?>
                                        <span class="text-muted opacity-50">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($picos > 0): ?>
                                        <span class="badge badge-picos px-3 py-2 rounded-pill"><i class="bi bi-box me-1"></i><?= number_format($picos, 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted opacity-50">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-quiet px-2 py-1" onclick="verDesglose(<?= $art['id'] ?>, '<?= htmlspecialchars($art['sku']) ?>')" title="Ver Ubicaciones">
                                        <i class="bi bi-geo-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Desglose Stock -->
<div class="modal fade" id="modalDesglose" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0">
                <h6 class="modal-title fw-bold text-dark">Ubicaciones: <span id="lblSkuDesglose" class="text-primary"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="desgloseContent" class="p-4 text-center">
                    <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    if ($('#tablaStockMulticliente').length) {
        $('#tablaStockMulticliente').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    }
});

function verDesglose(id, sku) {
    $('#lblSkuDesglose').text(sku);
    $('#desgloseContent').html('<div class="p-4 text-center"><div class="spinner-border text-primary spinner-border-sm"></div></div>');
    
    var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDesglose'));
    modalObj.show();
    
    $.ajax({
        url: 'stock.php',
        type: 'GET',
        data: { action: 'get_desglose', id: id, cliente_id: <?= $cliente_id ?> },
        success: function(response) {
            $('#desgloseContent').html(response);
        },
        error: function() {
            $('#desgloseContent').html('<div class="p-4 text-center text-danger">Error al cargar datos.</div>');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
