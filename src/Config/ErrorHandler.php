<?php
declare(strict_types=1);

namespace App\Config;

use Throwable;

class ErrorHandler {
    /**
     * Intercepta fallos globales y responde con el código HTTP correspondiente
     */
    public static function manejarExcepcion(Throwable $exception): void {
        error_log("Error centralizado: [" . $exception->getCode() . "] " . $exception->getMessage() . " en " . $exception->getFile());

        $codigoHttp = $exception->getCode();
        if ($codigoHttp < 400 || $codigoHttp > 599) {
            $codigoHttp = 500;
        }

        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        
        // Estructura de respuesta limpia para el cliente
        echo json_encode([
            'error'   => $codigoHttp === 500 ? 'Error interno del servidor.' : 'Petición incorrecta.',
            'mensaje' => $exception->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
