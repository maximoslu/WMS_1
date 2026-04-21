<?php
/**
 * WMS_1 - Panel de Control Principal
 */
require_once 'config/setup.php';
require_once 'config/db.php';

// El header ya contiene el control de sesión
include 'includes/header.php';

$nombre_usuario = $_SESSION['nombre'] ?? 'Operario';
$rol = $_SESSION['rol'] ?? 'Invitado';
$cliente_id = $_SESSION['cliente_id'] ?? 0;
?>

<div class="container px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark-emphasis mb-0">Bienvenido, <?= htmlspecialchars($nombre_usuario) ?></h2>
            <p class="text-muted">Resumen operativo del almacén</p>
        </div>
        <div class="text-end">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <i class="bi bi-clock-history me-1"></i> <?= date('d/m/Y H:i') ?>
            </span>
        </div>
    </div>

    <!-- Quick Stats / Cards -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-stat shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-primary-subtle p-3 rounded-3">
                        <i class="bi bi-box-seam text-primary fs-3"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Artículos</h6>
                        <h4 class="fw-bold mb-0">--</h4>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="articulos.php" class="text-decoration-none small text-primary fw-semibold">Ver catálogo <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-stat shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success-subtle p-3 rounded-3">
                        <i class="bi bi-box-arrow-in-down text-success fs-3"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Recibidos hoy</h6>
                        <h4 class="fw-bold mb-0">--</h4>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="entradas.php" class="text-decoration-none small text-success fw-semibold">Gestionar entradas <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-stat shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-warning-subtle p-3 rounded-3">
                        <i class="bi bi-box-arrow-up text-warning fs-3"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Pendientes salida</h6>
                        <h4 class="fw-bold mb-0">--</h4>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="salidas.php" class="text-decoration-none small text-warning fw-semibold">Ver pedidos <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-stat shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-info-subtle p-3 rounded-3">
                        <i class="bi bi-people text-info fs-3"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">ID Cliente</h6>
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($cliente_id) ?></h4>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="small text-muted">Aislamiento de datos activo <i class="bi bi-shield-check text-success"></i></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity / Info -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-primary me-2"></i>Acciones Rápidas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="almacen_nuevo.php" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-plus-circle-dotted me-3 text-primary"></i> Registrar nueva ubicación</span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="etiquetar.php" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-printer me-3 text-primary"></i> Impresión masiva de etiquetas</span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="informes.php" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center border-bottom-0">
                            <span><i class="bi bi-file-earmark-spreadsheet me-3 text-primary"></i> Exportar stock actual (CSV)</span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-brand-dark text-white p-4" style="background-color: #0f172a;">
                <h5 class="fw-bold mb-3">Soporte Técnico</h5>
                <p class="small text-white-50 mb-4">¿Necesitas ayuda con la terminal móvil o la configuración de estanterías?</p>
                <a href="mailto:soporte@maximosl.com" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">
                    <i class="bi bi-envelope-at me-2"></i> Contactar Soporte
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
