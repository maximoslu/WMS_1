<?php
/**
 * WMS_1 - Gestión de Usuarios (Aprobación/Rechazo)
 * Acceso restringido a SuperAdmin y Administracion.
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'administracion'])) {
    header("Location: ../dashboard.php");
    exit;
}

$mensaje = '';
$tipo    = '';

// Capturar mensaje de sesión (procedente de procesar_usuario.php)
if (isset($_SESSION['msg'])) {
    $mensaje = $_SESSION['msg'];
    $tipo    = $_SESSION['msg_tipo'] ?? 'info';
    unset($_SESSION['msg'], $_SESSION['msg_tipo']);
}



// Obtener todos los usuarios con sus estados
try {
    $stmt = $pdo->query("SELECT u.id, u.nombre, u.email, u.rol, u.estado, u.created_at, u.cliente_id,
                                c.nombre_empresa
                         FROM users u
                         LEFT JOIN clientes c ON u.cliente_id = c.id
                         ORDER BY FIELD(u.estado,'pendiente','activo','rechazado'), u.created_at DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de clientes para el modal de edición
    $stmtCl = $pdo->query("SELECT id, nombre_empresa FROM clientes ORDER BY nombre_empresa ASC");
    $clientes = $stmtCl->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $usuarios = [];
    $clientes = [];
    error_log("Error al cargar datos: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Gestión de Usuarios</h2>
            <p class="text-muted small mb-0">Administra usuarios, aprueba accesos y gestiona perfiles.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo ?> alert-dismissible fade show rounded-3 mb-4" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Cliente</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($u['nombre']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">ID: #<?= $u['id'] ?> &middot; <?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($u['rol']) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($u['nombre_empresa'] ?? '—') ?></td>
                            <td class="text-center">
                                <?php if ($u['estado'] === 'pendiente'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">Pendiente</span>
                                <?php elseif ($u['estado'] === 'activo'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">Rechazado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <?php
                                $id = $u['id'];
                                if ($u['estado'] === 'pendiente') {
                                    echo '<button class="btn btn-success" onclick="aprobar('.$id.')">Aprobar</button> ';
                                }
                                echo '<button class="btn btn-primary" onclick="editar('.$id.')">✏️</button> ';
                                echo '<button class="btn btn-warning" onclick="clave('.$id.')">🔑</button> ';
                                echo '<button class="btn btn-sm btn-danger" onclick="eliminarUsuario('.$id.')">🗑️ Borrar</button>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="procesar_usuario.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" value="update_perfil">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Editar Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Nombre Completo</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control rounded-3" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Rol</label>
                        <select name="rol" id="edit_rol" class="form-select rounded-3">
                            <option value="SuperAdmin">SuperAdmin</option>
                            <option value="Admin">Admin</option>
                            <option value="Almacen">Almacen</option>
                            <option value="Cliente">Cliente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Cliente Asociado</label>
                        <select name="cliente_id" id="edit_cliente_id" class="form-select rounded-3">
                            <option value="">Ninguno / Interno</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_empresa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CAMBIAR PASSWORD -->
<div class="modal fade" id="modalPassword" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form action="procesar_usuario.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="accion" value="update_pass">
            <input type="hidden" name="user_id" id="pass_user_id">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2"></i>Nueva Clave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-3">Cambiando clave para: <br><strong id="pass_user_nombre" class="text-dark"></strong></p>
                <div class="input-group">
                    <input type="password" name="new_pass" id="new_pass" class="form-control rounded-start-3" placeholder="Nueva contraseña" required minlength="6">
                    <button class="btn btn-outline-secondary rounded-end-3" type="button" id="toggleView">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-warning w-100 rounded-3 fw-bold">Actualizar Contraseña</button>
            </div>
        </form>
    </div>
</div>

<script>
// Funciones globales (fuera de document.ready) para compatibilidad total con onclick
function editar(id) {
    console.log("Editando ID: " + id);
    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    
    // Carga de datos AJAX
    fetch('get_usuario.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.error) return alert(data.error);
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_nombre').value  = data.nombre;
            document.getElementById('edit_email').value   = data.email;
            document.getElementById('edit_rol').value     = data.rol;
            document.getElementById('edit_cliente_id').value = data.cliente_id || '';
            modal.show();
        })
        .catch(err => console.error("Error al cargar usuario:", err));
}

function clave(id) {
    console.log("Cambiando clave de ID: " + id);
    const modal = new bootstrap.Modal(document.getElementById('modalPassword'));
    document.getElementById('pass_user_id').value = id;
    document.getElementById('pass_user_nombre').textContent = 'Usuario #' + id;
    document.getElementById('new_pass').value = '';
    modal.show();
}

function aprobar(id) {
    if(confirm('¿Seguro que deseas aprobar este usuario?')) {
        const formData = new FormData();
        formData.append('accion', 'aprobar');
        formData.append('user_id', id);

        fetch('procesar_usuario.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message || 'Error al aprobar');
            }
        })
        .catch(err => console.error("Error al aprobar:", err));
    }
}

// Intercepción de formularios para procesar vía AJAX
document.addEventListener('submit', function(e) {
    if (e.target.closest('.modal form')) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message || 'Error al guardar');
            }
        })
        .catch(err => alert('Error de conexión o respuesta inválida'));
    }
});

function togglePass() {
    const input = document.getElementById('new_pass');
    input.type = input.type === 'password' ? 'text' : 'password';
}

function eliminarUsuario(id) {
    if(confirm('¿Estás SEGURO de que quieres eliminar a este usuario? Esta acción no se puede deshacer.')) {
        fetch('procesar_usuario.php?accion=eliminar&id=' + id)
        .then(res => res.json())
        .then(data => { if(data.status === "success") location.reload(); });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
