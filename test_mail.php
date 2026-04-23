<?php
/**
 * WMS_1 – Test de Conexión SMTP (PHPMailer)
 * ─────────────────────────────────────────────────────────
 * ARCHIVO 100 % INDEPENDIENTE: no incluye header.php, sesiones ni BD.
 * Solo necesita MailerController.php y la carpeta vendor/.
 *
 * Acceso:
 *   - localhost : sin clave   → http://localhost/.../test_mail.php
 *   - IP externa: con clave   → https://dominio.com/.../test_mail.php?clave=WMS_TEST_2026
 *   - Email de prueba         → &email=otro@correo.com
 */

// ── 0. Deshabilitar buffering para que los errores fatales aparezcan en pantalla
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ── 1. Control de acceso ─────────────────────────────────────────────────────
$ip_cliente     = $_SERVER['REMOTE_ADDR'] ?? '';
$ips_permitidas = ['127.0.0.1', '::1'];

if (!in_array($ip_cliente, $ips_permitidas)) {
    $clave = $_GET['clave'] ?? '';
    if ($clave !== 'WMS_TEST_2026') {
        http_response_code(403);
        die('Acceso denegado. Añade ?clave=WMS_TEST_2026 a la URL.');
    }
}

// ── 2. Cargar MailerController ──────────────────────────────────────────────
$mailerPath = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'MailerController.php';

if (!file_exists($mailerPath)) {
    die("❌ ERROR: No se encontró MailerController.php en " . htmlspecialchars($mailerPath));
}
require_once $mailerPath;

// ── 3. Construir payload de prueba ────────────────────────────────────────────
$destinatario = filter_var($_GET['email'] ?? 'administracion@maximosl.com', FILTER_VALIDATE_EMAIL)
    ?: 'administracion@maximosl.com';

$asunto = 'WMS Test SMTP FINAL – ' . date('d/m/Y H:i:s');

// ── 4. Ejecutar envío ─────────────────────────────────────────────────────────
$logFile  = __DIR__ . DIRECTORY_SEPARATOR . 'debug_mail.log';
$logAntes = file_exists($logFile) ? file_get_contents($logFile) : '';
$tInicio  = microtime(true);

$mailer    = new MailerController();
$resultado = $mailer->enviarCorreo($destinatario, $asunto, "<h3>Prueba de Envío Directo SMTP</h3><p>Este correo valida que el servidor WMS puede enviar notificaciones sin intermediarios.</p>");

$duracionMs  = round((microtime(true) - $tInicio) * 1000);
$logDespues  = file_exists($logFile) ? file_get_contents($logFile) : '';
$logNuevo    = trim(substr($logDespues, strlen($logAntes)));

// ── 5. Datos de diagnóstico ───────────────────────────────────────────────────
$lastResponse = $mailer->lastResponse;
$httpCode     = $mailer->lastHttpCode;
$metodoUsado  = $mailer->lastMethod;

// Comprobar si PHPMailer está instalado y constantes cargadas
$vendorExiste = is_dir(__DIR__ . '/includes/vendor/PHPMailer');
$remitenteOk  = defined('MAIL_USER') && MAIL_USER === 'sistema@maximosl.com';
$configCargada = defined('MAIL_HOST');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>WMS – Panel de Diagnóstico SMTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; padding: 2rem 0; }
        .card-dark { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .code-box { background: #0d1117; color: #8b949e; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        .l-ok { color: #4ade80; } .l-err { color: #f87171; } .l-info { color: #60a5fa; }
        .badge-mono { font-family: monospace; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="container" style="max-width: 860px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-shield-check fs-2 text-primary"></i>
            <div>
                <h4 class="mb-0 fw-bold">WMS — Diagnóstico de Mensajería</h4>
                <small class="text-muted">Conexión Directa SMTP DonDominio</small>
            </div>
        </div>
        <div class="text-end">
            <span class="status-pill <?= $resultado ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                <?= $resultado ? 'Sistema Online' : 'Error Detectado' ?>
            </span>
        </div>
    </div>

    <!-- Alerta de Configuración -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-dark h-100 p-3 text-center">
                <div class="text-muted small mb-1">Configuración</div>
                <div class="fw-bold <?= $configCargada ? 'text-success' : 'text-danger' ?>">
                    <?= $configCargada ? '✅ Cargada' : '❌ No encontrada' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark h-100 p-3 text-center">
                <div class="text-muted small mb-1">Remitente Validado</div>
                <div class="fw-bold <?= $remitenteOk ? 'text-success' : 'text-warning' ?>">
                    <?= $remitenteOk ? '✅ sistema@' : '⚠️ ' . (MAIL_USER ?? 'N/A') ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark h-100 p-3 text-center">
                <div class="text-muted small mb-1">Librería</div>
                <div class="fw-bold <?= $vendorExiste ? 'text-success' : 'text-danger' ?>">
                    <?= $vendorExiste ? '✅ PHPMailer' : '❌ nativa mail()' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Banner de estado -->
    <?php if ($resultado): ?>
        <div class="alert alert-success border-0 rounded-4 p-3 d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-check-circle-fill fs-3"></i>
            <div>
                <div class="fw-bold">✅ ENVÍO EXITOSO</div>
                <div class="small">El servidor SMTP aceptó el mensaje. Revisa <strong><?= htmlspecialchars($destinatario) ?></strong>.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger border-0 rounded-4 p-3 d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-x-octagon-fill fs-3"></i>
            <div>
                <div class="fw-bold">❌ FALLO EN EL ENVÍO</div>
                <div class="small">No se pudo entregar el correo. Revisa los detalles de abajo.</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card-dark">
                <h6 class="text-uppercase text-muted small fw-bold mb-3">Respuesta del Sistema</h6>
                <div class="mb-3">
                    <span class="badge <?= $resultado ? 'bg-success' : 'bg-danger' ?> badge-mono p-2">
                        Status: <?= $resultado ? 'OK' : 'ERROR' ?>
                    </span>
                    <span class="badge bg-secondary badge-mono p-2 ms-2">
                        Método: <?= strtoupper($metodoUsado) ?>
                    </span>
                </div>
                <div class="code-box"><?= htmlspecialchars($lastResponse ?: '(Sin respuesta)') ?></div>
            </div>
        </div>
    </div>

    <div class="card-dark">
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Log de Red (PHPMailer)</h6>
        <div class="code-box"><?php
            foreach (explode("\n", htmlspecialchars($logNuevo)) as $linea) {
                if (str_contains($linea, 'ERROR') || str_contains($linea, 'FAIL')) echo '<span class="l-err">'.$linea.'</span>'."\n";
                elseif (str_contains($linea, 'OK') || str_contains($linea, 'INICIO')) echo '<span class="l-ok">'.$linea.'</span>'."\n";
                else echo '<span class="l-info">'.$linea.'</span>'."\n";
            }
        ?></div>
    </div>

    <div class="d-flex gap-2">
        <a href="test_mail.php?clave=WMS_TEST_2026&email=<?= urlencode($destinatario) ?>" class="btn btn-primary rounded-3 btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Reintentar Test
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 btn-sm">
            <i class="bi bi-grid me-1"></i>Volver al Panel
        </a>
    </div>

</div>
</body>
</html>

