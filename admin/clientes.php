<?php
/**
 * WMS_1 - Gestión de Clientes
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    header("Location: ../dashboard.php");
    exit;
}

// Obtener clientes
try {
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clientes = [];
}

include '../includes/header.php';
?>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><i class="bi bi-buildings text-primary me-2"></i>Gestión de Clientes</h2>
            <p class="text-muted small mb-0">Administra el portafolio de clientes y su información de contacto.</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary rounded-3 fw-medium" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="prepararNuevo()">
                <i class="bi bi-plus-lg me-2"></i>Nuevo Cliente
            </button>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i> Operación realizada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Empresa</th>
                            <th>CIF</th>
                            <th>Contacto</th>
                            <th>Registro</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No hay clientes registrados.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['nombre_empresa'] ?? '—') ?></div>
                                    <div class="user-id">ID: #<?= $c['id'] ?></div>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($c['cif'] ?? '—') ?></td>
                                <td>
                                    <div class="small fw-medium"><?= htmlspecialchars($c['contacto_nombre'] ?? '—') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($c['email_contacto'] ?? '—') ?></div>
                                </td>
                                <td class="small text-muted">
                                    <?= (isset($c['fecha_registro']) && $c['fecha_registro']) ? date('d/m/Y', strtotime($c['fecha_registro'])) : '—' ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button class="btn-quiet btn-quiet-edit" onclick='editarCliente(<?= json_encode($c) ?>)'>✏️ Editar</button>
                                        <button class="btn-quiet btn-quiet-delete" onclick="eliminarCliente(<?= $c['id'] ?>)">🗑️ Borrar</button>
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

<!-- MODAL: CLIENTE (Traditional POST) -->
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formNuevoCliente" method="POST" action="procesar_cliente.php" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" id="form_accion" value="insert">
            <input type="hidden" name="id" id="cliente_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NOMBRE DE LA EMPRESA</label>
                    <input type="text" name="nombre_empresa" id="nombre_empresa" class="form-control rounded-3" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">CIF / NIF</label>
                        <input type="text" name="cif" id="cif" class="form-control rounded-3" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">TELÉFONO / CONTACTO</label>
                        <input type="text" name="contacto_nombre" id="contacto_nombre" class="form-control rounded-3">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">EMAIL DE CONTACTO</label>
                    <input type="email" name="email_contacto" id="email_contacto" class="form-control rounded-3">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted">DIRECCIÓN FISCAL</label>
                    <textarea name="direccion" id="direccion" class="form-control rounded-3" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevo() {
    document.getElementById('formNuevoCliente').reset();
    document.getElementById('form_accion').value = 'insert';
    document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
}

function editarCliente(data) {
    document.getElementById('modalTitle').textContent = 'Editar Cliente';
    document.getElementById('form_accion').value = 'update';
    document.getElementById('cliente_id').value = data.id;
    document.getElementById('nombre_empresa').value = data.nombre_empresa;
    document.getElementById('cif').value = data.cif || '';
    document.getElementById('contacto_nombre').value = data.contacto_nombre || '';
    document.getElementById('email_contacto').value = data.email_contacto || '';
    document.getElementById('direccion').value = data.direccion || '';
    
    var modalEl = document.getElementById('modalCliente');
    var modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalObj.show();
}

function eliminarCliente(id) {
    if(confirm('¿Seguro que deseas eliminar este cliente?')) {
        window.location.href = 'procesar_cliente.php?accion=eliminar&id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
