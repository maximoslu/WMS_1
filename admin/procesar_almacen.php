<?php
/**
 * WMS_1 - Procesador de Almacenes
 */
require_once '../config/setup.php';
require_once '../config/db.php';

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'operario'])) {
    die("Acceso denegado");
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    if ($accion === 'insert_almacen') {
        $nombre = $_POST['nombre'] ?? '';
        $codigo = $_POST['codigo_almacen'] ?? '';
        $direccion = $_POST['direccion'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO almacenes (nombre, codigo_almacen, direccion) VALUES (:nombre, :codigo, :direccion)");
        $stmt->execute([':nombre' => $nombre, ':codigo' => $codigo, ':direccion' => $direccion]);
        header("Location: almacenes.php?status=success");
        exit;
    }

    if ($accion === 'update_almacen') {
        $id = (int)$_POST['id'];
        $nombre = $_POST['nombre'] ?? '';
        $codigo = $_POST['codigo_almacen'] ?? '';
        $direccion = $_POST['direccion'] ?? '';

        $stmt = $pdo->prepare("UPDATE almacenes SET nombre = :nombre, codigo_almacen = :codigo, direccion = :direccion WHERE id = :id");
        $stmt->execute([':nombre' => $nombre, ':codigo' => $codigo, ':direccion' => $direccion, ':id' => $id]);
        header("Location: almacenes.php?status=success");
        exit;
    }

    if ($accion === 'delete_almacen') {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM almacenes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: almacenes.php?status=success");
        exit;
    }

} catch (PDOException $e) {
    error_log("Error Procesador Almacen: " . $e->getMessage());
    die("Error en base de datos. Asegúrate de que el código de almacén sea único y no esté vacío.");
}

header("Location: almacenes.php");
exit;
