<?php
session_start();
// Proteger vista para usuarios internos
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] === 'Cliente') {
    header("Location: index.php?error=" . urlencode("Acceso denegado."));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Dashboard Interno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">WMS Interno</a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Rol: <?= htmlspecialchars($_SESSION['rol']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5 text-center">
                <h2 class="fw-bold mb-3">Bienvenido al Panel de <?= htmlspecialchars($_SESSION['rol']) ?></h2>
                <p class="text-muted">Aquí podrás gestionar la operativa interna de WMS.</p>
                <!-- Aquí irán los módulos en el futuro -->
            </div>
        </div>
    </div>
</body>
</html>
