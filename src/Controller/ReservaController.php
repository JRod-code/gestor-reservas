<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReservaRepository;
use Exception;

/**
 * Controlador para la gestión y validación de reservas
 */
class ReservaController {
    private ReservaRepository $repository;

    public function __construct(ReservaRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Lista las reservas capturando los filtros de la URL
     */
    public function listar(): void {
        $filtros = [
            'estado'      => $_GET['estado'] ?? null,
            'fecha_desde' => $_GET['fecha_desde'] ?? null,
            'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
            'busqueda'    => $_GET['busqueda'] ?? null
        ];
        $this->responderJson($this->repository->listar($filtros), 200);
    }

    /**
     * Muestra el detalle de una reserva y su historial de eventos
     */
    public function ver(int $id): void {
        $reserva = $this->repository->obtenerPorId($id);
        if (!$reserva) {
            $this->responderJson(['error' => 'La reserva no existe.'], 404);
            return;
        }
        
        $reserva['eventos'] = $this->repository->obtenerHistorial($id);
        $this->responderJson($reserva, 200);
    }

    /**
     * Valida y crea una nueva reserva
     */
    public function crear(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->responderJson(['error' => 'Formato de datos incorrecto.'], 400);
            return;
        }

        $errores = [];
        if (empty($input['nombre_huesped'])) {
            $errores[] = "El nombre del huésped es obligatorio.";
        }
        if (empty($input['email_huesped']) || !filter_var($input['email_huesped'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El correo electrónico no es válido.";
        }
        if (empty($input['nombre_alojamiento'])) {
            $errores[] = "El nombre del alojamiento es obligatorio.";
        }
        
        if (empty($input['fecha_entrada']) || empty($input['fecha_salida'])) {
            $errores[] = "Las fechas de entrada y salida son obligatorias.";
        } else {
            $t_entrada = strtotime($input['fecha_entrada']);
            $t_salida  = strtotime($input['fecha_salida']);
            if ($t_entrada >= $t_salida) {
                $errores[] = "La fecha de entrada debe ser anterior a la fecha de salida.";
            }
        }
        
        if (!isset($input['importe']) || !is_numeric($input['importe']) || floatval($input['importe']) <= 0) {
            $errores[] = "El importe debe ser un número positivo.";
        }

        if (!empty($errores)) {
            $this->responderJson(['errores' => $errores], 400);
            return;
        }

        try {
            $id = $this->repository->crear($input);
            $this->responderJson(['id' => $id, 'mensaje' => 'Reserva guardada con éxito.'], 201);
        } catch (Exception $e) {
            $this->responderJson(['error' => 'Error interno al guardar los datos.'], 500);
        }
    }

    /**
     * Cambia el estado de una reserva
     */
    public function cambiarEstado(int $id): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $nuevoEstado = $input['estado'] ?? null;

        if (!in_array($nuevoEstado, ['pendiente', 'confirmada', 'cancelada'], true)) {
            $this->responderJson(['error' => 'El estado indicado no es válido.'], 400);
            return;
        }

        if (!$this->repository->obtenerPorId($id)) {
            $this->responderJson(['error' => 'La reserva no existe.'], 404);
            return;
        }

        try {
            if ($this->repository->actualizarEstado($id, $nuevoEstado)) {
                $this->responderJson(['mensaje' => 'Estado actualizado correctamente.'], 200);
            } else {
                $this->responderJson(['error' => 'No se realizaron cambios en la reserva.'], 400);
            }
        } catch (Exception $e) {
            $this->responderJson(['error' => 'Error al actualizar el estado.'], 500);
        }
    }

    /**
     * Envía la respuesta HTTP en formato JSON limpio
     */
    public function responderJson(mixed $data, int $codigo): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
