<?php
/**
 * WMS_1 - Procesador de Entradas de Mercancía v3
 * Sistema 40+5: Pallets estándar masivos + picos individuales.
 */
require_once '../config/setup.php';
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado.']); exit;
}

$accion       = $_POST['accion'] ?? $_GET['accion'] ?? '';
$usuario_id   = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin = ($rol === 'superadmin');

/* ── Crear tabla y verificar columnas ─────────────────── */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS entradas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor VARCHAR(200) DEFAULT '',
        articulo_id INT NOT NULL,
        cliente_id INT NOT NULL,
        unidades_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        pallets INT DEFAULT 0,
        picos DECIMAL(10,2) DEFAULT 0,
        ubicacion_id INT DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        documento_url VARCHAR(500) DEFAULT NULL,
        notificado TINYINT(1) DEFAULT 0,
        bulto_ref VARCHAR(100) DEFAULT NULL,
        INDEX idx_articulo (articulo_id),
        INDEX idx_cliente (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Asegurar columna bulto_ref
    $columnas = $pdo->query("SHOW COLUMNS FROM entradas LIKE 'bulto_ref'")->fetchAll();
    if (empty($columnas)) {
        $pdo->exec("ALTER TABLE entradas ADD COLUMN bulto_ref VARCHAR(100) DEFAULT NULL");
    }
} catch (PDOException $e) {}

