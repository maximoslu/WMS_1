<?php
/**
 * WMS_1 - Módulo de Entradas de Mercancía
 * Estética: Modern Dark / Quiet Luxury
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    header("Location: ../dashboard.php");
    exit;
}

$isSuperAdmin = ($rol === 'superadmin');

// Crear tabla entradas si no existe (invocamos el procesador sin output)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS entradas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor VARCHAR(200) DEFAULT '',
        articulo_id INT NOT NULL,
        cliente_id INT NOT NULL,
        unidades_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        pallets INT DEFAULT 0,
        picos DECIMAL(10,2) DEFAULT 0,
        ubicacion_id INT DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        documento_url VARCHAR(500) DEFAULT NULL,
        notificado TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

include '../includes/header.php';
?>
<style>
/* ── Hero ──────────────────────────────────────── */
.ent-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 3rem 1rem 2.5rem;
    text-align: center;
    color: #fff;
    border-radius: 0 0 28px 28px;
    margin-bottom: 2rem;
}
.ent-hero h1 { font-size: 1.6rem; font-weight: 700; letter-spacing: -.03em; margin-bottom: .3rem; }
.ent-hero p  { color: #94a3b8; font-size: .85rem; margin-bottom: 0; }

/* ── Form Card ─────────────────────────────────── */
.ent-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 2px 20px rgba(0,0,0,.07);
    overflow: hidden; margin-bottom: 2rem;
}
.ent-card-header {
    background: linear-gradient(90deg, #0f172a, #1e3a5f);
    padding: 1rem 1.5rem;
    color: #fff; font-weight: 600; font-size: .95rem;
    display: flex; align-items: center; gap: .6rem;
}
.ent-card-body { padding: 1.5rem; }

/* ── Form Controls ─────────────────────────────── */
.fc {
    border: 1px solid #e2e8f0; border-radius: 8px;
    padding: .5rem .75rem; font-size: .875rem;
    font-family: 'Inter', sans-serif; width: 100%;
    background: #f8fafc; transition: border-color .2s, box-shadow .2s;
}
.fc:focus {
    outline: none; border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: #fff;
}
.fc-label {
    font-size: .72rem; font-weight: 700; color: #3b82f6;
    text-transform: uppercase; letter-spacing: .08em;
    margin-bottom: .3rem; display: block;
}

/* ── Search Box ────────────────────────────────── */
.art-search-wrap { position: relative; }
.art-search-wrap .bi-search {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%); color: #94a3b8; pointer-events: none;
}
#artInput { padding-left: 2.2rem; }
#artResults {
    position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 500;
    background: #fff; border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12); display: none; overflow: hidden;
}
.art-res-item {
    padding: .65rem 1rem; cursor: pointer; font-size: .85rem;
    border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: .6rem;
    transition: background .15s;
}
.art-res-item:hover { background: #f0f5ff; }
.art-res-item:last-child { border-bottom: none; }
.art-sku-b { background: #e7f1ff; color: #0056b3; font-size: .7rem; font-weight: 700; padding: 2px 8px; border-radius: 50px; white-space: nowrap; }
.art-cli  { font-size: .75rem; color: #64748b; margin-left: auto; white-space: nowrap; }

/* ── Pallet Info Box ───────────────────────────── */
#palletInfoBox {
    background: #f0f9ff; border: 1px solid #bae6fd;
    border-radius: 10px; padding: .75rem 1rem; font-size: .83rem; color: #0c4a6e;
    display: none; align-items: center; gap: .5rem;
}

/* ── Total Badge ───────────────────────────────── */
#totalDisplay {
    background: linear-gradient(135deg, #1e40af, #2563eb);
    color: #fff; border-radius: 10px; padding: .75rem 1.25rem;
    text-align: center; font-weight: 700; font-size: 1.1rem;
    min-width: 160px;
}
#totalDisplay small { display: block; font-size: .65rem; font-weight: 400; opacity: .8; text-transform: uppercase; letter-spacing: .06em; }

/* ── Histórico Table ───────────────────────────── */
.hist-table { width: 100%; border-collapse: collapse; }
.hist-table thead th {
    background: transparent; color: #94a3b8;
    font-size: .72rem; text-transform: uppercase; letter-spacing: .07em;
    padding: .6rem 1rem; border-bottom: 2px solid #f1f5f9; font-weight: 700;
}
.hist-table tbody tr { transition: background .15s; }
.hist-table tbody tr:hover { background: #f8fafc; }
.hist-table tbody td { padding: .75rem 1rem; font-size: .85rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.badge-notif { background: #dcfce7; color: #166534; border-radius: 50px; padding: 2px 8px; font-size: .7rem; font-weight: 700; }
.badge-nonotif { background: #f1f5f9; color: #64748b; border-radius: 50px; padding: 2px 8px; font-size: .7rem; font-weight: 700; }
.pallets-chip { display:inline-flex; align-items:center; gap:4px; background:#ecfdf5; color:#059669; border-radius:50px; padding:2px 10px; font-size:.75rem; font-weight:600; }
.picos-chip   { display:inline-flex; align-items:center; gap:4px; background:#fffbeb; color:#d97706; border-radius:50px; padding:2px 10px; font-size:.75rem; font-weight:600; }

/* ── Toast ─────────────────────────────────────── */
#toastWrap { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; }
.ent-toast { background:#fff; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.12); padding:.75rem 1.25rem; font-size:.85rem; font-weight:500; display:flex; align-items:center; gap:.5rem; margin-top:.5rem; animation:fadeUp .25s ease; }
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
.ent-toast.ok  { border-left:4px solid #22c55e; color:#166534; }
.ent-toast.err { border-left:4px solid #ef4444; color:#7f1d1d; }
.ent-toast.inf { border-left:4px solid #3b82f6; color:#1e40af; }
</style>

<div class="ent-hero">
    <div class="container">
        <h1><i class="bi bi-box-arrow-in-down me-2"></i>Entradas de Mercancía</h1>
        <p>Registra recepciones · Cálculo automático de bultos · Notificación al cliente</p>
    </div>
</div>

<div class="container px-4 pb-5">

    <!-- ═══ FORMULARIO ═══════════════════════════════════════ -->
    <div class="ent-card">
        <div class="ent-card-header">
            <i class="bi bi-plus-circle"></i> Nueva Entrada
        </div>
        <div class="ent-card-body">
            <div class="row g-3">

                <!-- Proveedor -->
                <div class="col-md-4">
                    <label class="fc-label">Proveedor</label>
                    <input type="text" id="f_proveedor" class="fc" placeholder="Nombre del proveedor..." autocomplete="off">
                </div>

                <!-- Buscador de Artículo -->
                <div class="col-md-8">
                    <label class="fc-label">Artículo (SKU / Descripción) *</label>
                    <div class="art-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="artInput" class="fc" placeholder="Escribe SKU o nombre del artículo..." autocomplete="off">
                        <input type="hidden" id="f_articulo_id">
                        <div id="artResults"></div>
                    </div>
                </div>

                <!-- Info de paletizado -->
                <div class="col-12">
                    <div id="palletInfoBox">
                        <i class="bi bi-info-circle-fill text-info"></i>
                        <span id="palletInfoText"></span>
                    </div>
                </div>

                <!-- Pallets -->
                <div class="col-md-3">
                    <label class="fc-label">Pallets</label>
                    <input type="number" id="f_pallets" class="fc" min="0" value="0" placeholder="0">
                </div>

                <!-- Picos / Sueltos -->
                <div class="col-md-3">
                    <label class="fc-label">Picos / Sueltos</label>
                    <input type="number" id="f_picos" class="fc" min="0" step="0.01" value="0" placeholder="0">
                </div>

                <!-- Total (calculado) -->
                <div class="col-md-3 d-flex align-items-end">
                    <div id="totalDisplay" class="w-100">
                        <small>Total Unidades</small>
                        <span id="totalVal">0</span>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="col-md-3">
                    <label class="fc-label">Ubicación destino</label>
                    <select id="f_ubicacion" class="fc">
                        <option value="">— Sin asignar —</option>
                    </select>
                </div>

                <!-- Documento -->
                <div class="col-md-6">
                    <label class="fc-label">Documento adjunto <span class="text-muted fw-normal" style="text-transform:none;">(UI preparada · pendiente de integración)</span></label>
                    <input type="file" id="f_documento" class="fc" accept=".pdf,.jpg,.png,.xlsx" disabled>
                </div>

                <!-- Botón -->
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" id="btnGuardar" class="btn btn-primary w-100 rounded-3 py-2 fw-bold fs-6">
                        <i class="bi bi-check2-circle me-2"></i>Confirmar Entrada
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══ HISTÓRICO ════════════════════════════════════════ -->
    <div class="ent-card">
        <div class="ent-card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history me-2"></i>Histórico de Entradas</span>
            <button class="btn btn-sm btn-outline-light rounded-3" id="btnRecargar" onclick="cargarHistorico()">
                <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
            </button>
        </div>
        <div class="table-responsive">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Artículo</th>
                        <th>Cliente</th>
                        <th class="text-center">Total Uds</th>
                        <th class="text-center">Bultos</th>
                        <th>Ubicación</th>
                        <th>Operario</th>
                        <th class="text-center">Notif.</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="histBody">
                    <tr><td colspan="10" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando histórico...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="toastWrap"></div>

<script>
(function() {
const PROC  = 'procesar_entrada.php';
const NOTIF = 'notificar_entrada.php';
const SRCH  = 'buscar_articulos_ajax.php';

const IS_SUPERADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;

let artTimer = null;
let selectedArt = null; // {id, sku, descripcion, paletizado_a, medida, cliente_nombre}

/* ── Ubicaciones ─────────────────────────────────────── */
fetch(PROC + '?accion=listar_ubicaciones')
    .then(r => r.json())
    .then(data => {
        const sel = document.getElementById('f_ubicacion');
        data.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.codigo_ubicacion} · ${u.tipo}`;
            sel.appendChild(opt);
        });
    });

/* ── Article Search ──────────────────────────────────── */
const artInput   = document.getElementById('artInput');
const artResults = document.getElementById('artResults');

artInput.addEventListener('input', function() {
    clearTimeout(artTimer);
    const q = this.value.trim();
    if (q.length < 2) { artResults.style.display='none'; return; }
    artTimer = setTimeout(() => {
        fetch(SRCH + '?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data) || !data.length) {
                    artResults.innerHTML = '<div class="art-res-item text-muted">Sin resultados</div>';
                } else {
                    artResults.innerHTML = data.map(a => `
                        <div class="art-res-item" onclick="selectArt(${JSON.stringify(a).replace(/"/g,'&quot;')})">
                            <span class="art-sku-b">${esc(a.sku)}</span>
                            <span>${esc(a.descripcion)}</span>
                            <span class="art-cli"><i class="bi bi-buildings me-1"></i>${esc(a.cliente_nombre||'—')}</span>
                        </div>`).join('');
                }
                artResults.style.display = 'block';
            });
    }, 280);
});

document.addEventListener('click', e => {
    if (!e.target.closest('.art-search-wrap')) artResults.style.display = 'none';
});

window.selectArt = function(art) {
    selectedArt = art;
    artInput.value = `[${art.sku}] ${art.descripcion}`;
    document.getElementById('f_articulo_id').value = art.id;
    artResults.style.display = 'none';

    const pal = parseInt(art.paletizado_a) || 0;
    const box = document.getElementById('palletInfoBox');
    const txt = document.getElementById('palletInfoText');
    if (pal > 0) {
        txt.innerHTML = `<strong>${art.sku}</strong>: <strong>${pal}</strong> ${art.medida||'uds'} por pallet. Introduce pallets y picos para calcular el total.`;
        box.style.display = 'flex';
    } else {
        txt.innerHTML = `<strong>${art.sku}</strong>: Sin paletizado configurado. Introduce el total de unidades en "Picos".`;
        box.style.display = 'flex';
    }
    recalcTotal();
};

/* ── Pallet Calculator ───────────────────────────────── */
['f_pallets','f_picos'].forEach(id => {
    document.getElementById(id).addEventListener('input', recalcTotal);
});

function recalcTotal() {
    const pal = parseInt(document.getElementById('f_pallets').value) || 0;
    const pic = parseFloat(document.getElementById('f_picos').value) || 0;
    const uPP = selectedArt ? (parseInt(selectedArt.paletizado_a) || 0) : 0;
    const total = (pal * uPP) + pic;
    document.getElementById('totalVal').textContent = total % 1 === 0 ? total : total.toFixed(2);
    return total;
}

/* ── Guardar Entrada ─────────────────────────────────── */
document.getElementById('btnGuardar').addEventListener('click', function() {
    const artId = document.getElementById('f_articulo_id').value;
    if (!artId) { toast('err', 'Selecciona un artículo del buscador.'); return; }

    const total = recalcTotal();
    if (total <= 0) { toast('err', 'El total de unidades debe ser mayor que cero.'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

    const fd = new FormData();
    fd.append('accion', 'guardar');
    fd.append('articulo_id', artId);
    fd.append('proveedor', document.getElementById('f_proveedor').value.trim());
    fd.append('pallets', document.getElementById('f_pallets').value || 0);
    fd.append('picos', document.getElementById('f_picos').value || 0);
    fd.append('unidades_total', total);
    fd.append('ubicacion_id', document.getElementById('f_ubicacion').value || '');

    fetch(PROC, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            toast(d.ok ? 'ok' : 'err', d.msg);
            if (d.ok) {
                // Reset form
                document.getElementById('f_proveedor').value = '';
                document.getElementById('artInput').value = '';
                document.getElementById('f_articulo_id').value = '';
                document.getElementById('f_pallets').value = 0;
                document.getElementById('f_picos').value = 0;
                document.getElementById('totalVal').textContent = '0';
                document.getElementById('palletInfoBox').style.display = 'none';
                selectedArt = null;
                cargarHistorico();
            }
        })
        .catch(() => toast('err', 'Error de red.'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Confirmar Entrada';
        });
});

/* ── Histórico ───────────────────────────────────────── */
function cargarHistorico() {
    const tbody = document.getElementById('histBody');
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando...</td></tr>';

    fetch(PROC + '?accion=listar')
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data) || !data.length) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox d-block fs-2 mb-2 opacity-25"></i>No hay entradas registradas aún.</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(e => {
                const fecha = new Date(e.fecha).toLocaleString('es-ES', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
                const bultos = [
                    e.pallets > 0 ? `<span class="pallets-chip"><i class="bi bi-stack"></i>${e.pallets} plt</span>` : '',
                    e.picos   > 0 ? `<span class="picos-chip"><i class="bi bi-box"></i>${parseFloat(e.picos).toFixed(2)}</span>` : ''
                ].filter(Boolean).join(' ');

                const notifBadge = e.notificado == 1
                    ? '<span class="badge-notif">✅ Enviado</span>'
                    : '<span class="badge-nonotif">Pendiente</span>';

                const acciones = `
                    <div class="d-flex gap-1 justify-content-end">
                        <button class="btn-quiet" onclick="notificar(${e.id})" title="Notificar al cliente">
                            <i class="bi bi-envelope me-1"></i> Notificar
                        </button>
                        ${IS_SUPERADMIN ? `<button class="btn-quiet btn-quiet-delete" onclick="eliminar(${e.id})" title="Eliminar entrada"><i class="bi bi-trash"></i></button>` : ''}
                    </div>`;

                return `<tr id="row_${e.id}">
                    <td class="text-muted small" style="white-space:nowrap">${fecha}</td>
                    <td class="fw-medium">${esc(e.proveedor||'—')}</td>
                    <td><div class="fw-bold text-dark" style="font-size:.82rem">[${esc(e.sku)}]</div><div class="text-muted" style="font-size:.78rem">${esc(e.art_desc)}</div></td>
                    <td class="small text-muted">${esc(e.cliente_nombre||'—')}</td>
                    <td class="text-center fw-bold">${parseFloat(e.unidades_total).toFixed(2)}</td>
                    <td class="text-center">${bultos||'—'}</td>
                    <td class="small">${esc(e.codigo_ubicacion||'—')}</td>
                    <td class="small text-muted">${esc(e.usuario_nombre||'—')}</td>
                    <td class="text-center">${notifBadge}</td>
                    <td>${acciones}</td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Error al cargar el histórico.</td></tr>';
        });
}

/* ── Notificar ───────────────────────────────────────── */
window.notificar = function(id) {
    toast('inf', 'Enviando notificación...');
    const fd = new FormData();
    fd.append('entrada_id', id);
    fetch(NOTIF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            toast(d.ok ? 'ok' : 'err', d.msg);
            if (d.ok) cargarHistorico();
        })
        .catch(() => toast('err', 'Error de red.'));
};

/* ── Eliminar ────────────────────────────────────────── */
window.eliminar = function(id) {
    if (!confirm('¿Eliminar esta entrada? El stock será revertido.')) return;
    const fd = new FormData();
    fd.append('accion', 'eliminar');
    fd.append('id', id);
    fetch(PROC, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            toast(d.ok ? 'ok' : 'err', d.msg);
            if (d.ok) { document.getElementById('row_' + id)?.remove(); }
        });
};

/* ── Toast ───────────────────────────────────────────── */
function toast(type, msg) {
    const w = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = `ent-toast ${type}`;
    const icons = {ok:'check-circle', err:'exclamation-circle', inf:'info-circle'};
    el.innerHTML = `<i class="bi bi-${icons[type]||'info-circle'}"></i>${esc(msg)}`;
    w.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Carga inicial del histórico */
cargarHistorico();

})();
</script>

<?php include '../includes/footer.php'; ?>
