<?php
// 1. Configuración de Zona Horaria
date_default_timezone_set('Europe/Madrid');

// 2. Informe de errores (Actívalo durante desarrollo, apágalo en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3. Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Definir una constante para rutas absolutas si fuera necesario
define('BASE_URL', 'https://maximosl.com/public/WMS/');
?>