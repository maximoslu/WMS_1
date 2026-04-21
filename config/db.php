<?php
// config/db.php
// Conexión PDO a MariaDB para el sistema WMS

$host = 'bbdd.maximosl.com'; // Modificar si el servidor de base de datos es distinto
$dbname = 'ddb271932';  // Nombre de la base de datos
$username = 'ddb271932';  // Usuario de la base de datos
$password = 'Maximo2026';      // Contraseña de la base de datos

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Manejo de errores basado en excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Resultados como arreglos asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Uso de sentencias preparadas reales
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // En un entorno de producción, es mejor registrar este error en un archivo de log y mostrar un mensaje genérico.
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, intente más tarde.");
}
?>