<?php
/**
 * WMS_1 - Gestión de Familias
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    header("Location: ../dashboard.php");
    exit;
}

// Obtener familias
try {
    $stmt = $pdo->query("SELECT * FROM familias ORDER BY id DESC");
    $familias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $familias = [];
}

include '../includes/header.php';
?>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><i class="bi bi-tags text-primary me-2"></i>Gestión de Familias</h2>
            <p class="text-muted small mb-0">Categoriza tus artículos para una mejor organización y búsqueda.</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary rounded-3 fw-medium" data-bs-toggle="modal" data-bs-target="#modalFamilia" onclick="prepararNueva()">
                <i class="bi bi-plus-lg me-2"></i>Nueva Familia
            </button>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i> Operación realizada correctamente.
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Nombre de la Familia</th>
                            <th>Descripción</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($familias)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No hay familias registradas.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($familias as $f): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($f['nombre_familia']) ?></div>
                                    <div class="user-id">ID: #<?= $f['id'] ?></div>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($f['descripcion']) ?></td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button class="btn-quiet btn-quiet-edit" onclick='editarFamilia(<?= json_encode($f) ?>)'>✏️ Editar</button>
                                        <button class="btn-quiet btn-quiet-delete" onclick="eliminarFamilia(<?= $f['id'] ?>)">🗑️ Borrar</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: FAMILIA (Traditional POST) -->
<div class="modal fade" id="modalFamilia" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form id="formNuevaFamilia" method="POST" action="procesar_familia.php" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" id="fam_accion" value="insert">
            <input type="hidden" name="id" id="fam_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="famModalTitle">Nueva Familia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NOMBRE</label>
                    <input type="text" name="nombre_familia" id="fam_nombre" class="form-control rounded-3" placeholder="Ej: Alimentación" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted">DESCRIPCIÓN</label>
                    <textarea name="descripcion" id="fam_desc" class="form-control rounded-3" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold">Guardar Familia</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNueva() {
    document.getElementById('formNuevaFamilia').reset();
    document.getElementById('fam_accion').value = 'insert';
    document.getElementById('famModalTitle').textContent = 'Nueva Familia';
}

function editarFamilia(data) {
    document.getElementById('famModalTitle').textContent = 'Editar Familia';
    document.getElementById('fam_accion').value = 'update';
    document.getElementById('fam_id').value = data.id;
    document.getElementById('fam_nombre').value = data.nombre_familia;
    document.getElementById('fam_desc').value = data.descripcion;
    
    var modalEl = document.getElementById('modalFamilia');
    var modalObj = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modalObj.show();
}

function eliminarFamilia(id) {
    if(confirm('¿Seguro que deseas eliminar esta familia?')) {
        window.location.href = 'procesar_familia.php?accion=eliminar&id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
