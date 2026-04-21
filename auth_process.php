<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            // Buscamos al usuario por correo, incluyendo cliente_id
            $stmt = $pdo->prepare("SELECT id, email, password, rol, cliente_id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Validación de contraseña. 
                // Se usa password_verify para hashes reales.
                // Además, como fallback de prueba se permite la comparación en texto plano (según el requerimiento del Excel).
                $isPasswordValid = password_verify($password, $user['password']);
                if (!$isPasswordValid && $password === $user['password']) {
                    $isPasswordValid = true; // Habilitado para fase inicial de pruebas en texto plano
                }

                if ($isPasswordValid) {
                    // Regenerar el ID de sesión para prevenir Session Fixation
                    session_regenerate_id(true);

                    // Autenticación exitosa: Guardamos datos en sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['cliente_id'] = $user['cliente_id'] ?? null;

                    // Redirección por roles
                    if ($user['rol'] === 'Cliente') {
                        header('Location: cliente_view.php');
                    } else {
                        // Roles Internos (SuperAdmin, Administración, Almacén)
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    $error = "Credenciales incorrectas.";
                }
            } else {
                $error = "Credenciales incorrectas.";
            }
        } catch (PDOException $e) {
            $error = "Error de sistema: Intente más tarde.";
            error_log($e->getMessage()); // Guardar el error internamente sin mostrar a usuario
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
} else {
    $error = "Método no permitido.";
}

// Si hubo error o se entra sin POST, redirige de vuelta con mensaje en GET
header("Location: index.php?error=" . urlencode($error));
exit;
