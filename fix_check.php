<?php
/**
 * WMS_1 - Fix Check: Verificación de integridad de columnas críticas
 * Acceso: solo localhost o con clave ?clave=WMS_TEST_2026
 */

// Protección de acceso
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'])) {
    if (($_GET['clave'] ?? '') !== 'WMS_TEST_2026') {
        http_response_code(403);
        die('<h3 style="color:red;font-family:sans-serif;">403 - Acceso denegado. Añade ?clave=WMS_TEST_2026</h3>');
    }
}

require_once 'config/db.php';

// Columnas requeridas por tabla
$checks = [
    'users' => [
        'id', 'nombre', 'email', 'empresa', 'password',
        'rol', 'cliente_id', 'estado', 'reset_token', 'token_expira'
    ],
    'notificaciones' => [
        'id', 'destinatario_rol', 'mensaje', 'url', 'leido', 'created_at'
    ],
    'articulos' => [
        'id', 'cliente_id', 'sku', 'descripcion', 'lote',
        'medida', 'paletizado_a', 'stock_actual'
    ]
];

$resultados = [];
$todo_ok    = true;

foreach ($checks as $tabla => $columnas_req) {
    try {
        $stmt = $pdo->query("DESCRIBE `{$tabla}`");
        $existentes = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

        foreach ($columnas_req as $col) {
            $existe = in_array($col, $existentes);
            $resultados[$tabla][$col] = $existe;
            if (!$existe) $todo_ok = false;
        }
    } catch (PDOException $e) {
        $resultados[$tabla]['_ERROR_'] = $e->getMessage();
        $todo_ok = false;
    }
}

// Verificar métodos de envío del Mailer
require_once 'includes/MailerController.php';
$curl_ok = function_exists('curl_init');
$fgc_ok  = (bool)ini_get('allow_url_fopen');
if (!$curl_ok && !$fgc_ok) $todo_ok = false;

// Salida para uso programático (llamada desde scripts)
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'       => $todo_ok,
        'columnas' => $resultados,
        'mailer'   => ['curl' => $curl_ok, 'fgc' => $fgc_ok]
    ]);
    exit;
}

// Salida simple para integración (compatible con Assert en CI)
if ($todo_ok) {
    echo "DB y Mailer listos";   // Salida exacta requerida
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Fix Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; min-height: 100vh; padding: 40px 20px; }
        .check-card { max-width: 800px; margin: auto; border-radius: 16px; overflow: hidden; }
        .col-ok   { color: #16a34a; }
        .col-fail { color: #dc2626; font-weight: 700; }
    </style>
</head>
<body>
<div class="check-card">
    <div class="card border-0 shadow-lg">
        <div class="card-header py-3 px-4" style="background:#0f172a;">
            <div class="d-flex align-items-center">
                <i class="bi bi-shield-check text-primary fs-4 me-2"></i>
                <div>
                    <div class="fw-bold text-white">WMS Fix Check</div>
                    <div class="small text-muted">Integridad de Base de Datos & Mailer · <?= date('d/m/Y H:i:s') ?></div>
                </div>
                <span class="ms-auto badge <?= $todo_ok ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-2">
                    <?= $todo_ok ? '✅ TODO OK' : '❌ HAY ERRORES' ?>
                </span>
            </div>
        </div>
        <div class="card-body p-4">

            <?php foreach ($resultados as $tabla => $cols): ?>
            <h6 class="fw-bold mb-2 mt-3"><i class="bi bi-table me-2 text-primary"></i>Tabla: <code><?= $tabla ?></code></h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr><th>Columna requerida</th><th style="width:120px;">Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cols as $col => $ok): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($col) ?></code></td>
                            <td>
                                <?php if ($col === '_ERROR_'): ?>
                                    <span class="col-fail"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($ok) ?></span>
                                <?php elseif ($ok): ?>
                                    <span class="col-ok"><i class="bi bi-check-circle-fill me-1"></i>Existe</span>
                                <?php else: ?>
                                    <span class="col-fail"><i class="bi bi-x-circle-fill me-1"></i>FALTA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <h6 class="fw-bold mb-2 mt-3"><i class="bi bi-envelope me-2 text-primary"></i>Mailer (métodos de envío)</h6>
            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.85rem;">
                <tbody>
                    <tr>
                        <td><code>cURL</code> (método principal)</td>
                        <td><?= $curl_ok ? '<span class="col-ok"><i class="bi bi-check-circle-fill me-1"></i>Disponible</span>' : '<span class="col-fail"><i class="bi bi-x-circle-fill me-1"></i>NO disponible</span>' ?></td>
                    </tr>
                    <tr>
                        <td><code>file_get_contents</code> (fallback)</td>
                        <td><?= $fgc_ok ? '<span class="col-ok"><i class="bi bi-check-circle-fill me-1"></i>allow_url_fopen = On</span>' : '<span class="col-fail"><i class="bi bi-x-circle-fill me-1"></i>allow_url_fopen = Off</span>' ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!$todo_ok): ?>
            <div class="alert alert-danger rounded-3 mt-4 mb-0">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Acción requerida:</strong> Ejecuta el SQL de migración para las columnas marcadas como FALTA.
            </div>
            <?php else: ?>
            <div class="alert alert-success rounded-3 mt-4 mb-0 d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                <div>
                    <div class="fw-bold">DB y Mailer listos</div>
                    <div class="small">Todas las columnas existen y al menos un método de envío está disponible.</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light text-center py-3">
            <a href="dashboard.php" class="btn btn-sm btn-dark rounded-3 me-2"><i class="bi bi-grid me-1"></i>Dashboard</a>
            <a href="?clave=WMS_TEST_2026" class="btn btn-sm btn-outline-primary rounded-3"><i class="bi bi-arrow-clockwise me-1"></i>Repetir</a>
            <a href="test_mail.php?clave=WMS_TEST_2026" class="btn btn-sm btn-outline-secondary rounded-3 ms-2"><i class="bi bi-envelope me-1"></i>Test Mailer</a>
        </div>
    </div>
</div>
</body>
</html>