/* ── GET ARTICULO ─────────────────────────────────────── */
if ($accion === 'get_articulo') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT a.id, a.sku, a.descripcion, a.paletizado_a, a.medida, a.cliente_id,
                              c.nombre_empresa AS cliente_nombre, c.email_contacto AS cliente_email
                              FROM articulos a
                              LEFT JOIN clientes c ON c.id = a.cliente_id
                              WHERE a.id = :id");
        $st->execute([':id' => $id]);
        echo json_encode($st->fetch(PDO::FETCH_ASSOC) ?: ['error' => 'No encontrado']);
    } catch (PDOException $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

/* ── LISTAR ENTRADAS ──────────────────────────────────── */
if ($accion === 'listar') {
    try {
        $sql = "SELECT e.bulto_ref, DATE(e.fecha) as fecha_corta, e.proveedor, a.sku, a.descripcion as art_desc,
                       c.nombre_empresa as cliente_nombre, u.nombre as usuario_nombre, ub.codigo_ubicacion,
                       COUNT(e.id) as cantidad_bultos,
                       SUM(e.unidades_total) as unidades_total, SUM(e.pallets) as pallets, SUM(CASE WHEN e.picos > 0 THEN 1 ELSE 0 END) as picos, MAX(e.notificado) as notificado,
                       MAX(e.id) as max_id
                FROM entradas e
                LEFT JOIN articulos   a  ON a.id = e.articulo_id
                LEFT JOIN clientes    c  ON c.id = e.cliente_id
                LEFT JOIN users       u  ON u.id = e.usuario_id
                LEFT JOIN ubicaciones ub ON ub.id = e.ubicacion_id
                GROUP BY e.bulto_ref, DATE(e.fecha), e.proveedor, a.sku, a.descripcion, c.nombre_empresa, u.nombre, ub.codigo_ubicacion
                ORDER BY max_id DESC
                LIMIT 200";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

/* ── GUARDAR SISTEMA 40+5 ─────────────────────────────── */
if ($accion === 'guardar_bultos') {
    $articulo_id       = (int)($_POST['articulo_id'] ?? 0);
    $proveedor         = trim($_POST['proveedor'] ?? '');
    $ubicacion_id      = !empty($_POST['ubicacion_id']) ? (int)$_POST['ubicacion_id'] : null;
    $pallets_completos = (int)($_POST['pallets_completos'] ?? 0);
    $picos_json        = $_POST['picos'] ?? '[]';
    $picos             = json_decode($picos_json, true);

    if (!$articulo_id) {
        echo json_encode(['ok' => false, 'msg' => 'El artículo es obligatorio.']); exit;
    }

    try {
        $stArt = $pdo->prepare("SELECT cliente_id, paletizado_a FROM articulos WHERE id = :id");
        $stArt->execute([':id' => $articulo_id]);
        $artRow = $stArt->fetch(PDO::FETCH_ASSOC);
        if (!$artRow) { echo json_encode(['ok' => false, 'msg' => 'Artículo no encontrado.']); exit; }

        $cliente_id   = (int)$artRow['cliente_id'];
        $paletizado_a = (int)$artRow['paletizado_a'];

        if ($pallets_completos > 0 && $paletizado_a <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Este artículo no tiene configuración de unidades por pallet. Utilice solo picos.']); exit;
        }

        $bulto_ref = 'ENT-' . date('ymdHis') . '-' . rand(100, 999);
        $total_general = 0;
        $total_registros = 0;

        $pdo->beginTransaction();

        $stIns = $pdo->prepare("INSERT INTO entradas
            (proveedor, articulo_id, cliente_id, unidades_total, pallets, picos, ubicacion_id, usuario_id, fecha, bulto_ref)
            VALUES (:prov, :aid, :cid, :total, :pallets, :picos, :uid, :usid, NOW(), :ref)");

        // 1. Insertar Pallets Completos (uno a uno)
        for ($i = 0; $i < $pallets_completos; $i++) {
            $stIns->execute([
                ':prov'    => $proveedor,
                ':aid'     => $articulo_id,
                ':cid'     => $cliente_id,
                ':total'   => $paletizado_a,
                ':pallets' => 1,
                ':picos'   => 0,
                ':uid'     => $ubicacion_id,
                ':usid'    => $usuario_id,
                ':ref'     => $bulto_ref,
            ]);
            $total_general += $paletizado_a;
            $total_registros++;
        }

        // 2. Insertar Picos (uno a uno)
        if (is_array($picos)) {
            foreach ($picos as $pico) {
                $uds = (float)($pico['uds'] ?? 0);
                if ($uds <= 0) continue;

                $stIns->execute([
                    ':prov'    => $proveedor,
                    ':aid'     => $articulo_id,
                    ':cid'     => $cliente_id,
                    ':total'   => $uds,
                    ':pallets' => 0,
                    ':picos'   => $uds,
                    ':uid'     => $ubicacion_id,
                    ':usid'    => $usuario_id,
                    ':ref'     => $bulto_ref,
                ]);
                $total_general += $uds;
                $total_registros++;
            }
        }

        if ($total_registros === 0) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => 'No se introdujeron cantidades válidas.']); exit;
        }

        // Sumar stock
        $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual + :cant WHERE id = :id")
            ->execute([':cant' => $total_general, ':id' => $articulo_id]);

        // Actualizar ubicaciones
        if ($ubicacion_id) {
            $pdo->prepare("INSERT INTO inventario_ubicaciones (cliente_id, articulo_id, ubicacion_id, cantidad)
                VALUES (:cid, :aid, :uid, :cant)
                ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant")
                ->execute([':cid'=>$cliente_id,':aid'=>$articulo_id,':uid'=>$ubicacion_id,':cant'=>$total_general]);
        }

        // Log
        try {
            $pdo->prepare("INSERT INTO movimientos_stock (cliente_id, articulo_id, tipo_movimiento, ubicacion_destino_id, cantidad, usuario_id)
                VALUES (:cid, :aid, 'ENTRADA', :uid, :cant, :usid)")
                ->execute([':cid'=>$cliente_id,':aid'=>$articulo_id,':uid'=>$ubicacion_id,':cant'=>$total_general,':usid'=>$usuario_id]);
        } catch (PDOException $e2) {}

        $pdo->commit();

        echo json_encode(['ok' => true, 'msg' => "Se registraron {$total_registros} bultos (".number_format($total_general, 2)." uds totales).", 'ref' => $bulto_ref]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ── ELIMINAR GRUPO DE BULTOS (por ref) ───────────────── */
if ($accion === 'eliminar_ref') {
    if (!$isSuperAdmin) { echo json_encode(['ok' => false, 'msg' => 'Solo SuperAdmin puede eliminar.']); exit; }
    $ref = $_POST['bulto_ref'] ?? '';
    if (empty($ref)) { echo json_encode(['ok' => false, 'msg' => 'Referencia vacía.']); exit; }

    try {
        $stGet = $pdo->prepare("SELECT articulo_id, unidades_total, ubicacion_id, cliente_id FROM entradas WHERE bulto_ref = :ref");
        $stGet->execute([':ref' => $ref]);
        $entradas = $stGet->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($entradas)) {
            $pdo->beginTransaction();
            foreach($entradas as $entry) {
                $pdo->prepare("UPDATE articulos SET stock_actual = GREATEST(0, stock_actual - :cant) WHERE id = :aid")
                    ->execute([':cant' => $entry['unidades_total'], ':aid' => $entry['articulo_id']]);
                if ($entry['ubicacion_id']) {
                    $pdo->prepare("UPDATE inventario_ubicaciones SET cantidad = GREATEST(0, cantidad - :cant)
                        WHERE articulo_id = :aid AND ubicacion_id = :uid AND cliente_id = :cid")
                        ->execute([':cant'=>$entry['unidades_total'],':aid'=>$entry['articulo_id'],':uid'=>$entry['ubicacion_id'],':cid'=>$entry['cliente_id']]);
                }
            }
            $pdo->prepare("DELETE FROM entradas WHERE bulto_ref = :ref")->execute([':ref' => $ref]);
            $pdo->commit();
        }
        echo json_encode(['ok' => true, 'msg' => 'Lote eliminado y stock revertido.']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ── LISTAR UBICACIONES ───────────────────────────────── */
if ($accion === 'listar_ubicaciones') {
    try {
        echo json_encode($pdo->query("SELECT id, codigo_ubicacion, tipo FROM ubicaciones ORDER BY codigo_ubicacion ASC")->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) { echo json_encode([]); }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
