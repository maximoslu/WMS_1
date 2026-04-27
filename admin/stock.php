<?php
/**
 * WMS_1 - Panel de Stock (Multicliente)
 * Lógica de bultos reales desde tabla entradas. Sin DataTables.
 */
require_once '../config/setup.php';
require_once '../config/db.php';
require_once '../includes/InventarioController.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario', 'cliente_admin'])) { header("Location: ../dashboard.php"); exit; }

$isReadOnly = ($rol === 'cliente_admin');
$cliente_id = (int)($_GET['cliente_id'] ?? 0);
if ($isReadOnly) {
    $cid = $_SESSION['cliente_id'] ?? ($_SESSION['user_cliente_id'] ?? 0);
    if ($cliente_id != $cid) $cliente_id = $cid;
}

/* ── AJAX: desglose ubicaciones ── */
if (isset($_GET['action']) && $_GET['action'] === 'get_desglose') {
    $inv = new InventarioController($pdo);
    $d   = $inv->getDesgloseStock($_GET['id'], $cliente_id);
    if (empty($d)) { echo '<div class="p-4 text-center text-muted"><i class="bi bi-info-circle me-2"></i>Sin stock ubicado.</div>'; }
    else {
        echo '<table class="table table-sm mb-0"><thead class="table-light"><tr><th class="ps-3 small text-muted">UBICACIÓN</th><th class="text-end pe-3 small text-muted">CANTIDAD</th></tr></thead><tbody>';
        foreach ($d as $r) echo '<tr><td class="ps-3"><strong>'.htmlspecialchars($r['codigo']).'</strong><br><small class="text-muted">'.htmlspecialchars($r['tipo']).'</small></td><td class="text-end pe-3 fw-bold text-primary">'.number_format($r['cantidad'],2).'</td></tr>';
        echo '</tbody></table>';
    }
    exit;
}

