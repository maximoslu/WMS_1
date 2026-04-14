<?php
// Seguridad: evitar acceso directo al archivo
if (count(get_included_files()) == 1) exit("Acceso denegado");

require_once 'setup.php'; // Incluimos la configuración general

$host = 'bbdd.maximosl.com';
$db   = 'ddb271932';
$user = 'ddb271932';
$pass = 'Maximo2026';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Error técnico para ti, mensaje genérico para el usuario
     error_log($e->getMessage());
     die("Error crítico: No se pudo conectar con la base de datos de logística.");
}
?>