<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        try {
            // Buscamos al usuario en la tabla 'users' (en minúsculas) extrayendo las columnas requeridas
            $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol, cliente_id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // Log de depuración (antes de la comparación, solo email)
            error_log("Intento de login para email: " . $email);

            if ($user) {
                // Triple validación de contraseña
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

                    // Guardamos los datos solicitados en sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['cliente_id'] = $user['cliente_id'] ?? null;

                    // Redirección directa
                    header("Location: dashboard.php");
                    exit();
                } else {
                    error_log("Error: Contraseña incorrecta");
                    $error = "Credenciales incorrectas.";
                }
            } else {
                error_log("Error: Usuario no encontrado en la tabla users");
                $error = "Credenciales incorrectas.";
            }
        } catch (PDOException $e) {
            error_log("Error al consultar la tabla users: " . $e->getMessage());
            $error = "Error de sistema: Intente más tarde.";
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
} else {
    $error = "Método no permitido.";
}

header("Location: index.php?error=" . urlencode($error));
exit;
