<?php
/**
 * WMS_1 - Maestro de Artículos (SuperAdmin)
 * Buscador global + Panel de propiedades dinámico
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    header("Location: ../dashboard.php");
    exit;
}

// Cargar familias y clientes para los selects
try {
    $familias = $pdo->query("SELECT id, nombre_familia FROM familias ORDER BY nombre_familia ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $familias = []; }

try {
    $clientes = $pdo->query("SELECT id, nombre_empresa FROM clientes ORDER BY nombre_empresa ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $clientes = []; }

include '../includes/header.php';
?>
<style>
/* ── Page Layout ─────────────────────────────── */
.am-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 3rem 1rem 2.5rem;
    text-align: center;
    color: #fff;
    border-radius: 0 0 28px 28px;
    margin-bottom: 2rem;
}
.am-hero h1 { font-size: 1.6rem; font-weight: 700; letter-spacing: -.03em; margin-bottom: .25rem; }
.am-hero p  { color: #94a3b8; font-size: .85rem; margin-bottom: 1.5rem; }

/* ── Search Box ──────────────────────────────── */
.search-wrap { position: relative; max-width: 640px; margin: 0 auto; }
.search-wrap .bi-search {
    position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
    font-size: 1.1rem; color: #64748b; pointer-events: none;
}
#searchInput {
    width: 100%; padding: .85rem 1rem .85rem 3rem;
    border: none; border-radius: 14px;
    font-size: 1rem; font-family: 'Inter', sans-serif;
    box-shadow: 0 4px 24px rgba(0,0,0,.25);
    outline: none; transition: box-shadow .2s;
}
#searchInput:focus { box-shadow: 0 4px 32px rgba(59,130,246,.35); }

