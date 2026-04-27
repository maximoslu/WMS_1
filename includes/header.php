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
        $nstmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE leido = 0");
        $nstmt->execute();
        $notif_count = (int)$nstmt->fetchColumn();
    } catch (Exception $e) { $notif_count = 0; }
}

// Determinar la ruta base para los enlaces (si estamos en admin/ o auth/, subir un nivel)
$current_dir = dirname($_SERVER['PHP_SELF']);
$basePath = (basename($current_dir) === 'admin' || basename($current_dir) === 'auth') ? '../' : './';
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
            background-color: #ffffff;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.025em;
            color: #1e293b !important;
        }
        .nav-link {
            font-weight: 500;
            color: #495057 !important;
            transition: all 0.2s ease;
            border-radius: 6px;
            padding: 0.5rem 0.75rem !important;
            margin: 0 0.1rem;
        }
        .nav-link:hover, .nav-link:focus {
            color: #2f54eb !important;
            background-color: #f0f5ff;
        }
        .dropdown-menu-saas {
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 0.5rem 0;
            margin-top: 0.5rem;
        }
        .dropdown-menu-saas .dropdown-item {
            color: #495057;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 0.5rem 1.5rem;
            transition: all 0.2s ease;
        }
        .dropdown-menu-saas .dropdown-item:hover {
            background-color: #f0f5ff;
            color: #2f54eb;
        }
        .user-avatar {
            border: 2px solid #e2e8f0;
            padding: 2px;
            transition: border-color 0.2s;
        }
        .user-avatar:hover {
            border-color: #3b82f6;
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

        /* Quiet Luxury UI - Paleta Neutra y Profesional */
        .btn-quiet {
            background: #f8f9fa !important;
            color: #495057 !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 6px !important;
            padding: 6px 14px !important;
            font-size: 0.75rem !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-quiet:hover {
            background: #ffffff !important;
            box-shadow: 0 3px 6px rgba(0,0,0,0.08) !important;
            transform: translateY(-1px);
        }

        /* Hovers específicos */
        .btn-quiet-approve:hover { background: #f6ffed !important; color: #389e0d !important; border-color: #b7eb8f !important; }
        .btn-quiet-edit:hover { background: #e7f1ff !important; color: #0056b3 !important; border-color: #adc6ff !important; }
        .btn-quiet-key:hover { background: #fff7e6 !important; color: #856404 !important; border-color: #fffb8f !important; }
        .btn-quiet-delete:hover { background: #fff1f0 !important; color: #a44141 !important; border-color: #ffa39e !important; }

        /* Indicadores de Estado (Dots) */
        .status-dot {
            height: 7px;
            width: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .dot-pending { background-color: #faad14; box-shadow: 0 0 0 2px rgba(250, 173, 20, 0.1); }
        .dot-active { background-color: #52c41a; box-shadow: 0 0 0 2px rgba(82, 196, 26, 0.1); }
        .dot-rejected { background-color: #f5222d; box-shadow: 0 0 0 2px rgba(245, 34, 45, 0.1); }

        .status-text {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: capitalize;
        }

        /* Tipografía Refinada */
        .user-id {
            font-size: 0.65rem !important;
            color: #adb5bd !important;
            margin-top: 2px;
            letter-spacing: 0.025em;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light navbar-custom shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="<?= $basePath ?>dashboard.php">
            <i class="bi bi-grid-1x2-fill text-primary me-2 fs-3"></i>
            <span>PANEL DE <span class="text-primary">CONTROL</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                
                <!-- Almacenes -->
                <?php if ($isSuperAdmin || $isOperario): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>admin/almacenes.php">
                        <i class="bi bi-house-gear me-1"></i> ALMACENES
                    </a>
                </li>
                <?php endif; ?>
                <!-- Clientes (Solo SuperAdmin) -->
                <?php if ($isSuperAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>admin/clientes.php">
                        <i class="bi bi-buildings me-1"></i> CLIENTES
                    </a>
                </li>

                <!-- Artículos (Solo SuperAdmin) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>admin/articulos_maestra.php">
                        <i class="bi bi-box2-heart me-1"></i> ARTÍCULOS
                    </a>
                </li>
                <?php endif; ?>

                <!-- Stock (Multicliente) -->
                <?php if ($isSuperAdmin || $isOperario): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropStock" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-box-seam me-1"></i> STOCK
                    </a>
                    <ul class="dropdown-menu dropdown-menu-saas" style="max-height: 300px; overflow-y: auto;">
                        <?php 
                        try {
                            if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';
                            $stmt_clientes = $pdo->query("SELECT id, nombre_empresa FROM clientes ORDER BY nombre_empresa ASC");
                            while ($row_cli = $stmt_clientes->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/stock.php?cliente_id=<?= $row_cli['id'] ?>"><i class="bi bi-person me-2 text-secondary"></i><?= htmlspecialchars($row_cli['nombre_empresa']) ?></a></li>
                        <?php 
                            endwhile;
                        } catch(Exception $e) {}
                        ?>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>admin/stock.php?cliente_id=<?= $_SESSION['cliente_id'] ?? ($_SESSION['user_cliente_id'] ?? 0) ?>"><i class="bi bi-box-seam me-1"></i> STOCK</a>
                </li>
                <?php endif; ?>

                <!-- Entrada -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>admin/entradas.php"><i class="bi bi-box-arrow-in-down me-1"></i> Entrada</a>
                </li>
                <?php endif; ?>

                <!-- Salida -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>salidas.php"><i class="bi bi-box-arrow-up me-1"></i> Salida</a>
                </li>
                <?php endif; ?>

                <!-- Etiquetar -->
                <?php if (!$isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>etiquetar.php"><i class="bi bi-upc-scan me-1"></i> Etiquetar</a>
                </li>
                <?php endif; ?>

                <!-- Informes -->
                <?php if ($isSuperAdmin || $isClienteAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $basePath ?>informes.php"><i class="bi bi-bar-chart-line me-1"></i> Informes</a>
                </li>
                <?php endif; ?>

            </ul>

            <div class="d-flex align-items-center gap-2">
                <!-- Campana de notificaciones (solo admins) -->
                <?php if ($isAdmin): ?>
                <a href="<?= $basePath ?>admin/usuarios.php" class="btn btn-icon position-relative text-secondary" title="Solicitudes de acceso pendientes">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                            <?= $notif_count > 9 ? '9+' : $notif_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <div class="text-secondary me-2 d-none d-md-block text-end">
                    <div class="small fw-semibold text-dark"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></div>
                    <div class="text-primary fw-bold" style="font-size: 0.70rem; text-transform: uppercase;"><?= htmlspecialchars($rol) ?></div>
                </div>
                <div class="dropdown">
                    <a href="#" class="d-block link-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre'] ?? 'U') ?>&background=e7f1ff&color=0056b3&bold=true" width="40" height="40" class="rounded-circle user-avatar">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-saas">
                        <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Perfil</a></li>
                        <?php if ($isAdmin): ?>
                        <li><a class="dropdown-item" href="<?= $basePath ?>admin/usuarios.php"><i class="bi bi-people me-2"></i>Gestionar Usuarios</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $basePath ?>auth/logout.php"><i class="bi bi-power me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="py-4">
