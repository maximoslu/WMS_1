<?php
/**
 * WMS_1 - Procesar cambios de Usuario (Perfil y Password)
 * Solo accesible para SuperAdmin.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/setup.php';
require_once '../config/db.php';
ob_clean(); // Limpiar cualquier buffer previo (espacios, warnings)

header('Content-Type: application/json');

// Verificación de rango
if (strtolower($_SESSION['rol'] ?? '') !== 'superadmin') {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['accion']) && $_GET['accion'] === 'eliminar')) {
    $accion  = $_POST['accion'] ?? $_GET['accion'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? $_GET['id'] ?? 0);

    if (!$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID de usuario no proporcionado']);
        exit;
    }

    try {
        if ($accion === 'update_perfil') {
            $nombre     = trim($_POST['nombre'] ?? '');
            $email      = trim($_POST['email']  ?? '');
            $rol_nuevo  = $_POST['rol'] ?? 'Almacen';
            $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

            // Seguridad: Un SuperAdmin no puede degradarse a sí mismo a otro rol
            if ($user_id == $_SESSION['user_id'] && strtolower($rol_nuevo) !== 'superadmin') {
                throw new Exception("No puedes cambiar tu propio rol de SuperAdmin por seguridad.");
            }

            $upd = $pdo->prepare("UPDATE users SET nombre = :nombre, email = :email, rol = :rol, cliente_id = :cliente WHERE id = :id");
            $upd->execute([
                ':nombre'  => $nombre,
                ':email'   => $email,
                ':rol'     => $rol_nuevo,
                ':cliente' => $cliente_id,
                ':id'      => $user_id
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente']);
            exit;

        } elseif ($accion === 'update_pass') {
            $new_pass = trim($_POST['new_pass'] ?? '');
            
            if (strlen($new_pass) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres.");
            }

            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $upd->execute([':pass' => $hash, ':id' => $user_id]);

            echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente']);
            exit;

        } elseif ($accion === 'aprobar') {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("UPDATE users SET estado = 'activo' WHERE id = :id");
            $upd->execute([':id' => $user_id]);

            $leer = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE leido = 0");
            $leer->execute();

            $pdo->commit();

            echo json_encode(['status' => 'success', 'message' => 'Usuario aprobado']);
            exit;
        } elseif ($accion === 'eliminar') {
            $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $del->execute([':id' => $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Petición inválida']);
exit;
