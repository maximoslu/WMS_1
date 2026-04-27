<?php
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    if (isset($_GET['accion']) && $_GET['accion'] === 'listar') {
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }
    die("Acceso denegado");
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    if ($accion === 'listar') {
        $almacen_id = $_GET['almacen_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM ubicaciones WHERE almacen_id = ? ORDER BY codigo_ubicacion ASC");
        $stmt->execute([$almacen_id]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($res);
        exit;
    }

    if ($accion === 'insert_ubicacion') {
        $almacen_id = (int)$_POST['almacen_id'];
        $codigo = $_POST['codigo_ubicacion'] ?? '';
        $tipo = $_POST['tipo'] ?? 'Picking';
        $descripcion = $_POST['descripcion'] ?? '';

        if (empty($almacen_id)) {
            throw new Exception("El ID del almacén no puede estar vacío. Selecciona un almacén.");
        }

        $stmt = $pdo->prepare("INSERT INTO ubicaciones (almacen_id, codigo_ubicacion, tipo, descripcion) VALUES (:almacen_id, :codigo, :tipo, :descripcion)");
        $stmt->execute([':almacen_id' => $almacen_id, ':codigo' => $codigo, ':tipo' => $tipo, ':descripcion' => $descripcion]);
        header("Location: almacenes.php?status=success&almacen_id=" . $almacen_id);
        exit;
    }

    if ($accion === 'update_ubicacion') {
        $id = (int)$_POST['id'];
        $almacen_id = (int)$_POST['almacen_id'];
        $codigo = $_POST['codigo_ubicacion'] ?? '';
        $tipo = $_POST['tipo'] ?? 'Picking';
        $descripcion = $_POST['descripcion'] ?? '';

        $stmt = $pdo->prepare("UPDATE ubicaciones SET codigo_ubicacion = :codigo, tipo = :tipo, descripcion = :descripcion WHERE id = :id");
        $stmt->execute([':codigo' => $codigo, ':tipo' => $tipo, ':descripcion' => $descripcion, ':id' => $id]);
        header("Location: almacenes.php?status=success&almacen_id=" . $almacen_id);
        exit;
    }

    if ($accion === 'delete_ubicacion') {
        $id = (int)$_GET['id'];
        $almacen_id = (int)($_GET['almacen_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM ubicaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($almacen_id > 0) {
            header("Location: almacenes.php?status=success&almacen_id=" . $almacen_id);
        } else {
            header("Location: almacenes.php?status=success");
        }
        exit;
    }

    if ($accion === 'mass_insert_ubicaciones') {
        $almacen_id = (int)$_POST['almacen_id'];
        $prefijo = $_POST['prefijo'] ?? '';
        $desde = (int)$_POST['desde'];
        $hasta = (int)$_POST['hasta'];
        $tipo = $_POST['tipo'] ?? 'Picking';

        if (empty($almacen_id)) {
            throw new Exception("El ID del almacén no puede estar vacío. Selecciona un almacén.");
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO ubicaciones (almacen_id, codigo_ubicacion, tipo) VALUES (:almacen_id, :codigo, :tipo)");
        
        for ($i = $desde; $i <= $hasta; $i++) {
            $codigo_ubi = $prefijo . str_pad($i, 2, '0', STR_PAD_LEFT);
            $stmt->execute([':almacen_id' => $almacen_id, ':codigo' => $codigo_ubi, ':tipo' => $tipo]);
        }
        
        $pdo->commit();
        header("Location: almacenes.php?status=success&almacen_id=" . $almacen_id);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error Procesador Ubicacion: " . $e->getMessage());
    
    // Depuración: Obtener las columnas actuales de la tabla si hay error
    $columnas = [];
    try {
        if (isset($pdo)) {
            $desc = $pdo->query("DESCRIBE ubicaciones")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($desc as $col) {
                $columnas[] = $col['Field'];
            }
        }
    } catch (Exception $ex) {
        $columnas = ['No se pudo ejecutar DESCRIBE'];
    }
    
    $errorMsg = $e->getMessage();
    if (!empty($columnas)) {
        $errorMsg .= " | Columnas actuales en BD: " . implode(', ', $columnas);
    }

    if ($accion === 'listar') {
        echo json_encode(['error' => $errorMsg]);
        exit;
    } else {
        die("Error: " . $errorMsg);
    }
}

header("Location: almacenes.php");
exit;