/* ── AJAX: bultos por artículo ─── */
if (isset($_GET['action']) && $_GET['action'] === 'get_entradas_articulo') {
    $aid = (int)($_GET['art_id'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT DATE(e.fecha) as fecha_corta, e.proveedor, e.bulto_ref,
                e.pallets, e.picos, e.unidades_total, ub.codigo_ubicacion, u.nombre AS op,
                COUNT(e.id) as qty_bultos, SUM(e.unidades_total) as suma_unidades
            FROM entradas e
            LEFT JOIN ubicaciones ub ON ub.id = e.ubicacion_id
            LEFT JOIN users u ON u.id = e.usuario_id
            WHERE e.articulo_id = :aid AND e.cliente_id = :cid
            GROUP BY DATE(e.fecha), e.bulto_ref, e.proveedor, e.unidades_total, e.pallets, e.picos, ub.codigo_ubicacion, u.nombre
            ORDER BY fecha_corta DESC LIMIT 100");
        $st->execute([':aid'=>$aid,':cid'=>$cliente_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) { echo '<div class="p-4 text-center text-muted small">No hay entradas para este artículo.</div>'; exit; }
        echo '<table class="table table-sm table-hover mb-0 small">';
        echo '<thead class="table-light"><tr><th class="ps-3">Fecha</th><th>Ref.</th><th>Proveedor</th><th class="text-center">Total Uds Grupo</th><th class="text-center">Bultos</th><th>Ubicación</th><th>Operario</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $f = date('d/m/Y', strtotime($r['fecha_corta']));
            $qty = (int)$r['qty_bultos'];
            $pal = (int)$r['pallets']; 
            
            if ($pal > 0) {
                $tipo = '<span style="background:#ecfdf5;color:#059669;border-radius:50px;padding:2px 8px;font-weight:600"><i class="bi bi-stack me-1"></i>'.$qty.' pallet'.($qty>1?'s':'').' ('.number_format($r['unidades_total'],0).' uds/u)</span>';
            } else {
                $tipo = '<span style="background:#fffbeb;color:#d97706;border-radius:50px;padding:2px 8px;font-weight:600"><i class="bi bi-box me-1"></i>'.$qty.' pico'.($qty>1?'s':'').' ('.number_format($r['unidades_total'],2).' uds/u)</span>';
            }

            echo '<tr><td class="ps-3 text-muted">'.$f.'</td><td class="text-muted" style="font-size:.7rem">'.htmlspecialchars($r['bulto_ref']??'—').'</td>
                <td>'.htmlspecialchars($r['proveedor']?:'—').'</td>
                <td class="text-center fw-bold text-dark">'.number_format($r['suma_unidades'],2).'</td>
                <td class="text-center">'.$tipo.'</td>
                <td>'.htmlspecialchars($r['codigo_ubicacion']??'—').'</td>
                <td class="text-muted">'.htmlspecialchars($r['op']??'—').'</td></tr>';
        }
        echo '</tbody></table>';
    } catch (PDOException $e) { echo '<div class="p-3 text-danger">'.htmlspecialchars($e->getMessage()).'</div>'; }
    exit;
}

/* ── Carga principal ─────────────────────────────────── */
$articulos = []; $kpi_bultos = 0; $nombre_cliente = '';

if ($cliente_id > 0) {
    try {
        $nombre_cliente = $pdo->prepare("SELECT nombre_empresa FROM clientes WHERE id=?")->execute([$cliente_id]) ? '' : '';
        $st = $pdo->prepare("SELECT nombre_empresa FROM clientes WHERE id=?"); $st->execute([$cliente_id]); $nombre_cliente = $st->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a.*,
                COALESCE(SUM(e.pallets), 0)  AS real_pallets,
                COALESCE(COUNT(e.id), 0)     AS total_bultos,
                COALESCE(SUM(CASE WHEN e.picos > 0 THEN 1 ELSE 0 END),0) AS num_picos
            FROM articulos a
            LEFT JOIN entradas e ON e.articulo_id = a.id AND e.cliente_id = a.cliente_id
            WHERE a.cliente_id = ?
            GROUP BY a.id ORDER BY a.sku ASC");
        $stmt->execute([$cliente_id]);
        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($articulos as $art) $kpi_bultos += (int)$art['total_bultos'];
    } catch (PDOException $e) { $nombre_cliente = 'Error'; }
}

include '../includes/header.php';
?>
<style>
body { background:#f8fafc; }
.am-hero { background: linear-gradient(135deg,#0f172a 0%,#1e293b 100%); padding:3rem 1rem 2.5rem; text-align:center; color:#fff; border-radius:0 0 28px 28px; margin-bottom:2rem; }
.am-hero h1 { font-size:1.6rem; font-weight:700; letter-spacing:-.03em; }
.am-hero p  { color:#94a3b8; font-size:.85rem; margin-bottom:0; }
.card-kpi { background:rgba(255,255,255,.1); border-radius:12px; border:1px solid rgba(255,255,255,.1); backdrop-filter:blur(10px); padding:.75rem 1.5rem; display:inline-block; text-align:start; margin:.25rem; }
.card-kpi .label { color:#94a3b8; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.card-kpi .value { color:#fff; font-size:1.5rem; font-weight:700; }

/* Stock filter */
#stockSearch { max-width:320px; border-radius:8px; border:1px solid #e2e8f0; padding:.45rem .75rem; font-size:.875rem; }

/* Table */
.stock-table { width:100%; border-collapse:collapse; }
.stock-table thead th { color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; padding:.65rem 1rem; border-bottom:2px solid #f1f5f9; }
.stock-table tbody tr { transition:background .12s; }
.stock-table tbody tr:hover { background:#f8fafc; }
.stock-table tbody td { padding:.75rem 1rem; border-bottom:1px solid #f8fafc; vertical-align:middle; font-size:.875rem; }
.status-circle { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:700; color:#fff; }
.status-d{background:#34d399;} .status-b{background:#fb7185;} .status-o{background:#94a3b8;}
.chip-plt { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; border-radius:50px; padding:2px 10px; font-size:.75rem; font-weight:600; }
.chip-blt { background:#f0f5ff; color:#3b82f6; border:1px solid #bfdbfe; border-radius:50px; padding:2px 10px; font-size:.75rem; font-weight:600; }

/* Expand */
.expand-btn { cursor:pointer; color:#94a3b8; transition:transform .2s,color .2s; display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:4px; }
.expand-btn:hover { color:#3b82f6; background:#f0f5ff; }
.expand-btn.open { transform:rotate(90deg); color:#3b82f6; }
.desglose-row { display:none; }
.desglose-row.show { display:table-row; }
.desglose-inner { background:#f8fafc; border-bottom:2px solid #e2e8f0 !important; padding:0 !important; }
</style>

<div class="am-hero">
    <div class="container">
        <?php if ($cliente_id === 0): ?>
            <h1 class="mb-1"><i class="bi bi-box-seam me-2"></i>Panel Multicliente</h1>
            <p>Seleccione un cliente del menú STOCK para ver su inventario</p>
        <?php else: ?>
            <h1 class="mb-1"><i class="bi bi-box-seam me-2"></i><?= htmlspecialchars($nombre_cliente) ?></h1>
            <p class="mb-3">Stock basado en bultos reales registrados</p>
            <div class="card-kpi mt-2">
                <div class="label"><i class="bi bi-boxes me-1"></i>Bultos Totales (Ocupación)</div>
                <div class="value"><?= number_format($kpi_bultos, 0) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container-fluid px-4">
<?php if ($cliente_id === 0): ?>
    <div class="d-flex align-items-center justify-content-center" style="min-height:300px">
        <div class="bg-white p-5 rounded-4 shadow-sm text-center" style="max-width:520px">
            <i class="bi bi-diagram-3 text-primary opacity-50 mb-3" style="font-size:3rem"></i>
            <h4 class="fw-bold">Panel Multicliente</h4>
            <p class="text-muted mb-0">Seleccione un cliente del menú <strong>STOCK</strong> para ver el inventario.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted small"><?= count($articulos) ?> artículos</div>
                <input type="text" id="stockSearch" placeholder="🔍  Filtrar SKU o descripción..." oninput="filtrarStock(this.value)">
            </div>
            <div class="table-responsive">
                <table class="stock-table" id="stockTable">
                    <thead>
                        <tr>
                            <th style="width:32px"></th>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Total Uds</th>
                            <th class="text-center">Pallets reales</th>
                            <th class="text-center">Bultos totales</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($articulos as $art):
                        $total    = (float)$art['stock_actual'];
                        $pallets  = (int)$art['real_pallets'];
                        $bultos   = (int)$art['total_bultos'];
                        $estado   = $art['estado'] ?? 'DISPONIBLE';
                        $cls      = $estado==='DISPONIBLE'?'status-d':($estado==='BLOQUEADO'?'status-b':'status-o');
                    ?>
                        <tr class="stock-main-row" data-sku="<?= strtolower(htmlspecialchars($art['sku'])) ?>" data-desc="<?= strtolower(htmlspecialchars($art['descripcion'])) ?>">
                            <td class="text-center">
                                <i class="bi bi-chevron-right expand-btn" onclick="toggleBultos(<?= $art['id'] ?>,this)" title="Ver bultos"></i>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($art['sku']) ?></td>
                            <td class="text-secondary"><?= htmlspecialchars($art['descripcion']) ?></td>
                            <td class="text-center"><div class="status-circle <?= $cls ?>" title="<?= htmlspecialchars($estado) ?>"><?= substr($estado,0,1) ?></div></td>
                            <td class="text-center fw-bold fs-6"><?= number_format($total,2) ?></td>
                            <td class="text-center">
                                <?= $pallets > 0
                                    ? '<span class="chip-plt"><i class="bi bi-stack me-1"></i>'.$pallets.'</span>'
                                    : '<span class="text-muted opacity-50">—</span>' ?>
                            </td>
                            <td class="text-center">
                                <?= $bultos > 0
                                    ? '<span class="chip-blt"><i class="bi bi-boxes me-1"></i>'.$bultos.'</span>'
                                    : '<span class="text-muted opacity-50">—</span>' ?>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-quiet px-2 py-1" onclick="verUbicaciones(<?= $art['id'] ?>,'<?= htmlspecialchars($art['sku']) ?>')" title="Ubicaciones">
                                    <i class="bi bi-geo-alt"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="desglose-row" id="desglose_<?= $art['id'] ?>">
                            <td colspan="8" class="desglose-inner">
                                <div id="desgloseData_<?= $art['id'] ?>" class="text-center py-3 text-muted small">
                                    <div class="spinner-border spinner-border-sm text-primary"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($articulos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox d-block fs-2 mb-2 opacity-25"></i>No hay artículos registrados para este cliente.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- Modal Ubicaciones -->
<div class="modal fade" id="modalUbi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0">
                <h6 class="modal-title fw-bold">Ubicaciones: <span id="lblSkuUbi" class="text-primary"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0"><div id="ubiContent" class="p-4 text-center"><div class="spinner-border text-primary spinner-border-sm"></div></div></div>
        </div>
    </div>
</div>

<script>
const CLIENTE_ID = <?= $cliente_id ?>;
const loaded = {};

function filtrarStock(q){
    q = q.toLowerCase().trim();
    document.querySelectorAll('.stock-main-row').forEach(tr=>{
        const match = !q || tr.dataset.sku.includes(q) || tr.dataset.desc.includes(q);
        const desId = tr.nextElementSibling?.id;
        tr.style.display = match ? '' : 'none';
        if(tr.nextElementSibling) tr.nextElementSibling.style.display = match && tr.nextElementSibling.classList.contains('show') ? '' : 'none';
    });
}

function toggleBultos(artId, icon){
    const row = document.getElementById('desglose_'+artId);
    const open = row.classList.contains('show');
    if(open){ row.classList.remove('show'); icon.classList.remove('open'); return; }
    row.classList.add('show'); icon.classList.add('open');
    if(!loaded[artId]){
        loaded[artId]=true;
        fetch(`stock.php?action=get_entradas_articulo&art_id=${artId}&cliente_id=${CLIENTE_ID}`)
            .then(r=>r.text()).then(html=>document.getElementById('desgloseData_'+artId).innerHTML=html)
            .catch(()=>document.getElementById('desgloseData_'+artId).innerHTML='<div class="text-danger p-3">Error al cargar.</div>');
    }
}

function verUbicaciones(id, sku){
    document.getElementById('lblSkuUbi').textContent=sku;
    document.getElementById('ubiContent').innerHTML='<div class="p-4 text-center"><div class="spinner-border text-primary spinner-border-sm"></div></div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUbi')).show();
    fetch(`stock.php?action=get_desglose&id=${id}&cliente_id=${CLIENTE_ID}`)
        .then(r=>r.text()).then(html=>document.getElementById('ubiContent').innerHTML=html);
}
</script>

<?php include '../includes/footer.php'; ?>
