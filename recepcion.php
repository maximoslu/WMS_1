<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];

    if ($producto_id && $cantidad > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO recepciones (producto_id, cantidad, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$producto_id, $cantidad, $_SESSION['user_id']]);

            $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?");
            $stmt->execute([$cantidad, $producto_id]);

            $pdo->commit();
            $mensaje = "Recepción registrada correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}

$productos = $pdo->query("SELECT id, sku FROM productos")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción de Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container" style="max-width: 500px;">
        <h2 class="mb-4 text-center">Registrar Recepción</h2>
        
        <?php if (isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <div class="card shadow-sm p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Producto (SKU)</label>
                    <select name="producto_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['sku']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="cantidad" class="form-control" min="1" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrar</button>
            </form>
        </div>
    </div>
</body>
</html>
