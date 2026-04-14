<?php
/**
 * Configuración de Conexión WMS
 * Arquitecto Logístico Workspace
 */

$host = 'bbdd.maximosl.com';
$db   = 'ddb271932';
$user = 'ddb271932';
$pass = 'Maximo2026';
$charset = 'utf8mb4';

// Data Source Name
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Seguridad extra contra SQL Injection
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // Opcional: echo "Conexión establecida correctamente"; 
} catch (\PDOException $e) {
     // En producción, no mostrar el error detallado por seguridad
     die("Error de conexión a la Base de Datos: " . $e->getMessage());
}
?>