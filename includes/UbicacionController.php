<?php
/**
 * WMS_1 - Controlador de Ubicaciones
 * Gestiona los espacios físicos del almacén (Pasillos, Estanterías, etc.)
 */

class UbicacionController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene el listado completo de ubicaciones del cliente.
     */
    public function getUbicaciones($cliente_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM ubicaciones WHERE cliente_id = :cid ORDER BY codigo ASC");
            $stmt->execute([':cid' => $cliente_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getUbicaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda o actualiza una ubicación física.
     */
    public function saveUbicacion($data) {
        try {
            if (isset($data['id']) && !empty($data['id'])) {
                // UPDATE con filtro de seguridad
                $sql = "UPDATE ubicaciones SET 
                            codigo = :codigo, 
                            descripcion = :descripcion, 
                            tipo = :tipo 
                        WHERE id = :id AND cliente_id = :cliente_id";
                
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([
                    ':codigo'       => $data['codigo'],
                    ':descripcion'  => $data['descripcion'],
                    ':tipo'         => $data['tipo'],
                    ':id'           => $data['id'],
                    ':cliente_id'   => $data['cliente_id']
                ]);
            } else {
                // INSERT
                $sql = "INSERT INTO ubicaciones (cliente_id, codigo, descripcion, tipo) 
                        VALUES (:cliente_id, :codigo, :descripcion, :tipo)";
                
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([
                    ':cliente_id'   => $data['cliente_id'],
                    ':codigo'       => $data['codigo'],
                    ':descripcion'  => $data['descripcion'],
                    ':tipo'         => $data['tipo']
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error en saveUbicacion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una ubicación (Solo si está vacía - Implementación futura de validación)
     */
    public function deleteUbicacion($id, $cliente_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM ubicaciones WHERE id = :id AND cliente_id = :cid");
            return $stmt->execute([':id' => $id, ':cid' => $cliente_id]);
        } catch (PDOException $e) {
            error_log("Error en deleteUbicacion: " . $e->getMessage());
            return false;
        }
    }
}
