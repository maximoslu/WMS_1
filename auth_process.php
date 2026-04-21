<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        try {
            // Recuperamos todos los datos necesarios incluido 'nombre' y 'estado'
            $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol, cliente_id, estado FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("Intento de login para email: " . $email);

            if ($user) {
                // Bloquear acceso a usuarios pendientes o rechazados
                $estado = $user['estado'] ?? 'activo'; // Compatibilidad con cuentas antiguas sin la columna
                if ($estado === 'pendiente') {
                    header("Location: index.php?error=" . urlencode("Tu cuenta está pendiente de aprobación por un administrador."));
                    exit;
                }
                if ($estado === 'rechazado') {
                    header("Location: index.php?error=" . urlencode("Tu cuenta ha sido rechazada. Contacta con el administrador."));
                    exit;
                }

                // Triple validación de contraseña (compatibilidad con contraseñas antiguas)
                $isPasswordValid = false;
                if (password_verify($password, $user['password'])) {
                    $isPasswordValid = true;
                } elseif ($password === $user['password']) {
                    $isPasswordValid = true;
                } elseif (md5($password) === $user['password']) {
                    $isPasswordValid = true;
                }

                if ($isPasswordValid) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['nombre']     = $user['nombre'];
                    $_SESSION['email']      = $user['email'];
                    $_SESSION['rol']        = $user['rol'];
                    $_SESSION['cliente_id'] = $user['cliente_id'] ?? 0;
                    $_SESSION['estado']     = $user['estado'];   // cacheado para verificación en header

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Credenciales incorrectas.";
                }
            } else {
                $error = "Credenciales incorrectas.";
            }
        } catch (PDOException $e) {
            error_log("Error en auth_process: " . $e->getMessage());
            $error = "Error de sistema. Intente más tarde.";
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
} else {
    $error = "Método no permitido.";
}

header("Location: index.php?error=" . urlencode($error));
exit;
