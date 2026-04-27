<?php
/**
 * WMS_1 - Módulo de Entradas de Mercancía
 * Sistema 40+5: Masivo de Pallets + Picos Variables
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) { header("Location: ../dashboard.php"); exit; }

$isSuperAdmin = ($rol === 'superadmin');

// Crear tabla si no existe (el procesador ya lo hace)
try { $pdo->exec("CREATE TABLE IF NOT EXISTS entradas (id INT AUTO_INCREMENT PRIMARY KEY)"); } catch(Exception $e){}

include '../includes/header.php';
?>
<style>
.ent-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 3rem 1rem 2.5rem; text-align: center; color: #fff; border-radius: 0 0 28px 28px; margin-bottom: 2rem; }
.ent-hero h1 { font-size: 1.6rem; font-weight: 700; letter-spacing: -.03em; margin-bottom: .3rem; }
.ent-hero p  { color: #94a3b8; font-size: .85rem; }
.ent-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 20px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 2rem; }
.ent-card-header { background: linear-gradient(90deg, #0f172a, #1e3a5f); padding: 1rem 1.5rem; color: #fff; font-weight: 600; font-size: .95rem; display: flex; align-items: center; justify-content: space-between; }
.ent-card-body { padding: 1.5rem; }

.fc { border: 1px solid #e2e8f0; border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; font-family: 'Inter', sans-serif; width: 100%; background: #f8fafc; transition: border-color .2s, box-shadow .2s; }
.fc:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); background: #fff; }
.fc-label { font-size: .72rem; font-weight: 700; color: #3b82f6; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .3rem; display: block; }
.fc:disabled { background: #e2e8f0; opacity: .7; cursor: not-allowed; }

.art-search-wrap { position: relative; }
.art-search-wrap .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
#artInput { padding-left: 2.2rem; }
#artResults { position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 999; background: #fff; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.12); display: none; overflow: hidden; max-height: 280px; overflow-y: auto; }
.art-res-item { padding: .65rem 1rem; cursor: pointer; font-size: .85rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: .6rem; transition: background .15s; }
.art-res-item:hover { background: #f0f5ff; }
.art-sku-b { background: #e7f1ff; color: #0056b3; font-size: .7rem; font-weight: 700; padding: 2px 8px; border-radius: 50px; }
.art-cli { font-size: .75rem; color: #64748b; margin-left: auto; }

#palletInfoBox { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: .75rem 1rem; font-size: .83rem; color: #0c4a6e; display: none; align-items: center; gap: .5rem; }

/* Entrada de Bultos 40+5 */
.panel-405 { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 1.25rem; background: #f8fafc; }
.bultos-table { width: 100%; border-collapse: collapse; }
.bultos-table th { font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: .5rem; border-bottom: 2px solid #e2e8f0; }
.bultos-table td { padding: .5rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }

#totalDisplay { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; border-radius: 10px; padding: .85rem 1.5rem; font-weight: 700; font-size: 1.1rem; text-align: center; }
#totalDisplay small { display: block; font-size: .65rem; font-weight: 400; opacity: .8; text-transform: uppercase; }

/* Histórico */
.hist-table { width: 100%; border-collapse: collapse; }
.hist-table thead th { color: #94a3b8; font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; padding: .6rem 1rem; border-bottom: 2px solid #f1f5f9; font-weight: 700; }
.hist-table tbody tr:hover { background: #f8fafc; }
.hist-table tbody td { padding: .7rem 1rem; font-size: .84rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.badge-notif   { background: #dcfce7; color: #166534; border-radius: 50px; padding: 2px 8px; font-size: .7rem; font-weight: 700; }
.badge-nonotif { background: #f1f5f9; color: #64748b; border-radius: 50px; padding: 2px 8px; font-size: .7rem; font-weight: 700; }
.chip-pallet { display:inline-flex; align-items:center; gap:3px; background:#ecfdf5; color:#059669; border-radius:50px; padding:2px 8px; font-size:.72rem; font-weight:600; }
.chip-pico   { display:inline-flex; align-items:center; gap:3px; background:#fffbeb; color:#d97706; border-radius:50px; padding:2px 8px; font-size:.72rem; font-weight:600; }

#histSearch { max-width: 280px; }

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
        <p>Sistema 40+5: Entrada masiva de pallets y registro de picos individuales.</p>
    </div>
</div>

<div class="container px-4 pb-5">

    <!-- FORMULARIO -->
    <div class="ent-card">
        <div class="ent-card-header"><span><i class="bi bi-plus-circle me-2"></i>Nueva Entrada</span></div>
        <div class="ent-card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="fc-label">Proveedor</label>
                    <input type="text" id="f_proveedor" class="fc" placeholder="Nombre del proveedor..." autocomplete="off">
                </div>
                <div class="col-md-5">
                    <label class="fc-label">Artículo *</label>
                    <div class="art-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="artInput" class="fc" placeholder="SKU o descripción..." autocomplete="off">
                        <input type="hidden" id="f_articulo_id">
                        <div id="artResults"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fc-label">Ubicación destino</label>
                    <select id="f_ubicacion" class="fc">
                        <option value="">— Sin asignar —</option>
                    </select>
                </div>
                <div class="col-12">
                    <div id="palletInfoBox">
                        <i class="bi bi-info-circle-fill"></i>
                        <span id="palletInfoText"></span>
                    </div>
                </div>
            </div>

            <!-- PANEL 40+5 -->
            <div class="panel-405 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-boxes me-2"></i>Cantidades y Bultos</h6>
                <div class="row g-4">
                    
                    <!-- Pallets Completos -->
                    <div class="col-md-5 border-end">
                        <label class="fc-label text-success">1. Pallets Completos (Masivo)</label>
                        <p class="small text-muted mb-2">Cantidad de pallets cerrados de este artículo.</p>
                        <div class="input-group">
                            <input type="number" id="f_pallets_completos" class="form-control fc rounded-end-0" placeholder="0" min="0" oninput="recalcTotal()" disabled>
                            <span class="input-group-text bg-light border-start-0 text-muted" id="lblUdsPorPallet">uds/plt</span>
                        </div>
                    </div>

                    <!-- Picos Variables -->
                    <div class="col-md-7">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <label class="fc-label text-warning mb-0">2. Picos (Bultos Incompletos)</label>
                                <p class="small text-muted mb-0">Unidades sueltas o bultos que no llegan a un pallet completo.</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-3 fw-bold" onclick="addPico()">
                                <i class="bi bi-plus-lg me-1"></i>Añadir Pico
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="bultos-table">
                                <tbody id="picosBody">
                                    <!-- rows added by JS -->
                                    <tr id="emptyPicos"><td class="text-muted text-center py-2 small">Sin picos añadidos.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <div id="totalDisplay">
                        <small>Total Unidades</small>
                        <span id="totalVal">0</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small" id="resumenBultos"></div>
                </div>
                <div class="col-md-4">
                    <button type="button" id="btnGuardar" class="btn btn-primary w-100 rounded-3 py-2 fw-bold fs-6">
                        <i class="bi bi-check2-circle me-2"></i>Confirmar Entrada
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="ent-card">
        <div class="ent-card-header">
            <span><i class="bi bi-clock-history me-2"></i>Histórico de Entradas (Agrupado por Lote)</span>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="histSearch" class="fc" placeholder="Filtrar..." style="background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2);font-size:.8rem;padding:.35rem .75rem;">
                <button class="btn btn-sm btn-outline-light rounded-3" onclick="cargarHistorico()"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ref. Entrada</th>
                        <th>Artículo</th>
                        <th>Cliente</th>
                        <th class="text-center">Total Uds</th>
                        <th class="text-center">Desglose (Bultos)</th>
                        <th>Ubicación</th>
                        <th>Operario</th>
                        <th class="text-center">Notif.</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="histBody">
                    <tr><td colspan="10" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="toastWrap"></div>

<script>
(function(){
const PROC  = 'procesar_entrada.php';
const NOTIF = 'notificar_entrada.php';
const SRCH  = 'buscar_articulos_ajax.php';
const IS_SA = <?= $isSuperAdmin ? 'true' : 'false' ?>;

let artTimer = null, selectedArt = null, picoCount = 0;

fetch(PROC + '?accion=listar_ubicaciones').then(r=>r.json()).then(data=>{
    const sel = document.getElementById('f_ubicacion');
    data.forEach(u=>{ const o=document.createElement('option'); o.value=u.id; o.textContent=`${u.codigo_ubicacion} · ${u.tipo}`; sel.appendChild(o); });
});

/* ── Article Search ────────────── */
const artInput = document.getElementById('artInput');
const artResults = document.getElementById('artResults');
artInput.addEventListener('input', function(){
    clearTimeout(artTimer);
    const q = this.value.trim();
    if(q.length<2){artResults.style.display='none';return;}
    artTimer = setTimeout(()=>{
        fetch(SRCH+'?q='+encodeURIComponent(q)).then(r=>r.json()).then(data=>{
            artResults.innerHTML = !Array.isArray(data)||!data.length
                ? '<div class="art-res-item text-muted">Sin resultados</div>'
                : data.map(a=>`<div class="art-res-item" onclick="selectArt(${JSON.stringify(a).replace(/"/g,'&quot;')})">
                    <span class="art-sku-b">${esc(a.sku)}</span><span>${esc(a.descripcion)}</span>
                    <span class="art-cli"><i class="bi bi-buildings me-1"></i>${esc(a.cliente_nombre||'—')}</span>
                  </div>`).join('');
            artResults.style.display='block';
        });
    }, 280);
});
document.addEventListener('click', e=>{ if(!e.target.closest('.art-search-wrap')) artResults.style.display='none'; });

window.selectArt = function(art){
    selectedArt = art;
    artInput.value = `[${art.sku}] ${art.descripcion}`;
    document.getElementById('f_articulo_id').value = art.id;
    artResults.style.display='none';
    
    const pal = parseInt(art.paletizado_a)||0;
    const box = document.getElementById('palletInfoBox');
    const inpPal = document.getElementById('f_pallets_completos');
    const lblUds = document.getElementById('lblUdsPorPallet');
    
    if (pal > 0) {
        document.getElementById('palletInfoText').innerHTML = `<strong>${art.sku}</strong>: Configurado con <strong>${pal}</strong> ${art.medida||'uds'} por pallet.`;
        inpPal.disabled = false;
        lblUds.textContent = `${pal} uds/plt`;
    } else {
        document.getElementById('palletInfoText').innerHTML = `<strong>${art.sku}</strong>: Sin unidades por pallet configuradas. Utilice solo la sección de Picos.`;
        inpPal.disabled = true;
        inpPal.value = '';
        lblUds.textContent = `N/A`;
    }
    box.style.display='flex';
    recalcTotal();
};

/* ── Picos ─────────────────────── */
window.addPico = function(){
    if(picoCount===0) document.getElementById('emptyPicos').style.display='none';
    picoCount++;
    const tr = document.createElement('tr');
    tr.id = 'pico_' + picoCount;
    tr.innerHTML = `
        <td style="width:40px"><i class="bi bi-box text-warning"></i></td>
        <td><input type="number" class="fc pico-uds-input form-control-sm" min="0.01" step="0.01" placeholder="Unidades del pico..." oninput="recalcTotal()"></td>
        <td style="width:50px" class="text-end"><button class="btn btn-sm text-danger" onclick="removePico(${picoCount})"><i class="bi bi-x-circle"></i></button></td>`;
    document.getElementById('picosBody').appendChild(tr);
    tr.querySelector('.pico-uds-input').focus();
    recalcTotal();
};

window.removePico = function(n){
    document.getElementById('pico_'+n)?.remove();
    picoCount--;
    if(picoCount===0) document.getElementById('emptyPicos').style.display='';
    recalcTotal();
};

function recalcTotal(){
    let total = 0, nPicos = 0;
    
    // Pallets
    const pCompletos = parseInt(document.getElementById('f_pallets_completos').value)||0;
    const uPP = selectedArt ? (parseInt(selectedArt.paletizado_a)||0) : 0;
    total += pCompletos * uPP;
    
    // Picos
    document.querySelectorAll('.pico-uds-input').forEach(inp=>{ const v=parseFloat(inp.value)||0; if(v>0){ total+=v; nPicos++; }});
    
    const tv = document.getElementById('totalVal');
    tv.textContent = total%1===0 ? total : total.toFixed(2);
    
    const res = document.getElementById('resumenBultos');
    const totalBultos = pCompletos + nPicos;
    if(totalBultos > 0){
        res.innerHTML = `<i class="bi bi-boxes me-1 text-primary"></i><strong>${totalBultos}</strong> bulto${totalBultos>1?'s':''} en total.<br>
        <span class="text-success">${pCompletos} pallets</span> + <span class="text-warning">${nPicos} picos</span>`;
    } else { res.innerHTML = ''; }
}

/* ── Guardar ───────────────────── */
document.getElementById('btnGuardar').addEventListener('click', function(){
    const artId = document.getElementById('f_articulo_id').value;
    if(!artId){ toast('err','Selecciona un artículo.'); return; }
    
    const pCompletos = parseInt(document.getElementById('f_pallets_completos').value)||0;
    const picos = [];
    document.querySelectorAll('.pico-uds-input').forEach(inp=>{ const v=parseFloat(inp.value)||0; if(v>0) picos.push({uds:v}); });
    
    if(pCompletos === 0 && picos.length === 0){ toast('err','Añade pallets completos o algún pico.'); return; }

    const btn=this; btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    const fd=new FormData();
    fd.append('accion','guardar_bultos');
    fd.append('articulo_id',artId);
    fd.append('proveedor',document.getElementById('f_proveedor').value.trim());
    fd.append('ubicacion_id',document.getElementById('f_ubicacion').value||'');
    fd.append('pallets_completos', pCompletos);
    fd.append('picos',JSON.stringify(picos));

    fetch(PROC,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        toast(d.ok?'ok':'err',d.msg);
        if(d.ok){
            document.getElementById('f_proveedor').value='';
            document.getElementById('artInput').value='';
            document.getElementById('f_articulo_id').value='';
            document.getElementById('f_pallets_completos').value='';
            document.getElementById('palletInfoBox').style.display='none';
            document.getElementById('picosBody').innerHTML='<tr id="emptyPicos"><td class="text-muted text-center py-2 small">Sin picos añadidos.</td></tr>';
            picoCount=0; selectedArt=null;
            recalcTotal(); cargarHistorico();
        }
    }).catch(()=>toast('err','Error de red.')).finally(()=>{
        btn.disabled=false; btn.innerHTML='<i class="bi bi-check2-circle me-2"></i>Confirmar Entrada';
    });
});

/* ── Histórico ─────────────────── */
function cargarHistorico(){
    const tbody=document.getElementById('histBody');
    tbody.innerHTML='<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div></td></tr>';
    fetch(PROC+'?accion=listar').then(r=>r.json()).then(data=>{
        if(!Array.isArray(data)||!data.length){
            tbody.innerHTML='<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox d-block fs-2 mb-2 opacity-25"></i>No hay entradas.</td></tr>';
            return;
        }
        tbody.innerHTML=data.map(e=>{
            const fecha=new Date(e.fecha_corta).toLocaleDateString('es-ES');
            
            // Agrupación visual de bultos
            const pals = parseInt(e.pallets)||0;
            const pics = parseInt(e.picos)||0; // En la query e.picos es count de picos
            
            let desglose = '';
            if(pals>0) desglose += `<span class="chip-pallet"><i class="bi bi-stack"></i>${pals} plt${pals>1?'s':''}</span> `;
            if(pics>0) desglose += `<span class="chip-pico"><i class="bi bi-box"></i>${pics} pico${pics>1?'s':''}</span>`;
            if(desglose==='') desglose = '—';

            const notif=e.notificado==1?'<span class="badge-notif">✅</span>':'<span class="badge-nonotif">—</span>';
            const ref=e.bulto_ref?`<span class="text-muted fw-semibold" style="font-size:.75rem">${esc(e.bulto_ref)}</span>`:'—';
            
            return `<tr id="row_${esc(e.bulto_ref)}">
                <td class="text-muted small" style="white-space:nowrap">${fecha}</td>
                <td>${ref}</td>
                <td><div class="fw-bold" style="font-size:.82rem">[${esc(e.sku)}]</div><div class="text-muted" style="font-size:.76rem">${esc(e.art_desc)}</div></td>
                <td class="small text-muted">${esc(e.cliente_nombre||'—')}</td>
                <td class="text-center fw-bold fs-6">${parseFloat(e.unidades_total).toFixed(2)}</td>
                <td class="text-center">${desglose}<br><small class="text-muted">(${e.cantidad_bultos} bultos)</small></td>
                <td class="small">${esc(e.codigo_ubicacion||'—')}</td>
                <td class="small text-muted">${esc(e.usuario_nombre||'—')}</td>
                <td class="text-center">${notif}</td>
                <td><div class="d-flex gap-1 justify-content-end">
                    <button class="btn-quiet" onclick="notificar_ref('${esc(e.bulto_ref)}')" title="Notificar (NO IMPL)"><i class="bi bi-envelope"></i></button>
                    ${IS_SA?`<button class="btn-quiet btn-quiet-delete" onclick="eliminar_ref('${esc(e.bulto_ref)}')" title="Eliminar todo el lote"><i class="bi bi-trash"></i></button>`:''}
                </div></td>
            </tr>`;
        }).join('');
        filterHistorico();
    }).catch(()=>{ tbody.innerHTML='<tr><td colspan="10" class="text-center text-danger py-4">Error al cargar.</td></tr>'; });
}

document.getElementById('histSearch').addEventListener('input', filterHistorico);
function filterHistorico(){
    const q=(document.getElementById('histSearch').value||'').toLowerCase();
    document.querySelectorAll('#histBody tr[id]').forEach(tr=>{
        tr.style.display=!q||tr.textContent.toLowerCase().includes(q)?'':'none';
    });
}

window.notificar_ref = function(ref){ toast('inf', 'Función de notificar por lote pendiente de actualizar.'); };

window.eliminar_ref = function(ref){
    if(!confirm('¿Eliminar todos los bultos de esta entrada ('+ref+')? El stock será revertido.'))return;
    const fd=new FormData(); fd.append('accion','eliminar_ref'); fd.append('bulto_ref',ref);
    fetch(PROC,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        toast(d.ok?'ok':'err',d.msg);
        if(d.ok) document.getElementById('row_'+ref)?.remove();
    });
};

function toast(type,msg){
    const w=document.getElementById('toastWrap'),el=document.createElement('div');
    el.className=`ent-toast ${type}`;
    el.innerHTML=`<i class="bi bi-${({ok:'check-circle',err:'exclamation-circle',inf:'info-circle'}[type])}"></i>${esc(msg)}`;
    w.appendChild(el); setTimeout(()=>el.remove(),4000);
}
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

cargarHistorico();
})();
</script>

<?php include '../includes/footer.php'; ?>
