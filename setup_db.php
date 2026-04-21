<?php
require 'config/db.php';

// Script para inicializar la base de datos y añadir un usuario SuperAdmin

// Primero, nos aseguramos de que la tabla users exista
$sqlCreate = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    rol VARCHAR(50),
    cliente_id INT NULL
);
";

try {
    $pdo->exec($sqlCreate);
    echo "Tabla users verificada/creada correctamente.<br>\n";
    
    // Insertamos el usuario especificado
    $email = 'administracion@maximosl.com';
    $password = 'PuestoArriba1'; // Se guarda en texto plano para que auth_process.php lo compare directamente
    $rol = 'SuperAdmin';
    $nombre = 'Administrador';
    
    // Usamos ON DUPLICATE KEY UPDATE para no fallar si ya existiese
    $stmt = $pdo->prepare("
        INSERT INTO users (nombre, email, password, rol) 
        VALUES (:nombre, :email, :password, :rol) 
        ON DUPLICATE KEY UPDATE 
            password = :password, 
            rol = :rol,
            nombre = :nombre
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password' => $password,
        ':rol' => $rol
    ]);
    
    echo "El usuario {$email} ha sido registrado exitosamente con el rol {$rol}.<br>\n";
    
} catch (PDOException $e) {
    echo "Error en la base de datos: " . $e->getMessage();
}
