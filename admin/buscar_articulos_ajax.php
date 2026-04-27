<?php
/**
 * WMS_1 - AJAX: Buscar artículos (SuperAdmin, sin restricción de cliente)
 */
require_once '../config/setup.php';
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Solo SuperAdmin
$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'superadmin') {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.sku,
            a.descripcion,
            a.estado,
            a.paletizado_a,
            a.medida,
            a.cliente_id,
            c.nombre_empresa AS cliente_nombre,
            a.familia_id
        FROM articulos a
        LEFT JOIN clientes c ON c.id = a.cliente_id
        WHERE a.sku LIKE :q1 OR a.descripcion LIKE :q2
        ORDER BY a.sku ASC
        LIMIT 30
    ");
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (PDOException $e) {
    // Si familia_id no existe aún, reintentamos sin esa columna
    try {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.sku,
                a.descripcion,
                a.estado,
                a.paletizado_a,
                a.medida,
                a.cliente_id,
                c.nombre_empresa AS cliente_nombre,
                NULL AS familia_id
            FROM articulos a
            LEFT JOIN clientes c ON c.id = a.cliente_id
            WHERE a.sku LIKE :q1 OR a.descripcion LIKE :q2
            ORDER BY a.sku ASC
            LIMIT 30
        ");
        $stmt->execute([':q1' => $like, ':q2' => $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (PDOException $e2) {
        echo json_encode(['error' => $e2->getMessage()]);
    }
}