/* ── Results Dropdown ────────────────────────── */
#searchResults {
    position: absolute; top: calc(100% + 8px); left: 0; right: 0;
    background: #fff; border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    z-index: 999; overflow: hidden;
    display: none;
}
.result-item {
    padding: .75rem 1.25rem;
    cursor: pointer; transition: background .15s;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: .75rem;
}
.result-item:last-child { border-bottom: none; }
.result-item:hover, .result-item.active { background: #f0f5ff; }
.result-item .sku-badge {
    background: #e7f1ff; color: #0056b3;
    font-size: .7rem; font-weight: 700;
    padding: 2px 8px; border-radius: 50px;
    white-space: nowrap;
}
.result-item .desc { font-size: .88rem; color: #1e293b; font-weight: 500; }
.result-item .client-tag { font-size: .8rem; color: #475569; font-weight: 600; margin-left: auto; white-space: nowrap; }
.no-results { padding: 1.25rem; text-align: center; color: #94a3b8; font-size: .9rem; }

/* ── Property Panel ──────────────────────────── */
#propPanel {
    display: none;
    animation: slideDown .3s cubic-bezier(.4,0,.2,1);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.prop-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    overflow: hidden;
}
.prop-card-header {
    background: linear-gradient(90deg, #0f172a, #1e3a5f);
    padding: 1.1rem 1.5rem;
    display: flex; align-items: center; gap: .75rem;
}
.prop-card-header .art-sku-label {
    color: #93c5fd; font-size: .75rem; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
}
.prop-card-header .art-desc-label {
    color: #fff; font-size: 1rem; font-weight: 600;
}
.prop-section {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
}
.prop-section:last-of-type { border-bottom: none; }
.prop-section-title {
    font-size: .68rem; font-weight: 700; color: #3b82f6;
    text-transform: uppercase; letter-spacing: .1em;
    margin-bottom: .75rem;
}
.form-label-sm {
    font-size: .75rem; font-weight: 600; color: #64748b;
    margin-bottom: .25rem; display: block;
}
.form-control-quiet, .form-select-quiet {
    border: 1px solid #e2e8f0; border-radius: 8px;
    padding: .45rem .75rem; font-size: .875rem;
    font-family: 'Inter', sans-serif;
    transition: border-color .2s, box-shadow .2s;
    width: 100%;
    background: #f8fafc;
}
.form-control-quiet:focus, .form-select-quiet:focus {
    outline: none; border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: #fff;
}
.prop-actions { padding: 1rem 1.5rem; display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; background: #f8fafc; }

.create-new-prompt {
    padding: 1.25rem; text-align: center; border-top: 1px solid #f1f5f9;
    background-color: #f8fafc;
}
.create-new-prompt a {
    color: #3b82f6; font-weight: 600; text-decoration: none;
    font-size: 0.9rem;
}
.create-new-prompt a:hover { text-decoration: underline; }

/* ── Toast ───────────────────────────────────── */
#toastWrap { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; }
.am-toast {
    background: #fff; border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    padding: .75rem 1.25rem;
    font-size: .85rem; font-weight: 500;
    display: flex; align-items: center; gap: .5rem;
    margin-top: .5rem;
    animation: fadeInUp .25s ease;
}
@keyframes fadeInUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
.am-toast.ok  { border-left: 4px solid #22c55e; color: #166534; }
.am-toast.err { border-left: 4px solid #ef4444; color: #7f1d1d; }

/* ── Familia Modal list ───────────────────────── */
.fam-row { display:flex; align-items:center; gap:.5rem; padding:.5rem 0; border-bottom:1px solid #f1f5f9; }
.fam-row:last-child { border-bottom:none; }
.fam-row .fam-name { flex:1; font-size:.875rem; font-weight:500; }
</style>

<style>
.search-container {
    display: flex; gap: 1rem; align-items: center; max-width: 740px; margin: 0 auto;
}
.search-wrap { position: relative; flex: 1; }
.btn-nuevo-main {
    background: #3b82f6; color: #fff;
    border: none; border-radius: 14px;
    padding: .85rem 1.5rem; font-weight: 600; font-size: 0.95rem;
    box-shadow: 0 4px 15px rgba(59,130,246,.4);
    transition: all .2s;
    white-space: nowrap;
    position: relative; z-index: 1000;
}
.btn-nuevo-main:hover {
    background: #2563eb; transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59,130,246,.5);
}
/* ... rest of the hero CSS stays the same ... */
</style>

<div class="am-hero">
    <h1><i class="bi bi-box2-heart me-2"></i>Maestro de Artículos</h1>
    <p>Búsqueda global en tiempo real · Panel de propiedades · Gestión de familias</p>
    <div class="search-container">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Buscar referencia o descripción..." autocomplete="off">
            <div id="searchResults"></div>
        </div>
        <button type="button" class="btn-nuevo-main" onclick="prepararNuevoConSKU('')">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Artículo
        </button>
    </div>
</div>


<div class="container px-4 pb-5">

    <!-- Property Panel -->
    <div id="propPanel">
        <form id="propForm">
            <input type="hidden" id="art_id" name="id">
            <div class="prop-card">
                <!-- Header -->
                <div class="prop-card-header">
                    <div>
                        <div class="art-sku-label" id="hdr_sku">SKU</div>
                        <div class="art-desc-label" id="hdr_desc">Selecciona un artículo</div>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn-quiet" id="btnAdminFamilias" data-bs-toggle="modal" data-bs-target="#modalFamilias">
                            <i class="bi bi-tags me-1"></i> Administrar Familias
                        </button>
                    </div>
                </div>

                <!-- Identificación -->
                <div class="prop-section">
                    <div class="prop-section-title"><i class="bi bi-fingerprint me-1"></i>Identificación</div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label-sm">SKU *</label>
                            <input type="text" name="sku" id="f_sku" class="form-control-quiet" required>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label-sm">Descripción *</label>
                            <input type="text" name="descripcion" id="f_desc" class="form-control-quiet" required>
                        </div>
                    </div>
                </div>

                <!-- Logística -->
                <div class="prop-section">
                    <div class="prop-section-title"><i class="bi bi-truck me-1"></i>Logística</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-sm">Unidades por Pallet</label>
                            <input type="number" name="paletizado_a" id="f_pallet" class="form-control-quiet" min="0" step="1" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-sm">Unidad de Medida</label>
                            <select name="medida" id="f_medida" class="form-select-quiet">
                                <option value="Uds">Uds</option>
                                <option value="Kg">Kg</option>
                                <option value="Mt">Mt</option>
                                <option value="Cajas">Cajas</option>
                                <option value="Litros">Litros</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Clasificación -->
                <div class="prop-section">
                    <div class="prop-section-title"><i class="bi bi-diagram-3 me-1"></i>Clasificación</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-sm">Familia</label>
                            <select name="familia_id" id="f_familia" class="form-select-quiet">
                                <option value="">— Sin familia —</option>
                                <?php foreach ($familias as $fam): ?>
                                <option value="<?= $fam['id'] ?>"><?= htmlspecialchars($fam['nombre_familia']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm">Cliente</label>
                            <select name="cliente_id" id="f_cliente" class="form-select-quiet">
                                <option value="">— Sin asignar —</option>
                                <?php foreach ($clientes as $cli): ?>
                                <option value="<?= $cli['id'] ?>"><?= htmlspecialchars($cli['nombre_empresa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Estado -->
                <div class="prop-section">
                    <div class="prop-section-title"><i class="bi bi-toggle-on me-1"></i>Configuración</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-sm">Estado</label>
                            <select name="estado" id="f_estado" class="form-select-quiet">
                                <option value="DISPONIBLE">DISPONIBLE</option>
                                <option value="BLOQUEADO">BLOQUEADO</option>
                                <option value="OBSOLETO">OBSOLETO</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="prop-actions">
                    <button type="submit" class="btn-quiet btn-quiet-edit">
                        <i class="bi bi-floppy me-1"></i> Guardar Cambios
                    </button>
                    <button type="button" id="btnEliminar" class="btn-quiet btn-quiet-delete">
                        <i class="bi bi-trash me-1"></i> Eliminar Artículo
                    </button>
                    <button type="button" id="btnNuevo" class="btn-quiet ms-auto">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo Artículo
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Empty state -->
    <div id="emptyState" class="text-center py-5 text-muted">
        <i class="bi bi-search display-4 d-block mb-3" style="opacity:.2"></i>
        <p class="mb-0">Escribe al menos 2 caracteres para buscar artículos en el catálogo global.</p>
    </div>
</div>

<!-- Toast container -->
<div id="toastWrap"></div>

<!-- ═══════════ MODAL: Administrar Familias ═══════════ -->
<div class="modal fade" id="modalFamilias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-tags text-primary me-2"></i>Administrar Familias</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario inline -->
                <div class="d-flex gap-2 mb-3">
                    <input type="hidden" id="fam_edit_id">
                    <input type="text" id="fam_nombre_input" class="form-control rounded-3" placeholder="Nombre de la familia">
                    <button class="btn btn-primary rounded-3 fw-semibold px-3" id="btnGuardarFam">
                        <i class="bi bi-floppy"></i>
                    </button>
                    <button class="btn btn-light rounded-3" id="btnCancelFam" style="display:none">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <!-- Lista -->
                <div id="famList" class="small"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const AJAX_SEARCH  = 'buscar_articulos_ajax.php';
    const AJAX_PROC    = 'procesar_articulo_maestra.php';

    let searchTimer = null;
    let currentArt  = null;

    const input   = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');
    const panel   = document.getElementById('propPanel');
    const empty   = document.getElementById('emptyState');

    /* ── SEARCH ──────────────────────────────────── */
    input.addEventListener('input', function() {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) { results.style.display='none'; return; }
        searchTimer = setTimeout(() => doSearch(q), 280);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrap')) results.style.display = 'none';
    });

    function doSearch(q) {
        fetch(AJAX_SEARCH + '?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) {
                    results.innerHTML = `
                        <div class="no-results">
                            <i class="bi bi-inbox me-2"></i>Sin resultados para "${escHtml(q)}"
                        </div>
                        <div class="create-new-prompt">
                            <a href="#" onclick="prepararNuevoConSKU('${escHtml(q)}'); return false;">
                                <i class="bi bi-plus-circle me-1"></i> ¿No existe? Crear "${escHtml(q)}" como nuevo artículo
                            </a>
                        </div>`;
                } else {
                    results.innerHTML = data.map(a => `
                        <div class="result-item" data-id="${a.id}" onclick="selectArticulo(${JSON.stringify(a).replace(/"/g,'&quot;')})">
                            <span class="sku-badge">${escHtml(a.sku)}</span>
                            <span class="desc">${escHtml(a.descripcion)}</span>
                            <span class="client-tag"><i class="bi bi-buildings me-1"></i>${escHtml(a.cliente_nombre || '—')}</span>
                        </div>`).join('');
                }
                results.style.display = 'block';
            })
            .catch(() => { results.style.display = 'none'; });
    }

    window.prepararNuevoConSKU = function(sku) {
        results.style.display = 'none';
        input.value = sku;
        document.getElementById('btnNuevo').click();
        document.getElementById('f_sku').value = sku;
        document.getElementById('f_sku').focus();
    };

    /* ── SELECT ARTICLE ──────────────────────────── */
    window.selectArticulo = function(art) {
        currentArt = art;
        results.style.display = 'none';
        input.value = art.sku + ' — ' + art.descripcion;

        document.getElementById('art_id').value       = art.id;
        document.getElementById('hdr_sku').textContent  = art.sku;
        document.getElementById('hdr_desc').textContent = art.descripcion;
        document.getElementById('f_sku').value         = art.sku;
        document.getElementById('f_desc').value        = art.descripcion;
        document.getElementById('f_pallet').value      = art.paletizado_a || '';
        document.getElementById('f_medida').value      = art.medida || 'Uds';
        document.getElementById('f_estado').value      = art.estado || 'DISPONIBLE';
        document.getElementById('f_cliente').value     = art.cliente_id || '';
        document.getElementById('f_familia').value     = art.familia_id || '';

        empty.style.display = 'none';
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    /* ── NUEVO ─────────────────────────────────── */
    document.getElementById('btnNuevo').addEventListener('click', function() {
        currentArt = null;
        document.getElementById('propForm').reset();
        document.getElementById('art_id').value = '';
        document.getElementById('hdr_sku').textContent  = 'NUEVO';
        document.getElementById('hdr_desc').textContent = 'Rellena los campos y guarda';
        input.value = '';
        empty.style.display = 'none';
        panel.style.display = 'block';
    });

    /* ── GUARDAR ──────────────────────────────── */
    document.getElementById('propForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('accion', 'guardar');
        fetch(AJAX_PROC, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => toast(d.ok, d.msg))
            .catch(() => toast(false, 'Error de red.'));
    });

    /* ── ELIMINAR ─────────────────────────────── */
    document.getElementById('btnEliminar').addEventListener('click', function() {
        const id = document.getElementById('art_id').value;
        if (!id) return;
        if (!confirm('¿Eliminar este artículo? Esta acción no se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('id', id);
        fetch(AJAX_PROC, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                toast(d.ok, d.msg);
                if (d.ok) {
                    panel.style.display = 'none';
                    empty.style.display = 'block';
                    input.value = '';
                }
            });
    });

    /* ── TOAST ────────────────────────────────── */
    function toast(ok, msg) {
        const wrap = document.getElementById('toastWrap');
        const el = document.createElement('div');
        el.className = 'am-toast ' + (ok ? 'ok' : 'err');
        el.innerHTML = `<i class="bi ${ok ? 'bi-check-circle' : 'bi-exclamation-circle'}"></i>${escHtml(msg)}`;
        wrap.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── FAMILIAS MODAL ──────────────────────── */
    document.getElementById('modalFamilias').addEventListener('show.bs.modal', loadFamilias);

    function loadFamilias() {
        fetch(AJAX_PROC + '?accion=listar_familias')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('famList');
                if (!data.length) { list.innerHTML='<p class="text-muted text-center">No hay familias.</p>'; return; }
                list.innerHTML = data.map(f => `
                    <div class="fam-row" id="famRow_${f.id}">
                        <span class="fam-name">${escHtml(f.nombre_familia)}</span>
                        <button class="btn-quiet btn-quiet-edit" onclick="editFam(${f.id},'${escHtml(f.nombre_familia)}')">✏️</button>
                        <button class="btn-quiet btn-quiet-delete" onclick="deleteFam(${f.id})">🗑️</button>
                    </div>`).join('');
            });
    }

    document.getElementById('btnGuardarFam').addEventListener('click', function() {
        const nombre = document.getElementById('fam_nombre_input').value.trim();
        const fid    = document.getElementById('fam_edit_id').value;
        if (!nombre) return;
        const fd = new FormData();
        fd.append('accion', 'guardar_familia');
        fd.append('nombre_familia', nombre);
        fd.append('fam_id', fid);
        fetch(AJAX_PROC, { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => {
                toast(d.ok, d.msg);
                if (d.ok) {
                    document.getElementById('fam_nombre_input').value = '';
                    document.getElementById('fam_edit_id').value = '';
                    document.getElementById('btnCancelFam').style.display = 'none';
                    loadFamilias();
                    reloadFamilySelect();
                }
            });
    });

    document.getElementById('btnCancelFam').addEventListener('click', function() {
        document.getElementById('fam_nombre_input').value = '';
        document.getElementById('fam_edit_id').value = '';
        this.style.display = 'none';
    });

    window.editFam = function(id, nombre) {
        document.getElementById('fam_edit_id').value = id;
        document.getElementById('fam_nombre_input').value = nombre;
        document.getElementById('btnCancelFam').style.display = '';
        document.getElementById('fam_nombre_input').focus();
    };

    window.deleteFam = function(id) {
        if (!confirm('¿Eliminar esta familia?')) return;
        const fd = new FormData();
        fd.append('accion', 'eliminar_familia');
        fd.append('id', id);
        fetch(AJAX_PROC, { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => { if(d.ok) { loadFamilias(); reloadFamilySelect(); } });
    };

    function reloadFamilySelect() {
        fetch(AJAX_PROC + '?accion=listar_familias')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('f_familia');
                const cur = sel.value;
                sel.innerHTML = '<option value="">— Sin familia —</option>' +
                    data.map(f => `<option value="${f.id}">${escHtml(f.nombre_familia)}</option>`).join('');
                sel.value = cur;
            });
    }
})();
</script>

<?php include '../includes/footer.php'; ?>
