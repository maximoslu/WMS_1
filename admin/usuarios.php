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

// Procesar acción (aprobar/rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['user_id'])) {
    $accion  = $_POST['accion'];
    $user_id = (int)$_POST['user_id'];
    $nuevo_estado = ($accion === 'aprobar') ? 'activo' : 'rechazado';

    try {
        $pdo->beginTransaction();

        // Actualizar estado del usuario
        $upd = $pdo->prepare("UPDATE users SET estado = :estado WHERE id = :id");
        $upd->execute([':estado' => $nuevo_estado, ':id' => $user_id]);

        // Marcar notificaciones relacionadas como leídas
        $leer = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE destinatario_rol IN ('superadmin','administracion') AND leido = 0");
        $leer->execute();

        $pdo->commit();

        $icono = $accion === 'aprobar' ? '✅' : '❌';
        $mensaje = "$icono Usuario " . ($accion === 'aprobar' ? 'aprobado y activado' : 'rechazado') . ' correctamente.';
        $tipo    = $accion === 'aprobar' ? 'success' : 'warning';

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en admin/usuarios.php: " . $e->getMessage());
        $mensaje = 'Error al procesar la acción.';
        $tipo    = 'danger';
    }
}

// Obtener todos los usuarios con sus estados
try {
    $stmt = $pdo->query("SELECT u.id, u.nombre, u.email, u.rol, u.estado, u.created_at,
                                c.nombre_empresa
                         FROM users u
                         LEFT JOIN clientes c ON u.cliente_id = c.id
                         ORDER BY FIELD(u.estado,'pendiente','activo','rechazado'), u.created_at DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
    error_log("Error al cargar usuarios: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container px-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Gestión de Usuarios</h2>
            <p class="text-muted small mb-0">Aprueba o rechaza solicitudes de acceso al sistema.</p>
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
                            <th class="text-center">Registro</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($u['nombre']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($u['rol']) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($u['nombre_empresa'] ?? '—') ?></td>
                            <td class="text-center">
                                <?php if ($u['estado'] === 'pendiente'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                                        <i class="bi bi-hourglass-split me-1"></i> Pendiente
                                    </span>
                                <?php elseif ($u['estado'] === 'activo'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                        <i class="bi bi-check-circle me-1"></i> Activo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                        <i class="bi bi-x-circle me-1"></i> Rechazado
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-muted">
                                <?= isset($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                            </td>
                            <td class="text-center pe-4">
                                <?php if ($u['estado'] === 'pendiente'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="accion"  value="aprobar">
                                        <button type="submit" class="btn btn-sm btn-success rounded-3 me-1" title="Aprobar">
                                            <i class="bi bi-check-lg me-1"></i>Aprobar
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="accion"  value="rechazar">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Rechazar">
                                            <i class="bi bi-x-lg me-1"></i>Rechazar
                                        </button>
                                    </form>
                                <?php elseif ($u['estado'] === 'rechazado'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="accion"  value="aprobar">
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-3">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay usuarios registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
