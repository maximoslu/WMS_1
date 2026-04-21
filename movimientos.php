<?php
session_start();
require_once 'config/db.php';

// Proteger vista para usuarios internos
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] === 'Cliente') {
    header("Location: index.php?error=" . urlencode("Acceso denegado."));
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'] ?? null;
    $cantidad = $_POST['cantidad'] ?? null;

    if ($producto_id && is_numeric($cantidad) && $cantidad > 0) {
        try {
            $pdo->beginTransaction();

            // Actualizar stock_actual sumando la cantidad
            $stmtUpdate = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + :cantidad WHERE id = :producto_id");
            $stmtUpdate->execute([':cantidad' => $cantidad, ':producto_id' => $producto_id]);

            // Registrar la recepción
            $stmtInsert = $pdo->prepare("INSERT INTO recepciones (producto_id, cantidad, usuario_id) VALUES (:producto_id, :cantidad, :usuario_id)");
            $stmtInsert->execute([
                ':producto_id' => $producto_id,
                ':cantidad' => $cantidad,
                ':usuario_id' => $_SESSION['user_id']
            ]);

            $pdo->commit();
            $message = "Entrada de stock registrada correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar el movimiento: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, selecciona un producto y proporciona una cantidad válida mayor a cero.";
    }
}

// Obtener lista de productos para el select
try {
    $stmtProducts = $pdo->query("SELECT id, sku, descripcion FROM productos ORDER BY descripcion ASC");
    $productos = $stmtProducts->fetchAll();
} catch (Exception $e) {
    $productos = [];
    $error = "Error al cargar los productos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Registrar Movimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">WMS Interno</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 text-white">Rol: <?= htmlspecialchars($_SESSION['rol']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 text-center">
                        <h4 class="fw-bold mb-0">Registrar Entrada de Stock</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="movimientos.php">
                            <div class="mb-3">
                                <label for="producto_id" class="form-label fw-semibold">Producto</label>
                                <select class="form-select" id="producto_id" name="producto_id" required>
                                    <option value="" disabled selected>Selecciona un producto...</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?= htmlspecialchars($producto['id']) ?>">
                                            <?= htmlspecialchars($producto['sku'] . ' - ' . $producto['descripcion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="cantidad" class="form-label fw-semibold">Cantidad a Ingresar</label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required placeholder="Ej: 50">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                    Registrar Entrada
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary py-2">
                                    Volver al Inicio
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
