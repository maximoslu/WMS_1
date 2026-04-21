<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            // Buscamos al usuario en la tabla 'users' (en minúsculas) extrayendo las columnas requeridas
            $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol, cliente_id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Validación combinada de password
                $isPasswordValid = password_verify($password, $user['password']);
                
                // Fallback para pruebas con texto plano si el hash falla
                if (!$isPasswordValid && $password === $user['password']) {
                    $isPasswordValid = true; 
                }

                if ($isPasswordValid) {
                    session_regenerate_id(true);

                    // Guardamos los datos solicitados en sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['cliente_id'] = $user['cliente_id'] ?? null;

                    // Redirección post-login
                    $rol = $user['rol'];
                    if ($rol === 'Cliente') {
                        header('Location: cliente_view.php');
                    } elseif (in_array($rol, ['SuperAdmin', 'Administracion', 'Almacen'])) {
                        header('Location: dashboard.php');
                    } else {
                        // Resguardo por si hay otro rol no contemplado
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
