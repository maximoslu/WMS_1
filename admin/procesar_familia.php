<?php
/**
 * WMS_1 - Procesamiento de Familias
 */
require_once '../config/setup.php';
require_once '../config/db.php';

// Verificar sesión y rol
$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    die("No autorizado");
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    if ($accion === 'insert') {
        $nombre = $_POST['nombre_familia'] ?? '';
        $desc   = $_POST['descripcion'] ?? '';

        if (empty($nombre)) {
            die("Error: El nombre de la familia es obligatorio.");
        }

        $sql = "INSERT INTO familias (nombre_familia, descripcion) VALUES (:nom, :desc)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':desc', $desc);

        if ($stmt->execute()) {
            header("Location: familias.php?status=success");
            exit;
        } else {
            print_r($stmt->errorInfo());
            die();
        }

    } elseif ($accion === 'update') {
        $id     = $_POST['id'] ?? 0;
        $nombre = $_POST['nombre_familia'] ?? '';
        $desc   = $_POST['descripcion'] ?? '';

        $sql = "UPDATE familias SET nombre_familia = :nom, descripcion = :desc WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':desc', $desc);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            header("Location: familias.php?status=success");
            exit;
        } else {
            print_r($stmt->errorInfo());
            die();
        }

    } elseif ($accion === 'eliminar') {
        $id = $_GET['id'] ?? 0;
        $sql = "DELETE FROM familias WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            header("Location: familias.php?status=success");
            exit;
        }
    }
} catch (PDOException $e) {
    die("Error crítico: " . $e->getMessage());
}
