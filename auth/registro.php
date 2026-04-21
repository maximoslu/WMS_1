<?php
/**
 * WMS_1 - Registro de Nuevos Usuarios
 * Estado inicial: 'pendiente'. Requiere aprobación de SuperAdmin/Administracion.
 */
session_start();
require_once '../config/db.php';

$mensaje = '';
$tipo    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $pass2    = trim($_POST['password_confirm'] ?? '');
    $empresa  = trim($_POST['empresa']  ?? '');

    $errores = [];
    if (strlen($nombre) < 3)                               $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errores[] = 'El email no es válido.';
    if (strlen($password) < 8)                             $errores[] = 'La contraseña debe tener mínimo 8 caracteres.';
    if ($password !== $pass2)                              $errores[] = 'Las contraseñas no coinciden.';

    if (empty($errores)) {
        try {
            // Verificar que el email no esté ya en uso
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $errores[] = 'Este email ya está registrado. ¿Olvidaste tu contraseña?';
            }
        } catch (PDOException $e) {
            $errores[] = 'Error al verificar el email.';
        }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Insertar usuario en estado 'pendiente' con todos los campos de la tabla
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare("
                INSERT INTO users (nombre, email, empresa, password, rol, cliente_id, estado)
                VALUES (:nombre, :email, :empresa, :pass, 'Almacen', NULL, 'pendiente')
            ");
            $ins->execute([
                ':nombre'  => $nombre,
                ':email'   => $email,
                ':empresa' => $empresa ?: null,   // NULL si no se informó
                ':pass'    => $hash
            ]);
            $nuevo_user_id = $pdo->lastInsertId();

            // Crear notificación para administradores
            $msg_notif = "Nuevo usuario pendiente de aprobación: <strong>" . htmlspecialchars($nombre) . "</strong> ({$email})";
            $notif = $pdo->prepare("INSERT INTO notificaciones (destinatario_rol, mensaje, url) VALUES ('superadmin', :msg, '/admin/usuarios.php')");
            $notif->execute([':msg' => $msg_notif]);

            $pdo->commit();

            // Notificar al SuperAdmin por email vía Apps Script Webhook
            try {
                require_once '../includes/MailerController.php';
                $mailer  = new MailerController();
                $admStmt = $pdo->prepare("SELECT email, nombre FROM users WHERE rol = 'superadmin' AND estado = 'activo' LIMIT 1");
                $admStmt->execute();
                $admin = $admStmt->fetch(PDO::FETCH_ASSOC);

                if ($admin) {
                    $admin_url = "https://" . $_SERVER['HTTP_HOST'] . "/admin/usuarios.php";
                    $htmlAdmin = "
                    <div style='font-family:Inter,sans-serif;max-width:520px;margin:auto;'>
                        <div style='background:#0f172a;padding:24px;border-radius:12px 12px 0 0;text-align:center;'>
                            <h2 style='color:#3b82f6;margin:0;'>MAXIMO<span style='color:#fff;'>WMS</span></h2>
                        </div>
                        <div style='background:#f8fafc;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
                            <p style='color:#1e293b;font-size:16px;'>Hola <strong>{$admin['nombre']}</strong>,</p>
                            <p style='color:#475569;'>Un nuevo usuario ha solicitado acceso al WMS:</p>
                            <div style='background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:20px 0;'>
                                <p style='margin:4px 0;'><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>
                                <p style='margin:4px 0;'><strong>Email:</strong> {$email}</p>
                                <p style='margin:4px 0;'><strong>Estado:</strong> <span style='color:#d97706;font-weight:700;'>Pendiente</span></p>
                            </div>
                            <div style='text-align:center;margin:24px 0;'>
                                <a href='{$admin_url}' style='background:#0f172a;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;'>
                                    Gestionar Solicitud
                                </a>
                            </div>
                            <p style='color:#94a3b8;font-size:11px;border-top:1px solid #e2e8f0;padding-top:16px;margin-top:24px;'>MAXIMO WMS &middot; Notificaci&oacute;n autom&aacute;tica</p>
                        </div>
                    </div>";
                    $mailer->enviarCorreo($admin['email'], "WMS - Nueva solicitud de acceso: " . htmlspecialchars($nombre), $htmlAdmin);
                }
            } catch (Exception $mailerEx) {
                error_log("[MailerController] Error notificando admin: " . $mailerEx->getMessage());
            }

            $mensaje = "Registro completado. Tu cuenta est&aacute; <strong>pendiente de aprobaci&oacute;n</strong> por un administrador.";
            $tipo    = 'success';

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error en registro.php: " . $e->getMessage());
            $mensaje = 'Error al crear la cuenta. Inténtalo más tarde.';
            $tipo    = 'danger';
        }
    } else {
        $mensaje = implode('<br>', $errores);
        $tipo    = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Crear Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); min-height: 100vh; }
        .auth-card { max-width: 520px; border-radius: 20px; border: none; }
        .form-control, .form-select { border-radius: 10px; padding: 0.7rem 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .form-control.is-valid  { border-color: #22c55e; }
        .form-control.is-invalid { border-color: #ef4444; }
        .btn-primary { background: #3b82f6; border: none; border-radius: 10px; font-weight: 700; transition: all 0.2s; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(59,130,246,0.3); }
        .strength-dots { display: flex; gap: 4px; margin-top: 6px; }
        .strength-dot { flex: 1; height: 4px; border-radius: 2px; background: #e2e8f0; transition: background 0.3s; }
        .field-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; }
        .input-wrap { position: relative; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="auth-card card shadow-lg p-4 p-md-5 w-100 mx-3">

        <div class="text-center mb-4">
            <div style="background: linear-gradient(135deg, #3b82f6, #6366f1); width:60px; height:60px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                <i class="bi bi-person-plus-fill text-white fs-3"></i>
            </div>
            <h4 class="fw-bold mb-1">Crear Cuenta</h4>
            <p class="text-muted small">Completa el formulario. Tu cuenta estará activa tras aprobación del administrador.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo ?> rounded-3 mb-4">
                <?= $mensaje ?>
                <?php if ($tipo === 'success'): ?>
                    <div class="mt-2"><a href="../index.php" class="alert-link fw-bold">Volver al inicio de sesión →</a></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tipo !== 'success'): ?>
        <form method="POST" id="registroForm" novalidate>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <input type="text" name="nombre" id="f_nombre" class="form-control"
                               placeholder="Juan García" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required minlength="3">
                        <i class="bi bi-person field-icon"></i>
                    </div>
                    <div class="invalid-feedback">Mínimo 3 caracteres.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <input type="email" name="email" id="f_email" class="form-control"
                               placeholder="tu@empresa.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <i class="bi bi-envelope field-icon"></i>
                    </div>
                    <div class="invalid-feedback">Introduce un email válido.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Empresa / Razón Social</label>
                    <div class="input-wrap">
                        <input type="text" name="empresa" class="form-control"
                               placeholder="Empresa S.L. (opcional)" value="<?= htmlspecialchars($_POST['empresa'] ?? '') ?>">
                        <i class="bi bi-building field-icon"></i>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="f_pass" class="form-control" placeholder="Mín. 8 caracteres" required minlength="8">
                        <i class="bi bi-eye field-icon" id="togglePass" style="right:12px;"></i>
                    </div>
                    <div class="strength-dots mt-2">
                        <div class="strength-dot" id="d1"></div>
                        <div class="strength-dot" id="d2"></div>
                        <div class="strength-dot" id="d3"></div>
                        <div class="strength-dot" id="d4"></div>
                    </div>
                    <div class="form-text" id="strengthLabel"></div>
                    <div class="invalid-feedback">Mínimo 8 caracteres.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirmar Contraseña <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <input type="password" name="password_confirm" id="f_pass2" class="form-control" placeholder="Repite la contraseña" required>
                        <i class="bi bi-eye field-icon" id="togglePass2" style="right:12px;"></i>
                    </div>
                    <div class="form-text text-danger d-none" id="matchError">Las contraseñas no coinciden</div>
                    <div class="invalid-feedback">Confirma tu contraseña.</div>
                </div>

                <div class="col-12 mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terminos" required>
                        <label class="form-check-label text-muted small" for="terminos">
                            Acepto los <a href="#" class="text-primary">términos de uso</a> y la <a href="#" class="text-primary">política de privacidad</a>.
                        </label>
                        <div class="invalid-feedback">Debes aceptar los términos.</div>
                    </div>
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary w-100 py-2 fs-6">
                        <i class="bi bi-person-check me-2"></i> Solicitar Acceso
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">¿Ya tienes cuenta? </span>
            <a href="../index.php" class="text-primary fw-semibold text-decoration-none small">Iniciar Sesión</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle visibilidad de contraseñas
    function toggleVisibility(btnId, inputId) {
        document.getElementById(btnId)?.addEventListener('click', function() {
            const input = document.getElementById(inputId);
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            this.classList.replace(isPass ? 'bi-eye' : 'bi-eye-slash', isPass ? 'bi-eye-slash' : 'bi-eye');
        });
    }
    toggleVisibility('togglePass', 'f_pass');
    toggleVisibility('togglePass2', 'f_pass2');

    // Indicador de fuerza de contraseña
    document.getElementById('f_pass')?.addEventListener('input', function() {
        const val = this.value;
        const dots  = [document.getElementById('d1'), document.getElementById('d2'), document.getElementById('d3'), document.getElementById('d4')];
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 8)           score++;
        if (/[A-Z]/.test(val))         score++;
        if (/[0-9]/.test(val))         score++;
        if (/[^A-Za-z0-9]/.test(val))  score++;

        const colors = ['#ef4444','#f59e0b','#f59e0b','#22c55e'];
        const labels = ['Muy débil','Débil','Media','Fuerte ✓'];
        dots.forEach((d, i) => { d.style.background = i < score ? colors[score - 1] : '#e2e8f0'; });
        label.textContent = val.length > 0 ? labels[score - 1] || '' : '';
    });

    // Validación de coincidencia en tiempo real
    document.getElementById('f_pass2')?.addEventListener('input', function() {
        const err = document.getElementById('matchError');
        this.value !== document.getElementById('f_pass').value
            ? err.classList.remove('d-none') : err.classList.add('d-none');
    });

    // Validación Bootstrap en envío
    document.getElementById('registroForm')?.addEventListener('submit', function(e) {
        const pass  = document.getElementById('f_pass').value;
        const pass2 = document.getElementById('f_pass2').value;
        if (!this.checkValidity() || pass !== pass2) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
    </script>
</body>
</html>
