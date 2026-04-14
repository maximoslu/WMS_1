<?php
require_once 'config/db.php';

try {
    // Intentamos leer la versión del servidor para confirmar conexión
    $stmt = $pdo->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    
    echo "<h1>WMS - Conexión Exitosa</h1>";
    echo "Servidor: " . $row['version'] . "<br>";
    echo "Hora local: " . date('d-m-Y H:i:s') . "<br>";
    echo "Estado PHP: <span style='color:green'>OK</span>";

} catch (Exception $e) {
    echo "<h1>Error de Configuración</h1>";
    echo "Detalle: " . $e->getMessage();
}
?>