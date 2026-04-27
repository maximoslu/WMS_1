<?php
/**
 * WMS_1 - Notificador de Entradas por Email
 * Busca el email del cliente y envía notificación profesional.
 */
require_once '../config/setup.php';
require_once '../config/db.php';
require_once '../includes/MailerController.php';

header('Content-Type: application/json; charset=utf-8');

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado.']);
    exit;
}

$entrada_id = (int)($_POST['entrada_id'] ?? $_GET['entrada_id'] ?? 0);
if (!$entrada_id) {
    echo json_encode(['ok' => false, 'msg' => 'ID de entrada no especificado.']);
    exit;
}

try {
    // Obtener datos completos de la entrada
    $st = $pdo->prepare("
        SELECT e.*,
               a.sku, a.descripcion AS art_desc, a.paletizado_a, a.medida,
               c.nombre_empresa, c.email_contacto, c.contacto_nombre,
               u.nombre AS usuario_nombre,
               ub.codigo_ubicacion
        FROM entradas e
        LEFT JOIN articulos   a  ON a.id = e.articulo_id
        LEFT JOIN clientes    c  ON c.id = e.cliente_id
        LEFT JOIN users       u  ON u.id = e.usuario_id
        LEFT JOIN ubicaciones ub ON ub.id = e.ubicacion_id
        WHERE e.id = :id
    ");
    $st->execute([':id' => $entrada_id]);
    $e = $st->fetch(PDO::FETCH_ASSOC);

    if (!$e) {
        echo json_encode(['ok' => false, 'msg' => 'Entrada no encontrada.']);
        exit;
    }

    $email = trim($e['email_contacto'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => "El cliente '{$e['nombre_empresa']}' no tiene email de contacto registrado."]);
        exit;
    }

    // Formato de fecha
    $fecha = date('d/m/Y H:i', strtotime($e['fecha']));

    // Calcular desglose
    $pallets_txt = $e['pallets'] > 0 ? "{$e['pallets']} pallets" : '';
    $picos_txt   = $e['picos']   > 0 ? number_format($e['picos'], 2) . ' ' . ($e['medida'] ?? 'uds') . ' picos' : '';
    $desglose    = implode(' + ', array_filter([$pallets_txt, $picos_txt])) ?: number_format($e['unidades_total'], 2) . ' uds';

    $ubicacion = $e['codigo_ubicacion'] ? "<strong>{$e['codigo_ubicacion']}</strong>" : '<em>Sin ubicación asignada</em>';
    $proveedor = htmlspecialchars($e['proveedor'] ?: '—');

    // Plantilla HTML del correo
    $html = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body { font-family: "Inter", Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
  .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
  .header  { background: linear-gradient(135deg, #0f172a, #1e3a5f); padding: 32px 32px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.4rem; margin: 0; }
  .header p  { color: #93c5fd; font-size: 0.85rem; margin: 6px 0 0; }
  .body    { padding: 32px; }
  .greeting { font-size: 1rem; color: #1e293b; margin-bottom: 20px; }
  .kpi-row { display: flex; gap: 12px; margin: 20px 0; }
  .kpi { flex: 1; background: #f0f5ff; border-radius: 10px; padding: 14px; text-align: center; }
  .kpi .num { font-size: 1.5rem; font-weight: 700; color: #1e40af; }
  .kpi .lbl { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
  .detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
  .detail-table td { padding: 10px 14px; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; }
  .detail-table td:first-child { color: #64748b; font-weight: 600; width: 38%; }
  .detail-table td:last-child  { color: #1e293b; font-weight: 500; }
  .footer { background: #f8fafc; padding: 20px 32px; text-align: center; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
  .badge-in { background: #dcfce7; color: #166534; border-radius: 50px; padding: 4px 12px; font-weight: 700; font-size: 0.8rem; }
</style></head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>📦 Nueva Mercancía Recibida</h1>
    <p>Notificación automática del sistema WMS · ' . $fecha . '</p>
  </div>
  <div class="body">
    <p class="greeting">Estimado/a <strong>' . htmlspecialchars($e['contacto_nombre'] ?: $e['nombre_empresa']) . '</strong>,<br>
    Le informamos que se ha recibido mercancía en nuestras instalaciones para su cuenta.</p>

    <div class="kpi-row">
      <div class="kpi">
        <div class="num">' . number_format($e['unidades_total'], 2) . '</div>
        <div class="lbl">Unidades Totales</div>
      </div>
      <div class="kpi">
        <div class="num">' . ($e['pallets'] > 0 ? $e['pallets'] : '—') . '</div>
        <div class="lbl">Pallets</div>
      </div>
      <div class="kpi">
        <div class="num">' . ($e['picos'] > 0 ? number_format($e['picos'], 2) : '—') . '</div>
        <div class="lbl">Picos / Sueltos</div>
      </div>
    </div>

    <table class="detail-table">
      <tr><td>Artículo (SKU)</td><td>[' . htmlspecialchars($e['sku']) . '] ' . htmlspecialchars($e['art_desc']) . '</td></tr>
      <tr><td>Proveedor</td><td>' . $proveedor . '</td></tr>
      <tr><td>Desglose</td><td>' . $desglose . '</td></tr>
      <tr><td>Ubicación destino</td><td>' . $ubicacion . '</td></tr>
      <tr><td>Registrado por</td><td>' . htmlspecialchars($e['usuario_nombre'] ?? 'Sistema') . '</td></tr>
      <tr><td>Estado</td><td><span class="badge-in">✅ RECIBIDO</span></td></tr>
    </table>

    <p style="font-size: 0.85rem; color: #64748b; margin-top: 24px;">
      Si tiene alguna consulta sobre esta recepción, contacte con su gestor de almacén.<br>
      Este correo ha sido generado automáticamente por el sistema <strong>MAXIMO WMS</strong>.
    </p>
  </div>
  <div class="footer">
    © ' . date('Y') . ' MAXIMO WMS · Gestión de Almacén Multicliente<br>
    <a href="https://maximosl.com" style="color: #3b82f6; text-decoration: none;">maximosl.com</a>
  </div>
</div>
</body></html>';

    $asunto = "WMS · Mercancía recibida: [{$e['sku']}] " . date('d/m/Y');

    $mailer = new MailerController();
    $ok = $mailer->enviarCorreo($email, $asunto, $html);

    if ($ok) {
        // Marcar como notificado
        try {
            $pdo->prepare("UPDATE entradas SET notificado = 1 WHERE id = :id")->execute([':id' => $entrada_id]);
        } catch (PDOException $e2) { /* ignorar */ }
        echo json_encode(['ok' => true, 'msg' => "Notificación enviada a {$email}"]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Error SMTP: ' . $mailer->lastResponse]);
    }

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
