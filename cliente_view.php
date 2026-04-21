<?php
session_start();
// Proteger vista para clientes
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Cliente') {
    header("Location: index.php?error=" . urlencode("Acceso denegado."));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Portal de Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Portal WMS</a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5">
                <h2 class="fw-bold mb-3">Tu Espacio de Cliente</h2>
                <p class="text-muted">Bienvenido. Tu identificador de cliente asociado es: <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['cliente_id'] ?? 'N/A') ?></span></p>
                <div class="alert alert-info mt-4" role="alert">
                    Próximamente: Historial de pedidos, inventario actual y facturación.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
