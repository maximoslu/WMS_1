<?php
/**
 * WMS_1 - Sistema de Gestión de Almacén
 * Componente: Header y Navegación Dinámica
 * Estética: Dark Blue Corporativo (#0f172a)
 */

// Aseguramos que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificación de sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Verificar que el estado de la cuenta siga siendo 'activo'
// Esto cierra la sesión si un admin rechazó la cuenta DESPUÉS del login
$estado_sesion = $_SESSION['estado'] ?? 'activo'; // compatibilidad con sesiones antiguas
if (in_array($estado_sesion, ['pendiente', 'rechazado'])) {
    session_destroy();
    header("Location: index.php?error=" . urlencode("Tu cuenta ha sido bloqueada. Contacta con el administrador."));
    exit;
}

// Si la sesión no tiene estado cacheado, verificar en BBDD una vez por sesión
if (!isset($_SESSION['estado'])) {
    try {
        if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';
        $stCheck = $pdo->prepare("SELECT estado FROM users WHERE id = :id LIMIT 1");
        $stCheck->execute([':id' => $_SESSION['user_id']]);
        $row = $stCheck->fetch(PDO::FETCH_ASSOC);
        $dbEstado = $row['estado'] ?? 'activo';
        $_SESSION['estado'] = $dbEstado; // cachear en sesión para no consultar cada página
        if (in_array($dbEstado, ['pendiente', 'rechazado'])) {
            session_destroy();
            header("Location: index.php?error=" . urlencode("Tu cuenta está bloqueada. Contacta con el administrador."));
            exit;
        }
    } catch (Exception $e) { /* Si falla la BD, no bloqueamos al usuario */ }
}

// Roles: superadmin, operario, cliente_admin
$rol = $_SESSION['rol'] ?? '';
$rolLower = strtolower($rol);

$isSuperAdmin   = ($rolLower === 'superadmin');
$isOperario     = ($rolLower === 'operario');
$isClienteAdmin = ($rolLower === 'cliente_admin');
$isAdmin        = ($isSuperAdmin || $rolLower === 'administracion');

// Contar notificaciones no leídas (solo para admins)
$notif_count = 0;
if ($isAdmin) {
    try {
        if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';
        $nstmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE destinatario_rol IN ('superadmin','administracion') AND leido = 0");
        $nstmt->execute();
        $notif_count = (int)$nstmt->fetchColumn();
    } catch (Exception $e) { $notif_count = 0; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-dark: #0f172a;
            --brand-primary: #3b82f6;
            --brand-accent: #6366f1;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .navbar-custom {
            background-color: var(--brand-dark);
            padding: 0.75rem 1rem;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .nav-link {
            font-weight: 500;
            color: rgba(255,255,255,0.85) !important;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: #fff !important;
            transform: translateY(-1px);
        }
        .dropdown-menu-dark {
            background-color: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .dropdown-item:hover {
            background-color: var(--brand-primary);
        }
        .user-avatar {
            border: 2px solid var(--brand-primary);
            padding: 2px;
        }
        /* Dashboard Cards */
        .card-stat {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-lg sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-grid-1x2-fill text-primary me-2 fs-3"></i>
            <span>PANEL DE <span class="text-primary">CONTROL</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                
                <!-- Almacén -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropAlmacen" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-house-gear me-1"></i> Almacén
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <?php if ($isSuperAdmin || $isOperario): ?>
                            <li><a class="dropdown-item" href="almacen_nuevo.php"><i class="bi bi-plus-lg me-2"></i>Nuevo</a></li>
                        <?php endif; ?>
                        
                        <li><a class="dropdown-item" href="ubicaciones.php"><i class="bi bi-search me-2"></i>Consulta</a></li>
                        
                        <?php if ($isSuperAdmin || $isOperario): ?>
                            <li><a class="dropdown-item" href="almacen_editar.php"><i class="bi bi-pencil-square me-2"></i>Editar</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <!-- Stock -->
                <li class="nav-item">
                    <a class="nav-link" href="stock.php"><i class="bi bi-box-seam me-1"></i> STOCK</a>
                </li>

                <!-- Entrada -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="entradas.php"><i class="bi bi-box-arrow-in-down me-1"></i> Entrada</a>
                </li>
                <?php endif; ?>

                <!-- Salida -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="salidas.php"><i class="bi bi-box-arrow-up me-1"></i> Salida</a>
                </li>
                <?php endif; ?>

                <!-- Etiquetar -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="etiquetar.php"><i class="bi bi-upc-scan me-1"></i> Etiquetar</a>
                </li>
                <?php endif; ?>

                <!-- Informes -->
                <?php if ($isSuperAdmin || $isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="informes.php"><i class="bi bi-bar-chart-line me-1"></i> Informes</a>
                </li>
                <?php endif; ?>

            </ul>

            <div class="d-flex align-items-center gap-2">
                <!-- Campana de notificaciones (solo admins) -->
                <?php if ($isAdmin): ?>
                <a href="admin/usuarios.php" class="btn btn-icon position-relative text-light" title="Solicitudes de acceso pendientes">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                            <?= $notif_count > 9 ? '9+' : $notif_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <div class="text-light me-1 d-none d-md-block text-end">
                    <div class="small fw-semibold"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></div>
                    <div class="text-primary fw-bold" style="font-size: 0.70rem; text-transform: uppercase;"><?= htmlspecialchars($rol) ?></div>
                </div>
                <div class="dropdown">
                    <a href="#" class="d-block link-light text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre'] ?? 'U') ?>&background=3b82f6&color=fff&bold=true" width="40" height="40" class="rounded-circle user-avatar">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                        <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Perfil</a></li>
                        <?php if ($isAdmin): ?>
                        <li><a class="dropdown-item" href="admin/usuarios.php"><i class="bi bi-people me-2"></i>Gestionar Usuarios</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="py-4">
