<?php
/**
 * WMS_1 - Procesador de Entradas de Mercancía
 * Acciones: guardar, eliminar, get_articulo
 */
require_once '../config/setup.php';
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado.']);
    exit;
}

$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';
$usuario_id = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin = ($rol === 'superadmin');

/* ────────────── CREAR TABLA SI NO EXISTE ────────────── */
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
        INDEX idx_articulo (articulo_id),
        INDEX idx_cliente (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) { /* ya existe */ }

/* ────────────── GET ARTICULO (info palletizado) ─────── */
if ($accion === 'get_articulo') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT a.id, a.sku, a.descripcion, a.paletizado_a, a.medida, a.cliente_id,
                              c.nombre_empresa AS cliente_nombre, c.email_contacto AS cliente_email
                              FROM articulos a
                              LEFT JOIN clientes c ON c.id = a.cliente_id
                              WHERE a.id = :id");
        $st->execute([':id' => $id]);
        $art = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode($art ?: ['error' => 'No encontrado']);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* ────────────── LISTAR ENTRADAS ─────────────────────── */
if ($accion === 'listar') {
    try {
        $sql = "SELECT e.*,
                    a.sku, a.descripcion AS art_desc, a.paletizado_a,
                    c.nombre_empresa AS cliente_nombre,
                    u.nombre AS usuario_nombre,
                    ub.codigo_ubicacion
                FROM entradas e
                LEFT JOIN articulos a  ON a.id = e.articulo_id
                LEFT JOIN clientes  c  ON c.id = e.cliente_id
                LEFT JOIN users     u  ON u.id = e.usuario_id
                LEFT JOIN ubicaciones ub ON ub.id = e.ubicacion_id
                ORDER BY e.fecha DESC
                LIMIT 200";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* ────────────── GUARDAR ENTRADA ─────────────────────── */
if ($accion === 'guardar') {
    $articulo_id    = (int)($_POST['articulo_id'] ?? 0);
    $proveedor      = trim($_POST['proveedor'] ?? '');
    $pallets        = (int)($_POST['pallets'] ?? 0);
    $picos          = (float)($_POST['picos'] ?? 0);
    $unidades_total = (float)($_POST['unidades_total'] ?? 0);
    $ubicacion_id   = !empty($_POST['ubicacion_id']) ? (int)$_POST['ubicacion_id'] : null;

    if (!$articulo_id || $unidades_total <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Artículo y cantidad son obligatorios.']);
        exit;
    }

    try {
        // 1. Obtener cliente_id del artículo
        $stArt = $pdo->prepare("SELECT cliente_id FROM articulos WHERE id = :id");
        $stArt->execute([':id' => $articulo_id]);
        $artRow = $stArt->fetch(PDO::FETCH_ASSOC);
        if (!$artRow) {
            echo json_encode(['ok' => false, 'msg' => 'Artículo no encontrado.']);
            exit;
        }
        $cliente_id = (int)$artRow['cliente_id'];

        $pdo->beginTransaction();

        // 2. Insertar entrada
        $stIns = $pdo->prepare("INSERT INTO entradas
            (proveedor, articulo_id, cliente_id, unidades_total, pallets, picos, ubicacion_id, usuario_id, fecha)
            VALUES (:prov, :aid, :cid, :total, :pallets, :picos, :uid, :usid, NOW())");
        $stIns->execute([
            ':prov'    => $proveedor,
            ':aid'     => $articulo_id,
            ':cid'     => $cliente_id,
            ':total'   => $unidades_total,
            ':pallets' => $pallets,
            ':picos'   => $picos,
            ':uid'     => $ubicacion_id,
            ':usid'    => $usuario_id,
        ]);
        $entrada_id = (int)$pdo->lastInsertId();

        // 3. Sumar stock en articulos
        $stStock = $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual + :cant WHERE id = :id");
        $stStock->execute([':cant' => $unidades_total, ':id' => $articulo_id]);

        // 4. Actualizar inventario_ubicaciones si hay ubicación
        if ($ubicacion_id) {
            $stInv = $pdo->prepare("INSERT INTO inventario_ubicaciones (cliente_id, articulo_id, ubicacion_id, cantidad)
                VALUES (:cid, :aid, :uid, :cant)
                ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant");
            $stInv->execute([':cid' => $cliente_id, ':aid' => $articulo_id, ':uid' => $ubicacion_id, ':cant' => $unidades_total]);
        }

        // 5. Log movimiento
        try {
            $stMov = $pdo->prepare("INSERT INTO movimientos_stock
                (cliente_id, articulo_id, tipo_movimiento, ubicacion_destino_id, cantidad, usuario_id)
                VALUES (:cid, :aid, 'ENTRADA', :uid, :cant, :usid)");
            $stMov->execute([':cid' => $cliente_id, ':aid' => $articulo_id, ':uid' => $ubicacion_id, ':cant' => $unidades_total, ':usid' => $usuario_id]);
        } catch (PDOException $e) { /* tabla opcional */ }

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => "Entrada registrada: {$unidades_total} uds.", 'entrada_id' => $entrada_id]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ────────────── ELIMINAR ENTRADA ───────────────────── */
if ($accion === 'eliminar') {
    if (!$isSuperAdmin) {
        echo json_encode(['ok' => false, 'msg' => 'Solo el SuperAdmin puede eliminar entradas.']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    try {
        // Obtener datos para restar stock
        $stGet = $pdo->prepare("SELECT articulo_id, unidades_total, ubicacion_id, cliente_id FROM entradas WHERE id = :id");
        $stGet->execute([':id' => $id]);
        $entry = $stGet->fetch(PDO::FETCH_ASSOC);

        if ($entry) {
            $pdo->beginTransaction();
            // Restar stock
            $pdo->prepare("UPDATE articulos SET stock_actual = GREATEST(0, stock_actual - :cant) WHERE id = :aid")
                ->execute([':cant' => $entry['unidades_total'], ':aid' => $entry['articulo_id']]);
            if ($entry['ubicacion_id']) {
                $pdo->prepare("UPDATE inventario_ubicaciones SET cantidad = GREATEST(0, cantidad - :cant)
                    WHERE articulo_id = :aid AND ubicacion_id = :uid AND cliente_id = :cid")
                    ->execute([':cant' => $entry['unidades_total'], ':aid' => $entry['articulo_id'],
                                ':uid' => $entry['ubicacion_id'], ':cid' => $entry['cliente_id']]);
            }
            $pdo->prepare("DELETE FROM entradas WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
        }
        echo json_encode(['ok' => true, 'msg' => 'Entrada eliminada y stock revertido.']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ────────────── LISTAR UBICACIONES ─────────────────── */
if ($accion === 'listar_ubicaciones') {
    try {
        $rows = $pdo->query("SELECT id, codigo_ubicacion, tipo FROM ubicaciones ORDER BY codigo_ubicacion ASC")
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
