<?php
$host = 'bbdd.maximosl.com';
$dbname = 'ddb271932';
$username = 'ddb271932';
$password = 'Maximo2026';

// --- CONFIGURACIÓN DE CORREO SMTP ---
define('MAIL_HOST', 'smtp.dondominio.com');
define('MAIL_USER', 'sistema@maximosl.com');
define('MAIL_PASS', 'Sistema1.');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_FROM_NAME', 'MAXIMO WMS - Sistema');


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    die("Hubo un problema al conectar con el sistema. Por favor, inténtalo más tarde.");
}
?>