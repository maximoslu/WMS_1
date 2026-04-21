<?php
/**
 * WMS_1 - Controlador de Inventario
 * Maneja las transacciones de stock, asegurando la integridad entre tablas y el log de movimientos.
 */

class InventarioController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Registra una entrada de stock (Inbound).
     * @param array $data [articulo_id, ubicacion_id, cantidad, usuario_id, cliente_id]
     * @return bool
     */
    public function registrarEntrada($data) {
        try {
            $this->pdo->beginTransaction();

            // 1. Actualizar stock total en la tabla maestro (articulos)
            $sqlArt = "UPDATE articulos SET stock_actual = stock_actual + :cantidad 
                       WHERE id = :articulo_id AND cliente_id = :cliente_id";
            $stmtArt = $this->pdo->prepare($sqlArt);
            $stmtArt->execute([
                ':cantidad'    => $data['cantidad'],
                ':articulo_id' => $data['articulo_id'],
                ':cliente_id'  => $data['cliente_id']
            ]);

            // 2. Actualizar stock detallado en ubicaciones (Upsert)
            $sqlInv = "INSERT INTO inventario_ubicaciones (cliente_id, articulo_id, ubicacion_id, cantidad) 
                       VALUES (:cliente_id, :articulo_id, :ubicacion_id, :cantidad) 
                       ON DUPLICATE KEY UPDATE cantidad = cantidad + :cantidad";
            $stmtInv = $this->pdo->prepare($sqlInv);
            $stmtInv->execute([
                ':cliente_id'  => $data['cliente_id'],
                ':articulo_id' => $data['articulo_id'],
                ':ubicacion_id' => $data['ubicacion_id'],
                ':cantidad'    => $data['cantidad']
            ]);

            // 3. Registrar el movimiento en el histórico
            $sqlMov = "INSERT INTO movimientos_stock (cliente_id, articulo_id, tipo_movimiento, ubicacion_destino_id, cantidad, usuario_id) 
                       VALUES (:cliente_id, :articulo_id, 'ENTRADA', :ubicacion_id, :cantidad, :usuario_id)";
            $stmtMov = $this->pdo->prepare($sqlMov);
            $stmtMov->execute([
                ':cliente_id'   => $data['cliente_id'],
                ':articulo_id'  => $data['articulo_id'],
                ':ubicacion_id' => $data['ubicacion_id'],
                ':cantidad'     => $data['cantidad'],
                ':usuario_id'   => $data['usuario_id']
            ]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error en registrarEntrada: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el desglose de stock de un artículo por ubicación.
     */
    public function getDesgloseStock($articulo_id, $cliente_id) {
        try {
            $sql = "SELECT iu.cantidad, u.codigo, u.descripcion, u.tipo 
                    FROM inventario_ubicaciones iu 
                    JOIN ubicaciones u ON iu.ubicacion_id = u.id 
                    WHERE iu.articulo_id = :aid AND iu.cliente_id = :cid 
                    AND iu.cantidad > 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':aid' => $articulo_id, ':cid' => $cliente_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getDesgloseStock: " . $e->getMessage());
            return [];
        }
    }
}
