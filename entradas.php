<?php
/**
 * WMS_1 - Recepción de Stock (Entradas)
 * Formulario transaccional para cargar mercancía en ubicaciones.
 */
require_once 'config/setup.php';
require_once 'config/db.php';
require_once 'includes/ArticuloController.php';
require_once 'includes/UbicacionController.php';
require_once 'includes/InventarioController.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$usuario_id = $_SESSION['user_id'] ?? 0;

$artController = new ArticuloController($pdo);
$ubiController = new UbicacionController($pdo);
$invController = new InventarioController($pdo);

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'articulo_id'  => $_POST['articulo_id'],
        'ubicacion_id' => $_POST['ubicacion_id'],
        'cantidad'     => (float)$_POST['cantidad'],
        'usuario_id'   => $usuario_id,
        'cliente_id'   => $cliente_id
    ];
    
    if ($data['cantidad'] > 0 && $invController->registrarEntrada($data)) {
        $mensaje = '<div class="alert alert-success shadow-sm border-0 rounded-4 p-3 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Entrada registrada con éxito</h6>
                                <small>El stock y el historial de movimientos han sido actualizados.</small>
                            </div>
                        </div>
                    </div>';
    } else {
        $mensaje = '<div class="alert alert-danger shadow-sm border-0 rounded-4 p-3 mb-4">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i> Error al registrar la entrada. Verifique los datos.
                    </div>';
    }
}

$articulos = $artController->getArticulos($cliente_id);
$ubicaciones = $ubiController->getUbicaciones($cliente_id);

include 'includes/header.php';
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .form-container { max-width: 700px; margin: 0 auto; }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 10px; padding: 0.375rem 0.75rem; border: 1px solid #dee2e6; height: calc(3.5rem + 2px); }
    .form-floating > .select2-container { width: 100% !important; }
    .card-reception { border: none; border-radius: 20px; background: #ffffff; }
    .btn-submit { padding: 12px; border-radius: 12px; font-weight: 700; transition: all 0.3s; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3); }
</style>

<div class="container px-4">
    <div class="form-container">
        <div class="text-center mb-4">
            <div class="bg-primary-subtle d-inline-block p-3 rounded-circle mb-3">
                <i class="bi bi-box-arrow-in-down text-primary fs-1"></i>
            </div>
            <h2 class="fw-bold">Recepción de Mercancía</h2>
            <p class="text-muted">Registre la entrada de productos a una ubicación específica.</p>
        </div>

        <?= $mensaje ?>

        <div class="card card-reception shadow-lg p-4 p-md-5">
            <form method="POST" id="receptionForm">
                <div class="row g-4">
                    <!-- Selección de Producto -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark-emphasis">Producto (SKU / Nombre)</label>
                        <select name="articulo_id" id="articulo_id" class="form-select select2-art" required>
                            <option value=""></option>
                            <?php foreach ($articulos as $art): ?>
                                <option value="<?= $art['id'] ?>">
                                    [<?= htmlspecialchars($art['sku']) ?>] <?= htmlspecialchars($art['descripcion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selección de Ubicación -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark-emphasis">Ubicación de Destino</label>
                        <select name="ubicacion_id" id="ubicacion_id" class="form-select select2-ubi" required>
                            <option value=""></option>
                            <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?= $ubi['id'] ?>">
                                    <?= htmlspecialchars($ubi['codigo']) ?> - <?= htmlspecialchars($ubi['tipo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cantidad -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark-emphasis">Cantidad a Recibir</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="cantidad" class="form-control form-control-lg rounded-start-3" placeholder="0.00" min="0.01" required>
                            <span class="input-group-text bg-light border-start-0 rounded-end-3 px-4 fw-bold">UNIDADES</span>
                        </div>
                    </div>

                    <!-- Botón Enviar -->
                    <div class="col-12 mt-5">
                        <button type="submit" class="btn btn-primary btn-submit w-100 fs-5">
                            <i class="bi bi-plus-circle-fill me-2"></i> Confirmar Entrada
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="text-center mt-4 mb-5">
            <a href="dashboard.php" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Volver al panel de control
            </a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-art').select2({
        theme: 'bootstrap-5',
        placeholder: 'Busque por SKU o Descripción...',
        language: { noResults: () => "No se encontraron productos" }
    });

    $('.select2-ubi').select2({
        theme: 'bootstrap-5',
        placeholder: 'Seleccione ubicación física...',
        language: { noResults: () => "No se encontraron ubicaciones" }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
