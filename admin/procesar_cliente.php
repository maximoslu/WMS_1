<?php
/**
 * WMS_1 - Procesamiento de Clientes
 * Sincronizado con la estructura real de la tabla.
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
        $nombre   = $_POST['nombre_empresa'] ?? '';
        $cif      = $_POST['cif'] ?? '';
        $dir      = $_POST['direccion'] ?? '';
        $contacto = $_POST['contacto_nombre'] ?? '';
        $email    = $_POST['email_contacto'] ?? '';

        if (empty($nombre)) {
            die("Error: El nombre de la empresa es obligatorio.");
        }

        // INSERT con nombres de columna verificados
        $sql = "INSERT INTO clientes (nombre_empresa, cif, direccion, contacto_nombre, email_contacto, fecha_registro) 
                VALUES (:nom, :cif, :dir, :con, :ema, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':cif', $cif);
        $stmt->bindParam(':dir', $dir);
        $stmt->bindParam(':con', $contacto);
        $stmt->bindParam(':ema', $email);

        if ($stmt->execute()) {
            header("Location: clientes.php?status=success");
            exit;
        } else {
            print_r($stmt->errorInfo());
            die();
        }

    } elseif ($accion === 'update') {
        $id       = $_POST['id'] ?? 0;
        $nombre   = $_POST['nombre_empresa'] ?? '';
        $cif      = $_POST['cif'] ?? '';
        $dir      = $_POST['direccion'] ?? '';
        $contacto = $_POST['contacto_nombre'] ?? '';
        $email    = $_POST['email_contacto'] ?? '';

        $sql = "UPDATE clientes SET nombre_empresa = :nom, cif = :cif, direccion = :dir, 
                contacto_nombre = :con, email_contacto = :ema WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':cif', $cif);
        $stmt->bindParam(':dir', $dir);
        $stmt->bindParam(':con', $contacto);
        $stmt->bindParam(':ema', $email);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            header("Location: clientes.php?status=success");
            exit;
        } else {
            print_r($stmt->errorInfo());
            die();
        }

    } elseif ($accion === 'eliminar') {
        $id = $_GET['id'] ?? 0;
        $sql = "DELETE FROM clientes WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            header("Location: clientes.php?status=success");
            exit;
        }
    }
} catch (PDOException $e) {
    die("Error crítico de base de datos: " . $e->getMessage());
}
