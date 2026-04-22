<?php
/**
 * WMS_1 – Test de Conexión con Google Apps Script Webhook
 * ─────────────────────────────────────────────────────────
 * ARCHIVO 100 % INDEPENDIENTE: no incluye header.php, sesiones ni BD.
 * Solo necesita MailerController.php (cargado con ruta absoluta __DIR__).
 *
 * Acceso:
 *   - localhost : sin clave   → http://localhost/.../test_mail.php
 *   - IP externa: con clave   → https://dominio.com/.../test_mail.php?clave=WMS_TEST_2026
 *   - Email de prueba         → &email=otro@correo.com
 *
 * ⚠️  ELIMINAR O PROTEGER ESTE ARCHIVO ANTES DE PASAR A PRODUCCIÓN.
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
        die(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>403</title><style>body{font-family:monospace;background:#0f172a;color:#f87171;'
            . 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}'
            . 'div{text-align:center;}</style></head><body><div>'
            . '<h1>🔒 403 – Acceso denegado</h1>'
            . '<p>Añade <code style="background:#1e293b;padding:4px 8px;border-radius:4px;">'
            . '?clave=WMS_TEST_2026</code> a la URL.</p>'
            . '</div></body></html>'
        );
    }
}

// ── 2. Cargar MailerController con ruta ABSOLUTA ─────────────────────────────
//     __DIR__ = directorio de este archivo (raíz del proyecto WMS_1)
//     No depende del CWD ni del DocumentRoot del servidor.
$mailerPath = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'MailerController.php';

if (!file_exists($mailerPath)) {
    die(
        '<pre style="background:#1e293b;color:#f87171;padding:20px;font-family:monospace;">'
        . "❌ ERROR CRÍTICO: No se encontró MailerController.php\n"
        . "Ruta buscada: " . htmlspecialchars($mailerPath) . "\n"
        . "Verifica que el archivo existe y que test_mail.php está en la raíz del proyecto."
        . '</pre>'
    );
}
require_once $mailerPath;

// ── 3. Construir payload de prueba ────────────────────────────────────────────
$destinatario = filter_var($_GET['email'] ?? 'jorge@maximosl.com', FILTER_VALIDATE_EMAIL)
    ?: 'jorge@maximosl.com';

$asunto = 'WMS Test de Conexión – ' . date('d/m/Y H:i:s');

$html = "
<div style='font-family:Inter,sans-serif;max-width:480px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
    <div style='background:#0f172a;padding:20px;text-align:center;'>
        <h2 style='color:#3b82f6;margin:0;'>MAXIMO<span style='color:#fff;'>WMS</span></h2>
        <p style='color:#94a3b8;margin:6px 0 0;font-size:13px;'>Prueba de Webhook</p>
    </div>
    <div style='padding:28px;background:#f8fafc;'>
        <h3 style='color:#16a34a;'>✅ Conexión Exitosa</h3>
        <p style='color:#475569;'>El sistema WMS puede enviar correos a través de Google Apps Script.</p>
        <hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0;'>
        <table style='font-size:13px;color:#64748b;width:100%;'>
            <tr><td><strong>Token:</strong></td><td>WMS_SECURE_CLOUD_2026</td></tr>
            <tr><td><strong>Destino:</strong></td><td>" . htmlspecialchars($destinatario) . "</td></tr>
            <tr><td><strong>Timestamp:</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
            <tr><td><strong>PHP:</strong></td><td>" . phpversion() . "</td></tr>
            <tr><td><strong>IP servidor:</strong></td><td>" . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "</td></tr>
        </table>
    </div>
</div>";

// Parámetros GET visibles (sin el HTML completo del cuerpo para no saturar la pantalla)
$payloadVisualData = [
    'email'   => $destinatario,
    'asunto'  => $asunto,
    'mensaje' => '(cuerpo HTML — ver arriba)',
    'token'   => 'WMS_SECURE_CLOUD_2026',
];
$payloadVisual = http_build_query($payloadVisualData, '', " &\n");

// ── 4. Ejecutar envío ─────────────────────────────────────────────────────────
$logFile  = __DIR__ . DIRECTORY_SEPARATOR . 'debug_mail.log';
$logAntes = file_exists($logFile) ? file_get_contents($logFile) : '';
$tInicio  = microtime(true);

$mailer    = new MailerController();
$resultado = $mailer->enviarCorreo($destinatario, $asunto, $html);

$duracionMs  = round((microtime(true) - $tInicio) * 1000);
$logDespues  = file_exists($logFile) ? file_get_contents($logFile) : '';
$logNuevo    = trim(substr($logDespues, strlen($logAntes)));

// ── 5. Datos de diagnóstico de respuesta ──────────────────────────────────────
$rawResponse  = $mailer->lastResponse;   // respuesta bruta de Google
$httpCode     = $mailer->lastHttpCode;   // código HTTP
$metodoUsado  = $mailer->lastMethod;     // 'fgc' | 'curl' | 'none'
$urlContactada = $mailer->lastUrl;       // URL final (tras posibles redirects)

// ── 6. Entorno del servidor ───────────────────────────────────────────────────
$infoCurl   = function_exists('curl_version') ? curl_version() : null;
$curlVer    = $infoCurl ? $infoCurl['version'] : 'NO DISPONIBLE';
$sslVer     = $infoCurl ? $infoCurl['ssl_version'] : 'N/A';
$fgcEnabled = ini_get('allow_url_fopen') ? '✅ Habilitado' : '❌ Deshabilitado';

// ── 7. Comprobar permisos del log ─────────────────────────────────────────────
$logEscribible = is_writable(dirname($logFile)) || is_writable($logFile);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS – Test de Correo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', monospace;
            background: #0f172a;
            min-height: 100vh;
            padding: 2rem 1rem;
            color: #e2e8f0;
        }
        .wrap { max-width: 860px; margin: auto; }

        /* Cajas de código / log */
        .code-box {
            background: #1e293b;
            color: #94a3b8;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.82rem;
            line-height: 1.65;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #334155;
        }
        /* Colores del log */
        .l-ok   { color: #4ade80; }
        .l-err  { color: #f87171; }
        .l-warn { color: #fbbf24; }
        .l-info { color: #60a5fa; }

        /* Caja de respuesta bruta */
        .raw-box {
            background: #0d1a2d;
            color: #38bdf8;
            border: 1px solid #1e40af;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.85rem;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            min-height: 52px;
        }

        .section-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .badge-mono { font-family: monospace; }

        .card-dark {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
        }
        .status-ok   { background: #052e16; border-left: 4px solid #16a34a; }
        .status-fail { background: #2d0c0c; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
<div class="wrap">

    <!-- ── Cabecera ─────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-envelope-paper-fill fs-2 text-primary"></i>
        <div>
            <h4 class="text-white mb-0 fw-bold">WMS — API Mailer 3 (GET)</h4>
            <small class="text-muted">
                Google Apps Script &nbsp;·&nbsp; <?= date('d/m/Y H:i:s') ?>
                &nbsp;·&nbsp; <?= $duracionMs ?> ms
                &nbsp;·&nbsp; método: <code class="text-warning"><?= htmlspecialchars($metodoUsado) ?></code>
            </small>
        </div>
    </div>

    <!-- ── Diagnóstico de ruta ──────────────────────────────────────────── -->
    <?php if (!$logEscribible): ?>
    <div class="alert alert-warning rounded-3 small py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Aviso:</strong> El directorio no tiene permisos de escritura para
        <code>debug_mail.log</code>. El log de red no estará disponible.
        Ruta intentada: <code><?= htmlspecialchars($logFile) ?></code>
    </div>
    <?php endif; ?>

    <!-- ── Banner de estado ─────────────────────────────────────────────── -->
    <?php if ($resultado): ?>
        <div class="alert rounded-4 d-flex align-items-start gap-3 mb-4 status-ok" style="border:none;">
            <i class="bi bi-check-circle-fill fs-3 text-success mt-1"></i>
            <div>
                <div class="fw-bold text-success fs-5">✅ WEBHOOK OK — Correo enviado</div>
                <div class="text-success small mt-1">
                    Apps Script respondió <code class="bg-dark px-1 rounded">OK</code>.
                    Revisa la bandeja de <strong><?= htmlspecialchars($destinatario) ?></strong>
                    (incluye la carpeta de Spam).
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert rounded-4 d-flex align-items-start gap-3 mb-4 status-fail" style="border:none;">
            <i class="bi bi-x-octagon-fill fs-3 text-danger mt-1"></i>
            <div>
                <div class="fw-bold text-danger fs-5">❌ WEBHOOK FAIL — Sin respuesta "OK"</div>
                <div class="text-danger small mt-1">
                    Examina la <strong>Respuesta bruta</strong> y el <strong>Log</strong> de abajo
                    para identificar si es TOKEN inválido, error de red o redirección.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Respuesta bruta + HTTP code (visible siempre) ────────────────── -->
    <div class="card-dark p-3 mb-3">
        <div class="section-label">📡 Respuesta bruta de Google Apps Script</div>
        <div class="d-flex gap-2 mb-2 align-items-center flex-wrap">
            <span class="badge badge-mono fs-6
                <?= $httpCode === 200 ? 'bg-success' : ($httpCode === 0 ? 'bg-secondary' : 'bg-danger') ?>">
                HTTP <?= $httpCode ?: '(sin respuesta)' ?>
            </span>
            <span class="badge bg-secondary badge-mono">URL: <?= htmlspecialchars($urlContactada ?: $mailer->lastUrl ?: '—') ?></span>
        </div>
        <div class="raw-box"><?= $rawResponse !== null
            ? htmlspecialchars($rawResponse)
            : '<span style="color:#64748b;font-style:italic;">(null — no se recibió respuesta del servidor. Ver log para el error.)</span>' ?></div>
        <?php if ($httpCode === 200 && trim((string)$rawResponse) !== 'OK'): ?>
        <div class="small text-warning mt-2">
            <i class="bi bi-info-circle me-1"></i>
            El servidor respondió HTTP 200 pero el body no es exactamente <code>"OK"</code>.
            Posible error en el código del Apps Script o token incorrecto.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Fila: entorno + JSON enviado ─────────────────────────────────── -->
    <div class="row g-3 mb-3">

        <div class="col-md-5">
            <div class="card-dark p-3 h-100">
                <div class="section-label mb-2">⚙️ Entorno del servidor</div>
                <table class="table table-dark table-sm mb-0" style="font-size:0.82rem;">
                    <tr><td class="text-muted">PHP</td>
                        <td><span class="badge bg-secondary badge-mono"><?= PHP_VERSION ?></span></td></tr>
                    <tr><td class="text-muted">cURL</td>
                        <td><span class="badge bg-secondary badge-mono"><?= $curlVer ?></span></td></tr>
                    <tr><td class="text-muted">SSL</td>
                        <td><span class="badge bg-secondary badge-mono"><?= $sslVer ?></span></td></tr>
                    <tr><td class="text-muted">allow_url_fopen</td>
                        <td><small><?= $fgcEnabled ?></small></td></tr>
                    <tr><td class="text-muted">IP cliente</td>
                        <td><span class="badge bg-secondary badge-mono"><?= htmlspecialchars($ip_cliente) ?></span></td></tr>
                    <tr><td class="text-muted">IP servidor</td>
                        <td><span class="badge bg-secondary badge-mono"><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'N/A') ?></span></td></tr>
                    <tr><td class="text-muted">Log escribible</td>
                        <td><small><?= $logEscribible ? '✅ Sí' : '❌ No' ?></small></td></tr>
                    <tr><td class="text-muted">Duración</td>
                        <td><span class="badge badge-mono <?= $duracionMs > 8000 ? 'bg-warning text-dark' : 'bg-success' ?>">
                            <?= $duracionMs ?> ms</span></td></tr>
                </table>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card-dark p-3 h-100">
                <div class="section-label mb-2">📤 Parámetros GET enviados a Google Apps Script</div>
                <div class="code-box" style="max-height:200px;"><?= htmlspecialchars($payloadVisual) ?></div>
            </div>
        </div>

    </div>

    <!-- ── Log de red de esta ejecución ─────────────────────────────────── -->
    <div class="card-dark p-3 mb-3">
        <div class="section-label mb-2">📋 Log de red — debug_mail.log (solo esta ejecución)</div>
        <?php if ($logNuevo): ?>
            <div class="code-box" id="logBox"><?php
                foreach (explode("\n", htmlspecialchars($logNuevo)) as $linea) {
                    if (str_contains($linea, 'ERROR') || str_contains($linea, 'FAIL') || str_contains($linea, 'false')) {
                        echo '<span class="l-err">' . $linea . '</span>' . "\n";
                    } elseif (str_contains($linea, 'AVISO') || str_contains($linea, 'REDIRECT') || str_contains($linea, 'inesperada')) {
                        echo '<span class="l-warn">' . $linea . '</span>' . "\n";
                    } elseif (str_contains($linea, ' OK') || str_contains($linea, 'INICIO') || str_contains($linea, 'FIN')) {
                        echo '<span class="l-ok">' . $linea . '</span>' . "\n";
                    } elseif (str_contains($linea, 'HTTP') || str_contains($linea, 'Headers') || str_contains($linea, 'Body') || str_contains($linea, 'URL')) {
                        echo '<span class="l-info">' . $linea . '</span>' . "\n";
                    } else {
                        echo $linea . "\n";
                    }
                }
            ?></div>
        <?php else: ?>
            <div class="text-muted small">
                <?= $logEscribible
                    ? 'No se generaron entradas de log (respuesta vacía de Google o error anterior al envío).'
                    : '⚠️ El archivo debug_mail.log no pudo escribirse. Revisa permisos del directorio.' ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Guía de diagnóstico ───────────────────────────────────────────── -->
    <div class="card-dark p-3 mb-4" style="border-color:#713f12;">
        <div class="section-label text-warning mb-2">🔍 Cómo interpretar el resultado</div>
        <div style="font-size:0.82rem; color:#94a3b8; line-height:2;">
            <div><span class="text-success fw-semibold">HTTP 200 + Body "OK"</span> &rarr; Todo correcto. El correo fue enviado.</div>
            <div><span class="text-success fw-semibold">HTTP 200 + Body ≠ "OK"</span> &rarr; El script recibió la petición pero hay un error interno (token incorrecto, campos faltantes).</div>
            <div><span class="text-warning fw-semibold">HTTP 301/302 + Location</span> &rarr; Redirección de Google detectada. El controlador reintentará en la nueva URL automáticamente.</div>
            <div><span class="text-danger fw-semibold">HTTP 403</span> &rarr; El token <code class="bg-dark px-1 rounded">WMS_SECURE_CLOUD_2026</code> no coincide con el de Apps Script.</div>
            <div><span class="text-danger fw-semibold">HTTP 0 / null</span> &rarr; Sin respuesta. Problema de red, DNS o firewall bloqueando script.google.com.</div>
            <div><span class="text-danger fw-semibold">cURL error #7</span> &rarr; El servidor no puede alcanzar script.google.com. Verifica conectividad saliente.</div>
        </div>
    </div>

    <!-- ── Acciones ──────────────────────────────────────────────────────── -->
    <div class="d-flex gap-2 flex-wrap mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-dark rounded-3">
            <i class="bi bi-grid me-1"></i>Dashboard
        </a>
        <a href="test_mail.php?clave=WMS_TEST_2026&email=<?= urlencode($destinatario) ?>"
           class="btn btn-sm btn-outline-primary rounded-3">
            <i class="bi bi-arrow-clockwise me-1"></i>Repetir test
        </a>
        <a href="test_mail.php?clave=WMS_TEST_2026&email=<?= urlencode($destinatario) ?>&nc=<?= time() ?>"
           class="btn btn-sm btn-outline-warning rounded-3">
            <i class="bi bi-lightning me-1"></i>Test frío (sin caché)
        </a>
    </div>

    <div class="alert alert-danger border-0 rounded-3 small py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Producción:</strong> Elimina o protege
        <code>test_mail.php</code> y <code>debug_mail.log</code>
        antes de desplegar en producción.
    </div>

</div>
<script>
// Auto-scroll al final del log
const lb = document.getElementById('logBox');
if (lb) lb.scrollTop = lb.scrollHeight;
</script>
</body>
</html>
