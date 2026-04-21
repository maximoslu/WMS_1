<?php
/**
 * WMS_1 - Test de Conexión con Google Apps Script Webhook
 * ⚠️ ELIMINAR ESTE ARCHIVO TRAS CONFIRMAR LA CONEXIÓN EN PRODUCCIÓN.
 *
 * Uso: Accede a este archivo desde el navegador.
 * Resultado esperado: "✅ WEBHOOK OK" si Apps Script responde correctamente.
 */

// Acceso restringido: solo desde localhost o IP de desarrollo
$ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '';
$ips_permitidas = ['127.0.0.1', '::1'];

if (!in_array($ip_cliente, $ips_permitidas)) {
    // En servidor remoto: requerir clave en URL para evitar acceso no autorizado
    $clave_parametro = $_GET['clave'] ?? '';
    if ($clave_parametro !== 'WMS_TEST_2026') {
        http_response_code(403);
        die('<h2 style="color:red;font-family:sans-serif;">403 - Acceso Denegado. Añade ?clave=WMS_TEST_2026 a la URL.</h2>');
    }
}

require_once 'includes/MailerController.php';

$destinatario = $_GET['email'] ?? 'jorge@maximosl.com'; // Cambia a tu email de prueba
$asunto       = 'WMS Test de Conexión - ' . date('d/m/Y H:i:s');
$html         = "
<div style='font-family:Inter,sans-serif;max-width:480px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
    <div style='background:#0f172a;padding:20px;text-align:center;'>
        <h2 style='color:#3b82f6;margin:0;'>MAXIMO<span style='color:#fff;'>WMS</span></h2>
        <p style='color:#94a3b8;margin:6px 0 0;font-size:13px;'>Prueba de Webhook</p>
    </div>
    <div style='padding:28px;background:#f8fafc;'>
        <h3 style='color:#16a34a;'>✅ Conexión Exitosa</h3>
        <p style='color:#475569;'>El sistema WMS puede enviar correos correctamente a través de Google Apps Script.</p>
        <hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0;'>
        <table style='font-size:13px;color:#64748b;width:100%;'>
            <tr><td><strong>Token usado:</strong></td><td>WMS_SECURE_CLOUD_2026</td></tr>
            <tr><td><strong>Destinatario:</strong></td><td>" . htmlspecialchars($destinatario) . "</td></tr>
            <tr><td><strong>Timestamp:</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
            <tr><td><strong>Servidor PHP:</strong></td><td>" . phpversion() . "</td></tr>
            <tr><td><strong>IP servidor:</strong></td><td>" . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "</td></tr>
        </table>
    </div>
</div>";

// --- Ejecutar envío ---
$mailer    = new MailerController();
$resultado = $mailer->enviarCorreo($destinatario, $asunto, $html);

// --- Mostrar resultado en HTML ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Test de Correo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', monospace; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .test-card { max-width: 600px; border-radius: 16px; }
        pre { background: #1e293b; color: #94a3b8; border-radius: 8px; padding: 16px; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="test-card mx-auto p-4 w-100">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-header text-white py-3 px-4" style="background:#0f172a;">
            <div class="d-flex align-items-center">
                <i class="bi bi-envelope-check fs-4 me-2 text-primary"></i>
                <div>
                    <div class="fw-bold">WMS Webhook Test</div>
                    <div class="small text-muted">Google Apps Script · <?= date('d/m/Y H:i:s') ?></div>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if ($resultado): ?>
                <div class="alert alert-success border-0 rounded-3 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div>
                        <div class="fw-bold">✅ WEBHOOK OK — Correo enviado</div>
                        <div class="small">Revisa la bandeja de <strong><?= htmlspecialchars($destinatario) ?></strong> (comprueba también Spam).</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center">
                    <i class="bi bi-x-octagon-fill fs-4 me-3"></i>
                    <div>
                        <div class="fw-bold">❌ WEBHOOK FAIL — El script no respondió "OK"</div>
                        <div class="small">Revisa el log de errores PHP y comprueba que el Apps Script está desplegado como "Cualquiera".</div>
                    </div>
                </div>
            <?php endif; ?>

            <h6 class="fw-bold mt-3 mb-2">Diagnóstico del sistema</h6>
            <pre>
Destino:     <?= htmlspecialchars($destinatario) ?>

Token:       WMS_SECURE_CLOUD_2026
PHP:         <?= PHP_VERSION ?> | cURL: <?= function_exists('curl_version') ? curl_version()['version'] : 'NO DISPONIBLE' ?>

Resultado:   <?= $resultado ? 'TRUE  ← Apps Script devolvió "OK"' : 'FALSE ← Respuesta inesperada o cURL error' ?>

Timestamp:   <?= date('Y-m-d H:i:s') ?>
            </pre>

            <div class="alert alert-warning border-0 rounded-3 small mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Recuerda:</strong> Elimina o protege este archivo (<code>test_mail.php</code>) antes de pasar a producción.
            </div>
        </div>
        <div class="card-footer bg-light py-3 px-4 text-center">
            <a href="dashboard.php" class="btn btn-sm btn-dark rounded-3 me-2"><i class="bi bi-grid me-1"></i>Dashboard</a>
            <a href="test_mail.php?clave=WMS_TEST_2026&email=<?= urlencode($destinatario) ?>" class="btn btn-sm btn-outline-primary rounded-3">
                <i class="bi bi-arrow-clockwise me-1"></i>Repetir Test
            </a>
        </div>
    </div>
</div>
</body>
</html>
