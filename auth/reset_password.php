<?php
/**
 * WMS_1 - Restablecimiento de Contraseña (Paso 2: Cambio con token)
 */
session_start();
require_once '../config/db.php';

$token   = trim($_GET['token'] ?? '');
$mensaje = '';
$tipo    = '';
$tokenValido = false;
$user    = null;

// Validar token
if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, email FROM users WHERE reset_token = :token AND token_expira > NOW() AND estado = 'activo' LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $tokenValido = true;
    } catch (PDOException $e) {
        error_log("Error en reset_password.php (validación): " . $e->getMessage());
    }
}

// Procesar nuevo password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $pass1 = $_POST['password']         ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if (strlen($pass1) < 8) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
        $tipo    = 'danger';
    } elseif ($pass1 !== $pass2) {
        $mensaje = 'Las contraseñas no coinciden.';
        $tipo    = 'danger';
    } else {
        try {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            $upd  = $pdo->prepare("UPDATE users SET password = :pass, reset_token = NULL, token_expira = NULL WHERE id = :id AND reset_token = :token");
            $upd->execute([':pass' => $hash, ':id' => $user['id'], ':token' => $token]);

            $mensaje     = '¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.';
            $tipo        = 'success';
            $tokenValido = false; // Ocultar el formulario
        } catch (PDOException $e) {
            error_log("Error en reset_password.php (update): " . $e->getMessage());
            $mensaje = 'Error al actualizar. Inténtalo más tarde.';
            $tipo    = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Nueva Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); min-height: 100vh; }
        .auth-card { max-width: 440px; border-radius: 20px; border: none; }
        .btn-primary { background: #3b82f6; border: none; border-radius: 10px; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .form-control { border-radius: 10px; padding: 0.75rem 1rem; }
        .strength-bar { height: 4px; border-radius: 2px; transition: all 0.3s; background: #e2e8f0; }
        .strength-bar.weak   { background: #ef4444; width: 33%; }
        .strength-bar.medium { background: #f59e0b; width: 66%; }
        .strength-bar.strong { background: #22c55e; width: 100%; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="auth-card card shadow-lg p-5 w-100 mx-3">
        <div class="text-center mb-4">
            <div class="bg-success-subtle d-inline-block p-3 rounded-circle mb-3">
                <i class="bi bi-shield-lock-fill text-success fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1">Nueva Contraseña</h4>
            <?php if ($user): ?>
                <p class="text-muted small">Hola <strong><?= htmlspecialchars($user['nombre']) ?></strong>, elige una contraseña segura.</p>
            <?php endif; ?>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo ?> rounded-3 mb-4">
                <i class="bi bi-<?= $tipo === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                <?= htmlspecialchars($mensaje) ?>
                <?php if ($tipo === 'success'): ?>
                    <div class="mt-2"><a href="../index.php" class="alert-link fw-bold">Ir al inicio de sesión →</a></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$tokenValido && !$mensaje): ?>
            <div class="alert alert-danger rounded-3">
                <i class="bi bi-x-octagon-fill me-2"></i>
                El enlace de recuperación es <strong>inválido o ha expirado</strong>. Solicita uno nuevo.
            </div>
        <?php endif; ?>

        <?php if ($tokenValido): ?>
        <form method="POST" id="resetForm" novalidate>
            <div class="mb-3">
                <label class="form-label fw-semibold">Nueva Contraseña</label>
                <div class="input-group">
                    <input type="password" name="password" id="pass1" class="form-control border-end-0" placeholder="Mínimo 8 caracteres" required>
                    <button class="btn btn-outline-secondary border-start-0 rounded-end-3" type="button" id="togglePass1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-bar mt-2" id="strengthBar"></div>
                <div class="form-text" id="strengthText"></div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirmar Contraseña</label>
                <div class="input-group">
                    <input type="password" name="password_confirm" id="pass2" class="form-control border-end-0" placeholder="Repite la contraseña" required>
                    <button class="btn btn-outline-secondary border-start-0 rounded-end-3" type="button" id="togglePass2">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="form-text text-danger d-none" id="matchError">Las contraseñas no coinciden</div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="bi bi-check-circle me-2"></i> Guardar Nueva Contraseña
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="../auth/recuperar.php" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Solicitar nuevo enlace
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle visibilidad contraseña
    ['togglePass1','togglePass2'].forEach(function(id) {
        document.getElementById(id)?.addEventListener('click', function() {
            const targetId = id === 'togglePass1' ? 'pass1' : 'pass2';
            const input = document.getElementById(targetId);
            const icon  = this.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('bi-eye','bi-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('bi-eye-slash','bi-eye'); }
        });
    });

    // Indicador de fuerza
    document.getElementById('pass1')?.addEventListener('input', function() {
        const val = this.value;
        const bar = document.getElementById('strengthBar');
        const txt = document.getElementById('strengthText');
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        bar.className = 'strength-bar mt-2';
        if (score <= 1) { bar.classList.add('weak');   txt.textContent = 'Contraseña débil'; }
        else if (score <= 3) { bar.classList.add('medium'); txt.textContent = 'Contraseña media'; }
        else { bar.classList.add('strong'); txt.textContent = 'Contraseña fuerte ✓'; }
    });

    // Validación coincidencia
    document.getElementById('pass2')?.addEventListener('input', function() {
        const match = document.getElementById('matchError');
        this.value !== document.getElementById('pass1').value
            ? match.classList.remove('d-none')
            : match.classList.add('d-none');
    });
    </script>
</body>
</html>
