<?php
/**
 * WMS_1 - Recuperación de Contraseña (Paso 1: Solicitud de email)
 */
session_start();
require_once '../config/db.php';

$mensaje = '';
$tipo    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Por favor, introduce un email válido.';
        $tipo    = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nombre FROM users WHERE email = :email AND estado = 'activo' LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Siempre mostramos el mismo mensaje para no revelar si el email existe (seguridad)
            $mensaje = 'Si el correo existe en nuestro sistema, recibirás las instrucciones en breve.';
            $tipo    = 'success';

            if ($user) {
                // Generar token seguro (64 chars hex, expira en 1 hora)
                $token  = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $upd = $pdo->prepare("UPDATE users SET reset_token = :token, token_expira = :expira WHERE id = :id");
                $upd->execute([':token' => $token, ':expira' => $expira, ':id' => $user['id']]);

                $reset_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                // Envío real via MailerController → Google Apps Script Webhook
                require_once '../includes/MailerController.php';
                $mailer = new MailerController();

                $html = "
                <div style='font-family:Inter,sans-serif;max-width:520px;margin:auto;'>
                    <div style='background:#0f172a;padding:24px;border-radius:12px 12px 0 0;text-align:center;'>
                        <h2 style='color:#3b82f6;margin:0;'>MAXIMO<span style='color:#fff;'>WMS</span></h2>
                    </div>
                    <div style='background:#f8fafc;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;'>
                        <p style='font-size:16px;color:#1e293b;'>Hola <strong>{$user['nombre']}</strong>,</p>
                        <p style='color:#475569;'>Hemos recibido una solicitud para restablecer la contrase&ntilde;a de tu cuenta.</p>
                        <div style='text-align:center;margin:32px 0;'>
                            <a href='{$reset_url}' style='background:#3b82f6;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;'>
                                Restablecer Contrase&ntilde;a
                            </a>
                        </div>
                        <p style='color:#94a3b8;font-size:13px;'>Este enlace caduca en <strong>1 hora</strong>. Si no lo solicitaste, ignora este mensaje.</p>
                        <p style='color:#94a3b8;font-size:11px;margin-top:24px;border-top:1px solid #e2e8f0;padding-top:16px;'>MAXIMO WMS &middot; Sistema de Gesti&oacute;n de Almac&eacute;n</p>
                    </div>
                </div>";

                $enviado = $mailer->enviarCorreo($email, "WMS - Recuperación de contraseña", $html);
                if (!$enviado) {
                    error_log("[WMS RECUPERACIÓN] WEBHOOK FAIL | {$user['nombre']} | {$email} | URL: {$reset_url}");
                }
            }
        } catch (PDOException $e) {
            error_log("Error en recuperar.php: " . $e->getMessage());
            $mensaje = 'Error del sistema. Inténtalo más tarde.';
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
    <title>WMS - Recuperar Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); min-height: 100vh; }
        .auth-card { max-width: 440px; border-radius: 20px; border: none; }
        .btn-primary { background: #3b82f6; border: none; border-radius: 10px; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .form-control { border-radius: 10px; padding: 0.75rem 1rem; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="auth-card card shadow-lg p-5 w-100 mx-3">
        <div class="text-center mb-4">
            <div class="bg-primary-subtle d-inline-block p-3 rounded-circle mb-3">
                <i class="bi bi-key-fill text-primary fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1">Recuperar Contraseña</h4>
            <p class="text-muted small">Introduce tu email y te enviaremos las instrucciones.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo ?> rounded-3 mb-4">
                <i class="bi bi-<?= $tipo === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($tipo !== 'success'): ?>
        <form method="POST" novalidate>
            <div class="mb-4">
                <label class="form-label fw-semibold">Correo Electrónico</label>
                <input type="email" name="email" class="form-control" placeholder="tu@email.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="bi bi-send me-2"></i> Enviar Instrucciones
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="../index.php" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Volver al inicio de sesión
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
