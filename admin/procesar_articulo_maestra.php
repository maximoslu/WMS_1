<?php
/**
 * WMS_1 - Procesador de Artículo Maestra (guardar / eliminar via AJAX)
 */
require_once '../config/setup.php';
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

/* ─────── GUARDAR ARTÍCULO ─────── */
if ($accion === 'guardar') {
    $id          = (int)($_POST['id'] ?? 0);
    $sku         = trim($_POST['sku'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $medida      = trim($_POST['medida'] ?? 'Uds');
    $paletizado  = (float)($_POST['paletizado_a'] ?? 0);
    $estado      = trim($_POST['estado'] ?? 'DISPONIBLE');
    $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
    $familia_id  = !empty($_POST['familia_id']) ? (int)$_POST['familia_id'] : null;

    if (!$sku || !$descripcion) {
        echo json_encode(['ok' => false, 'msg' => 'SKU y Descripción son obligatorios.']);
        exit;
    }

    try {
        // Intentamos con familia_id primero
        if ($id > 0) {
            try {
                $sql = "UPDATE articulos SET sku=:sku, descripcion=:desc, medida=:med,
                        paletizado_a=:pal, estado=:est, cliente_id=:cid, familia_id=:fid
                        WHERE id=:id";
                $st = $pdo->prepare($sql);
                $st->execute([':sku'=>$sku,':desc'=>$descripcion,':med'=>$medida,
                    ':pal'=>$paletizado,':est'=>$estado,':cid'=>$cliente_id,
                    ':fid'=>$familia_id,':id'=>$id]);
            } catch (PDOException $e) {
                // Columna familia_id no existe, actualizar sin ella
                $sql = "UPDATE articulos SET sku=:sku, descripcion=:desc, medida=:med,
                        paletizado_a=:pal, estado=:est, cliente_id=:cid WHERE id=:id";
                $st = $pdo->prepare($sql);
                $st->execute([':sku'=>$sku,':desc'=>$descripcion,':med'=>$medida,
                    ':pal'=>$paletizado,':est'=>$estado,':cid'=>$cliente_id,':id'=>$id]);
            }
        } else {
            try {
                $sql = "INSERT INTO articulos (cliente_id, sku, descripcion, medida, paletizado_a, estado, familia_id, stock_actual)
                        VALUES (:cid, :sku, :desc, :med, :pal, :est, :fid, 0)";
                $st = $pdo->prepare($sql);
                $st->execute([':cid'=>$cliente_id,':sku'=>$sku,':desc'=>$descripcion,
                    ':med'=>$medida,':pal'=>$paletizado,':est'=>$estado,':fid'=>$familia_id]);
            } catch (PDOException $e) {
                $sql = "INSERT INTO articulos (cliente_id, sku, descripcion, medida, paletizado_a, estado, stock_actual)
                        VALUES (:cid, :sku, :desc, :med, :pal, :est, 0)";
                $st = $pdo->prepare($sql);
                $st->execute([':cid'=>$cliente_id,':sku'=>$sku,':desc'=>$descripcion,
                    ':med'=>$medida,':pal'=>$paletizado,':est'=>$estado]);
            }
        }
        echo json_encode(['ok' => true, 'msg' => 'Artículo guardado correctamente.']);
    } catch (PDOException $e) {
        $msg = str_contains($e->getMessage(), 'Duplicate') 
            ? 'El SKU ya existe para este cliente.' 
            : $e->getMessage();
        echo json_encode(['ok' => false, 'msg' => $msg]);
    }
    exit;
}

/* ─────── ELIMINAR ARTÍCULO ─────── */
if ($accion === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
        exit;
    }
    try {
        $st = $pdo->prepare("DELETE FROM articulos WHERE id = :id");
        $st->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'msg' => 'Artículo eliminado.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ─────── GUARDAR FAMILIA (desde modal interno) ─────── */
if ($accion === 'guardar_familia') {
    $fid    = (int)($_POST['fam_id'] ?? 0);
    $nombre = trim($_POST['nombre_familia'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');

    if (!$nombre) {
        echo json_encode(['ok' => false, 'msg' => 'El nombre es obligatorio.']);
        exit;
    }
    try {
        if ($fid > 0) {
            $st = $pdo->prepare("UPDATE familias SET nombre_familia=:n, descripcion=:d WHERE id=:id");
            $st->execute([':n'=>$nombre,':d'=>$desc,':id'=>$fid]);
        } else {
            $st = $pdo->prepare("INSERT INTO familias (nombre_familia, descripcion) VALUES (:n, :d)");
            $st->execute([':n'=>$nombre,':d'=>$desc]);
        }
        echo json_encode(['ok' => true, 'msg' => 'Familia guardada.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ─────── ELIMINAR FAMILIA ─────── */
if ($accion === 'eliminar_familia') {
    $fid = (int)($_POST['id'] ?? 0);
    try {
        $st = $pdo->prepare("DELETE FROM familias WHERE id = :id");
        $st->execute([':id' => $fid]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* ─────── LISTAR FAMILIAS (GET) ─────── */
if ($accion === 'listar_familias') {
    try {
        $rows = $pdo->query("SELECT id, nombre_familia, descripcion FROM familias ORDER BY nombre_familia ASC")
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
