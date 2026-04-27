<?php
/**
 * WMS_1 - Controlador de Artículos
 * Maneja la lógica de negocio del maestro de productos con aislamiento por cliente_id.
 */

class ArticuloController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todos los artículos del cliente actual.
     */
    public function getArticulos($cliente_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM articulos WHERE cliente_id = :cid ORDER BY sku ASC");
            $stmt->execute([':cid' => $cliente_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getArticulos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene un artículo específico validando que pertenezca al cliente.
     */
    public function getArticuloById($id, $cliente_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM articulos WHERE id = :id AND cliente_id = :cid");
            $stmt->execute([':id' => $id, ':cid' => $cliente_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getArticuloById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Guarda o actualiza un artículo.
     */
    public function saveArticulo($data) {
        try {
            // Debug & Fallback de cliente_id (según requerimiento de emergencia)
            $cid = (isset($data['cliente_id']) && $data['cliente_id'] != 0) ? $data['cliente_id'] : 1;

            if (isset($data['id']) && !empty($data['id'])) {
                // UPDATE con filtro de seguridad cliente_id
                $sql = "UPDATE articulos SET 
                            sku = :sku, 
                            descripcion = :descripcion, 
                            lote = :lote,
                            medida = :medida,
                            paletizado_a = :paletizado_a,
                            estado = :estado
                        WHERE id = :id AND cliente_id = :cliente_id";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':sku'          => $data['sku'],
                    ':descripcion'  => $data['descripcion'],
                    ':lote'         => $data['lote'],
                    ':medida'       => $data['medida'],
                    ':paletizado_a' => $data['paletizado_a'],
                    ':estado'       => $data['estado'] ?? 'DISPONIBLE',
                    ':id'           => $data['id'],
                    ':cliente_id'   => $cid
                ]);
                return ['success' => true];
            } else {
                // INSERT exacto según requerimiento final
                $sql = "INSERT INTO articulos (cliente_id, sku, descripcion, lote, medida, paletizado_a, stock_actual, estado) 
                        VALUES (:cliente_id, :sku, :descripcion, :lote, :medida, :paletizado_a, 0, :estado)";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':cliente_id'   => $cid,
                    ':sku'          => $data['sku'],
                    ':descripcion'  => $data['descripcion'],
                    ':lote'         => $data['lote'],
                    ':medida'       => $data['medida'],
                    ':paletizado_a' => $data['paletizado_a'],
                    ':estado'       => $data['estado'] ?? 'DISPONIBLE'
                ]);
                return ['success' => true];
            }
        } catch (PDOException $e) {
            error_log("Error en saveArticulo: " . $e->getMessage());
            // Devolvemos el error real para depuración
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Elimina un artículo validando el cliente_id.
     */
    public function deleteArticulo($id, $cliente_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM articulos WHERE id = :id AND cliente_id = :cid");
            return $stmt->execute([':id' => $id, ':cid' => $cliente_id]);
        } catch (PDOException $e) {
            error_log("Error en deleteArticulo: " . $e->getMessage());
            return false;
        }
    }
}
