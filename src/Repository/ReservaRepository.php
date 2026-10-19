<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;
use Exception;

/**
 * Capa de persistencia para la gestión de reservas e historiales
 */
class ReservaRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lista las reservas aplicando filtros combinables asíncronos de forma segura
     */
    public function listar(array $filtros = []): array {
        $sql = "SELECT * FROM reserva WHERE 1=1";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND estado = :estado";
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_entrada >= :fecha_desde";
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_salida <= :fecha_hasta";
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND nombre_huesped LIKE :busqueda";
            // Búsqueda segura de coincidencia parcial
            $params['busqueda'] = "%" . $filtros['busqueda'] . "%";
        }

        $sql .= " ORDER BY fecha_creacion DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el detalle completo de una reserva por su ID
     */
    public function obtenerPorId(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM reserva WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        $reserva = $stmt->fetch();
        return $reserva ?: null;
    }

    /**
     * Recupera el historial de eventos asociados a una reserva
     */
    public function obtenerHistorial(int $reservaId): array {
        $stmt = $this->db->prepare("SELECT * FROM reserva_evento WHERE reserva_id = :reserva_id ORDER BY fecha_creacion DESC");
        $stmt->execute(['reserva_id' => $reservaId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Persiste una nueva reserva y registra su evento inicial usando transacciones atómicas.
     */
    public function crear(array $datos): int {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO reserva (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas) 
                    VALUES (:nombre_huesped, :email_huesped, :nombre_alojamiento, :fecha_entrada, :fecha_salida, 'pendiente', :importe, :notas)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_huesped'     => $datos['nombre_huesped'],
                'email_huesped'      => $datos['email_huesped'],
                'nombre_alojamiento' => $datos['nombre_alojamiento'],
                'fecha_entrada'      => $datos['fecha_entrada'],
                'fecha_salida'       => $datos['fecha_salida'],
                'importe'            => floatval($datos['importe']),
                'notas'              => !empty($datos['notas']) ? $datos['notas'] : null
            ]);

            $reservaId = (int)$this->db->lastInsertId();
            
            // Registro automático del log en la misma transacción
            $this->registrarEvento($reservaId, 'CREACION', 'Reserva inicial registrada en estado pendiente.');
            
            $this->db->commit();
            return $reservaId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al crear reserva en repositorio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza el estado de una reserva y añade el cambio en el historial
     */
    public function actualizarEstado(int $id, string $nuevoEstado): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE reserva SET estado = :estado WHERE id = :id");
            $resultado = $stmt->execute(['estado' => $nuevoEstado, 'id' => $id]);

            if ($resultado) {
                $this->registrarEvento($id, 'ESTADO_CAMBIO', "La reserva cambió al estado: " . strtoupper($nuevoEstado));
            }

            $this->db->commit();
            return $resultado;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al actualizar estado en repositorio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Centraliza las inserciones en el historial de eventos
     */
    private function registrarEvento(int $reservaId, string $tipo, string $descripcion): void {
        $stmt = $this->db->prepare("INSERT INTO reserva_evento (reserva_id, tipo, descripcion) VALUES (:reserva_id, :tipo, :descripcion)");
        $stmt->execute([
            'reserva_id'  => $reservaId,
            'tipo'        => $tipo,
            'descripcion' => $descripcion
        ]);
    }
}
